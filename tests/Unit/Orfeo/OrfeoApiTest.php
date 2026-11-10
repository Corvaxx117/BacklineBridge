<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orfeo;

use App\Integration\Orfeo\Exception\OrfeoUnavailable;
use App\Integration\Orfeo\OrfeoApi;
use App\Integration\Orfeo\OrfeoConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class OrfeoApiTest extends TestCase
{
    private function api(MockHttpClient $http): OrfeoApi
    {
        return new OrfeoApi($http,
            new RateLimiterFactory(['id' => 'orfeo-test', 'policy' => 'no_limit'], new InMemoryStorage()),
            new OrfeoConfiguration('1', 'private-test-key', '301', '44', 'Europe/Paris', false));
    }

    public function testPaginationNeverForwardsTheKeyToTheNextUrl(): void
    {
        $urls = [];
        $http = new MockHttpClient(static function ($method, $url, $options) use (&$urls): MockResponse {
            $urls[] = $url;
            self::assertSame('GET', $method);
            self::assertStringStartsWith('https://orfeoapp.com/api/project/', $url);
            self::assertStringContainsString('spectacle=301', $url);
            self::assertContains('Authorization: Token private-test-key', $options['headers']);

            return new MockResponse(json_encode(1 === count($urls)
                ? ['results' => [['pk' => 8]], 'next' => 'https://foreign.example/steal-key']
                : ['results' => [], 'next' => null], JSON_THROW_ON_ERROR));
        }, 'https://orfeoapp.com/');
        self::assertSame([['pk' => 8]], $this->api($http)->list('project', ['spectacle' => 301]));
        self::assertCount(2, $urls);
        self::assertStringContainsString('page=2', $urls[1]);
    }

    public function testDeniedAccessDoesNotExposeTheKeyOrServerBody(): void
    {
        $api = $this->api(new MockHttpClient(new MockResponse('private-test-key sensitive server body', ['http_code' => 403]), 'https://orfeoapp.com/'));
        try {
            $api->list('project');
            self::fail('Le refus doit arrêter la lecture.');
        } catch (OrfeoUnavailable $e) {
            self::assertStringContainsString('permissions', $e->getMessage());
            self::assertStringNotContainsString('private-test-key', $e->getMessage());
            self::assertStringNotContainsString('sensitive', $e->getMessage());
        }
    }

    public function testPaginationHasABound(): void
    {
        $api = $this->api(new MockHttpClient(static fn (): MockResponse => new MockResponse('{"results":[{"pk":1}],"next":"more"}'), 'https://orfeoapp.com/'));
        $this->expectException(OrfeoUnavailable::class);
        $this->expectExceptionMessage('Trop de résultats');
        $api->list('project');
    }
}
