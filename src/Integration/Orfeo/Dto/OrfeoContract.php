<?php

declare(strict_types=1);

namespace App\Integration\Orfeo\Dto;

/** Seules les conditions utiles au groupe sont conservées, sans données fiscales ou bancaires. */
final readonly class OrfeoContract
{
    public function __construct(
        public int $id,
        public string $label,
        public string $status,
        public string $accommodationNotes,
        public string $transportNotes,
        public string $mealNotes,
        public ?int $feeHtCents,
        public ?int $feeTtcCents,
    ) {
    }

    /** @return array{id: int, label: string, status: string, accommodationNotes: string, transportNotes: string, mealNotes: string, feeHtCents: ?int, feeTtcCents: ?int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
            'accommodationNotes' => $this->accommodationNotes,
            'transportNotes' => $this->transportNotes,
            'mealNotes' => $this->mealNotes,
            'feeHtCents' => $this->feeHtCents,
            'feeTtcCents' => $this->feeTtcCents,
        ];
    }

    /** @param array{id: int, label: string, status: string, accommodationNotes: string, transportNotes: string, mealNotes: string, feeHtCents: ?int, feeTtcCents: ?int} $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
