<?php

declare(strict_types=1);

namespace App\Integration\WebPush;

use App\Dto\Notification\PushSubscriptionInput;
use App\Entity\PushSubscription;
use App\Service\Notification\Push\PushConfiguration;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class WebPushGateway implements PushGateway
{
    public function __construct(
        private PushConfiguration $configuration,
        #[Autowire(service: 'web_push.http_client')] private ClientInterface $client,
    ) {
    }

    public function send(PushSubscription $subscription, string $payload, string $topic, int $ttl): PushSendResult
    {
        // Le contrôle est répété avant chaque sortie réseau, même pour une ancienne ligne en base.
        if (!PushSubscriptionInput::allowedEndpoint($subscription->getEndpoint())) {
            return new PushSendResult(400);
        }
        try {
            $sender = new WebPush(['VAPID' => $this->configuration->credentials()], client: $this->client);
            $report = $sender->sendOneNotification(new Subscription(
                $subscription->getEndpoint(), $subscription->getPublicKey(),
                $subscription->getAuthToken(), 'aes128gcm',
            ), $payload, [
                'TTL' => $ttl,
                'urgency' => 'normal',
                'topic' => substr(hash('sha256', $topic), 0, 32),
            ]);
            $response = $report->getResponse();
            $retry = $response?->getHeaderLine('Retry-After') ?? '';

            return new PushSendResult(
                $response?->getStatusCode(),
                ctype_digit($retry) ? min(86400, (int) $retry) : 0,
            );
        } catch (\Throwable) {
            // Ni les clés, ni l’URL privée du relais, ni sa réponse ne vont dans les journaux.
            return new PushSendResult(400);
        }
    }
}
