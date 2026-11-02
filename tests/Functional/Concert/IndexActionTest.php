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
            new \DateTimeImmutable('+20 days', new \DateTimeZone('Europe/Paris')),
            'Plus tard',
            'Lyon',
        );
        $earlier = $creator->create(
            $reference,
            new \DateTimeImmutable('+3 days', new \DateTimeZone('Europe/Paris')),
            'Première date',
            'Marseille',
        );
        $crawler = $this->client->request('GET', '/concerts');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Première date', 'Plus tard'],
            $crawler->filter('.concert-card h3')->each(static fn ($node): string => $node->text()),
        );
        self::assertSelectorExists('a[href="/concerts/'.$earlier->getId().'"]');
        self::assertSelectorExists('a[href="/concerts/'.$later->getId().'"]');
    }

    public function testUpcomingAndPastConcertsAreSeparatedAndTodayRemainsUpcoming(): void
    {
        $creator = static::getContainer()->get(ConcertCreator::class);
        $reference = $this->sheet();
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        foreach ([
            ['Ancienne date', '-30 days'], ['Hier', '-1 day'], ['Aujourd’hui', '+0 days'], ['Demain', '+1 day'],
        ] as [$venue, $offset]) {
            $creator->create($reference, $today->modify($offset), $venue, 'Lyon');
        }
        $crawler = $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSame(['Aujourd’hui', 'Demain'], $crawler->filter('section[aria-labelledby="upcoming-concerts-title"] h3')->each(static fn ($node): string => $node->text()));
        self::assertSame(['Hier', 'Ancienne date'], $crawler->filter('section[aria-labelledby="past-concerts-title"] h3')->each(static fn ($node): string => $node->text()));
        self::assertSelectorCount(2, '.concert-card--past .concert-period-badge');
        self::assertSelectorNotExists('.concert-history .concert-availability-link');
        self::assertSelectorCount(2, 'section[aria-labelledby="upcoming-concerts-title"] .concert-availability-link');
    }

    public function testHistoryRemainsAvailableWhenThereAreNoUpcomingDates(): void
    {
        static::getContainer()->get(ConcertCreator::class)->create(
            $this->sheet(), new \DateTimeImmutable('yesterday', new \DateTimeZone('Europe/Paris')), 'Date passée', 'Lyon',
        );
        $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.concert-group-empty', 'Aucun concert à venir');
        self::assertSelectorTextContains('.concert-history', 'Date passée');
        self::assertSelectorNotExists('.empty-state');
    }
}
