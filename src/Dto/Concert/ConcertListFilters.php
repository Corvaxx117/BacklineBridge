<?php

declare(strict_types=1);

namespace App\Dto\Concert;

final readonly class ConcertListFilters
{
    public const int MAX_SEARCH_LENGTH = 160;

    private function __construct(public string $sort, public string $search)
    {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $sort = isset($query['sort']) && 'importance' === $query['sort'] ? 'importance' : 'date';
        $search = $query['q'] ?? '';
        $search = is_string($search) ? mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH) : '';

        return new self($sort, $search);
    }

    public function isFiltered(): bool
    {
        return '' !== $this->search;
    }
}
