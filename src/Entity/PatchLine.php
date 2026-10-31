<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PatchLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PatchLineRepository::class)]
#[ORM\Table(name: 'patch_line')]
#[ORM\Index(name: 'idx_patch_line_sheet', columns: ['sheet_id'])]
#[ORM\UniqueConstraint(
    name: 'uniq_patch_line_sheet_channel',
    columns: ['sheet_id', 'channel_number', 'active'],
),]
#[UniqueEntity(
    fields: ['sheet', 'channelNumber', 'active'],
    errorPath: 'channelNumber',
    message: 'Ce numéro de canal est déjà utilisé dans cette fiche.',
),]
class PatchLine
{
    public const SECTIONS = ['drums', 'bass', 'guitar', 'keys', 'vocals', 'utility', 'effects'];

    public const SECTION_LABELS = [
        'drums' => 'Batterie',
        'bass' => 'Basse',
        'guitar' => 'Guitares',
        'keys' => 'Claviers',
        'vocals' => 'Voix',
        'utility' => 'Utilitaires',
        'effects' => 'Effets',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sourceKey;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TechnicalSheet $sheet = null;

    #[ORM\Column(name: 'channel_number')]
    #[Assert\NotNull(message: 'Indique un numéro de canal.')]
    #[Assert\Positive(message: 'Le numéro de canal doit être supérieur à zéro.')]
    private ?int $channelNumber = null;

    #[ORM\Column(name: 'source_name', length: 150)]
    #[Assert\NotBlank(message: 'Indique le nom de la source.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le nom de la source ne peut pas dépasser {{ limit }} caractères.',
    ),]
    private string $sourceName = '';

    #[ORM\Column(name: 'preferred_microphone', length: 120, nullable: true)]
    #[Assert\Length(
        max: 120,
        maxMessage: 'Le microphone ne peut pas dépasser {{ limit }} caractères.',
    ),]
    private ?string $preferredMicrophone = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80, maxMessage: 'Le pied ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $stand = null;

    #[ORM\Column(length: 2000, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.',
    ),]
    private ?string $notes = null;

    #[ORM\Column(length: 20, options: ['default' => 'utility'])]
    #[Assert\Choice(choices: self::SECTIONS, message: 'Choisis une section proposée.')]
    private string $section = 'utility';

    // NULL libère le canal dans l'index unique, tout en conservant la ligne dans la corbeille.
    #[ORM\Column(nullable: true, options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(name: 'deleted_at', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->sourceKey = bin2hex(random_bytes(16));
    }

    public function getSourceKey(): ?string
    {
        return $this->sourceKey;
    }

    public function inheritSourceKey(self $source): self
    {
        if (null !== $source->sourceKey) {
            $this->sourceKey = $source->sourceKey;
        }

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function isActive(): bool
    {
        return true === $this->active;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function archive(): void
    {
        $this->active = null;
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->active = true;
        $this->deletedAt = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSheet(): ?TechnicalSheet
    {
        return $this->sheet;
    }

    public function setSheet(TechnicalSheet $sheet): self
    {
        $this->sheet = $sheet;

        if (!$sheet->getLines()->contains($this)) {
            $sheet->addLine($this);
        }

        return $this;
    }

    public function getChannelNumber(): ?int
    {
        return $this->channelNumber;
    }

    public function setChannelNumber(?int $channelNumber): self
    {
        $this->channelNumber = $channelNumber;

        return $this;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function setSourceName(string $sourceName): self
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    public function getPreferredMicrophone(): ?string
    {
        return $this->preferredMicrophone;
    }

    public function setPreferredMicrophone(?string $preferredMicrophone): self
    {
        $this->preferredMicrophone = $preferredMicrophone;

        return $this;
    }

    public function getStand(): ?string
    {
        return $this->stand;
    }

    public function setStand(?string $stand): self
    {
        $this->stand = $stand;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function setSection(string $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function getSectionLabel(): string
    {
        return self::SECTION_LABELS[$this->section] ?? $this->section;
    }
}
