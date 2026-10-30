<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\User;
use App\Enum\MemberKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MemberProfile> */
final class MemberProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberProfile::class);
    }

    /** @return list<MemberProfile> */
    public function forBand(Band $band): array
    {
        return $this->findBy(['band' => $band], ['publicName' => 'ASC']);
    }

    public function forUser(User $user, Band $band): ?MemberProfile
    {
        return $this->findOneBy(['user' => $user, 'band' => $band]);
    }

    public function isMusician(User $user, Band $band): bool
    {
        return MemberKind::Musician === $this->forUser($user, $band)?->getKind();
    }

    /** @return list<MemberProfile> */
    public function activationChoices(): array
    {
        return $this->createQueryBuilder('profile')
            ->addSelect('person')
            ->join('profile.user', 'person')
            ->andWhere("person.password = ''")
            ->andWhere('profile.activationHash IS NOT NULL')
            ->andWhere('profile.activationExpiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('profile.publicName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
