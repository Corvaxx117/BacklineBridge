<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Entity\ConcertContact;
use App\Entity\OrfeoConcert;
use App\Enum\ConcertImportance;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Repository\BandDocumentRepository;
use App\Repository\BandsintownConcertRepository;
use App\Repository\PatchLineRepository;
use App\Security\Voter\ConcertVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class ConcertViewBuilder
{
    public function __construct(
        private PatchLineRepository $lines,
        private EntityManagerInterface $entityManager,
        private BandsintownConcertRepository $bandsintown,
        private BandDocumentRepository $documents,
        private GoogleCalendarConfiguration $calendar,
        private Security $security,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(Concert $concert): array
    {
        $bandsintownSource = $this->bandsintown->forConcert($concert);
        $calendarConfigured = $this->calendar->isConfiguredFor($concert->getBand());
        $canViewConditions = $this->security->isGranted(ConcertVoter::CONDITIONS, $concert);

        return [
            'concert' => $concert,
            'importanceLevels' => ConcertImportance::cases(),
            'calendarConfigured' => $calendarConfigured,
            'calendarName' => $this->calendar->name(),
            'calendarTimezone' => $calendarConfigured ? $this->calendar->timezone()->getName() : null,
            'contacts' => $this->entityManager
                ->getRepository(ConcertContact::class)
                ->findBy(['concert' => $concert], ['name' => 'ASC']),
            'documents' => $this->documents->forConcert($concert),
            'bandsintownSource' => $bandsintownSource,
            'orfeoSource' => $this->entityManager->getRepository(OrfeoConcert::class)->findOneBy(['concert' => $concert]),
            'sheet' => $concert->getPatchSheet(),
            'lines' => $this->lines->findForSheet($concert->getPatchSheet()),
            'canViewConditions' => $canViewConditions,
            'conditions' => $canViewConditions
                ? $this->entityManager
                    ->getRepository(ConcertConditions::class)
                    ->findOneBy(['concert' => $concert])
                : null,
        ];
    }
}
