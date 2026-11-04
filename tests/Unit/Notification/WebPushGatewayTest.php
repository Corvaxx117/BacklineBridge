<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Integration\WebPush\WebPushGateway;
use App\Service\Notification\Push\PushConfiguration;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\VAPID;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class WebPushGatewayTest extends TestCase
{
    public function testActualLibraryEncryptsPayloadAndClassifiesRelayResponses(): void
    {
        $file = sys_get_temp_dir().'/bb-push-gateway-'.bin2hex(random_bytes(8)).'.json';
        $keys = ['subject' => 'https://bb.example.test', ...VAPID::createVapidKeys()];
        file_put_contents($file, json_encode($keys, JSON_THROW_ON_ERROR));
        try {
            $device = new PushSubscription(
                new User('test@example.test', 'Membre fictif'), str_repeat('a', 64),
                'https://web.push.apple.com/Q/test',
                VAPID::createVapidKeys()['publicKey'], rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
                $keys['publicKey'], new \DateTimeImmutable());
            $http = $this->createMock(ClientInterface::class);
            $calls = 0;
            $http->expects(self::exactly(3))->method('sendRequest')->willReturnCallback(
                static function (RequestInterface $request) use (&$calls): Response {
                    self::assertSame('POST', $request->getMethod());
                    self::assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
                    self::assertStringContainsString('vapid', $request->getHeaderLine('Authorization'));
                    self::assertSame('300', $request->getHeaderLine('TTL'));
                    self::assertStringNotContainsString('Secret payload', (string) $request->getBody());
                    ++$calls;
                    if (3 === $calls) {
                        throw new ConnectException('Private endpoint must not appear in logs', $request);
                    }

                    return 1 === $calls ? new Response(201) : new Response(429, ['Retry-After' => '180']);
                },
            );
            $gateway = new WebPushGateway(new PushConfiguration(true, $file, '', false), $http);
            self::assertTrue($gateway->send($device, 'Secret payload', 'bb-test', 300)->success());
            $retry = $gateway->send($device, 'Secret payload', 'bb-test', 300);
            self::assertTrue($retry->retryable());
            self::assertSame(180, $retry->retryAfter);
            $network = $gateway->send($device, 'Secret payload', 'bb-test', 300);
            self::assertTrue($network->retryable());
            self::assertNull($network->status);
        } finally {
            unlink($file);
        }
    }
}
