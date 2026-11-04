<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\BandMembership;
use App\Entity\BandNotification;
use App\Entity\Concert;
use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\DocumentCategory;
use App\Enum\NotificationType;
use App\Service\Concert\ConcertCreator;
use App\Service\Notification\NotificationInbox;
use App\Service\Notification\NotificationRecorder;
use App\Service\Security\UserCreator;
use App\Tests\CollaborationTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotificationWorkspaceTest extends CollaborationTestCase
{
    public function testConcertCreationRecordsActorTimeAndPersonalReadState(): void
    {
        $reader = $this->member('reader', BandRole::Viewer);
        static::getContainer()->set(ClockInterface::class, new MockClock('2026-11-04 12:30:00 UTC'));
        $concert = $this->createdConcert();
        $concertId = $concert->getId();
        $em = $this->entityManager();
        $notice = $em->getRepository(BandNotification::class)->findOneBy([]);
        self::assertInstanceOf(BandNotification::class, $notice);
        self::assertSame(NotificationType::ConcertCreated, $notice->getType());
        self::assertSame('Responsable de test', $notice->getActorName());
        self::assertSame('2026-11-04T12:30:00+00:00', $notice->getCreatedAt()->format(DATE_ATOM));
        self::assertSame(2, $em->getRepository(NotificationRecipient::class)->count([]));
        $this->client->request('GET', '/notifications');
        self::assertSelectorCount(1, '.notification-card');
        self::assertSelectorNotExists('.notification-card--unread');
        $this->client->loginUser($reader);
        $this->client->request('GET', '/notifications');
        self::assertSelectorTextContains('.notification-action', 'Responsable de test a créé un concert.');
        self::assertSelectorTextContains('.notification-content time', '04/11/2026 à 13:30');
        self::assertSelectorCount(1, '.notification-card--unread');
        self::assertSelectorExists('[data-notification-bell][data-unread="1"]');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $form = $this->client->getCrawler()->filter('.notification-actions form')->first()->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/concerts/'.$concertId, 303);
        $this->client->request('GET', '/notifications/compteur');
        self::assertJsonStringEqualsJsonString('{"unread":0}', $this->client->getResponse()->getContent());
    }

    public function testReadAllAndUnreadFilterArePersonal(): void
    {
        $first = $this->member('first', BandRole::Booker);
        $second = $this->member('second', BandRole::TeamManager);
        $this->createdConcert();
        $this->createdConcert();
        $this->client->loginUser($first);
        $page = $this->client->request('GET', '/notifications?view=unread');
        self::assertSelectorCount(2, '.notification-card');
        $this->client->submit($page->filter('form[action="/notifications/tout-lire"]')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/notifications?view=unread');
        self::assertSelectorExists('.notification-empty');
        $this->client->request('GET', '/notifications');
        $form = $this->client->getCrawler()->filter('.notification-read')->first()->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/notifications?view=unread');
        self::assertSelectorCount(1, '.notification-card--unread');
        $this->client->loginUser($second);
        $this->client->request('GET', '/notifications?view=unread');
        self::assertSelectorCount(2, '.notification-card--unread');
    }

    public function testForgedStateMissingCsrfAndForeignReceiptsCannotBeChanged(): void
    {
        $other = $this->member('other', BandRole::Viewer);
        $this->createdConcert();
        $em = $this->entityManager();
        $foreign = $em->getRepository(NotificationRecipient::class)->findOneBy(['user' => $other]);
        self::assertInstanceOf(NotificationRecipient::class, $foreign);
        $id = $foreign->getId();
        try {
            static::getContainer()->get(NotificationInbox::class)->setRead(
                $em->find(User::class, $this->userId), $id, true,
            );
            self::fail('Another person’s notification must be rejected.');
        } catch (NotFoundHttpException) {
            self::assertNull($foreign->getReadAt());
        }
        $this->client->loginUser($other);
        $this->client->request('POST', '/notifications/'.$id.'/lecture', ['state' => 'read']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/notifications/tout-lire');
        self::assertResponseStatusCodeSame(403);
        $page = $this->client->request('GET', '/notifications');
        $form = $page->filter('.notification-read')->form();
        $values = $form->getPhpValues();
        $values['state'] = 'invalid';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/notifications/compteur');
        self::assertJsonStringEqualsJsonString('{"unread":1}', $this->client->getResponse()->getContent());
    }

    public function testRevokedMembershipImmediatelyHidesHistoryAndDeniesReading(): void
    {
        $reader = $this->member('removed', BandRole::Manager);
        $readerId = $reader->getId();
        $this->createdConcert();
        $em = $this->entityManager();
        $receipt = $em->getRepository(NotificationRecipient::class)->findOneBy(['user' => $reader]);
        $receiptId = $receipt->getId();
        $membership = $em->getRepository(BandMembership::class)->findOneBy(['user' => $reader]);
        $em->remove($membership);
        $em->flush();
        $this->client->loginUser($reader);
        $this->client->request('GET', '/notifications');
        self::assertSelectorNotExists('.notification-card');
        $this->client->request('GET', '/notifications/compteur');
        self::assertJsonStringEqualsJsonString('{"unread":0}', $this->client->getResponse()->getContent());
        $em = $this->entityManager();
        $this->expectException(NotFoundHttpException::class);
        static::getContainer()->get(NotificationInbox::class)->setRead(
            $em->find(User::class, $readerId), $receiptId, true,
        );
    }

    public function testPdfRoadmapProducesOneNotificationAndLinksToTheConcert(): void
    {
        $reader = $this->member('engineer', BandRole::Manager);
        $id = $this->concert()->getId();
        $uri = '/groupes/'.$this->bandId.'/documents/ajouter/pdf?concert='.$id.'&usage=roadmap';
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $fileField = $form['document[file]'];
        self::assertInstanceOf(FileFormField::class, $fileField);
        $fileField->upload($this->pdfFile());
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->entityManager()->getRepository(BandNotification::class)->count([]));
        $notice = $this->entityManager()->getRepository(BandNotification::class)->findOneBy([]);
        self::assertSame(NotificationType::RoadmapAdded, $notice->getType());
        $this->client->loginUser($reader);
        $page = $this->client->request('GET', '/notifications');
        self::assertSelectorTextContains('.notification-action', 'a ajouté une feuille de route.');
        $this->client->submit($page->filter('.notification-open')->form());
        self::assertResponseRedirects('/concerts/'.$id.'#concert-documents', 303);
    }

    public function testDeletingADocumentKeepsEscapedHistoryWithoutABrokenLink(): void
    {
        $reader = $this->member('document-reader', BandRole::Viewer);
        $readerId = $reader->getId();
        $title = '<script>alert("document")</script>';
        $doc = $this->recordDocument($title);
        $id = $doc->getId();
        $em = $this->entityManager();
        $em->getConnection()->executeStatement('DELETE FROM band_document WHERE id = ?', [$id]);
        $em->clear();
        $this->client->loginUser($em->find(User::class, $readerId));
        $this->client->request('GET', '/notifications');
        self::assertSelectorTextContains('.notification-subject', $title);
        self::assertSelectorNotExists('.notification-subject script');
        self::assertSelectorTextContains('.notification-removed', 'Élément retiré');
        self::assertSelectorNotExists('.notification-open');
        self::assertSelectorTextContains('.notification-action', 'Responsable de test');
    }

    public function testPaginationKeepsStableOrderingWithoutDuplicatingItems(): void
    {
        $reader = $this->member('many', BandRole::Viewer);
        for ($index = 1; $index <= 23; ++$index) {
            $this->recordDocument('Document '.$index);
        }
        $this->client->loginUser($reader);
        $first = $this->client->request('GET', '/notifications');
        self::assertSelectorCount(20, '.notification-card');
        $firstIds = $first->filter('[data-notification-id]')->extract(['data-notification-id']);
        $second = $this->client->request('GET', '/notifications?page=2');
        self::assertSelectorCount(3, '.notification-card');
        self::assertSame([], array_intersect($firstIds, $second->filter('[data-notification-id]')->extract(['data-notification-id'])));
        $this->client->request('GET', '/notifications?page=-1');
        self::assertSelectorCount(20, '.notification-card');
    }

    public function testNotificationFailureRollsBackTheConcertAndItsPatch(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement("CREATE TRIGGER fail_notification BEFORE INSERT ON band_notification BEGIN SELECT RAISE(ABORT, 'Notification failure'); END");
        try {
            $this->createdConcert();
            self::fail('A database failure was expected.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM concert'));
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM technical_sheet'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM band_notification'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM notification_recipient'));
        } finally {
            $connection->executeStatement('DROP TRIGGER fail_notification');
        }
    }

    public function testFailedDocumentNotificationRemovesTheStoredPdfAndAllDatabaseRows(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement("CREATE TRIGGER fail_document_notice BEFORE INSERT ON band_notification BEGIN SELECT RAISE(ABORT, 'Notification failure'); END");
        $input = new \App\Dto\Document\DocumentInput();
        $input->title = 'PDF qui ne doit pas rester';
        $input->category = DocumentCategory::Technical;
        $input->file = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $this->pdfFile(), 'test.pdf', 'application/pdf', UPLOAD_ERR_OK, true,
        );
        try {
            static::getContainer()->get(\App\Service\Document\DocumentManager::class)->create(
                $this->entityManager()->find(Band::class, $this->bandId),
                $this->entityManager()->find(User::class, $this->userId),
                $input,
                \App\Enum\DocumentSource::Pdf,
            );
            self::fail('A database failure was expected.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM band_document'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM band_notification'));
            self::assertSame([], glob($this->documentsDirectory.'/'.$this->bandId.'/*.pdf'));
        } finally {
            $connection->executeStatement('DROP TRIGGER fail_document_notice');
        }
    }

    public function testAnonymousVisitorsCannotReadNotificationsOrTheirCounter(): void
    {
        $this->client->getCookieJar()->clear();
        foreach (['/notifications', '/notifications/compteur'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseRedirects('/connexion');
        }
    }

    private function member(string $name, BandRole $role): User
    {
        return static::getContainer()->get(UserCreator::class)->create(
            $name.'@example.test', $name, 'NotificationTest2026!',
            $this->entityManager()->find(Band::class, $this->bandId), $role,
        );
    }

    private function createdConcert(): Concert
    {
        return static::getContainer()->get(ConcertCreator::class)->create(
            $this->sheet(), new \DateTimeImmutable('2026-12-12'), 'Salle de test', 'Lyon',
            createdBy: $this->entityManager()->find(User::class, $this->userId),
        );
    }

    private function recordDocument(string $title): BandDocument
    {
        $em = $this->entityManager();
        $document = new BandDocument(
            $em->find(Band::class, $this->bandId), null, $em->find(User::class, $this->userId),
            $title, DocumentCategory::Other, null, 'https://example.test/document', null, null, 0,
        );
        $em->persist($document);
        static::getContainer()->get(NotificationRecorder::class)->documentAdded($document);
        $em->flush();

        return $document;
    }
}
