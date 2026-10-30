<?php

declare(strict_types=1);

namespace App\Integration\Bandsintown;

use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Integration\Bandsintown\Dto\EventsResult;
use App\Integration\Bandsintown\Exception\BandsintownUnavailable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BandsintownClient implements BandsintownGateway
{
    private const int MAX_RESPONSE_BYTES = 4_000_000;
    private const int MAX_EVENTS = 2_000;

    public function __construct(
        #[Autowire(service: 'bandsintown.http_client')] private HttpClientInterface $http,
        #[Autowire(service: 'cache.bandsintown')] private CacheInterface $cache,
        private BandsintownConfiguration $configuration,
        private BandsintownEventNormalizer $normalizer,
    ) {
    }

    public function events(): EventsResult
    {
        if ('' === $this->configuration->credentials()) {
            throw new BandsintownUnavailable(
                'La clé Bandsintown du groupe n’est pas encore configurée.',
            );
        }
        $data = $this->cache->get($this->configuration->cacheKey(), $this->fetch(...));
        if (isset($data['error'])) {
            throw new BandsintownUnavailable($data['error']);
        }

        return new EventsResult(
            array_map(BandsintownEvent::fromArray(...), $data['events']),
            $data['ignored'],
            new \DateTimeImmutable($data['fetchedAt']),
        );
    }

    /** @return array{error: string}|array{events: list<array>, ignored: int, fetchedAt: string} */
    private function fetch(ItemInterface $item): array
    {
        $item->expiresAfter(300);
        try {
            $response = $this->http->request(
                'GET',
                'artists/id_'.$this->configuration->artistId.'/events/',
                [
                    'query' => [
                        'app_id' => $this->configuration->credentials(),
                        'date' => 'all',
                    ],
                    'on_progress' => static function (int $downloaded, int $total): void {
                        if (
                            $downloaded > self::MAX_RESPONSE_BYTES
                            || $total > self::MAX_RESPONSE_BYTES
                        ) {
                            throw new TransportException('Réponse trop volumineuse.');
                        }
                    },
                ],
            );
            $status = $response->getStatusCode();
            if (200 !== $status) {
                $response->cancel();
                $item->expiresAfter(404 === $status ? 86400 : 60);

                return [
                    'error' => match ($status) {
                        401,
                        403 => 'La clé Bandsintown est refusée. Vérifie qu’elle correspond à cet artiste.',
                        404 => 'L’artiste Bandsintown est introuvable. Vérifie son identifiant.',
                        429 => 'Bandsintown demande de patienter avant une nouvelle consultation.',
                        default => 'Bandsintown est temporairement indisponible.',
                    },
                ];
            }
            $body = $response->getContent(false);
            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                throw new \UnexpectedValueException();
            }
            $rows = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || !array_is_list($rows) || count($rows) > self::MAX_EVENTS) {
                throw new \UnexpectedValueException();
            }
            $events = [];
            $ignored = 0;
            foreach ($rows as $row) {
                try {
                    if (!is_array($row)) {
                        throw new \UnexpectedValueException();
                    }
                    $event = $this->normalizer->normalize($row, $this->configuration->artistId);
                    if (isset($events[$event->id])) {
                        throw new \UnexpectedValueException();
                    }
                    $events[$event->id] = $event->toArray();
                } catch (\UnexpectedValueException) {
                    ++$ignored;
                }
            }

            return [
                'events' => array_values($events),
                'ignored' => $ignored,
                'fetchedAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->format(
                    DATE_ATOM,
                ),
            ];
        } catch (TransportExceptionInterface|\JsonException|\UnexpectedValueException) {
            // Ne pas propager l'exception HTTP : son URL peut contenir la clé d'accès.
            $item->expiresAfter(60);

            return [
                'error' => 'Impossible de lire les concerts Bandsintown. Réessaie dans une minute.',
            ];
        }
    }
}
