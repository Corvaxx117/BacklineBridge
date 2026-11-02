<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

final class QueryCounter extends AbstractLogger
{
    private int $count = 0;

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            ++$this->count;
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}
