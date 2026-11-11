<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\Band;
use App\Entity\TechnicalSheet;
use App\Enum\ConcertImportance;
use App\Service\Concert\ConcertCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SearchTest extends DatabaseTestCase
{
    #[DataProvider('keywords')]
    public function testSearchFindsVisibleConcertsByVenueCityNotesOrBandName(string $keyword): void
    {
        $this->createConcerts();
        $this->client->request('GET', '/concerts?'.http_build_query(['q' => $keyword]));
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.concert-card');
        self::assertSelectorTextContains('.concert-card', 'Le Petit Bain');
        self::assertInputValueSame('q', trim($keyword));
    }

    public static function keywords(): iterable
    {
        yield 'venue, case insensitive' => ['  PETIT BAIN  '];
        yield 'city' => ['Marseille'];
        yield 'notes' => ['navette'];
        yield 'band name' => ['Groupe recherché'];
        yield 'literal wildcard characters' => ['100%_!'];
    }

    public function testSearchAndSortArePreservedInTheControlsAndImportanceForm(): void
    {
        $this->createConcerts();
        $this->client->request('GET', '/concerts?q=petit&sort=importance');
        self::assertSelectorCount(1, '.concert-card');
        self::assertSelectorExists('.concert-sort-links a[aria-current="page"][href*="sort=importance"]');
        self::assertSelectorExists('.concert-sort-links a[href*="sort=date"][href*="q=petit"]');
        self::assertInputValueSame('sort', 'importance');
        self::assertInputValueSame('concert_importance[returnSearch]', 'petit');
        self::assertSelectorExists('a[aria-label="Effacer la recherche"][href="/concerts?sort=importance"]');
        self::assertSelectorNotExists('.concert-level-filters');
    }

    public function testEmptyResultsRemainEscapedAndDoNotLookLikeAFirstVisit(): void
    {
        $keyword = '<script>missing</script>';
        $this->client->request('GET', '/concerts?'.http_build_query(['q' => $keyword]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', $keyword);
        self::assertSelectorNotExists('.empty-state');
        self::assertSelectorNotExists('script:not([src])');
    }

    public function testSearchNeverRevealsAnotherBandsConcertOrTreatsInputAsSql(): void
    {
        $this->createConcerts();
        $other = new Band('Groupe privé', new TechnicalSheet());
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        static::getContainer()->get(ConcertCreator::class)->create($other->getReferenceSheet(), new \DateTimeImmutable('+1 day'), 'Mot confidentiel', 'Ville cachée');
        foreach (['confidentiel', "%' OR 1=1 --"] as $keyword) {
            $this->client->request('GET', '/concerts?'.http_build_query(['q' => $keyword]));
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(0, '.concert-card');
            self::assertSelectorTextNotContains('body', 'Mot confidentiel');
        }
    }

    public function testMalformedAndOverlongQueriesAreSafelyNormalized(): void
    {
        $this->createConcerts();
        $this->client->request('GET', '/concerts?q[]=test&sort[]=importance');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.concert-card');
        self::assertInputValueSame('q', '');
        $this->client->request('GET', '/concerts?'.http_build_query(['q' => str_repeat('x', 300)]));
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('q', str_repeat('x', 160));
    }

    private function createConcerts(): void
    {
        $this->sheet()->setBandName('Groupe recherché');
        $creator = static::getContainer()->get(ConcertCreator::class);
        $match = $creator->create($this->sheet(), new \DateTimeImmutable('+5 days'), 'Le Petit Bain', 'Marseille', notes: 'Navette à 16 h · 100%_!');
        $match->setImportance(ConcertImportance::High, null);
        $this->sheet()->setBandName('Autre nom');
        $creator->create($this->sheet(), new \DateTimeImmutable('+10 days'), 'Grande salle', 'Lyon');
        $this->entityManager()->flush();
    }
}
