<?php

declare(strict_types=1);

namespace App\Tests\Functional\Setlist;

use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\Setlist;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Service\Concert\ConcertRemover;
use App\Service\Pdf\TechnicalDossierFactory;
use App\Service\Security\UserCreator;
use App\Service\Venue\PublicationBuilder;
use App\Tests\VenueTestCase;
use App\ValueObject\SetlistContent;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;

final class SetlistTest extends VenueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $em = $this->entityManager();
        $em->persist(new MemberProfile($em->find(Band::class, $this->bandId), $em->find(User::class, $this->userId), 'Musicien test', 'Guitare', MemberKind::Musician));
        $em->flush();
    }

    public function testReferenceIsImportedExplicitlyAndConcertEditsRemainIndependent(): void
    {
        $this->edit($this->sheetId, "  Première chanson \r\n\n Deuxième chanson\r\nRappel");
        self::assertResponseRedirects('/#setlist', 303);
        self::assertSame(['Première chanson', 'Deuxième chanson', 'Rappel'], $this->tracks($this->sheetId));
        $this->client->followRedirect();
        self::assertSelectorExists('#setlist:not([open])');
        self::assertSelectorTextContains('#setlist > summary', '3 titres');
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));

        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        self::assertSame([], $this->tracks($sheetId));
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorExists('#setlist a[href="/concerts/'.$id.'/setlist/reference"]');
        $uri = '/concerts/'.$id.'/setlist/reference';
        $form = $this->client->request('GET', $uri)->filter('form[name="import_setlist"]')->form();
        self::assertSelectorNotExists('input[name="import_setlist[confirmReplacement]"]');
        $this->client->submit($form);
        self::assertResponseRedirects('/concerts/'.$id.'#setlist', 303);
        self::assertSame($this->tracks($this->sheetId), $this->tracks($sheetId));

        $this->edit($sheetId, "Deuxième chanson\nPremière chanson\nRappel");
        self::assertResponseStatusCodeSame(303);
        $this->edit($this->sheetId, 'Nouvelle référence');
        self::assertResponseStatusCodeSame(303);
        self::assertSame(['Deuxième chanson', 'Première chanson', 'Rappel'], $this->tracks($sheetId));
        self::assertSame(['Nouvelle référence'], $this->tracks($this->sheetId));
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorTextContains('#setlist', 'Modifiée par Responsable de test');
        self::assertSelectorTextNotContains('#setlist', 'Nouvelle référence');
    }

    public function testReplacingAnExistingConcertRequiresConfirmation(): void
    {
        $this->put($this->sheetId, ['Référence A', 'Référence B']);
        $concert = $this->concert();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->put($sheetId, ['Ordre propre au concert']);
        $uri = '/concerts/'.$concert->getId().'/setlist/reference';
        $form = $this->client->request('GET', $uri)->filter('form[name="import_setlist"]')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Ordre propre au concert'], $this->tracks($sheetId));
        $form = $this->client->request('GET', $uri)->filter('form[name="import_setlist"]')->form();
        $this->client->submit($form, ['import_setlist' => ['confirmReplacement' => '1']]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(['Référence A', 'Référence B'], $this->tracks($sheetId));
    }

    public function testStaleEditionPreservesTheLatestListAndTheSubmittedText(): void
    {
        $this->put($this->sheetId, ['Avant']);
        $uri = '/fiches/'.$this->sheetId.'/setlist';
        $first = $this->client->request('GET', $uri)->filter('form[name="setlist"]')->form();
        $stale = $this->client->request('GET', $uri)->filter('form[name="setlist"]')->form();
        $this->client->submit($first, ['setlist' => ['songs' => 'Version récente']]);
        self::assertResponseStatusCodeSame(303);
        $this->client->submit($stale, ['setlist' => ['songs' => 'Ma proposition à conserver']]);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('textarea[name="setlist[songs]"]', 'Ma proposition à conserver');
        self::assertSelectorExists('a[href="'.$uri.'"]');
        self::assertSame(['Version récente'], $this->tracks($this->sheetId));
    }

    #[DataProvider('changedSetlist')]
    public function testAnImportCannotOverwriteAChangedSourceOrDestination(bool $changeReference): void
    {
        $this->put($this->sheetId, ['Référence initiale']);
        $concert = $this->concert();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->put($sheetId, ['Version du concert']);
        $uri = '/concerts/'.$concert->getId().'/setlist/reference';
        $form = $this->client->request('GET', $uri)->filter('form[name="import_setlist"]')->form();
        $this->put($changeReference ? $this->sheetId : $sheetId, ['Modification concurrente']);
        $this->client->submit($form, ['import_setlist' => ['confirmReplacement' => '1']]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame($changeReference ? ['Version du concert'] : ['Modification concurrente'], $this->tracks($sheetId));
    }

    public static function changedSetlist(): iterable
    {
        yield 'référence modifiée' => [true];
        yield 'concert modifié' => [false];
    }

    public function testAnEmptyReferenceCannotClearAConcertThroughAForgedImport(): void
    {
        $concert = $this->concert();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->put($sheetId, ['À garder']);
        $uri = '/concerts/'.$concert->getId().'/setlist/reference';
        $form = $this->client->request('GET', $uri)->filter('form[name="import_setlist"]')->form();
        self::assertSelectorTextContains('.setlist-empty', 'référence est vide');
        $this->client->submit($form, ['import_setlist' => ['confirmReplacement' => '1']]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['À garder'], $this->tracks($sheetId));
    }

    #[DataProvider('invalidLists')]
    public function testInvalidTextIsRejectedBeforeAnyWrite(string $text): void
    {
        $form = $this->client->request('GET', '/fiches/'.$this->sheetId.'/setlist')->filter('form[name="setlist"]')->form();
        $this->client->submit($form, ['setlist' => ['songs' => $text]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.field-errors li');
        self::assertSame([], $this->tracks($this->sheetId));
    }

    public static function invalidLists(): iterable
    {
        yield 'titre trop long' => [str_repeat('é', 161)];
        yield 'trop de titres' => [implode("\n", array_fill(0, 101, 'Morceau'))];
        yield 'texte trop long' => [str_repeat('a', SetlistContent::MAX_TEXT_LENGTH + 1)];
    }

    #[DataProvider('memberPermissions')]
    public function testAllMembersCanReadAndOnlyMusiciansCanWrite(MemberKind $kind, BandRole $role, bool $canWrite): void
    {
        $this->put($this->sheetId, ['Référence commune']);
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->put($sheetId, ['Concert commun', '<script>alert(1)</script>']);
        $em = $this->entityManager();
        $band = $em->find(Band::class, $this->bandId);
        $user = static::getContainer()->get(UserCreator::class)->create('member-setlist@example.test', 'Autre membre', 'SetlistTest2026!', $band, $role);
        $em->persist(new MemberProfile($band, $user, 'Autre membre', $kind->label(), $kind));
        $em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#setlist', 'Référence commune');
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#setlist', 'Concert commun');
        self::assertSelectorNotExists('#setlist script');
        self::assertSelectorCount($canWrite ? 2 : 0, '#setlist .setlist-action');
        foreach (['/fiches/'.$sheetId.'/setlist', '/fiches/'.$this->sheetId.'/setlist', '/concerts/'.$id.'/setlist/reference'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame($canWrite ? 200 : 403);
            if (!$canWrite) {
                $this->client->request('POST', $uri, ['setlist' => ['songs' => 'Interdit']]);
                self::assertResponseStatusCodeSame(403);
            }
        }
        self::assertSame(['Concert commun', '<script>alert(1)</script>'], $this->tracks($sheetId));
    }

    public static function memberPermissions(): iterable
    {
        yield 'musicien responsable' => [MemberKind::Musician, BandRole::Manager, true];
        yield 'ingénieur son responsable' => [MemberKind::Engineer, BandRole::Manager, false];
        yield 'booker' => [MemberKind::Booker, BandRole::Booker, false];
        yield 'musicien en lecture' => [MemberKind::Musician, BandRole::Viewer, false];
        yield 'profil technique' => [MemberKind::Musician, BandRole::Editor, false];
    }

    public function testCsrfAndRevisionAreRequiredAndExtraFieldsAreRejected(): void
    {
        $uri = '/fiches/'.$this->sheetId.'/setlist';
        foreach (['_token', 'revision'] as $field) {
            $values = $this->client->request('GET', $uri)->filter('form[name="setlist"]')->form()->getPhpValues();
            unset($values['setlist'][$field]);
            $values['setlist']['songs'] = 'Ne pas enregistrer';
            $this->client->request('POST', $uri, $values);
            self::assertResponseStatusCodeSame(422);
        }
        $form = $this->client->request('GET', $uri)->filter('form[name="setlist"]')->form();
        $values = $form->getPhpValues();
        $values['setlist']['sheet'] = 999;
        $values['setlist']['songs'] = 'Interdit';
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->tracks($this->sheetId));
        $this->put($this->sheetId, ['Référence protégée']);
        $concert = $this->concert();
        $target = $concert->getPatchSheet()->getId();
        $uri = '/concerts/'.$concert->getId().'/setlist/reference';
        foreach (['_token', 'revision', 'referenceRevision'] as $field) {
            $values = $this->client->request('GET', $uri)->filter('form[name="import_setlist"]')->form()->getPhpValues();
            unset($values['import_setlist'][$field]);
            $this->client->request('POST', $uri, $values);
            self::assertResponseStatusCodeSame(422);
        }
        self::assertSame([], $this->tracks($target));
    }

    public function testAClearedSetlistAndConcertDeletionDoNotAffectTheReference(): void
    {
        $this->put($this->sheetId, ['Référence conservée']);
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->put($sheetId, ['Morceau du concert']);
        $this->edit($sheetId, '');
        self::assertResponseStatusCodeSame(303);
        self::assertSame([], $this->tracks($sheetId));
        $em = $this->entityManager();
        $concert = $em->find(\App\Entity\Concert::class, $id);
        static::getContainer()->get(ConcertRemover::class)->remove($concert, (string) $concert->getRevision());
        $em->clear();
        self::assertSame(1, $em->getRepository(Setlist::class)->count([]));
        self::assertSame(['Référence conservée'], $this->tracks($this->sheetId));
    }

    public function testForeignBandListsCannotBeReadEditedOrImported(): void
    {
        $em = $this->entityManager();
        $sheet = new TechnicalSheet()->setBandName('Autre groupe');
        $band = new Band('Autre groupe', $sheet);
        $em->persist($band);
        $em->flush();
        $otherConcert = static::getContainer()->get(\App\Service\Concert\ConcertCreator::class)->create($sheet, new \DateTimeImmutable('2026-12-15'), 'Autre salle', 'Nantes');
        foreach (['/fiches/'.$sheet->getId().'/setlist', '/fiches/'.$otherConcert->getPatchSheet()->getId().'/setlist', '/concerts/'.$otherConcert->getId().'/setlist/reference', '/concerts/'.$otherConcert->getId()] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testSetlistsDoNotEnterTheSharedSnapshotPdfOrGuestWorkspace(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $builder = static::getContainer()->get(PublicationBuilder::class);
        $before = $builder->snapshot($concert);
        $secret = 'SETLIST_INTERNE_7429';
        $this->put($this->sheetId, [$secret]);
        $this->put($sheetId, [$secret]);
        $concert = $this->entityManager()->find(\App\Entity\Concert::class, $id);
        self::assertSame($before, $builder->snapshot($concert));
        $factory = static::getContainer()->get(TechnicalDossierFactory::class);
        self::assertStringNotContainsString($secret, serialize($factory->forSheet($this->sheet())));
        self::assertStringNotContainsString($secret, serialize($factory->forSheet($concert->getPatchSheet())));
        $invitation = $this->issue($concert);
        $this->enter($invitation->token);
        self::assertSelectorNotExists('#setlist');
        self::assertStringNotContainsString($secret, $this->client->getResponse()->getContent());
        $this->client->request('GET', $this->guestUrl('/salle/fiche-technique.pdf'));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        $this->client->request('GET', '/fiches/'.$sheetId.'/setlist');
        self::assertResponseRedirects('/connexion');
    }

    public function testAWriteRacingWithTheOrmFlushShowsAConflictInsteadOfLosingTheOtherEdit(): void
    {
        $this->client->disableReboot();
        $setlistId = $this->put($this->sheetId, ['Avant']);
        $uri = '/fiches/'.$this->sheetId.'/setlist';
        $form = $this->client->request('GET', $uri)->filter('form[name="setlist"]')->form();
        $em = $this->entityManager();
        $connection = $em->getConnection();
        $other = DriverManager::getConnection($connection->getParams(), $connection->getConfiguration());
        $listener = new class($other, $setlistId) {
            public function __construct(private Connection $connection, private int $id)
            {
            }

            public function preFlush(): void
            {
                $this->connection->executeStatement('UPDATE setlist SET tracks = ?, revision = revision + 1 WHERE id = ?', [json_encode(['Autre membre'], JSON_THROW_ON_ERROR), $this->id]);
            }
        };
        $em->getEventManager()->addEventListener([Events::preFlush], $listener);
        try {
            $this->client->submit($form, ['setlist' => ['songs' => 'Ma proposition']]);
            self::assertResponseStatusCodeSame(409);
            self::assertSelectorTextContains('textarea[name="setlist[songs]"]', 'Ma proposition');
            self::assertSame(['Autre membre'], json_decode($other->fetchOne('SELECT tracks FROM setlist WHERE id = ?', [$setlistId]), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $em->getEventManager()->removeEventListener([Events::preFlush], $listener);
            $other->close();
        }
    }

    public function testSimultaneousFirstEditCannotCreateTwoSetlistsForOneSheet(): void
    {
        $this->client->disableReboot();
        $form = $this->client->request('GET', '/fiches/'.$this->sheetId.'/setlist')->filter('form[name="setlist"]')->form();
        $em = $this->entityManager();
        $connection = $em->getConnection();
        $other = DriverManager::getConnection($connection->getParams(), $connection->getConfiguration());
        $listener = new class($other, $this->sheetId, $this->userId) {
            public function __construct(private Connection $connection, private int $sheetId, private int $userId)
            {
            }

            public function preFlush(): void
            {
                $this->connection->executeStatement(
                    'INSERT INTO setlist (sheet_id, updated_by_id, updated_by_name, updated_at, tracks, revision) VALUES (?, ?, ?, ?, ?, 1)',
                    [$this->sheetId, $this->userId, 'Autre membre', '2026-11-02 12:00:00', json_encode(['Créée entre-temps'], JSON_THROW_ON_ERROR)],
                );
            }
        };
        $em->getEventManager()->addEventListener([Events::preFlush], $listener);
        try {
            $this->client->submit($form, ['setlist' => ['songs' => 'Ma proposition']]);
            self::assertResponseStatusCodeSame(409);
            self::assertSelectorTextContains('textarea[name="setlist[songs]"]', 'Ma proposition');
            self::assertSame(1, (int) $other->fetchOne('SELECT COUNT(*) FROM setlist WHERE sheet_id = ?', [$this->sheetId]));
            self::assertSame(['Créée entre-temps'], json_decode($other->fetchOne('SELECT tracks FROM setlist WHERE sheet_id = ?', [$this->sheetId]), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $em->getEventManager()->removeEventListener([Events::preFlush], $listener);
            $other->close();
        }
    }

    private function edit(int $sheetId, string $text): void
    {
        $form = $this->client->request('GET', '/fiches/'.$sheetId.'/setlist')->filter('form[name="setlist"]')->form();
        self::assertResponseIsSuccessful();
        $this->client->submit($form, ['setlist' => ['songs' => $text]]);
    }

    /** @param list<string> $tracks */
    private function put(int $sheetId, array $tracks): int
    {
        $em = $this->entityManager();
        $sheet = $em->find(TechnicalSheet::class, $sheetId);
        $author = $em->find(User::class, $this->userId);
        $setlist = $em->getRepository(Setlist::class)->findOneBy(['sheet' => $sheet]);
        if (null === $setlist) {
            $setlist = new Setlist($sheet, SetlistContent::fromTracks($tracks), $author);
            $em->persist($setlist);
        } else {
            $setlist->replace(SetlistContent::fromTracks($tracks), $author);
        }
        $em->flush();

        return $setlist->getId();
    }

    /** @return list<string> */
    private function tracks(int $sheetId): array
    {
        $em = $this->entityManager();
        $em->clear();
        $sheet = $em->find(TechnicalSheet::class, $sheetId);

        return $em->getRepository(Setlist::class)->forSheet($sheet)?->getTracks() ?? [];
    }
}
