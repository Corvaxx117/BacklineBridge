<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Dto\Notification\PushSubscriptionInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PushInputTest extends TestCase
{
    #[DataProvider('endpoints')]
    public function testOnlyBrowserPushServicesAreAllowed(string $endpoint, bool $valid): void
    {
        self::assertSame($valid, PushSubscriptionInput::allowedEndpoint($endpoint));
    }

    public static function endpoints(): iterable
    {
        yield ['https://fcm.googleapis.com/fcm/send/token', true];
        yield ['https://fcm.googleapis.com/wp/token', true];
        yield ['https://web.push.apple.com/Q/token', true];
        yield ['https://updates.push.services.mozilla.com/wpush/v2/token', true];
        yield ['http://fcm.googleapis.com/fcm/send/token', false];
        yield ['https://fcm.googleapis.com.evil.test/token', false];
        yield ['https://fcm.googleapis.com@127.0.0.1/private', false];
        yield ['https://127.0.0.1/push', false];
        yield ['https://[::1]/push', false];
        yield ['https://web.push.apple.com:8443/token', false];
        yield ['https://fcm.googleapis.com/token#fragment', false];
        yield ['https://user@fcm.googleapis.com/token', false];
        yield ['https://example.test/token', false];
    }

    public function testMalformedKeysAreRejectedBeforeAnyNetworkAccess(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PushSubscriptionInput::fromArray([
            'endpoint' => 'https://web.push.apple.com/Q/token',
            'keys' => ['p256dh' => 'not-a-key', 'auth' => 'not-a-key'],
        ]);
    }
}
