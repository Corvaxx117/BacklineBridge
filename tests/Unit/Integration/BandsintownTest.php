<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Integration\Bandsintown\{
    BandsintownClient,
    BandsintownConfiguration,
    BandsintownEventNormalizer,
};
use App\Integration\Bandsintown\Exception\BandsintownUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\{MockResponse, JsonMockResponse};

final class BandsintownTest extends TestCase
{
    private function event(array $changes = []): array
    {
        return array_replace(
            [
                'id' => '10001',
                'artist_id' => '15466975',
                'datetime' => '2026-12-12T23:30:00-08:00',
                'venue' => [
                    'name' => 'Salle test',
                    'city' => 'Lyon',
                    'country' => 'France',
                    'street_address' => 'Adresse publique',
                ],
                'title' => '<b>Concert test</b>',
                'description' => '<script>alert(1)</script>Description',
                'lineup' => ['Avee Mana'],
                'offers' => [
                    [
                        'type' => 'Tickets',
                        'url' => 'https://www.bandsintown.com/t/123?app_id=SECRET&ref=show',
                    ],
                    ['type' => 'Bad', 'url' => 'javascript:alert(1)'],
                ],
            ],
            $changes,
        );
    }

    public function testNormalizerKeepsLocalDayAndSanitizesPublicData(): void
    {
        $event = new BandsintownEventNormalizer()->normalize($this->event(), '15466975');
        self::assertSame('2026-12-12', $event->date);
        self::assertSame('23:30', $event->localTime);
        self::assertSame('Concert test', $event->title);
        self::assertStringNotContainsString('<script>', $event->description);
        self::assertCount(1, $event->tickets);
        self::assertSame('https://www.bandsintown.com/t/123?ref=show', $event->tickets[0]['url']);
        self::assertSame('https://www.bandsintown.com/e/10001', $event->sourceUrl());
    }

    #[DataProvider('invalidEvents')]
    public function testInvalidOrUnrelatedEventsAreRejected(array $changes): void
    {
        $this->expectException(\UnexpectedValueException::class);
        new BandsintownEventNormalizer()->normalize($this->event($changes), '15466975');
    }

    public static function invalidEvents(): iterable
    {
        yield 'wrong artist' => [['artist_id' => '999']];
        yield 'impossible date' => [['datetime' => '2026-02-30T20:00:00']];
        yield 'impossible hour' => [['datetime' => '2026-12-12T25:00:00']];
        yield 'missing city' => [['venue' => ['name' => 'Salle']]];
        yield 'cancelled' => [['cancelled' => true]];
        yield 'stream' => [['streaming_event' => true]];
        yield 'malformed ID' => [['id' => ['oops']]];
    }

    public function testClientUsesStableArtistIdAndCachesOnlyNormalizedData(): void
    {
        $count = 0;
        $response = [
            $this->event(),
            $this->event(),
            $this->event(['id' => '10002', 'venue' => []]),
        ];
        $http = new MockHttpClient(function (string $method, string $url) use (
            &$count,
            $response,
        ): JsonMockResponse {
            ++$count;
            self::assertSame('GET', $method);
            self::assertStringStartsWith(
                'https://rest.bandsintown.com/artists/id_15466975/events/',
                $url,
            );
            self::assertStringContainsString('app_id=private-test-key', $url);
            self::assertStringContainsString('date=all', $url);
            return new JsonMockResponse($response);
        }, 'https://rest.bandsintown.com/');
        $client = new BandsintownClient(
            $http,
            new ArrayAdapter(),
            new BandsintownConfiguration('1', '15466975', 'private-test-key'),
            new BandsintownEventNormalizer(),
        );
        $result = $client->events();
        self::assertCount(1, $result->events);
        self::assertSame(2, $result->ignored);
        $client->events();
        self::assertSame(1, $count);
        self::assertStringNotContainsString('SECRET', json_encode($result->events[0]->toArray()));
    }

    #[DataProvider('unavailableResponses')]
    public function testHttpFailuresAreCachedAndDoNotExposeCredentials(
        int $status,
        string $body,
    ): void {
        $http = new MockHttpClient(
            new MockResponse($body, ['http_code' => $status]),
            'https://rest.bandsintown.com/',
        );
        $client = new BandsintownClient(
            $http,
            new ArrayAdapter(),
            new BandsintownConfiguration('1', '15466975', 'private-test-key'),
            new BandsintownEventNormalizer(),
        );
        for ($i = 0; $i < 2; ++$i) {
            try {
                $client->events();
                self::fail('Failure expected.');
            } catch (BandsintownUnavailable $error) {
                self::assertStringNotContainsString('private-test-key', $error->getMessage());
                self::assertNull($error->getPrevious());
            }
        }
        self::assertSame(1, $http->getRequestsCount());
    }

    public static function unavailableResponses(): iterable
    {
        yield [403, 'private-test-key'];
        yield [404, 'private-test-key'];
        yield [429, 'private-test-key'];
        yield [500, 'private-test-key'];
        yield [200, '{invalid'];
        yield [200, '{"error":"private-test-key"}'];
    }

    public function testTransportErrorDoesNotExposeTheRequestUrl(): void
    {
        $http = new MockHttpClient(
            new MockResponse('', ['error' => 'timeout with private-test-key']),
            'https://rest.bandsintown.com/',
        );
        $client = new BandsintownClient(
            $http,
            new ArrayAdapter(),
            new BandsintownConfiguration('1', '15466975', 'private-test-key'),
            new BandsintownEventNormalizer(),
        );
        try {
            $client->events();
            self::fail('Transport failure expected.');
        } catch (BandsintownUnavailable $error) {
            self::assertStringNotContainsString('private-test-key', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }

    public function testMissingKeyMakesNoNetworkRequest(): void
    {
        $http = new MockHttpClient();
        $client = new BandsintownClient(
            $http,
            new ArrayAdapter(),
            new BandsintownConfiguration('1', '15466975', ''),
            new BandsintownEventNormalizer(),
        );
        try {
            $client->events();
            self::fail('Key required.');
        } catch (BandsintownUnavailable) {
            self::assertSame(0, $http->getRequestsCount());
        }
    }
}
