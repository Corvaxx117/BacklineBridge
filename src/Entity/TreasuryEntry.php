<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TreasuryEntryKind;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'treasury_entry')]
class TreasuryEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 250, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private TreasuryAccount $account,
        #[
            ORM\Column(length: 15, enumType: TreasuryEntryKind::class),
        ]
        private TreasuryEntryKind $kind,
        #[ORM\Column] private int $amountCents,
        #[ORM\Column(length: 160)] private string $label,
        #[ORM\Column(length: 30)] private string $category,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)] private \DateTimeImmutable $occurredOn,
        #[ORM\ManyToOne] #[
            ORM\JoinColumn(nullable: true, onDelete: 'SET NULL'),
        ]
        private ?User $createdBy = null,
        #[ORM\ManyToOne] #[
            ORM\JoinColumn(nullable: true),
        ]
        private ?TreasuryAccount $destination = null,
        #[ORM\OneToOne(cascade: ['persist'])] #[
            ORM\JoinColumn(nullable: true),
        ]
        private ?StockMovement $stockMovement = null,
    ) {
        if ($amountCents < 1) {
            throw new \InvalidArgumentException('Montant positif attendu.');
        }
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getBand(): Band
    {
        return $this->account->getBand();
    }

    public function getAccount(): TreasuryAccount
    {
        return $this->account;
    }

    public function getKind(): TreasuryEntryKind
    {
        return $this->kind;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getDestination(): ?TreasuryAccount
    {
        return $this->destination;
    }

    public function getStockMovement(): ?StockMovement
    {
        return $this->stockMovement;
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function cancel(User $actor, string $reason): void
    {
        if ($this->isCancelled() || $this->kind === TreasuryEntryKind::Opening) {
            throw new \DomainException('Ce mouvement ne peut pas être annulé.');
        }
        $this->cancelledAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->cancelledBy = $actor;
        $this->cancelReason = $reason;
    }
}
