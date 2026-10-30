<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;

final class AuthorizationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
    }

    public function testAnotherGroupsConcertAndItsSourcesAreDeniedForGetAndPost(): void
    {
        $em = $this->entityManager();
        $otherSheet = new TechnicalSheet()
            ->setBandName('GROUPE CONFIDENTIEL')
            ->setDocumentLabel('Fiche privée');
        $otherSheet->addLine(
            new PatchLine()->setChannelNumber(1)->setSourceName('SOURCE CONFIDENTIELLE'),
        );
        $band = new Band('GROUPE CONFIDENTIEL', $otherSheet);
        $em->persist($band);
        $em->flush();
        $concert = static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $otherSheet,
                new \DateTimeImmutable('2026-12-12'),
                'SALLE CONFIDENTIELLE',
                'Lyon',
            );
        $source = $concert->getPatchSheet()->getLines()->first();
        $uri = '/concerts/'.$concert->getId();
        $lineUri = '/sources/'.$source->getId().'/edit';
        $ownReferenceLine = $otherSheet->getLines()->first()->getId();

        $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', 'SALLE CONFIDENTIELLE');
        $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('main', 'GROUPE CONFIDENTIEL');

        foreach (
            [
                $uri,
                $lineUri,
                '/sources/'.$ownReferenceLine.'/edit',
                $uri.'/sources/new',
                $uri.'/edit',
                $uri.'/conditions',
            ] as $path
        ) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
            // Vérifier le refus avant le binding/traitement, même si un formulaire est forgé.
            if ($path !== $uri) {
                $this->client->request('POST', $path, [
                    'patch_line' => ['sourceName' => 'TENTATIVE'],
                ]);
                self::assertResponseStatusCodeSame(403);
            }
        }
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            'SOURCE CONFIDENTIELLE',
            $em->find(PatchLine::class, $source->getId())->getSourceName(),
        );
        self::assertSame(24, $em->getRepository(PatchLine::class)->count([]));
    }

    public function testAViewerCanReadButCannotCreateOrEditThroughAnyRoute(): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $viewer = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'viewer@example.test',
                'Lecture seule',
                'Viewer test password 2026!',
                $band,
                BandRole::Viewer,
            );
        $concert = $this->concert();
        $sourceId = $concert->getPatchSheet()->getLines()->first()->getId();
        $referenceLineId = $this->lineForChannel(17)->getId();
        $this->client->loginUser($viewer);
        foreach (['/', '/concerts', '/concerts/'.$concert->getId()] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('a.hero-action');
            self::assertSelectorNotExists('a.edit-link');
            self::assertSelectorNotExists('a[href="/concerts/new"]');
        }
        foreach (
            [
                '/sources/new',
                '/concerts/new',
                '/sources/'.$referenceLineId.'/edit',
                '/sources/'.$sourceId.'/edit',
                '/concerts/'.$concert->getId().'/sources/new',
            ] as $path
        ) {
            foreach (['GET', 'POST'] as $method) {
                $this->client->request($method, $path);
                self::assertResponseStatusCodeSame(403);
            }
        }
        $this->assertLineCount(44);
        self::assertSame(1, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testAnEditorCanModifyAnAuthorizedPatch(): void
    {
        $editor = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'editor@example.test',
                'Éditeur',
                'Editor test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                BandRole::Editor,
            );
        $concert = $this->concert();
        $lineId = $concert->getPatchSheet()->getLines()->first()->getId();
        $this->client->loginUser($editor);
        $this->submitLineForm('/sources/'.$lineId.'/edit', [
            'notes' => 'Préparation par l’éditeur',
        ]);

        self::assertResponseRedirects('/concerts/'.$concert->getId(), 303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            'Préparation par l’éditeur',
            $em->find(PatchLine::class, $lineId)->getNotes(),
        );
    }

    public function testMembershipRevocationTakesEffectWhileTheUserRemainsLoggedIn(): void
    {
        $concert = $this->concert();
        $concertId = $concert->getId();
        $lineId = $concert->getPatchSheet()->getLines()->first()->getId();
        $this->client->request('GET', '/concerts/'.$concertId);
        self::assertResponseIsSuccessful();
        $this->entityManager()
            ->getConnection()
            ->executeStatement('DELETE FROM band_membership WHERE user_id = ?', [$this->userId]);

        $this->client->request('GET', '/concerts/'.$concertId);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/sources/'.$lineId.'/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.concert-card');
        self::assertSelectorExists('.logout-form');
        $this->client->request('GET', '/');
        self::assertResponseStatusCodeSame(403);
    }

    private function concert(): Concert
    {
        return static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $this->sheet(),
                new \DateTimeImmutable('2026-11-14'),
                'Salle de test',
                'Marseille',
            );
    }
}
