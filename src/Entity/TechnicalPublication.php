<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Une publication conserve les demandes exactes auxquelles répond la salle. */
#[ORM\Entity]
#[ORM\Table(name: 'technical_publication')]
#[ORM\Index(name: 'idx_publication_concert', columns: ['concert_id'])]
class TechnicalPublication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Concert $concert;

    #[ORM\Column(type: Types::JSON)]
    private array $snapshot;

    #[ORM\Column(length: 64)]
    private string $fingerprint;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    public function __construct(Concert $concert, array $snapshot, string $fingerprint)
    {
        $this->concert = $concert;
        $this->snapshot = $snapshot;
        $this->fingerprint = $fingerprint;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getConcert(): Concert
    {
        return $this->concert;
    }
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }
    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
