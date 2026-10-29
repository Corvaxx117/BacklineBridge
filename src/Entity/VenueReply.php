<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'venue_reply')]
#[ORM\UniqueConstraint(name: 'uniq_reply_invitation', columns: ['invitation_id'])]
class VenueReply
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private VenueInvitation $invitation;

    #[ORM\Column(type: Types::JSON)]
    private array $answers = [];

    #[ORM\Column(length: 150)]
    private string $respondent = '';

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    public function __construct(VenueInvitation $invitation)
    {
        $this->invitation = $invitation;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getInvitation(): VenueInvitation
    {
        return $this->invitation;
    }
    public function getAnswers(): array
    {
        return $this->answers;
    }
    public function getRespondent(): string
    {
        return $this->respondent;
    }
    public function getNotes(): ?string
    {
        return $this->notes;
    }
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
    public function getRevision(): int
    {
        return $this->revision;
    }

    public function update(string $respondent, ?string $notes, array $answers): void
    {
        $this->respondent = trim($respondent);
        $this->notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        $this->answers = $answers;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
