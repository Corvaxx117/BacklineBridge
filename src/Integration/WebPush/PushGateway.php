<?php

declare(strict_types=1);

namespace App\Integration\WebPush;

use App\Entity\PushSubscription;

interface PushGateway
{
    public function send(PushSubscription $subscription, string $payload, string $topic, int $ttl): PushSendResult;
}
