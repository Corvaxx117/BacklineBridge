<?php

declare(strict_types=1);

namespace App\Service\Venue;

use App\Dto\Venue\VenueReplyInput;
use App\Entity\VenueInvitation;
use App\Entity\VenueReply;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ReplyRecorder
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(
        VenueInvitation $invitation,
        VenueReplyInput $input,
        ?VenueReply $reply,
    ): void {
        if ($input->revision !== (string) ($reply?->getRevision() ?? 0)) {
            throw new ConflictHttpException(
                'La réponse a changé dans un autre onglet. Rechargez la page avant de réessayer.',
            );
        }
        $reply ??= new VenueReply($invitation);
        $answers = [];
        foreach ($input->answers as $key => $answer) {
            $status =
                $answer->status ??
                throw new \DomainException('Choisis une disponibilité pour chaque demande.');
            $answers[$key] = [
                'status' => $status->value,
                'details' => trim($answer->details ?? '') ?: null,
            ];
        }
        $reply->update($input->respondent, $input->notes, $answers);
        $this->em->persist($reply);
        $this->em->flush();
    }
}
