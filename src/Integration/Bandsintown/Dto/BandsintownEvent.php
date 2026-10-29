<?php

declare(strict_types=1);

namespace App\Integration\Bandsintown\Dto;

/** Données publiques normalisées. Le jour et l'heure restent ceux annoncés sur place. */
final readonly class BandsintownEvent
{
    /** @param list<string> $lineup @param list<array{label: string, url: string}> $tickets */
    public function __construct(
        public string $id,
        public string $artistId,
        public string $date,
        public string $localTime,
        public string $venue,
        public string $city,
        public string $country,
        public string $address,
        public string $title,
        public string $description,
        public array $lineup,
        public array $tickets,
    ) {}

    public function sourceUrl(): string
    {
        return 'https://www.bandsintown.com/e/' . $this->id;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
