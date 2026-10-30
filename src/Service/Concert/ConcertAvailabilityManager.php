<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\AvailabilityInput;
use App\Entity\Concert;
use App\Entity\ConcertAvailability;
use App\Entity\User;
use App\Enum\AvailabilityStatus;
use App\Enum\BandRole;
use App\Repository\BandMembershipRepository;
use App\Repository\ConcertAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ConcertAvailabilityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandMembershipRepository $memberships,
        private ConcertAvailabilityRepository $availabilities,
    ) {
    }

    /** Prépare les réponses dans la transaction de création du concert. */
    public function initialize(Concert $concert): void
    {
        foreach ($this->memberships->managersFor($concert->getBand()) as $membership) {
            $this->entityManager->persist(
                new ConcertAvailability($concert, $membership->getUser()),
            );
        }
    }

    /**
     * @return array{rows: list<array{user: User, status: AvailabilityStatus, comment: ?string, answeredAt: ?\DateTimeImmutable, mine: bool}>, counts: array<string, int>, total: int}
     */
    public function roster(Concert $concert, User $currentUser): array
    {
        $records = [];
        foreach ($this->availabilities->forConcert($concert) as $record) {
            $records[$record->getUser()->getId()] = $record;
        }
        $rows = [];
        $counts = array_fill_keys(array_column(AvailabilityStatus::cases(), 'value'), 0);
        foreach ($this->memberships->managersFor($concert->getBand()) as $membership) {
            $user = $membership->getUser();
            $record = $records[$user->getId()] ?? null;
            $status = $record?->getStatus() ?? AvailabilityStatus::Pending;
            ++$counts[$status->value];
            $rows[] = [
                'user' => $user,
                'status' => $status,
                'comment' => $record?->getComment(),
                'answeredAt' => $record?->getAnsweredAt(),
                'mine' => $user->getId() === $currentUser->getId(),
            ];
        }

        return ['rows' => $rows, 'counts' => $counts, 'total' => count($rows)];
    }

    public function inputFor(Concert $concert, User $user): AvailabilityInput
    {
        $record = $this->availabilities->findOneBy(['concert' => $concert, 'user' => $user]);
        $input = new AvailabilityInput();
        $input->status = $record?->getStatus() ?? AvailabilityStatus::Pending;
        $input->comment = $record?->getComment();
        $input->expectedDate = $concert->getDate()->format('Y-m-d');

        return $input;
    }

    public function respond(Concert $concert, User $user, AvailabilityInput $input): void
    {
        if (BandRole::Manager !== $this->memberships->roleFor($user, $concert->getBand())) {
            throw new AccessDeniedException('Seuls les responsables répondent pour eux-mêmes.');
        }
        if ($input->expectedDate !== $concert->getDate()->format('Y-m-d')) {
            throw new \DomainException('La date a changé. Recharge le concert avant de répondre.');
        }
        $record =
            $this->availabilities->findOneBy(['concert' => $concert, 'user' => $user]) ??
            new ConcertAvailability($concert, $user);
        $status = $input->status ?? throw new \DomainException('Choisis une disponibilité.');
        $record->respond($status, $input->comment);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    /** Les réponses ne valent plus pour la nouvelle date ; le service appelant enregistre le tout. */
    public function resetForNewDate(Concert $concert): void
    {
        foreach ($this->availabilities->forConcert($concert) as $record) {
            $record->reset();
        }
    }
}
