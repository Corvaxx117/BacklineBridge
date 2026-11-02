<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\CreateConcertInput;
use App\Entity\Concert;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ConcertUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConcertAvailabilityManager $availability,
    ) {
    }

    /** @return bool Si la date a changé et que les disponibilités doivent être redonnées. */
    public function update(Concert $concert, CreateConcertInput $input): bool
    {
        if ($input->revision !== (string) $concert->getRevision()) {
            throw new ConflictHttpException('Les informations du concert ont changé.');
        }

        assert(null !== $input->date && null !== $input->status);
        $dateChanged = $concert->getDate()->format('Y-m-d') !== $input->date->format('Y-m-d');
        $concert->updateDetails(
            $input->date,
            $input->venue,
            $input->city,
            $input->status,
            $input->notes,
        );
        $concert->setStartTime($input->startTime);
        if ($dateChanged) {
            $this->availability->resetForNewDate($concert);
        }
        // La version ORM protège aussi une écriture concurrente après la comparaison ci-dessus.
        // Le concert et la remise à zéro des disponibilités sont enregistrés dans la même transaction.
        $this->entityManager->flush();

        return $dateChanged;
    }
}
