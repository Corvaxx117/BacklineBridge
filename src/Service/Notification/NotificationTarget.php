<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\BandNotification;
use App\Enum\NotificationType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class NotificationTarget
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function url(BandNotification $notification): ?string
    {
        $concert = $notification->getConcert();
        if (in_array($notification->getType(), [NotificationType::ConcertsImported, NotificationType::OrfeoConcertsImported], true)) {
            return $this->urls->generate('app_concert_index');
        }
        if (NotificationType::ConcertCreated === $notification->getType()) {
            return null === $concert ? null
                : $this->urls->generate('app_concert_show', ['id' => $concert->getId()]);
        }
        $document = $notification->getDocument();
        if (null === $document) {
            return null;
        }
        if (null !== $concert) {
            return $this->urls->generate('app_concert_show', ['id' => $concert->getId()])
                .'#concert-documents';
        }

        return $this->urls->generate('app_document_index', ['id' => $notification->getBand()->getId()])
            .'#document-'.$document->getId();
    }
}
