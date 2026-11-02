<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Integration\GoogleCalendar\Dto\AgendaEvent;
use App\Integration\GoogleCalendar\Dto\EventDraft;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Integration\GoogleCalendar\GoogleAccessTokenProvider;
use App\Integration\GoogleCalendar\GoogleCalendarClient;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Integration\GoogleCalendar\GoogleEventNormalizer;
use App\Integration\GoogleCalendar\ServiceAccountTokenProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleCalendarTest extends TestCase
{
    private function configuration(string $file = '/not-configured.json'): GoogleCalendarConfiguration
    {
        return new GoogleCalendarConfiguration('1', 'group@example.test', $file, str_repeat('a', 32), 'Europe/Paris');
    }

    private function client(array|callable $responses): GoogleCalendarClient
    {
        $tokens = $this->createMock(GoogleAccessTokenProvider::class);
        $tokens->method('token')->willReturn('synthetic-token');

        return new GoogleCalendarClient(
            new MockHttpClient($responses, 'https://www.googleapis.com/calendar/v3/'),
            $tokens,
            $this->configuration(),
            new GoogleEventNormalizer($this->configuration()),
        );
    }

    private function draft(): EventDraft
    {
        $start = new \DateTimeImmutable('2026-12-12 20:00', new \DateTimeZone('Europe/Paris'));

        return new EventDraft($this->configuration()->eventId(12), 'Avee Mana — Salle', 'Salle, Lyon', $start, $start->modify('+1 hour'));
    }

    public function testPublicationUsesOneGroupCalendarAndExplicitTimezone(): void
    {
        $draft = $this->draft();
        $client = $this->client(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://www.googleapis.com/calendar/v3/calendars/group%40example.test/events?sendUpdates=none', $url);
            $data = json_decode($options['body'], true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('2026-12-12T20:00:00+01:00', $data['start']['dateTime']);
            self::assertSame('Europe/Paris', $data['start']['timeZone']);
            self::assertSame('2026-12-12T21:00:00+01:00', $data['end']['dateTime']);
            self::assertArrayNotHasKey('attendees', $data);
            self::assertArrayNotHasKey('description', $data);

            return new MockResponse(json_encode($data + ['htmlLink' => 'https://calendar.google.com/calendar/event?eid=sample'], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });
        $result = $client->publish($draft);
        self::assertTrue($result->created);
        self::assertSame('https://calendar.google.com/calendar/event?eid=sample', $result->url);
    }

    public function testDuplicateAndUncertainPreviousRequestKeepExistingEventUntouched(): void
    {
        $draft = $this->draft();
        $requests = [];
        $client = $this->client(function (string $method, string $url) use ($draft, &$requests): MockResponse {
            $requests[] = $method;
            if ('POST' === $method) {
                return new MockResponse('', ['http_code' => 409]);
            }
            self::assertStringEndsWith('/events/'.$draft->id, $url);

            return new MockResponse(json_encode($draft->payload() + ['htmlLink' => 'https://www.google.com/calendar/event?eid=sample'], JSON_THROW_ON_ERROR));
        });
        self::assertFalse($client->publish($draft)->created);
        self::assertSame(['POST', 'GET'], $requests);
    }

    public function testCollisionWithAnUnrelatedEventIsNeverClaimedOrOverwritten(): void
    {
        $client = $this->client([
            new MockResponse('', ['http_code' => 409]),
            new MockResponse(json_encode(['id' => $this->draft()->id, 'summary' => 'Autre événement'], JSON_THROW_ON_ERROR)),
        ]);
        $this->expectException(GoogleCalendarUnavailable::class);
        $client->publish($this->draft());
    }

    public function testPagesAreReadAndRecurringOccurrencesAreRequested(): void
    {
        $requests = 0;
        $client = $this->client(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;
            self::assertSame('GET', $method);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('true', $query['singleEvents']);
            self::assertSame('startTime', $query['orderBy']);
            if (1 === $requests) {
                return new MockResponse(json_encode(['items' => [$this->timedEvent()], 'nextPageToken' => 'next-page'], JSON_THROW_ON_ERROR));
            }
            self::assertSame('next-page', $query['pageToken']);

            return new MockResponse(json_encode(['items' => [[
                'summary' => 'Réunion', 'start' => ['date' => '2026-12-13'], 'end' => ['date' => '2026-12-14'],
            ], ['status' => 'cancelled']]], JSON_THROW_ON_ERROR));
        });
        $listing = $client->events(new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2027-03-01'));
        self::assertCount(2, $listing->events);
        self::assertFalse($listing->hasMore);
        self::assertTrue($listing->events[1]->allDay);
        self::assertSame('2026-12-13', $listing->events[1]->end->format('Y-m-d'));
    }

    private function timedEvent(): array
    {
        return ['summary' => 'Répétition', 'start' => ['dateTime' => '2026-12-12T19:00:00Z'], 'end' => ['dateTime' => '2026-12-12T20:00:00Z']];
    }

    #[DataProvider('failures')]
    public function testFailuresDoNotDiscloseRemoteContent(int $status, string $body): void
    {
        $client = $this->client([new MockResponse($body, ['http_code' => $status])]);
        try {
            $client->publish($this->draft());
            self::fail('La publication devait échouer.');
        } catch (GoogleCalendarUnavailable $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public static function failures(): iterable
    {
        yield 'interdit' => [403, 'secret'];
        yield 'quota' => [429, 'secret'];
        yield 'indisponible' => [503, 'secret'];
        yield 'malformé' => [200, 'secret'];
        yield 'liste au lieu d’objet' => [200, '["secret"]'];
        yield 'trop volumineux' => [200, str_repeat('secret', 350000)];
    }

    public function testWholeDayAndSummerTimesAreDisplayedInCalendarTimezone(): void
    {
        $normalizer = new GoogleEventNormalizer($this->configuration());
        $event = $normalizer->normalize([
            'summary' => 'Résidence', 'start' => ['date' => '2027-07-10'], 'end' => ['date' => '2027-07-13'],
        ]);
        self::assertSame('2027-07-12', $event->end->format('Y-m-d'));
        $event = $normalizer->normalize([
            'start' => ['dateTime' => '2027-07-10T18:00:00Z'], 'end' => ['dateTime' => '2027-07-10T19:00:00Z'],
        ]);
        self::assertSame('20:00 +02:00', $event->start->format('H:i P'));
    }

    #[DataProvider('invalidUrls')]
    public function testOnlyGoogleHttpsLinksCanBeRendered(mixed $url): void
    {
        self::assertNull(AgendaEvent::safeUrl($url));
    }

    public static function invalidUrls(): iterable
    {
        yield ['javascript:alert(1)'];
        yield ['https://calendar.google.com.evil.test/calendar'];
        yield ['https://name@calendar.google.com/calendar'];
        yield ['http://calendar.google.com/calendar'];
        yield ['https://calendar.google.com:8443/calendar'];
        yield [null];
    }

    public function testCredentialsOutsidePublicAreRequiredBeforeNetworkAccess(): void
    {
        $provider = new ServiceAccountTokenProvider($this->configuration(), sys_get_temp_dir());
        $this->expectException(GoogleCalendarUnavailable::class);
        $provider->token();
    }

    public function testUnexpectedCredentialFormatIsRejectedWithoutUsingItsUrl(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bb-google-invalid-');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, '{"type":"external_account","token_uri":"https://evil.test"}');
            $provider = new ServiceAccountTokenProvider($this->configuration($path), dirname(__DIR__, 3).'/public');
            $this->expectException(GoogleCalendarUnavailable::class);
            $provider->token();
        } finally {
            unlink($path);
        }
    }

    public function testPublicCredentialFileIsRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bb-google-public-');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, '{}');
            $provider = new ServiceAccountTokenProvider($this->configuration($path), dirname($path));
            $this->expectException(GoogleCalendarUnavailable::class);
            $provider->token();
        } finally {
            unlink($path);
        }
    }

    public function testEventIdentifierIsStableAndIsolatedBetweenInstallations(): void
    {
        $configuration = $this->configuration();
        self::assertSame($configuration->eventId(1), $configuration->eventId(1));
        self::assertNotSame($configuration->eventId(1), $configuration->eventId(2));
        $other = new GoogleCalendarConfiguration('1', 'group@example.test', '/key.json', str_repeat('b', 32), 'Europe/Paris');
        self::assertNotSame($configuration->eventId(1), $other->eventId(1));
        self::assertMatchesRegularExpression('/^[a-v0-9]{5,1024}$/D', $configuration->eventId(1));
    }
}
