<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar;

use App\Integration\GoogleCalendar\Dto\AgendaEvent;
use App\Integration\GoogleCalendar\Dto\AgendaListing;
use App\Integration\GoogleCalendar\Dto\EventDraft;
use App\Integration\GoogleCalendar\Dto\PublicationResult;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleCalendarClient implements GoogleCalendarGateway
{
    private const int MAX_RESPONSE_BYTES = 2_000_000;

    public function __construct(
        #[Autowire(service: 'google_calendar.http_client')] private HttpClientInterface $http,
        private GoogleAccessTokenProvider $tokens,
        private GoogleCalendarConfiguration $configuration,
        private GoogleEventNormalizer $normalizer,
    ) {
    }

    public function events(\DateTimeImmutable $from, \DateTimeImmutable $until): AgendaListing
    {
        $events = [];
        $pageToken = null;
        for ($page = 0; $page < 10; ++$page) {
            $query = [
                'timeMin' => $from->format(\DateTimeInterface::RFC3339),
                'timeMax' => $until->format(\DateTimeInterface::RFC3339),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'showDeleted' => 'false',
                'maxResults' => 100,
                'timeZone' => $this->configuration->timezone()->getName(),
                'fields' => 'nextPageToken,items(summary,location,start,end,htmlLink,status)',
            ];
            if (null !== $pageToken) {
                $query['pageToken'] = $pageToken;
            }
            [, $data] = $this->request('GET', '', ['query' => $query]);
            $items = $data['items'] ?? [];
            if (!is_array($items) || !array_is_list($items) || count($items) > 100) {
                throw new GoogleCalendarUnavailable('La réponse de Google Agenda est invalide.');
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new GoogleCalendarUnavailable('La réponse de Google Agenda est invalide.');
                }
                if (($item['status'] ?? null) === 'cancelled') {
                    continue;
                }
                try {
                    $events[] = $this->normalizer->normalize($item);
                } catch (\Exception) {
                    throw new GoogleCalendarUnavailable('Un événement Google contient une date invalide. Réessaie après sa correction dans Google Agenda.');
                }
            }
            $pageToken = $data['nextPageToken'] ?? null;
            if (null === $pageToken) {
                break;
            }
            if (!is_string($pageToken) || '' === $pageToken || strlen($pageToken) > 4096) {
                throw new GoogleCalendarUnavailable('La pagination de Google Agenda est invalide.');
            }
        }

        return new AgendaListing($events, null !== $pageToken, new \DateTimeImmutable('now'));
    }

    public function publish(EventDraft $event): PublicationResult
    {
        [$status, $data] = $this->request('POST', '', [
            'query' => ['sendUpdates' => 'none'],
            'json' => $event->payload(),
        ], [409]);
        if (409 === $status) {
            [, $data] = $this->request('GET', '/'.rawurlencode($event->id));
        }
        if (
            ($data['id'] ?? null) !== $event->id
            || ($data['extendedProperties']['private']['backlinebridgeEventId'] ?? null) !== $event->id
            || ($data['status'] ?? null) === 'cancelled'
        ) {
            throw new GoogleCalendarUnavailable('L’événement associé n’est plus disponible. Vérifie l’agenda Google avant de réessayer.');
        }

        return new PublicationResult(409 !== $status, AgendaEvent::safeUrl($data['htmlLink'] ?? null));
    }

    /**
     * @param array<string, mixed> $options
     * @param list<int>            $accepted
     *
     * @return array{int, array<string, mixed>}
     */
    private function request(string $method, string $suffix, array $options = [], array $accepted = []): array
    {
        $token = $this->tokens->token();
        try {
            $response = $this->http->request(
                $method,
                'calendars/'.rawurlencode($this->configuration->calendarId()).'/events'.$suffix,
                array_replace($options, [
                    'auth_bearer' => $token,
                    'on_progress' => static function (int $downloaded, int $total): void {
                        if ($downloaded > self::MAX_RESPONSE_BYTES || $total > self::MAX_RESPONSE_BYTES) {
                            throw new TransportException('Réponse trop volumineuse.');
                        }
                    },
                ]),
            );
            $status = $response->getStatusCode();
            if (200 !== $status && 201 !== $status && !in_array($status, $accepted, true)) {
                $response->cancel();
                throw new GoogleCalendarUnavailable(match ($status) {
                    401, 403, 404 => 'L’agenda est inaccessible. Vérifie son identifiant et le partage avec le compte de service.',
                    429 => 'Google Agenda demande de patienter avant une nouvelle tentative.',
                    default => 'Google Agenda est temporairement indisponible. Réessaie dans quelques minutes.',
                });
            }
            if (409 === $status && in_array(409, $accepted, true)) {
                $response->cancel();

                return [409, []];
            }
            $body = $response->getContent(false);
            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                throw new \UnexpectedValueException();
            }
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data) || ([] !== $data && array_is_list($data))) {
                throw new \UnexpectedValueException();
            }

            return [$status, $data];
        } catch (ExceptionInterface|\JsonException|\UnexpectedValueException) {
            throw new GoogleCalendarUnavailable('Google Agenda n’a pas pu répondre correctement. Tu peux réessayer ; le même concert ne sera pas ajouté deux fois.');
        }
    }
}
