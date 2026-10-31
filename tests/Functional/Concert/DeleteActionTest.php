<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\BandDocument;
use App\Entity\BandsintownConcert;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Entity\ConcertContact;
use App\Entity\TechnicalSheet;
use App\Entity\VenueReply;
use App\Enum\BandRole;
use App\Enum\ContactRole;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Tests\CollaborationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class DeleteActionTest extends CollaborationTestCase
{
    public function testDeletionRemovesTheDossierAndAccessesButKeepsTheReferenceAndDocuments(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $otherId = $this->concert()->getId();
        $issued = $this->issue($concert);
        $em = $this->entityManager();
        $em->persist(new VenueReply($issued->invitation));
        $em->persist(new ConcertConditions($concert));
        $contact = new ConcertContact($concert);
        $contact->update('Camille', ContactRole::Organizer, null, null, null);
        $em->persist($contact);
        $em->persist(new BandsintownConcert($concert, new BandsintownEvent(
            '123', '456', '2026-12-12', '20:00', 'Salle de test', 'Lyon', 'France', '', '', '', [], [],
        )));
        $em->flush();
        $this->uploadPdf($id);
        self::assertResponseStatusCodeSame(303);
        $documentId = $this->document()->getId();
        $key = $this->document()->getStorageKey();
        $uri = '/concerts/'.$id.'/supprimer';
        $form = $this->client->request('GET', $uri)->filter('form[action="'.$uri.'"]')->form();
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->entityManager()->find(Concert::class, $id));
        $this->client->submit($form);
        self::assertResponseRedirects('/concerts', 303);
        $em = $this->entityManager();
        $em->clear();
        self::assertNull($em->find(Concert::class, $id));
        self::assertNull($em->find(TechnicalSheet::class, $sheetId));
        self::assertNotNull($em->find(TechnicalSheet::class, $this->sheetId));
        self::assertNotNull($em->find(Concert::class, $otherId));
        $connection = $em->getConnection();
        foreach (['concert_contact', 'venue_reply', 'venue_invitation', 'technical_publication', 'concert_conditions', 'bandsintown_concert'] as $table) {
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        $document = $em->find(BandDocument::class, $documentId);
        self::assertNull($document->getConcert());
        self::assertFileExists($this->documentsDirectory.'/'.$this->bandId.'/'.$key);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/invitation/'.$issued->token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletionRequiresCsrfAndTheCurrentRevision(): void
    {
        $id = $this->concert()->getId();
        $uri = '/concerts/'.$id.'/supprimer';
        $form = $this->client->request('GET', $uri)->filter('form')->last()->form();
        $this->client->request('POST', $uri, ['revision' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->entityManager()->getConnection()->executeStatement('UPDATE concert SET revision = revision + 1 WHERE id = ?', [$id]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(409);
        self::assertNotNull($this->entityManager()->find(Concert::class, $id));
    }

    #[DataProvider('nonManagers')]
    public function testOnlyResponsibleMembersCanDeleteConcerts(BandRole $role): void
    {
        $id = $this->concert()->getId();
        $this->loginRole($role);
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/concerts/'.$id.'/supprimer');
            self::assertResponseStatusCodeSame(403);
        }
    }

    public static function nonManagers(): iterable
    {
        yield [BandRole::Booker];
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
    }
}
