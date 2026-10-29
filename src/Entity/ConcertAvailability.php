<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AvailabilityStatus;
use App\Repository\ConcertAvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConcertAvailabilityRepository::class)]
#[ORM\Table(name: 'concert_availability')]
#[ORM\UniqueConstraint(name: 'uniq_availability_concert_user', columns: ['concert_id', 'user_id'])]
class ConcertAvailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Concert $concert;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20, enumType: AvailabilityStatus::class)]
    private AvailabilityStatus $status = AvailabilityStatus::Pending;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $answeredAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $concertDate;

    public function __construct(Concert $concert, User $user)
    {
        $this->concert = $concert;
        $this->user = $user;
        $this->concertDate = $concert->getDate();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConcert(): Concert
    {
        return $this->concert;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isForCurrentDate(): bool
    {
        return $this->concertDate->format('Y-m-d') === $this->concert->getDate()->format('Y-m-d');
    }

    public function getStatus(): AvailabilityStatus
    {
        return $this->isForCurrentDate() ? $this->status : AvailabilityStatus::Pending;
    }

    public function getComment(): ?string
    {
        return $this->isForCurrentDate() ? $this->comment : null;
    }

    public function getAnsweredAt(): ?\DateTimeImmutable
    {
        return $this->isForCurrentDate() ? $this->answeredAt : null;
    }

    public function respond(AvailabilityStatus $status, ?string $comment): void
    {
        $this->concertDate = $this->concert->getDate();
        $this->status = $status;
        $this->comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
        $this->answeredAt =
            $status === AvailabilityStatus::Pending ? null : new \DateTimeImmutable();
    }

    public function reset(): void
    {
        $this->respond(AvailabilityStatus::Pending, null);
    }
}
