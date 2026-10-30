<?php

declare(strict_types=1);

namespace App\Service\Venue;

use App\Entity\Concert;
use App\Entity\VenueInvitation;
use App\Entity\VenueReply;
use Doctrine\ORM\EntityManagerInterface;

final readonly class VenueWorkspace
{
    public function __construct(
        private EntityManagerInterface $em,
        private PublicationBuilder $builder,
    ) {
    }

    /**
     * @return list<array{invitation: VenueInvitation, reply: ?VenueReply, outdated: bool, requests: array<string, array{category: string, label: string, specification: string}>}>
     */
    public function invitations(Concert $concert): array
    {
        $invitations = $this->em
            ->getRepository(VenueInvitation::class)
            ->createQueryBuilder('i')
            ->join('i.publication', 'p')
            ->addSelect('p')
            ->where('p.concert = :concert')
            ->setParameter('concert', $concert)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
        $rows = [];
        foreach ($invitations as $invitation) {
            $reply = $this->em
                ->getRepository(VenueReply::class)
                ->findOneBy(['invitation' => $invitation]);
            $rows[] = [
                'invitation' => $invitation,
                'reply' => $reply,
                'outdated' => $this->builder->isOutdated($invitation->getPublication()),
                'requests' => $this->builder->requests(
                    $invitation->getPublication()->getSnapshot(),
                ),
            ];
        }

        return $rows;
    }
}
