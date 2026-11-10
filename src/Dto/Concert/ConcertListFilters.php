<?php

declare(strict_types=1);

namespace App\Dto\Concert;

final readonly class ConcertListFilters
{
    private function __construct(public string $sort, public string $level)
    {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $sort = isset($query['sort']) && 'importance' === $query['sort'] ? 'importance' : 'date';
        $level = $query['enjeu'] ?? 'all';
        if (!is_string($level) || !in_array($level, ['all', 'unrated', '1', '2', '3', '4', '5'], true)) {
            $level = 'all';
        }

        return new self($sort, $level);
    }

    public function isFiltered(): bool
    {
        return 'all' !== $this->level;
    }
}
