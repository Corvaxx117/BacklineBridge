<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Reference;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\ConcertAvailability;
use App\Entity\ConcertConditions;
use App\Entity\PatchLine;
use App\Entity\TechnicalPublication;
use App\Entity\TechnicalSheet;
use App\Enum\AvailabilityStatus;
use App\Enum\BandRole;
use App\Service\Concert\ConcertAvailabilityManager;
use App\Service\Concert\ConcertConditionsUpdater;
use App\Service\Security\UserCreator;
use App\Service\Venue\PublicationBuilder;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReferenceImportTest extends VenueTestCase
{
    public function testAnIdenticalDossierNeedsNoImport(): void
    {
        $id = $this->concert()->getId();
        $this->client->request('GET', '/concerts/'.$id.'/reference');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.reference-import-notice', 'déjà identique');
        self::assertSelectorNotExists('form[name="reference_import"]');
    }

    public function testStageOnlyImportPreservesTheOtherSectionsConcertDetailsAndPublications(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $other = $this->concert();
        $otherId = $other->getId();
        $conditionsInput = new ConcertConditionsInput();
        $conditionsInput->feeAmount = '1500';
        static::getContainer()
            ->get(ConcertConditionsUpdater::class)
            ->save($concert, $conditionsInput);
        $availability = static::getContainer()->get(ConcertAvailabilityManager::class);
        $input = $availability->inputFor(
            $concert,
            $this->entityManager()->find(\App\Entity\User::class, $this->userId),
        );
        $input->status = AvailabilityStatus::Available;
        $input->comment = 'PRECISION PRIVEE DE DISPONIBILITE';
        $availability->respond(
            $concert,
            $this->entityManager()->find(\App\Entity\User::class, $this->userId),
            $input,
        );
        $issued = $this->issue($concert);
        $publicationId = $issued->invitation->getPublication()->getId();
        $snapshot = $issued->invitation->getPublication()->getSnapshot();
        self::assertStringNotContainsString(
            $input->comment,
            json_encode($snapshot, JSON_THROW_ON_ERROR),
        );
        $oldPatch = $concert->getPatchSheet()->getLines()->first()->getSourceName();
        $oldEquipment = $concert->getPatchSheet()->getEquipmentRequests();
        $reference = $this->sheet();
        $plan = $reference->getStagePlan();
        self::assertNotEmpty($plan);
        $plan[0]['rotation'] = 30;
        $plan[0]['scale'] = 1.3;
        $reference->setStagePlan($plan)->setNotices(['Nouvelle note de référence']);
        $this->lineForChannel(1)->setSourceName('NOUVEAU KICK');
        $reference->setEquipmentRequests([]);
        $this->entityManager()->flush();
        $this->import($id, ['stage']);
        self::assertResponseRedirects('/concerts/'.$id, 303);
        $em = $this->entityManager();
        $em->clear();
        $concert = $em->find(Concert::class, $id);
        self::assertSame($plan, $concert->getPatchSheet()->getStagePlan());
        self::assertSame($sheetId, $concert->getPatchSheet()->getId());
        self::assertSame(
            $oldPatch,
            $concert->getPatchSheet()->getLines()->first()->getSourceName(),
        );
        self::assertSame($oldEquipment, $concert->getPatchSheet()->getEquipmentRequests());
        self::assertSame($this->fixture['notices'], $concert->getPatchSheet()->getNotices());
        self::assertSame('Accueil à 16 h.', $concert->getNotes());
        self::assertSame(
            150000,
            $em
                ->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $id])
                ->getFeeCents(),
        );
        self::assertSame(
            AvailabilityStatus::Available,
            $em
                ->getRepository(ConcertAvailability::class)
                ->findOneBy(['concert' => $id])
                ->getStatus(),
        );
        self::assertNotSame(
            $plan,
            $em->find(Concert::class, $otherId)->getPatchSheet()->getStagePlan(),
        );
        self::assertSame(
            $snapshot,
            $em->find(TechnicalPublication::class, $publicationId)->getSnapshot(),
        );
        self::assertTrue(
            static::getContainer()
                ->get(PublicationBuilder::class)
                ->isOutdated($em->find(TechnicalPublication::class, $publicationId)),
        );
        $this->enter($issued->token);
        self::assertSelectorNotExists('.availability-panel');
        self::assertSelectorTextNotContains('main', $input->comment);
        self::assertSelectorTextNotContains('main', 'NOUVEAU KICK');
        $this->client->request('GET', $this->guestUrl('/salle/fiche-technique.pdf'));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testPatchImportKeepsMatchingIdsArchivesLocalSourcesAndIgnoresArchivedReferenceLines(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $firstId = $concert->getPatchSheet()->getLines()->first()->getId();
        $local = new PatchLine()->setChannelNumber(30)->setSourceName('AJOUT LOCAL');
        $concert->getPatchSheet()->addLine($local);
        $this->lineForChannel(1)
            ->setSourceName('KICK VERSION SUIVANTE')
            ->setNotes('Nouvelle précision');
        $this->lineForChannel(2)->archive();
        $this->sheet()->addLine(
            new PatchLine()->setChannelNumber(23)->setSourceName('INVITÉ RÉFÉRENCE'),
        );
        $this->entityManager()->flush();
        $localId = $local->getId();
        $this->import($id, ['patch']);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            'KICK VERSION SUIVANTE',
            $em->find(PatchLine::class, $firstId)->getSourceName(),
        );
        self::assertSame('Nouvelle précision', $em->find(PatchLine::class, $firstId)->getNotes());
        self::assertFalse($em->find(PatchLine::class, $localId)->isActive());
        self::assertNotNull($em->find(PatchLine::class, $localId)->getDeletedAt());
        $active = $em
            ->getRepository(PatchLine::class)
            ->findBy(['sheet' => $sheetId, 'active' => true], ['channelNumber' => 'ASC']);
        self::assertCount(22, $active);
        self::assertSame(
            [1, ...range(3, 23)],
            array_map(static fn (PatchLine $line): int => $line->getChannelNumber(), $active),
        );
        $this->client->request('GET', '/concerts/'.$id.'/reference');
        self::assertSelectorTextContains('.reference-import-notice', 'déjà identique');
    }

    public function testNotesAndEmptyEquipmentCanBeImportedWithoutReplacingThePlan(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $oldPlan = $concert->getPatchSheet()->getStagePlan();
        $this->sheet()
            ->setDocumentLabel('2026.4')
            ->setNotices(['Nouvelle note'])
            ->setEquipmentRequests([])
            ->setStagePlan([]);
        $this->entityManager()->flush();
        $this->import($id, ['equipment', 'notes']);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $sheet = $em->find(Concert::class, $id)->getPatchSheet();
        self::assertSame('2026.4', $sheet->getDocumentLabel());
        self::assertSame(['Nouvelle note'], $sheet->getNotices());
        self::assertSame([], $sheet->getEquipmentRequests());
        self::assertSame($oldPlan, $sheet->getStagePlan());
    }

    #[DataProvider('invalidImports')]
    public function testUnconfirmedEmptyAndInvalidSelectionsCannotApplyChanges(
        array $changes,
        bool $csrf,
    ): void {
        $id = $this->concert()->getId();
        $this->sheet()->setStagePlan([]);
        $this->entityManager()->flush();
        $crawler = $this->client->request('GET', '/concerts/'.$id.'/reference');
        $values = $crawler->filter('form[name="reference_import"]')->form()->getPhpValues();
        $values['reference_import'] = array_replace(
            $values['reference_import'],
            ['sections' => ['stage'], 'confirmReplacement' => '1'],
            $changes,
        );
        if (!$csrf) {
            unset($values['reference_import']['_token']);
        }
        $this->client->request('POST', '/concerts/'.$id.'/reference', $values);
        self::assertResponseStatusCodeSame(422);
        $em = $this->entityManager();
        $em->clear();
        self::assertNotEmpty($em->find(Concert::class, $id)->getPatchSheet()->getStagePlan());
    }

    public static function invalidImports(): iterable
    {
        yield 'no confirmation' => [['confirmReplacement' => '0'], true];
        yield 'no section' => [['sections' => []], true];
        yield 'invented section' => [['sections' => ['invented']], true];
        yield 'unchanged section' => [['sections' => ['patch']], true];
        yield 'no CSRF' => [[], false];
    }

    #[DataProvider('changedSides')]
    public function testAStalePreviewDoesNotOverwriteAnySection(bool $changeReference): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->sheet()->setStagePlan([]);
        $this->entityManager()->flush();
        $crawler = $this->client->request('GET', '/concerts/'.$id.'/reference');
        $values = $crawler->filter('form[name="reference_import"]')->form()->getPhpValues();
        $values['reference_import']['sections'] = ['stage'];
        $values['reference_import']['confirmReplacement'] = '1';
        $sheet = $changeReference
            ? $this->sheet()
            : $this->entityManager()->find(TechnicalSheet::class, $sheetId);
        $sheet->setNotices(['Changement depuis l’aperçu']);
        $this->entityManager()->flush();
        $this->client->request('POST', '/concerts/'.$id.'/reference', $values);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains(
            'form[name="reference_import"]',
            'a changé depuis l’aperçu',
        );
        $em = $this->entityManager();
        $em->clear();
        self::assertNotEmpty($em->find(TechnicalSheet::class, $sheetId)->getStagePlan());
    }

    public static function changedSides(): iterable
    {
        yield 'reference changed' => [true];
        yield 'concert changed' => [false];
    }

    #[DataProvider('forbiddenRoles')]
    public function testOnlyManagersCanImportTheReference(BandRole $role): void
    {
        $id = $this->concert()->getId();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                $role->value.'@reference.test',
                'Autre rôle',
                'Test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                $role,
            );
        $this->client->loginUser($user);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorNotExists('a[href="/concerts/'.$id.'/reference"]');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/concerts/'.$id.'/reference');
            self::assertResponseStatusCodeSame(403);
        }
    }

    public static function forbiddenRoles(): iterable
    {
        yield [BandRole::Booker];
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
    }

    public function testAnotherBandCannotImportOrReadTheReferenceComparison(): void
    {
        $id = $this->concert()->getId();
        $other = new Band('Autre groupe', new TechnicalSheet()->setBandName('Autre groupe'));
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'outsider@reference.test',
                'Autre groupe',
                'Test password 2026!',
                $other,
                BandRole::Manager,
            );
        $this->client->loginUser($user);
        $this->client->request('GET', '/concerts/'.$id.'/reference');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/concerts/'.$id.'/reference');
        self::assertResponseStatusCodeSame(403);
    }

    private function import(int $id, array $sections): void
    {
        $crawler = $this->client->request('GET', '/concerts/'.$id.'/reference');
        self::assertResponseIsSuccessful();
        $values = $crawler->filter('form[name="reference_import"]')->form()->getPhpValues();
        $values['reference_import']['sections'] = $sections;
        $values['reference_import']['confirmReplacement'] = '1';
        $this->client->request('POST', '/concerts/'.$id.'/reference', $values);
    }
}
