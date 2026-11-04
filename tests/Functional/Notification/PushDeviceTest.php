<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Entity\PushDelivery;
use App\Entity\PushSubscription;
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
        $this->client->loginUser($this->entityManager()->find(\App\Entity\User::class, $this->userId));
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
