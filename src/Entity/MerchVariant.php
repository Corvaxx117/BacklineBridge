<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'merch_variant')]
#[ORM\UniqueConstraint(name: 'uniq_merch_product_variant', columns: ['product_id', 'label'])]
class MerchVariant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $needsVerification = false;
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'variants')] #[
            ORM\JoinColumn(nullable: false),
        ]
        private MerchProduct $product,
        #[ORM\Column(length: 30)] private string $label = 'Unique',
    ) {
        $product->addVariant($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): MerchProduct
    {
        return $this->product;
    }

    public function getBand(): Band
    {
        return $this->product->getBand();
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function needsVerification(): bool
    {
        return $this->needsVerification;
    }

    public function setQuantity(int $quantity, bool $needsVerification = false): void
    {
        if ($quantity < 0 || $quantity > 1000000) {
            throw new \DomainException('Le stock doit être compris entre 0 et 1 000 000.');
        }
        $this->quantity = $quantity;
        $this->needsVerification = $needsVerification;
    }
}
