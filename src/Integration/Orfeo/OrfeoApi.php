<?php

declare(strict_types=1);

namespace App\Integration\Orfeo;

use App\Integration\Orfeo\Exception\OrfeoUnavailable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OrfeoApi
{
    private const int MAX_BYTES = 4_000_000;
    private const int MAX_PAGES = 20;

    public function __construct(
        #[Autowire(service: 'orfeo.http_client')] private HttpClientInterface $http,
        #[Autowire(service: 'limiter.orfeo')] private RateLimiterFactoryInterface $limiter,
        private OrfeoConfiguration $configuration,
    ) {
    }

    /** @param array<string, int|string> $filters
     * @return list<array<string, mixed>> */
    public function list(string $resource, array $filters = []): array
    {
        if (!in_array($resource, ['spectacle', 'project', 'project_status', 'project_linked_contact'], true)) {
            throw new \InvalidArgumentException('Ressource Orfeo non autorisée.');
        }
        $rows = [];
        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            $data = $this->request('api/'.$resource.'/', [...$filters, 'page_size' => 100, 'page' => $page]);
            $results = $data['results'] ?? null;
            if (!is_array($results) || !array_is_list($results) || count($results) > 100) {
                throw new OrfeoUnavailable('La réponse Orfeo n’a pas le format attendu.');
            }
            foreach ($results as $row) {
                if (!is_array($row)) {
                    throw new OrfeoUnavailable('La réponse Orfeo n’a pas le format attendu.');
                }
                $rows[] = $row;
            }
            if (empty($data['next'])) {
                return $rows;
            }
            // Ne jamais suivre l’URL next : la clé ne doit pas quitter l’hôte Orfeo.
            if ([] === $results) {
                break;
            }
        }
        throw new OrfeoUnavailable('Trop de résultats Orfeo. Réduis le périmètre des spectacles.');
    }

    /** @param array<string, int|string> $query
     * @return array<string, mixed> */
    private function request(string $path, array $query): array
    {
        if ('' === $this->configuration->credentials()) {
            throw new OrfeoUnavailable('La clé Orfeo manque dans la configuration privée.');
        }
        try {
            $this->limiter->create($this->configuration->cacheKey())->reserve(1, 2)->wait();
            $response = $this->http->request('GET', $path, [
                'headers' => ['Authorization' => 'Token '.$this->configuration->credentials()],
                'query' => $query,
                'on_progress' => static function (int $downloaded, int $total): void {
                    if ($downloaded > self::MAX_BYTES || $total > self::MAX_BYTES) {
                        throw new TransportException('Réponse trop volumineuse.');
                    }
                },
            ]);
            $status = $response->getStatusCode();
            if (200 !== $status) {
                $response->cancel();
                throw new OrfeoUnavailable(match ($status) {
                    401, 403 => 'Orfeo refuse cet accès. Vérifie les permissions de la clé.',
                    429 => 'Orfeo demande de patienter. Réessaie dans quelques instants.',
                    default => 'Orfeo est temporairement indisponible.',
                });
            }
            $body = $response->getContent(false);
            if (strlen($body) > self::MAX_BYTES) {
                throw new \UnexpectedValueException();
            }
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \UnexpectedValueException();
            }

            return $data;
        } catch (TransportExceptionInterface|\JsonException|\UnexpectedValueException|MaxWaitDurationExceededException) {
            // L’exception HTTP peut contenir l’en-tête privé : ne pas la propager ni la journaliser.
            throw new OrfeoUnavailable('Impossible de lire Orfeo. Réessaie dans quelques instants.');
        }
    }
}
