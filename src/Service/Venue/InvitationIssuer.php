<?php

declare(strict_types=1);

namespace App\Service\Venue;

use App\Dto\Venue\{InvitationInput, IssuedInvitation};
use App\Entity\{Concert, TechnicalPublication, VenueInvitation};
use Doctrine\ORM\EntityManagerInterface;

final readonly class InvitationIssuer
{
    public function __construct(
        private EntityManagerInterface $em,
        private PublicationBuilder $builder,
    ) {}

    public function issue(Concert $concert, InvitationInput $input): IssuedInvitation
    {
        $snapshot = $this->builder->snapshot($concert);
        $publication = new TechnicalPublication(
            $concert,
            $snapshot,
            $this->builder->fingerprint($snapshot),
        );
        $token = bin2hex(random_bytes(32));
        $invitation = new VenueInvitation(
            $publication,
            trim($input->label),
            hash('sha256', $token),
            new \DateTimeImmutable('+' . $input->days . ' days'),
        );
        $this->em->persist($invitation);
        $this->em->flush();

        return new IssuedInvitation($invitation, $token);
    }
}
