<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContactRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'concert_contact')]
class ConcertContact
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 30, enumType: ContactRole::class)]
    private ContactRole $role = ContactRole::Other;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $notes = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Concert $concert,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConcert(): Concert
    {
        return $this->concert;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): ContactRole
    {
        return $this->role;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getTelephoneUri(): ?string
    {
        return null === $this->phone ? null : 'tel:'.preg_replace('/[^+0-9]/', '', $this->phone);
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function update(
        string $name,
        ContactRole $role,
        ?string $email,
        ?string $phone,
        ?string $notes,
    ): void {
        $this->name = trim($name);
        $this->role = $role;
        $this->email = self::optional($email);
        $this->phone = self::optional($phone);
        $this->notes = self::optional($notes);
    }

    private static function optional(?string $value): ?string
    {
        return null !== $value && '' !== trim($value) ? trim($value) : null;
    }
}
