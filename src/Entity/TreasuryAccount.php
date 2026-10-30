<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TreasuryAccountKind;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'treasury_account')]
#[ORM\UniqueConstraint(name: 'uniq_treasury_band_kind', columns: ['band_id', 'kind'])]
class TreasuryAccount
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column]
    private int $balanceCents = 0;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private Band $band,
        #[ORM\Column(length: 10, enumType: TreasuryAccountKind::class),]
        private TreasuryAccountKind $kind,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getKind(): TreasuryAccountKind
    {
        return $this->kind;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getBalanceCents(): int
    {
        return $this->balanceCents;
    }

    public function credit(int $cents): void
    {
        if ($cents < 1) {
            throw new \DomainException('Le montant doit être positif.');
        }
        $this->balanceCents += $cents;
    }

    public function debit(int $cents): void
    {
        if ($cents < 1 || $cents > $this->balanceCents) {
            throw new \DomainException('Le montant dépasse le solde disponible.');
        }
        $this->balanceCents -= $cents;
    }
}
