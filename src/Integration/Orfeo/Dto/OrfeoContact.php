<?php

declare(strict_types=1);

namespace App\Integration\Orfeo\Dto;

use App\Enum\ContactRole;

final readonly class OrfeoContact
{
    public function __construct(
        public string $key,
        public string $name,
        public ContactRole $role,
        public ?string $email,
        public ?string $phone,
        public ?string $notes,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key' => $this->key, 'name' => $this->name, 'role' => $this->role->value,
            'email' => $this->email, 'phone' => $this->phone, 'notes' => $this->notes];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['key'], $data['name'], ContactRole::from($data['role']),
            $data['email'], $data['phone'], $data['notes']);
    }
}
