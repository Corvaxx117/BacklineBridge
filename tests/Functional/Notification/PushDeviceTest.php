<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Entity\PushDelivery;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Service\Notification\Push\PushConfiguration;
use App\Service\Notification\Push\PushDeviceManager;
use App\Tests\NotificationPushTestCase;

final class PushDeviceTest extends NotificationPushTestCase
{
    public function testActivationIsExplicitPersonalAndIdempotent(): void
    {
        $this->client->request('GET', '/notifications/appareil');
        self::assertResponseIsSuccessful();
        $contents = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString($this->keys['privateKey'], $contents);
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
        $this->activate();
        $cookie = $this->client->getCookieJar()->get(PushDeviceManager::COOKIE);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(1, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
        $this->activate();
        self::assertSame(1, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
        $this->pushRequest('POST', '/notifications/appareil/test');
        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, $this->entityManager()->getRepository(PushDelivery::class)->count([]));
        $this->pushRequest('POST', '/notifications/appareil/test');
        self::assertResponseStatusCodeSame(429);
        $this->activate();
        $this->pushRequest('POST', '/notifications/appareil/test');
        self::assertResponseStatusCodeSame(429);
        $this->pushRequest('DELETE', '/notifications/appareil');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
        self::assertSame(0, $this->entityManager()->getRepository(PushDelivery::class)->count([]));
    }

    public function testCooldownIsRespectedEvenIfAnotherRequestChangesTheDeviceAfterItsRead(): void
    {
        $this->activate();
        $em = $this->entityManager();
        $device = $em->getRepository(PushSubscription::class)->findOneBy([]);
        self::assertInstanceOf(PushSubscription::class, $device);
        self::assertNull($device->getLastTestAt());
        // Une autre requête vient de réserver le test ; l’objet déjà chargé reste ancien.
        $em->getConnection()->update('push_subscription', [
            'last_test_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], ['id' => $device->getId()]);
        $this->pushRequest('POST', '/notifications/appareil/test');
        self::assertResponseStatusCodeSame(429);
        self::assertSame(0, $em->getRepository(PushDelivery::class)->count([]));
        $this->clock->sleep(60);
        $this->pushRequest('POST', '/notifications/appareil/test');
        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, $em->getRepository(PushDelivery::class)->count([]));
    }

    public function testPasswordChangeRevokesEveryDeviceOfThatAccountAndKeepsOtherMembersDevices(): void
    {
        static::getContainer()->get('limiter.account_password_change')
            ->create((string) $this->userId)->reset();
        $this->activate();
        $em = $this->entityManager();
        $owner = $em->find(User::class, $this->userId);
        self::assertInstanceOf(User::class, $owner);
        $device = $em->getRepository(PushSubscription::class)->findOneBy([]);
        self::assertInstanceOf(PushSubscription::class, $device);
        $second = new PushSubscription(
            $owner, str_repeat('c', 64), $device->getEndpoint().'-second',
            $device->getPublicKey(), $device->getAuthToken(), $device->getVapidPublicKey(), $this->clock->now(),
        );
        $other = new PushSubscription(
            $this->member('other-password'), str_repeat('d', 64), $device->getEndpoint().'-other',
            $device->getPublicKey(), $device->getAuthToken(), $device->getVapidPublicKey(), $this->clock->now(),
        );
        $em->persist($second);
        $em->persist($other);
        foreach ([$device, $second, $other] as $subscription) {
            $em->persist(new PushDelivery($subscription, $this->clock->now()));
        }
        $em->flush();
        $page = $this->client->request('GET', '/mon-compte/mot-de-passe');
        $this->client->submit($page->filter('form[name="change_password"]')->form(), [
            'change_password' => [
                'currentPassword' => 'BacklineBridge test password 2026!',
                'password' => ['first' => 'Securite!84', 'second' => 'Securite!84'],
            ],
        ]);
        self::assertResponseRedirects('/mon-compte/mot-de-passe', 303);
        self::assertSame(0, $em->getRepository(PushSubscription::class)->count(['user' => $this->userId]));
        self::assertSame(1, $em->getRepository(PushSubscription::class)->count([]));
        self::assertSame(1, $em->getRepository(PushDelivery::class)->count([]));
        $this->client->request('GET', '/notifications/appareil');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"enabled":false', $this->client->getResponse()->getContent());
    }

    public function testOtherAccountCannotReuseOrDisableAnExistingDevice(): void
    {
        $this->activate();
        $this->client->loginUser($this->member('another'));
        $this->client->request('GET', '/notifications/appareil');
        self::assertStringContainsString('"enabled":false', $this->client->getResponse()->getContent());
        $this->pushRequest('POST', '/notifications/appareil', $this->pushInput);
        self::assertResponseStatusCodeSame(409);
        $this->pushRequest('POST', '/notifications/appareil/test');
        self::assertResponseStatusCodeSame(409);
        $this->pushRequest('DELETE', '/notifications/appareil');
        self::assertSame(1, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
    }

    public function testLogoutRevokesOnlyTheCurrentDeviceAndCancelsItsQueue(): void
    {
        $this->activate();
        $this->pushRequest('POST', '/notifications/appareil/test');
        $page = $this->client->request('GET', '/notifications');
        $form = $page->filter('form[action="/deconnexion"]')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(302);
        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
        self::assertSame(0, $this->entityManager()->getRepository(PushDelivery::class)->count([]));
        $this->client->request('GET', '/notifications/appareil');
        self::assertResponseRedirects('/connexion');
    }

    public function testCsrfAndInvalidEndpointsCannotCreateSubscriptions(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/notifications/appareil', server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->pushInput, JSON_THROW_ON_ERROR));
        self::assertResponseRedirects('http://localhost/connexion');
        $this->client->loginUser($this->entityManager()->find(User::class, $this->userId));
        $this->pushInput['endpoint'] = 'http://127.0.0.1/admin';
        $this->pushRequest('POST', '/notifications/appareil', $this->pushInput);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
    }

    public function testDisabledConfigurationAndDemoNeverAllowActivation(): void
    {
        static::getContainer()->set(PushConfiguration::class, new PushConfiguration(true, $this->keyFile, '', true));
        $this->pushRequest('POST', '/notifications/appareil', $this->pushInput);
        self::assertResponseStatusCodeSame(503);
        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
    }
}
