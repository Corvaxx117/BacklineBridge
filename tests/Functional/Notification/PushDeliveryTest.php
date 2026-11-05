<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Entity\BandMembership;
use App\Entity\Concert;
use App\Entity\NotificationRecipient;
use App\Entity\PushDelivery;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Integration\WebPush\PushGateway;
use App\Integration\WebPush\PushSendResult;
use App\Service\Notification\Push\PushSender;
use App\Tests\NotificationPushTestCase;

final class PushDeliveryTest extends NotificationPushTestCase
{
    public function testNewActionsQueueOncePerOptedInDeviceAndSkipTheAuthor(): void
    {
        $this->activate();
        $author = $this->member('author');
        $concert = $this->createdConcert($author);
        $em = $this->entityManager();
        self::assertSame(1, $em->getRepository(PushDelivery::class)->count([]));
        self::assertSame(2, $em->getRepository(NotificationRecipient::class)->count([]));
        $gateway = $this->createMock(PushGateway::class);
        $gateway->expects(self::once())->method('send')->willReturnCallback(
            function ($device, $payload, $topic, $ttl): PushSendResult {
                self::assertSame($this->userId, $device->getUser()->getId());
                $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
                self::assertStringContainsString('author', $data['body']);
                self::assertStringContainsString('Salle fictive', $data['body']);
                self::assertSame('/notifications', $data['url']);
                self::assertStringStartsWith('bb-', $topic);
                self::assertSame(86400, $ttl);

                return new PushSendResult(201);
            },
        );
        static::getContainer()->set(PushGateway::class, $gateway);
        $sender = static::getContainer()->get(PushSender::class);
        self::assertSame(1, $sender->run()['sent']);
        self::assertSame(0, $sender->run()['sent']);
        $concert = $em->find(Concert::class, $concert->getId());
        self::assertInstanceOf(Concert::class, $concert);
        $em->remove($concert);
        $em->flush();
        self::assertSame(1, $em->getRepository(NotificationRecipient::class)->count(['user' => $this->userId]));
    }

    public function testTemporaryFailuresAreRetriedWithABoundAndStableTopic(): void
    {
        $this->activate();
        $this->pushRequest('POST', '/notifications/appareil/test');
        $topics = [];
        $gateway = $this->createMock(PushGateway::class);
        $gateway->expects(self::exactly(5))->method('send')->willReturnCallback(
            static function ($device, $payload, $topic) use (&$topics): PushSendResult {
                $topics[] = $topic;

                return new PushSendResult(503);
            },
        );
        static::getContainer()->set(PushGateway::class, $gateway);
        $sender = static::getContainer()->get(PushSender::class);
        // Une action ordinaire bénéficie de 24 h de validité, le test personnel de cinq minutes.
        $this->entityManager()->remove($this->entityManager()->getRepository(PushDelivery::class)->findOneBy([]));
        $this->entityManager()->flush();
        $this->createdConcert($this->member('retry-author'));
        for ($i = 0; $i < 5; ++$i) {
            $counts = $sender->run();
            self::assertSame($i < 4 ? 1 : 0, $counts['deferred']);
            self::assertSame(0, $sender->run()['deferred']);
            $this->clock->sleep(960);
        }
        self::assertCount(1, array_unique($topics));
        self::assertSame('failed', $this->entityManager()->getRepository(PushDelivery::class)->findOneBy([])->getState());
    }

    public function testExpiredDeviceIsRemovedWithAllItsPendingMessages(): void
    {
        $this->activate();
        $author = $this->member('expired-author');
        $this->createdConcert($author);
        $this->createdConcert($author);
        $gateway = $this->createMock(PushGateway::class);
        $gateway->expects(self::once())->method('send')->willReturn(new PushSendResult(410));
        static::getContainer()->set(PushGateway::class, $gateway);
        static::getContainer()->get(PushSender::class)->run();
        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
        self::assertSame(0, $this->entityManager()->getRepository(PushDelivery::class)->count([]));
    }

    public function testReadRemovedTargetsAndRevokedMembersDoNotReceivePendingPushes(): void
    {
        $this->activate();
        $author = $this->member('revoked-author');
        $read = $this->createdConcert($author);
        $removed = $this->createdConcert($author);
        $this->createdConcert($author);
        $em = $this->entityManager();
        foreach ($em->getRepository(NotificationRecipient::class)->findBy(['user' => $this->userId]) as $receipt) {
            if ($receipt->getNotification()->getConcert()?->getId() === $read->getId()) {
                $receipt->markRead($this->clock->now());
            }
        }
        $em->remove($removed);
        $membership = $em->getRepository(BandMembership::class)->findOneBy(['user' => $this->userId]);
        $em->remove($membership);
        $em->flush();
        $em->clear();
        $gateway = $this->createMock(PushGateway::class);
        $gateway->expects(self::never())->method('send');
        static::getContainer()->set(PushGateway::class, $gateway);
        self::assertSame(3, static::getContainer()->get(PushSender::class)->run()['cancelled']);
    }

    public function testTransactionRollbackDoesNotLeavePushesToSend(): void
    {
        $this->activate();
        $author = $this->member('rollback-author');
        $connection = $this->entityManager()->getConnection();
        $connection->beginTransaction();
        $this->createdConcert($author);
        self::assertSame(1, $this->entityManager()->getRepository(PushDelivery::class)->count([]));
        $connection->rollBack();
        $this->entityManager()->clear();
        self::assertSame(0, $this->entityManager()->getRepository(PushDelivery::class)->count([]));
    }

    public function testExpiredDeviceDoesNotPreventSendingToAnotherDevice(): void
    {
        $this->activate();
        $em = $this->entityManager();
        $first = $em->getRepository(PushSubscription::class)->findOneBy([]);
        $owner = $em->find(User::class, $this->userId);
        self::assertInstanceOf(PushSubscription::class, $first);
        self::assertInstanceOf(User::class, $owner);
        $second = new PushSubscription(
            $owner, str_repeat('b', 64), $first->getEndpoint().'-second',
            $first->getPublicKey(), $first->getAuthToken(), $first->getVapidPublicKey(), $this->clock->now(),
        );
        $em->persist($second);
        $em->flush();
        $this->createdConcert($this->member('expiry-author'));
        $gateway = $this->createMock(PushGateway::class);
        $gateway->expects(self::exactly(2))->method('send')->willReturnOnConsecutiveCalls(
            new PushSendResult(410), new PushSendResult(201),
        );
        static::getContainer()->set(PushGateway::class, $gateway);
        $counts = static::getContainer()->get(PushSender::class)->run();
        self::assertSame(1, $counts['sent']);
        self::assertSame(1, $counts['cancelled']);
        self::assertSame(1, $em->getRepository(PushSubscription::class)->count([]));
    }
}
