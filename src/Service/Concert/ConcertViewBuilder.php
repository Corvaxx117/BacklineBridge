<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Entity\ConcertContact;
use App\Entity\User;
use App\Form\ConcertAvailabilityType;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Repository\BandDocumentRepository;
use App\Repository\BandsintownConcertRepository;
use App\Repository\PatchLineRepository;
use App\Repository\SetlistRepository;
use App\Security\Voter\ConcertVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;

final readonly class ConcertViewBuilder
{
    public function __construct(
        private PatchLineRepository $lines,
        private SetlistRepository $setlists,
        private EntityManagerInterface $entityManager,
        private ConcertAvailabilityManager $availability,
        private BandsintownConcertRepository $bandsintown,
        private BandDocumentRepository $documents,
        private GoogleCalendarConfiguration $calendar,
        private Security $security,
        private FormFactoryInterface $forms,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(Concert $concert, User $user): array
    {
        $bandsintownSource = $this->bandsintown->forConcert($concert);
        $calendarConfigured = $this->calendar->isConfiguredFor($concert->getBand());
        $canViewConditions = $this->security->isGranted(ConcertVoter::CONDITIONS, $concert);

        return [
            'concert' => $concert,
            'setlist' => $this->setlists->forSheet($concert->getPatchSheet()),
            'referenceSetlist' => $this->setlists->forSheet($concert->getBand()->getReferenceSheet()),
            'calendarConfigured' => $calendarConfigured,
            'calendarName' => $this->calendar->name(),
            'calendarTimezone' => $calendarConfigured ? $this->calendar->timezone()->getName() : null,
            'contacts' => $this->entityManager
                ->getRepository(ConcertContact::class)
                ->findBy(['concert' => $concert], ['name' => 'ASC']),
            'documents' => $this->documents->forConcert($concert),
            'bandsintownSource' => $bandsintownSource,
            'sheet' => $concert->getPatchSheet(),
            'lines' => $this->lines->findForSheet($concert->getPatchSheet()),
            'canViewConditions' => $canViewConditions,
            'availability' => null === $bandsintownSource
                ? $this->availability->roster($concert, $user)
                : null,
            'availabilityForm' => null === $bandsintownSource
                && $this->security->isGranted(ConcertVoter::RESPOND_AVAILABILITY, $concert)
                ? $this->forms->create(
                    ConcertAvailabilityType::class,
                    $this->availability->inputFor($concert, $user),
                )->createView()
                : null,
            'conditions' => $canViewConditions
                ? $this->entityManager
                    ->getRepository(ConcertConditions::class)
                    ->findOneBy(['concert' => $concert])
                : null,
        ];
    }
}
