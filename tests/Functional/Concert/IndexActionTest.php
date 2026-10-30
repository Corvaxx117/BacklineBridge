<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Service\Concert\ConcertCreator;
use App\Tests\DatabaseTestCase;

final class IndexActionTest extends DatabaseTestCase
{
    public function testTheFirstVisitOffersToCreateAConcert(): void
    {
        $this->client->request('GET', '/concerts');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Concerts');
        self::assertSelectorTextContains('.empty-state', 'première date');
        self::assertSelectorExists('a[href="/concerts/new"]');
    }

    public function testConcertsAreListedInDateOrderWithLinksToTheirOwnDossier(): void
    {
        $creator = static::getContainer()->get(ConcertCreator::class);
        $reference = $this->sheet();
        $later = $creator->create(
            $reference,
            new \DateTimeImmutable('2027-04-02'),
            'Plus tard',
            'Lyon',
        );
        $earlier = $creator->create(
            $reference,
            new \DateTimeImmutable('2026-11-14'),
            'Première date',
            'Marseille',
        );
        $crawler = $this->client->request('GET', '/concerts');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Première date', 'Plus tard'],
            $crawler->filter('.concert-card h2')->each(static fn ($node): string => $node->text()),
        );
        self::assertSelectorExists('a[href="/concerts/'.$earlier->getId().'"]');
        self::assertSelectorExists('a[href="/concerts/'.$later->getId().'"]');
    }
}
