<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MemberKind;
use App\Repository\MemberProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberProfileRepository::class)]
#[ORM\Table(name: 'member_profile')]
#[ORM\UniqueConstraint(name: 'uniq_profile_band_user', columns: ['band_id', 'user_id'])]
class MemberProfile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $publicId;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $administrativeCiphertext = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $activationHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activationExpiresAt = null;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private Band $band,
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL'),]
        private ?User $user,
        #[ORM\Column(length: 100)] private string $publicName,
        #[ORM\Column(length: 120)] private string $function,
        #[ORM\Column(length: 20, enumType: MemberKind::class)] private MemberKind $kind,
    ) {
        $this->publicId = bin2hex(random_bytes(16));
        if (MemberKind::Booker === $kind) {
            $this->function = $kind->label();
        }
    }

    public function attachUser(User $user): void
    {
        if (null !== $this->user) {
            throw new \DomainException('Ce profil est déjà associé à un compte.');
        }
        $this->user = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getPublicName(): string
    {
        return $this->publicName;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    public function getKind(): MemberKind
    {
        return $this->kind;
    }

    public function getAdministrativeCiphertext(): ?string
    {
        return $this->administrativeCiphertext;
    }

    public function setAdministrativeCiphertext(string $value): void
    {
        $this->administrativeCiphertext = $value;
    }

    public function getActivationHash(): ?string
    {
        return $this->activationHash;
    }

    public function getActivationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->activationExpiresAt;
    }

    public function canActivate(): bool
    {
        return null !== $this->user
            && !$this->user->isActivated()
            && null !== $this->activationHash
            && $this->activationExpiresAt > new \DateTimeImmutable();
    }

    public function issueActivation(string $hash, \DateTimeImmutable $expires): void
    {
        if ($this->user?->isActivated()) {
            throw new \DomainException('Ce compte possède déjà un mot de passe.');
        }
        $this->activationHash = $hash;
        $this->activationExpiresAt = $expires;
    }

    public function consumeActivation(): void
    {
        $this->activationHash = null;
        $this->activationExpiresAt = null;
    }
}
