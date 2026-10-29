<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StockMovementKind;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stock_movement')]
class StockMovement
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private MerchVariant $variant,
        #[
            ORM\Column(length: 20, enumType: StockMovementKind::class),
        ]
        private StockMovementKind $kind,
        #[ORM\Column(nullable: true)] private ?int $beforeQuantity,
        #[ORM\Column] private int $afterQuantity,
        #[ORM\Column(length: 250)] private string $reason,
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

    public function getVariant(): MerchVariant
    {
        return $this->variant;
    }

    public function getKind(): StockMovementKind
    {
        return $this->kind;
    }

    public function getBeforeQuantity(): ?int
    {
        return $this->beforeQuantity;
    }

    public function getAfterQuantity(): int
    {
        return $this->afterQuantity;
    }

    public function getDifference(): ?int
    {
        return $this->beforeQuantity === null ? null : $this->afterQuantity - $this->beforeQuantity;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
