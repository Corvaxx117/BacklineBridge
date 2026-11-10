<?php

declare(strict_types=1);

namespace App\Integration\Orfeo\Dto;

use App\Enum\ConcertStatus;

final readonly class OrfeoEvent
{
    public string $id;

    /** @param list<OrfeoContact> $contacts
     * @param list<OrfeoContract> $contracts */
    public function __construct(
        public int $projectId,
        public int $performanceId,
        public int $spectacleId,
        public string $date,
        public ?string $localTime,
        public string $venue,
        public string $city,
        public ?string $address,
        public int $statusId,
        public string $statusName,
        public bool $confirmed,
        public bool $cancelled,
        public array $contacts,
        public array $contracts = [],
    ) {
        $this->id = $projectId.':'.$performanceId;
    }

    public function status(): ConcertStatus
    {
        return $this->confirmed ? ConcertStatus::Validated : ConcertStatus::Pending;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'projectId' => $this->projectId, 'performanceId' => $this->performanceId,
            'spectacleId' => $this->spectacleId, 'date' => $this->date, 'localTime' => $this->localTime,
            'venue' => $this->venue, 'city' => $this->city, 'address' => $this->address,
            'statusId' => $this->statusId, 'statusName' => $this->statusName,
            'confirmed' => $this->confirmed, 'cancelled' => $this->cancelled,
            'contracts' => array_map(static fn (OrfeoContract $contract): array => $contract->toArray(), $this->contracts),
            'contacts' => array_map(static fn (OrfeoContact $contact): array => $contact->toArray(), $this->contacts),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['projectId'], $data['performanceId'], $data['spectacleId'],
            $data['date'], $data['localTime'], $data['venue'], $data['city'], $data['address'],
            $data['statusId'], $data['statusName'], $data['confirmed'], $data['cancelled'],
            array_values(array_map(OrfeoContact::fromArray(...), $data['contacts'])),
            array_values(array_map(OrfeoContract::fromArray(...), $data['contracts'] ?? [])));
    }
}
