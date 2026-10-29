<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'merch_product')]
#[ORM\UniqueConstraint(name: 'uniq_merch_band_name', columns: ['band_id', 'name'])]
class MerchProduct
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    /** @var Collection<int, MerchVariant> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: MerchVariant::class, cascade: ['persist'])]
    private Collection $variants;
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private Band $band,
        #[ORM\Column(length: 160)] private string $name,
        #[ORM\Column(length: 30)] private string $category,
    ) {
        $this->variants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(MerchVariant $variant): void
    {
        $this->variants->add($variant);
    }

    public function getKnownQuantity(): int
    {
        return array_sum(
            array_map(
                static fn(MerchVariant $variant): int => $variant->getQuantity() ?? 0,
                $this->variants->toArray(),
            ),
        );
    }

    public function isQuantityComplete(): bool
    {
        return !$this->variants->exists(
            static fn(int $key, MerchVariant $variant): bool => $variant->getQuantity() === null,
        );
    }
}
