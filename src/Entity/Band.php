<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'band')]
class Band
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\OneToOne(inversedBy: 'referenceBand', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'reference_sheet_id', nullable: false)]
    private TechnicalSheet $referenceSheet;

    /** @var Collection<int, BandMembership> */
    #[ORM\OneToMany(mappedBy: 'band', targetEntity: BandMembership::class)]
    private Collection $memberships;

    public function __construct(string $name, TechnicalSheet $referenceSheet)
    {
        $this->name = $name;
        $this->referenceSheet = $referenceSheet;
        $this->memberships = new ArrayCollection();
        $referenceSheet->setReferenceBand($this);
    }

    /** @var list<array{label: string, url: string}> */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $usefulLinks = [];

    /**
     * @return list<array{label: string, url: string}>
     */
    public function getUsefulLinks(): array
    {
        return $this->usefulLinks;
    }

    /**
     * @param list<array{label: string, url: string}> $links
     */
    public function setUsefulLinks(array $links): void
    {
        $this->usefulLinks = $links;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getReferenceSheet(): TechnicalSheet
    {
        return $this->referenceSheet;
    }
}
