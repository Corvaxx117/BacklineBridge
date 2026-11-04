<?php

declare(strict_types=1);

namespace App\Integration\WebPush;

final readonly class PushSendResult
{
    public function __construct(public ?int $status, public int $retryAfter = 0)
    {
    }

    public function success(): bool
    {
        return null !== $this->status && $this->status >= 200 && $this->status < 300;
    }

    public function expired(): bool
    {
        return in_array($this->status, [404, 410], true);
    }

    public function retryable(): bool
    {
        return null === $this->status || 429 === $this->status || $this->status >= 500;
    }
}
