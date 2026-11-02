<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Bandsintown;

use App\Dto\Concert\BandsintownImportInput;
use App\Entity\Band;
use App\Entity\BandsintownConcert;
use App\Entity\User;
use App\Enum\ConcertStatus;
use App\Integration\Bandsintown\BandsintownConfiguration;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Repository\BandMembershipRepository;
use App\Service\Concert\ConcertCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ConcertImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConcertCreator $creator,
        private ImportPreview $previews,
        private BandMembershipRepository $memberships,
        private BandsintownConfiguration $configuration,
        private ValidatorInterface $validator,
    ) {
    }

    /** @param list<BandsintownEvent> $events */
    public function import(
        Band $band,
        User $user,
        array $events,
        BandsintownImportInput $input,
    ): ImportResult {
        if (
            !($this->memberships->roleFor($user, $band)?->canPlanConcerts() ?? false)
            || !$this->configuration->isConfiguredFor($band)
        ) {
            throw new AccessDeniedException('Aucun accès à cet import.');
        }
        if (0 !== count($this->validator->validate($input))) {
            throw new \DomainException('Sélectionne de 1 à 100 concerts.');
        }
        $indexed = [];
        foreach ($events as $event) {
            if ($event->artistId !== $this->configuration->artistId) {
                throw new \DomainException(
                    'L’artiste de cet aperçu ne correspond pas au groupe configuré.',
                );
            }
            $indexed[$event->id] = $event;
        }
        foreach ($input->events as $id) {
            if (!isset($indexed[$id])) {
                throw new \DomainException('La sélection ne correspond pas à cet aperçu.');
            }
        }

        return $this->em->wrapInTransaction(function () use (
            $band,
            $user,
            $indexed,
            $input,
        ): ImportResult {
            $rows = $this->previews->rows($band, array_values($indexed));
            $created = $linked = $skipped = 0;
            $identities = [];
            $occupied = [];
            foreach (array_unique($input->events) as $id) {
                $row = $rows[$id];
                $event = $row['event'];
                $key = ConcertIdentity::key($event->date, $event->venue, $event->city);
                if (
                    !in_array($row['state'], ['new', 'link'], true)
                    || isset($identities[$key])
                    || (null !== $row['concert'] && isset($occupied[$row['concert']->getId()]))
                ) {
                    ++$skipped;
                    continue;
                }
                $concert = $row['concert'];
                if (null === $concert) {
                    $concert = $this->creator->prepare(
                        $band->getReferenceSheet(),
                        new \DateTimeImmutable($event->date, new \DateTimeZone('UTC')),
                        $event->venue,
                        $event->city,
                        ConcertStatus::Validated,
                        createdBy: $user,
                        startTime: $event->localTime,
                    );
                    ++$created;
                } else {
                    $concert->confirm();
                    if (null === $concert->getStartTime()) {
                        $concert->setStartTime($event->localTime);
                    }
                    ++$linked;
                    $occupied[$concert->getId()] = true;
                }
                $this->em->persist(new BandsintownConcert($concert, $event));
                $identities[$key] = true;
            }

            return new ImportResult($created, $linked, $skipped);
        });
    }
}
