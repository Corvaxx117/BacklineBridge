<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\CreateConcertInput;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Repository\BandsintownConcertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ConcertUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConcertAvailabilityManager $availability,
        private BandsintownConcertRepository $bandsintown,
    ) {
    }

    /** @return bool Si la date a changé. */
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
            if (!$this->bandsintown->isImported($concert)) {
                $this->availability->resetForNewDate($concert);
            }
            $this->entityManager->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $concert])?->resetEngineerPresence();
        }
        // La version ORM protège aussi une écriture concurrente après la comparaison ci-dessus.
        // Le concert et la remise à zéro des disponibilités sont enregistrés dans la même transaction.
        $this->entityManager->flush();

        return $dateChanged;
    }
}
