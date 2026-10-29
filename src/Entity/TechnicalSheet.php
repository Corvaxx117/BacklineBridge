<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TechnicalSheetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TechnicalSheetRepository::class)]
#[ORM\Table(name: 'technical_sheet')]
class TechnicalSheet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'band_name', length: 150)]
    private string $bandName = '';

    #[ORM\Column(name: 'document_label', length: 255)]
    private string $documentLabel = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $notices = [];

    /** @var list<array{label: string, kind: string, x: float|int, y: float|int, notes: string, rotation?: float, scale?: float}> */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $stagePlan = null;

    /** @var list<array{id: string, category: string, label: string, quantity: int, specification: string}> */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $equipmentRequests = null;

    public function getStagePlan(): array
    {
        return $this->stagePlan ?? [];
    }

    public function setStagePlan(array $items): self
    {
        $this->stagePlan = array_values($items);

        return $this;
    }

    public function getEquipmentRequests(): array
    {
        return $this->equipmentRequests ?? [];
    }

    public function setEquipmentRequests(array $items): self
    {
        $this->equipmentRequests = array_values($items);

        return $this;
    }

    /** @var Collection<int, PatchLine> */
    #[ORM\OneToMany(targetEntity: PatchLine::class, mappedBy: 'sheet', cascade: ['persist'])]
    #[ORM\OrderBy(['channelNumber' => \SortDirection::Ascending])]
    private Collection $lines;

    // Une fiche sans concert est une référence ; une fiche liée est sa copie pour cette date.
    #[ORM\OneToOne(mappedBy: 'patchSheet', targetEntity: Concert::class)]
    private ?Concert $concert = null;

    #[ORM\OneToOne(mappedBy: 'referenceSheet', targetEntity: Band::class)]
    private ?Band $referenceBand = null;

    public function getBand(): ?Band
    {
        return $this->referenceBand ?? $this->concert?->getBand();
    }

    public function setReferenceBand(Band $band): self
    {
        $this->referenceBand = $band;

        return $this;
    }

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConcert(): ?Concert
    {
        return $this->concert;
    }

    public function setConcert(Concert $concert): self
    {
        $this->concert = $concert;

        return $this;
    }

    public function getBandName(): string
    {
        return $this->bandName;
    }

    public function setBandName(string $bandName): self
    {
        $this->bandName = $bandName;

        return $this;
    }

    public function getDocumentLabel(): string
    {
        return $this->documentLabel;
    }

    public function setDocumentLabel(string $documentLabel): self
    {
        $this->documentLabel = $documentLabel;

        return $this;
    }

    /** @return list<string> */
    public function getNotices(): array
    {
        return $this->notices;
    }

    /** @param list<string> $notices */
    public function setNotices(array $notices): self
    {
        $this->notices = array_values($notices);

        return $this;
    }

    /** @return Collection<int, PatchLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function getActiveLineCount(): int
    {
        return $this->lines->filter(static fn(PatchLine $line): bool => $line->isActive())->count();
    }

    public function addLine(PatchLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
        }

        if ($line->getSheet() !== $this) {
            $line->setSheet($this);
        }

        return $this;
    }
}
