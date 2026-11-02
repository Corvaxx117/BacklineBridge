<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SetlistRepository;
use App\ValueObject\SetlistContent;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Contenu interne, séparé des données de la fiche publiées aux salles.
#[ORM\Entity(repositoryClass: SetlistRepository::class)]
#[ORM\Table(name: 'setlist')]
class Setlist
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tracks = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 100)]
    private string $updatedByName;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy;

    public function __construct(
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private TechnicalSheet $sheet,
        SetlistContent $content,
        User $author,
    ) {
        $this->replace($content, $author);
    }

    public function replace(SetlistContent $content, User $author): void
    {
        $this->tracks = $content->tracks();
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedBy = $author;
        $this->updatedByName = $author->getDisplayName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSheet(): TechnicalSheet
    {
        return $this->sheet;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    /** @return list<string> */
    public function getTracks(): array
    {
        return $this->tracks;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdatedByName(): string
    {
        return $this->updatedByName;
    }
}
