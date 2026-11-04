<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\BandNotification;
use App\Entity\Concert;
use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Enum\DocumentCategory;
use App\Enum\NotificationType;
use App\Repository\BandMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Les notifications et leurs destinataires sont enregistrés avec l’action métier. */
final readonly class NotificationRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandMembershipRepository $memberships,
        private ClockInterface $clock,
    ) {
    }

    public function concertCreated(Concert $concert): void
    {
        if (null === $concert->getCreatedBy()) {
            return;
        }
        $this->record(new BandNotification(
            $concert->getBand(),
            $concert->getCreatedBy(),
            NotificationType::ConcertCreated,
            $concert->getVenue().' · '.$concert->getCity().' · '.$concert->getDate()->format('d/m/Y'),
            $this->now(),
            concert: $concert,
        ));
    }

    public function documentAdded(BandDocument $document): void
    {
        if (null === $document->getCreatedBy()) {
            return;
        }
        $concert = $document->getConcert();
        $this->record(new BandNotification(
            $document->getBand(),
            $document->getCreatedBy(),
            DocumentCategory::Roadmap === $document->getCategory()
                ? NotificationType::RoadmapAdded : NotificationType::DocumentAdded,
            $document->getTitle(),
            $this->now(),
            concert: $concert,
            document: $document,
            contextLabel: null === $concert ? null
                : $concert->getVenue().' · '.$concert->getDate()->format('d/m/Y'),
        ));
    }

    public function concertsImported(Band $band, User $actor, int $count): void
    {
        if ($count < 1) {
            return;
        }
        $this->record(new BandNotification(
            $band, $actor, NotificationType::ConcertsImported, 'Import Bandsintown', $this->now(),
            itemCount: $count,
        ));
    }

    private function record(BandNotification $notification): void
    {
        $this->entityManager->persist($notification);
        foreach ($this->memberships->forBand($notification->getBand()) as $membership) {
            $recipient = new NotificationRecipient($notification, $membership->getUser());
            if ($membership->getUser()->getId() === $notification->getActor()?->getId()) {
                // L’auteur garde une trace de son action sans faire monter son compteur.
                $recipient->markRead($notification->getCreatedAt());
            }
            $this->entityManager->persist($recipient);
        }
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
