<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BandRole;
use App\Repository\BandMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BandMembershipRepository::class)]
#[ORM\Table(name: 'band_membership')]
#[ORM\UniqueConstraint(name: 'uniq_band_user', columns: ['band_id', 'user_id'])]
class BandMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    private Band $band;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 20, enumType: BandRole::class)]
    private BandRole $role;

    public function __construct(Band $band, User $user, BandRole $role)
    {
        $this->band = $band;
        $this->user = $user;
        $this->role = $role;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): BandRole
    {
        return $this->role;
    }
}
