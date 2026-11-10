<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\ConcertImportance;
use App\Service\Concert\ConcertCreator;
use App\Tests\CollaborationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImportanceTest extends CollaborationTestCase
{
    public function testAnInlineUpdatePreservesTheDossierAndEscapesTheInternalNote(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $crawler = $this->client->request('GET', '/concerts');
        self::assertSelectorTextContains('#enjeu-'.$id, 'Enjeu à évaluer');
        $form = $crawler->filter('#enjeu-'.$id.' form')->form();
        $this->client->submit($form, ['concert_importance' => [
            'level' => '5', 'note' => '<script>secret</script> · Pros attendus',
            'returnSort' => 'importance', 'returnLevel' => '5',
        ]]);
        self::assertResponseRedirects('/concerts?sort=importance&enjeu=5#concert-'.$id, 303);
        $this->entityManager()->clear();
        $saved = $this->entityManager()->find(Concert::class, $id);
        self::assertSame(ConcertImportance::Major, $saved->getImportance());
        self::assertSame('Accueil à 16 h.', $saved->getNotes());
        self::assertSame($sheetId, $saved->getPatchSheet()->getId());
        self::assertSame('Salle de test', $saved->getVenue());
        $this->client->followRedirect();
        self::assertSelectorTextContains('#enjeu-'.$id, 'Enjeu majeur · 5/5');
        self::assertSelectorNotExists('#enjeu-'.$id.' script');
        self::assertSelectorTextContains('#enjeu-'.$id, '<script>secret</script>');
    }

    public function testImportanceCanBeClearedAndTheDetailFormReturnsToTheConcert(): void
    {
        $concert = $this->concert();
        $concert->setImportance(ConcertImportance::High, 'Une précision');
        $this->entityManager()->flush();
        $id = $concert->getId();
        $crawler = $this->client->request('GET', '/concerts/'.$id);
        $form = $crawler->filter('#enjeu-'.$id.' form')->form();
        $this->client->submit($form, ['concert_importance[level]' => '', 'concert_importance[note]' => '']);
        self::assertResponseRedirects('/concerts/'.$id.'#enjeu-'.$id, 303);
        $this->entityManager()->clear();
        $saved = $this->entityManager()->find(Concert::class, $id);
        self::assertNull($saved->getImportance());
        self::assertNull($saved->getImportanceNote());
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidOrForgedFormsNeverOverwriteTheNote(array $changes): void
    {
        $concert = $this->concert();
        $concert->setImportance(ConcertImportance::High, 'À conserver');
        $this->entityManager()->flush();
        $uri = '/concerts/'.$concert->getId().'/enjeu';
        $values = $this->client->request('GET', $uri)->filter('form[name="concert_importance"]')->form()->getPhpValues();
        $values['concert_importance'] = array_replace($values['concert_importance'], $changes);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        self::assertSame('À conserver', $this->entityManager()->find(Concert::class, $concert->getId())->getImportanceNote());
    }

    public static function invalidPayloads(): iterable
    {
        yield 'unknown level' => [['level' => '6']];
        yield 'overlong note' => [['note' => str_repeat('x', 501)]];
        yield 'forged csrf' => [['_token' => 'forged']];
        yield 'missing revision' => [['revision' => '']];
        yield 'unsafe return filter' => [['returnSort' => 'external']];
    }

    public function testAStaleFormCannotOverwriteAnotherMembersChanges(): void
    {
        $id = $this->concert()->getId();
        $uri = '/concerts/'.$id.'/enjeu';
        $form = $this->client->request('GET', $uri)->filter('form[name="concert_importance"]')->form();
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE concert SET importance_level = 4, importance_note = ?, revision = revision + 1 WHERE id = ?',
            ['Saisie par un autre membre', $id],
        );
        $this->client->submit($form, ['concert_importance[level]' => '2', 'concert_importance[note]' => 'Mon brouillon']);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('textarea', 'Mon brouillon');
        $this->entityManager()->clear();
        self::assertSame('Saisie par un autre membre', $this->entityManager()->find(Concert::class, $id)->getImportanceNote());
    }

    #[DataProvider('roles')]
    public function testEditingRespectsTheConcertPlanningPermissions(BandRole $role, bool $allowed): void
    {
        $id = $this->concert()->getId();
        $this->loginRole($role);
        $this->client->request('GET', '/concerts');
        self::assertSelectorCount($allowed ? 1 : 0, '#enjeu-'.$id.' form');
        $this->client->request('GET', '/concerts/'.$id.'/enjeu');
        self::assertResponseStatusCodeSame($allowed ? 200 : 403);
    }

    public static function roles(): iterable
    {
        yield [BandRole::Booker, true];
        yield [BandRole::TeamManager, true];
        yield [BandRole::Editor, false];
        yield [BandRole::Viewer, false];
    }

    public function testOtherBandsAndVenueGuestsCannotAccessTheInternalNote(): void
    {
        $concert = $this->concert();
        $concert->setImportance(ConcertImportance::Major, 'Stratégie privée unique');
        $this->entityManager()->flush();
        $issued = $this->issue($concert);
        $other = new Band('Autre groupe', new TechnicalSheet());
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $this->loginRole(BandRole::Manager, $other);
        $this->client->request('GET', '/concerts/'.$concert->getId().'/enjeu');
        self::assertResponseStatusCodeSame(403);
        $this->enter($issued->token);
        self::assertSelectorTextNotContains('body', 'Stratégie privée unique');
        self::assertSelectorNotExists('.concert-importance');
    }

    public function testFiltersAndImportanceSortingKeepPastAndFutureSeparated(): void
    {
        $creator = static::getContainer()->get(ConcertCreator::class);
        foreach ([
            ['Future faible', '+1 day', ConcertImportance::Low],
            ['Future majeure', '+10 days', ConcertImportance::Major],
            ['Future sans enjeu', '+2 days', null],
            ['Passée forte', '-10 days', ConcertImportance::High],
            ['Passée faible', '-1 day', ConcertImportance::Low],
        ] as [$venue, $date, $importance]) {
            $concert = $creator->create($this->sheet(), new \DateTimeImmutable($date), $venue, 'Lyon');
            $concert->setImportance($importance, null);
        }
        $this->entityManager()->flush();
        $crawler = $this->client->request('GET', '/concerts?sort=importance');
        self::assertResponseIsSuccessful();
        self::assertSame(['Future majeure', 'Future faible', 'Future sans enjeu'],
            $crawler->filter('[aria-labelledby="upcoming-concerts-title"] h3')->each(static fn ($node): string => $node->text()));
        self::assertSame(['Passée forte', 'Passée faible'],
            $crawler->filter('[aria-labelledby="past-concerts-title"] h3')->each(static fn ($node): string => $node->text()));
        $crawler = $this->client->request('GET', '/concerts?enjeu=1&sort=importance');
        self::assertSame(['Future faible', 'Passée faible'], $crawler->filter('.concert-card h3')->each(static fn ($node): string => $node->text()));
        $crawler = $this->client->request('GET', '/concerts?enjeu=unrated');
        self::assertSame(['Future sans enjeu'], $crawler->filter('.concert-card h3')->each(static fn ($node): string => $node->text()));
        $this->client->request('GET', '/concerts?enjeu=3');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aucun concert pour ce niveau');
        $this->client->request('GET', '/concerts?enjeu[]=5&sort[]=importance');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '.concert-card');
    }
}
