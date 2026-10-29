<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'finance_note')]
class FinanceNote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private Band $band,
        #[ORM\Column(length: 2000)] private string $text,
        #[ORM\ManyToOne] #[
            ORM\JoinColumn(nullable: true, onDelete: 'SET NULL'),
        ]
        private ?User $createdBy = null,
    ) {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function resolve(User $user): void
    {
        if ($this->resolvedAt !== null) {
            throw new \DomainException('Cette note est déjà vérifiée.');
        }
        $this->resolvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->resolvedBy = $user;
    }
}
