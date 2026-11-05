<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_push_endpoint', columns: ['endpoint_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_push_device', columns: ['device_hash'])]
class PushSubscription
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $endpointHash;

    #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTestAt = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 64)] private string $deviceHash,
        #[ORM\Column(length: 2048)] private string $endpoint,
        #[ORM\Column(length: 100)] private string $publicKey,
        #[ORM\Column(length: 30)] private string $authToken,
        #[ORM\Column(length: 100)] private string $vapidPublicKey,
        #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE)] private \DateTimeImmutable $createdAt,
    ) {
        $this->endpointHash = hash('sha256', $endpoint);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getVapidPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    public function getLastTestAt(): ?\DateTimeImmutable
    {
        return $this->lastTestAt;
    }

    public function recordTest(\DateTimeImmutable $at): void
    {
        $this->lastTestAt = $at;
    }
}
