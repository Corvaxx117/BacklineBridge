<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Orfeo;

use App\Dto\Concert\CreateConcertInput;
use App\Dto\Concert\OrfeoImportInput;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\OrfeoConcert;
use App\Entity\User;
use App\Enum\ConcertStatus;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Integration\Orfeo\OrfeoConfiguration;
use App\Repository\BandMembershipRepository;
use App\Repository\BandsintownConcertRepository;
use App\Repository\OrfeoConcertRepository;
use App\Service\Concert\ConcertCreator;
use App\Service\Concert\ConcertUpdater;
use App\Service\Notification\NotificationRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ConcertImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConcertCreator $creator,
        private ConcertUpdater $updater,
        private ContactImporter $contacts,
        private OrfeoConcertRepository $sources,
        private BandsintownConcertRepository $bandsintown,
        private BandMembershipRepository $memberships,
        private OrfeoConfiguration $configuration,
        private ValidatorInterface $validator,
        private ClockInterface $clock,
        private NotificationRecorder $notifications,
    ) {
    }

    /** @param list<OrfeoEvent> $events
     * @param array<int, int> $versions
     * @param array<int, int> $sourceVersions */
    public function import(Band $band, User $user, array $events, OrfeoImportInput $input, array $versions, array $sourceVersions): ImportResult
    {
        if (!$this->configuration->isConfiguredFor($band)
            || !($this->memberships->roleFor($user, $band)?->canPlanConcerts() ?? false)) {
            throw new AccessDeniedException('Aucun accès à cet import.');
        }
        if (0 !== count($this->validator->validate($input))) {
            throw new \DomainException('Sélectionne de 1 à 100 représentations.');
        }
        $indexed = [];
        foreach ($events as $event) {
            if (!in_array($event->spectacleId, $this->configuration->spectacles(), true)) {
                throw new \DomainException('Le spectacle ne correspond pas au groupe configuré.');
            }
            $indexed[$event->id] = $event;
        }
        foreach ($input->events as $id) {
            if (!isset($indexed[$id]) || $indexed[$id]->cancelled) {
                throw new \DomainException('La sélection ne correspond pas aux dates disponibles de cet aperçu.');
            }
        }

        return $this->em->wrapInTransaction(function () use ($band, $user, $indexed, $input, $versions, $sourceVersions): ImportResult {
            $created = $linked = $updated = $added = $preserved = 0;
            $byEvent = [];
            foreach ($this->sources->identitiesForBand($band) as $row) {
                $byEvent[$row['project_id'].':'.$row['performance_id']] = $row['concert_id'];
            }
            $occupied = [];
            foreach (array_unique($input->events) as $id) {
                $event = $indexed[$id];
                $existingId = $byEvent[$id] ?? null;
                $target = null !== $existingId ? (string) $existingId : ($input->targets[OrfeoImportInput::fieldKey($id)] ?? '');
                if ('new' !== $target && 1 !== preg_match('/^[1-9][0-9]*$/D', $target)) {
                    throw new \DomainException('Choisis un rattachement pour chaque représentation sélectionnée.');
                }
                $concert = 'new' === $target ? null : $this->em->find(Concert::class, (int) $target);
                $source = null;
                if ('new' !== $target) {
                    if (!$concert || $concert->getBand()->getId() !== $band->getId()) {
                        throw new AccessDeniedException('Ce concert n’appartient pas au groupe.');
                    }
                    if (($versions[$concert->getId()] ?? null) !== $concert->getRevision()) {
                        throw new ConflictHttpException('Un concert a changé depuis la préparation de l’aperçu.');
                    }
                    if (isset($occupied[$concert->getId()])) {
                        throw new \DomainException('Deux représentations ne peuvent pas être rattachées au même concert.');
                    }
                    $occupied[$concert->getId()] = true;
                    $source = $this->sources->forConcert($concert);
                    if ($source && ($source->getEvent()->id !== $event->id
                        || ($sourceVersions[$source->getId()] ?? null) !== $source->getRevision())) {
                        throw new ConflictHttpException('Le rattachement Orfeo a changé. Recharge l’aperçu.');
                    }
                }
                if (null === $concert) {
                    $concert = $this->creator->prepare($band->getReferenceSheet(),
                        new \DateTimeImmutable($event->date, new \DateTimeZone('Europe/Paris')),
                        $event->venue, $event->city, $event->status(), createdBy: $user, startTime: $event->localTime);
                    ++$created;
                } elseif ($input->updateExisting) {
                    $details = CreateConcertInput::fromConcert($concert);
                    $details->date = new \DateTimeImmutable($event->date, new \DateTimeZone('Europe/Paris'));
                    $details->venue = $event->venue;
                    $details->city = $event->city;
                    $details->startTime = $event->localTime;
                    $details->status = $this->bandsintown->isImported($concert) && ConcertStatus::Validated === $concert->getStatus()
                        ? ConcertStatus::Validated : $event->status();
                    $this->updater->update($concert, $details);
                    ++$updated;
                }
                if (null === $source) {
                    $source = new OrfeoConcert($concert, $event, $this->clock->now());
                    $this->em->persist($source);
                    if ('new' !== $target) {
                        ++$linked;
                    }
                } else {
                    $source->refresh($event, $this->clock->now());
                }
                if ($input->importContacts) {
                    $counts = $this->contacts->import($source, $event->contacts);
                    $added += $counts['added'];
                    $preserved += $counts['preserved'];
                }
            }
            $this->notifications->orfeoConcertsImported($band, $user, $created + $linked);

            return new ImportResult($created, $linked, $updated, $added, $preserved);
        });
    }
}
