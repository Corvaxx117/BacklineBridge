<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\User;
use App\Enum\BandRole;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

abstract class NotificationPushTestCase extends CollaborationTestCase
{
    protected MockClock $clock;
    protected string $keyFile;
    /** @var array<string, string> */
    protected array $keys;
    /** @var array<string, mixed> */
    protected array $pushInput;
    /** @var array<string, mixed> */
    private array $previousEnvironment = [];

    protected function setUp(): void
    {
        $this->keyFile = sys_get_temp_dir().'/bb-push-test-'.bin2hex(random_bytes(8)).'.json';
        $this->keys = ['subject' => 'https://bb.example.test', ...VAPID::createVapidKeys()];
        file_put_contents($this->keyFile, json_encode($this->keys, JSON_THROW_ON_ERROR));
        foreach (['WEB_PUSH_ENABLED' => '1', 'WEB_PUSH_KEY_FILE' => $this->keyFile, 'APP_DEMO_MODE' => '0'] as $key => $value) {
            $this->previousEnvironment[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        parent::setUp();
        $this->client->disableReboot();
        static::getContainer()->get('limiter.push_device_updates')->create((string) $this->userId)->reset();
        $this->clock = new MockClock('2026-11-04 12:00:00 UTC');
        static::getContainer()->set(ClockInterface::class, $this->clock);
        $this->pushInput = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.bin2hex(random_bytes(12)),
            'keys' => [
                'p256dh' => VAPID::createVapidKeys()['publicKey'],
                'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
            ],
        ];
    }

    protected function tearDown(): void
    {
        if (is_file($this->keyFile)) {
            unlink($this->keyFile);
        }
        foreach ($this->previousEnvironment as $key => [$env, $server]) {
            if (null === $env) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
        }
        parent::tearDown();
    }

    protected function csrf(): string
    {
        $this->client->request('GET', '/notifications/appareil');
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $data['token'];
    }

    protected function activate(): void
    {
        $this->pushRequest('POST', '/notifications/appareil', $this->pushInput);
        self::assertResponseIsSuccessful();
    }

    /** @param array<string, mixed> $data */
    protected function pushRequest(string $method, string $url, array $data = []): void
    {
        $token = $this->csrf();
        $this->client->request($method, $url, server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode($data, JSON_THROW_ON_ERROR));
    }

    protected function member(string $name): User
    {
        return static::getContainer()->get(UserCreator::class)->create(
            $name.'@example.test', $name, 'CompteTest2026!',
            $this->entityManager()->find(Band::class, $this->bandId), BandRole::Booker,
        );
    }

    protected function createdConcert(User $actor): Concert
    {
        return static::getContainer()->get(ConcertCreator::class)->create(
            $this->sheet(), new \DateTimeImmutable('2027-01-10'), 'Salle fictive', 'Ville fictive', createdBy: $actor,
        );
    }
}
