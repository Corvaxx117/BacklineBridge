<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'venue_invitation')]
#[ORM\UniqueConstraint(name: 'uniq_invitation_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_invitation_publication', columns: ['publication_id'])]
class VenueInvitation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private TechnicalPublication $publication;

    #[ORM\Column(length: 150)]
    private string $label;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        TechnicalPublication $publication,
        string $label,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->publication = $publication;
        $this->label = $label;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublication(): TechnicalPublication
    {
        return $this->publication;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function isUsable(): bool
    {
        return null === $this->revokedAt && $this->expiresAt > new \DateTimeImmutable();
    }
}
