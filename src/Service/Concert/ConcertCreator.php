<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Enum\ConcertStatus;
use App\Repository\BandMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConcertCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConcertAvailabilityManager $availability,
        private BandMembershipRepository $memberships,
    ) {}

    public function create(
        TechnicalSheet $reference,
        \DateTimeImmutable $date,
        string $venue,
        string $city,
        ConcertStatus $status = ConcertStatus::Pending,
        ?string $notes = null,
        ?User $createdBy = null,
    ): Concert {
        $concert = $this->prepare($reference, $date, $venue, $city, $status, $notes, $createdBy);
        $this->entityManager->flush();

        return $concert;
    }

    /** Prépare l’agrégat dans l’unité de travail ; l’appelant valide la transaction. */
    public function prepare(
        TechnicalSheet $reference,
        \DateTimeImmutable $date,
        string $venue,
        string $city,
        ConcertStatus $status = ConcertStatus::Pending,
        ?string $notes = null,
        ?User $createdBy = null,
    ): Concert {
        if ($reference->getConcert() !== null) {
            throw new \InvalidArgumentException(
                'Un concert doit partir de la fiche de référence du groupe.',
            );
        }

        $sheet = new TechnicalSheet()
            ->setBandName($reference->getBandName())
            ->setDocumentLabel($reference->getDocumentLabel())
            ->setNotices($reference->getNotices())
            ->setStagePlan($reference->getStagePlan())
            ->setEquipmentRequests($reference->getEquipmentRequests());

        // De nouvelles entités évitent de partager les lignes avec la référence ou un autre concert.
        foreach ($reference->getLines() as $source) {
            if (!$source->isActive()) {
                continue;
            }
            $sheet->addLine(
                new PatchLine()
                    ->setChannelNumber($source->getChannelNumber())
                    ->setSourceName($source->getSourceName())
                    ->setPreferredMicrophone($source->getPreferredMicrophone())
                    ->setStand($source->getStand())
                    ->setNotes($source->getNotes())
                    ->setSection($source->getSection()),
            );
        }

        $band =
            $reference->getBand() ??
            throw new \InvalidArgumentException(
                'La fiche de référence doit appartenir à un groupe.',
            );
        if (
            $createdBy !== null &&
            !($this->memberships->roleFor($createdBy, $band)?->canPlanConcerts() ?? false)
        ) {
            throw new \InvalidArgumentException(
                'Le créateur doit pouvoir organiser les concerts de ce groupe.',
            );
        }
        $concert = new Concert($date, $venue, $city, $sheet, $band, $createdBy);
        $concert->updateDetails($date, $venue, $city, $status, $notes);
        $this->entityManager->persist($concert);
        $this->availability->initialize($concert);
        return $concert;
    }
}
