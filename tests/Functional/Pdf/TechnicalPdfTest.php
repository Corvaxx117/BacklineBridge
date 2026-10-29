<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pdf;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\{Band, TechnicalSheet, VenueInvitation};
use App\Enum\BandRole;
use App\Service\Concert\ConcertConditionsUpdater;
use App\Service\Pdf\TechnicalDossierFactory;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TechnicalPdfTest extends VenueTestCase
{
    public function testReferencePdfContainsOnlyActiveSourcesInChannelOrder(): void
    {
        $line = $this->lineForChannel(2);
        $line->archive();
        $this->entityManager()->flush();
        $dossier = static::getContainer()
            ->get(TechnicalDossierFactory::class)
            ->forSheet($this->sheet());
        self::assertCount(21, $dossier->lines);
        self::assertSame([1, ...range(3, 22)], array_column($dossier->lines, 'channel'));
        self::assertCount(14, $dossier->stagePlan);
        self::assertCount(19, $dossier->equipment);
        $this->client->request('GET', '/fiches/' . $this->sheetId . '/fiche-technique.pdf');
        $this->assertPdf('fiche-technique-avee-mana-reference.pdf');
    }

    public function testConcertPdfExcludesPrivateBookingData(): void
    {
        $concert = $this->concert();
        $input = new ConcertConditionsInput();
        $input->feeAmount = '9876.54';
        $input->notes = 'DONNEES PRIVEES HEBERGEMENT ET FINANCES';
        static::getContainer()->get(ConcertConditionsUpdater::class)->save($concert, $input);
        $dossier = static::getContainer()
            ->get(TechnicalDossierFactory::class)
            ->forSheet($concert->getPatchSheet());
        self::assertSame('Accueil à 16 h.', $dossier->notes);
        self::assertStringNotContainsString(
            $input->notes,
            json_encode($dossier, JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('9876', json_encode($dossier, JSON_THROW_ON_ERROR));
        $this->client->request(
            'GET',
            '/fiches/' . $concert->getPatchSheet()->getId() . '/fiche-technique.pdf',
        );
        $this->assertPdf('fiche-technique-avee-mana-2026-12-12-salle-de-test.pdf');
    }

    #[DataProvider('readableRoles')]
    public function testAllMembersWithReadAccessCanExportTheirDossier(BandRole $role): void
    {
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                $role->value . '@pdf.example.test',
                'Lecteur du dossier',
                'Export PDF test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                $role,
            );
        $this->client->loginUser($user);
        $this->client->request('GET', '/fiches/' . $this->sheetId . '/fiche-technique.pdf');
        $this->assertPdf('fiche-technique-avee-mana-reference.pdf');
    }

    public static function readableRoles(): iterable
    {
        yield [BandRole::Booker];
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
    }

    public function testOtherBandsAndAnonymousVisitorsCannotExportTheSheet(): void
    {
        $other = new TechnicalSheet()->setBandName('Autre groupe');
        $this->entityManager()->persist(new Band('Autre groupe', $other));
        $this->entityManager()->flush();
        $this->client->request('GET', '/fiches/' . $other->getId() . '/fiche-technique.pdf');
        self::assertResponseStatusCodeSame(403);
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/fiches/' . $this->sheetId . '/fiche-technique.pdf');
        self::assertResponseRedirects();
        self::assertSame(
            '/connexion',
            parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH),
        );
    }

    public function testGuestPdfUsesItsFrozenPublicationAndChecksRevocation(): void
    {
        $issued = $this->issue();
        $invitationId = $issued->invitation->getId();
        $publication = $issued->invitation->getPublication();
        $snapshot = $publication->getSnapshot();
        $concert = $publication->getConcert();
        $concert->updateDetails(
            $concert->getDate(),
            'Nouvelle salle confidentielle',
            'Paris',
            $concert->getStatus(),
            'NOUVELLE NOTE NON PUBLIEE',
        );
        $concert->getPatchSheet()->setStagePlan([])->setEquipmentRequests([]);
        $this->entityManager()->flush();
        $dossier = static::getContainer()
            ->get(TechnicalDossierFactory::class)
            ->forPublication($publication);
        self::assertSame('Salle de test', $dossier->venue);
        self::assertSame($snapshot['stagePlan'], $dossier->stagePlan);
        self::assertSame($snapshot['equipment'], $dossier->equipment);
        self::assertSame($snapshot['notes'], $dossier->notes);
        $this->enter($issued->token);
        self::assertSelectorExists(
            'a[href="' . $this->guestUrl('/salle/fiche-technique.pdf') . '"]',
        );
        $this->client->request('GET', $this->guestUrl('/salle/fiche-technique.pdf'));
        $this->assertPdf('fiche-technique-avee-mana-2026-12-12-salle-de-test.pdf');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $this->entityManager()->find(VenueInvitation::class, $invitationId)->revoke();
        $this->entityManager()->flush();
        $this->client->request('GET', $this->guestUrl('/salle/fiche-technique.pdf'));
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Ce lien est indisponible');
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $this->guestUrl('/salle/fiche-technique.pdf'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnEmptyDossierAndVeryLongNotesStillProduceAValidPdf(): void
    {
        $this->sheet()->setStagePlan([])->setEquipmentRequests([]);
        $this->lineForChannel(1)->setNotes(
            str_repeat('Précisions techniques à conserver intégralement. ', 40),
        );
        $this->entityManager()->flush();
        $this->client->request('GET', '/fiches/' . $this->sheetId . '/fiche-technique.pdf');
        $this->assertPdf('fiche-technique-avee-mana-reference.pdf');
    }

    private function assertPdf(string $filename): void
    {
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=' . $filename);
        self::assertStringContainsString(
            'no-store',
            $this->client->getResponse()->headers->get('Cache-Control'),
        );
        $content = $this->client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-', $content);
        self::assertGreaterThan(10000, strlen($content));
        self::assertStringContainsString('%%EOF', $content);
    }
}
