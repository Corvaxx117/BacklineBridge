<?php

declare(strict_types=1);

namespace App\Dto\Technical;

use App\Entity\TechnicalSheet;
use Symfony\Component\Validator\Constraints as Assert;

final class TechnicalContactsInput
{
    /** @var list<TechnicalContactInput> */
    #[Assert\Valid, Assert\Count(max: 4)]
    public array $contacts = [];

    #[Assert\NotBlank, Assert\Regex(pattern: '/^[a-f0-9]{64}$/D')]
    public string $revision = '';

    public static function fromSheet(TechnicalSheet $sheet): self
    {
        $input = new self();
        $input->revision = self::fingerprint($sheet);
        foreach ($sheet->getBand()?->getTechnicalIdentity()->contacts ?? [] as $row) {
            $contact = new TechnicalContactInput();
            $contact->name = $row['name'];
            $contact->role = $row['role'];
            $contact->email = $row['email'];
            $contact->phone = $row['phone'];
            $input->contacts[] = $contact;
        }
        while (count($input->contacts) < 4) {
            $input->contacts[] = new TechnicalContactInput();
        }

        return $input;
    }

    public static function fingerprint(TechnicalSheet $sheet): string
    {
        return hash('sha256', json_encode($sheet->getBand()?->getTechnicalIdentity()->contacts ?? [], JSON_THROW_ON_ERROR));
    }

    /** @return list<array{name: string, role: string, email: string, phone: string}> */
    public function values(): array
    {
        $rows = [];
        foreach ($this->contacts as $contact) {
            if (!$contact->remove && !$contact->isEmpty()) {
                $rows[] = $contact->toArray();
            }
        }

        return $rows;
    }
}
