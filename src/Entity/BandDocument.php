<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DocumentCategory;
use App\Repository\BandDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BandDocumentRepository::class)]
#[ORM\Table(name: 'band_document')]
class BandDocument
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 100)]
    private string $createdByName;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Band $band,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Concert $concert,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy,
        #[ORM\Column(length: 160)]
        private string $title,
        #[ORM\Column(length: 30, enumType: DocumentCategory::class)]
        private DocumentCategory $category,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $description,
        #[ORM\Column(length: 2048, nullable: true)]
        private ?string $externalUrl,
        #[ORM\Column(length: 36, nullable: true)]
        private ?string $storageKey,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $originalFilename,
        #[ORM\Column]
        private int $sizeBytes,
    ) {
        if ((null === $externalUrl) === (null === $storageKey)) {
            throw new \InvalidArgumentException('Un document doit contenir un PDF ou un lien.');
        }
        if (null !== $concert && $concert->getBand() !== $band) {
            throw new \InvalidArgumentException('Le concert doit appartenir au même groupe.');
        }
        $this->createdAt = new \DateTimeImmutable();
        $this->createdByName = $createdBy?->getDisplayName() ?? 'Membre du groupe';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getConcert(): ?Concert
    {
        return $this->concert;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedByName(): string
    {
        return $this->createdByName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCategory(): DocumentCategory
    {
        return $this->category;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function getExternalHost(): ?string
    {
        $host = null === $this->externalUrl ? null : parse_url($this->externalUrl, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    public function getStorageKey(): ?string
    {
        return $this->storageKey;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function isPdf(): bool
    {
        return null !== $this->storageKey;
    }
}
