<?php

declare(strict_types=1);

namespace App\Tests\Functional\Patch;

use App\Tests\DatabaseTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class IndexActionTest extends DatabaseTestCase
{
    public function testTheTwentyTwoOriginalSourcesAreDisplayedInChannelOrder(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'AVEE MANA');
        $rows = $crawler->filter('table tbody tr');
        self::assertCount(22, $rows);
        $channels = $rows->each(
            static fn(Crawler $row): int => (int) trim($row->filter('td, th')->first()->text()),
        );
        self::assertSame(range(1, 22), $channels);

        foreach ($this->fixture['lines'] as $position => $data) {
            self::assertStringContainsString($data['sourceName'], $rows->eq($position)->text());
        }
    }
}
