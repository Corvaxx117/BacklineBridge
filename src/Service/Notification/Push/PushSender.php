<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\PushDelivery;
use App\Integration\WebPush\PushGateway;
use App\Repository\BandMembershipRepository;
use App\Repository\PushDeliveryRepository;
use App\Service\Notification\NotificationTarget;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class PushSender
{
    public function __construct(
        private EntityManagerInterface $em,
        private PushDeliveryRepository $deliveries,
        private BandMembershipRepository $memberships,
        private PushConfiguration $configuration,
        private PushGateway $gateway,
        private NotificationTarget $targets,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{sent: int, deferred: int, cancelled: int, failed: int} */
    public function run(int $limit = 30, int $seconds = 40): array
    {
        $counts = ['sent' => 0, 'deferred' => 0, 'cancelled' => 0, 'failed' => 0];
        if (!$this->configuration->available()) {
            return $counts;
        }
        $deadline = microtime(true) + $seconds;
        for ($i = 0; $i < $limit && microtime(true) < $deadline; ++$i) {
            $now = $this->now();
            $delivery = $this->deliveries->next($now);
            if (null === $delivery) {
                break;
            }
            $device = $delivery->getSubscription();
            $receipt = $delivery->getRecipient();
            if ($delivery->getAttempts() >= 5) {
                $delivery->finish('failed', $now);
                ++$counts['failed'];
                $this->em->flush();
                $this->em->clear();

                continue;
            }
            if ($device->getVapidPublicKey() !== $this->configuration->publicKey()
                || !$device->getUser()->isActivated()
                || (null !== $receipt && (
                    $receipt->getUser()->getId() !== $device->getUser()->getId()
                    || null !== $receipt->getReadAt()
                    || null === $this->targets->url($receipt->getNotification())
                    || null === $this->memberships->roleFor(
                        $receipt->getUser(), $receipt->getNotification()->getBand(),
                    )
                ))
                || $delivery->getCreatedAt() < $now->modify(null === $receipt ? '-5 minutes' : '-1 day')
            ) {
                $delivery->finish('cancelled', $now);
                ++$counts['cancelled'];
            } else {
                // Un arrêt après cette écriture ne fait pas perdre l’envoi ; le tag reste stable à la reprise.
                $delivery->attempted();
                $this->em->flush();
                $result = $this->gateway->send(
                    $device, $this->payload($delivery), 'bb-'.$delivery->getId(),
                    null === $receipt ? 300 : 86400,
                );
                $now = $this->now();
                if ($result->expired()) {
                    // La suppression annule aussi toutes les autres tentatives de cet appareil.
                    $this->em->remove($device);
                    ++$counts['cancelled'];
                } elseif ($result->success()) {
                    $delivery->finish('sent', $now, $result->status);
                    ++$counts['sent'];
                } elseif ($result->retryable() && $delivery->getAttempts() < 5) {
                    $delay = max($result->retryAfter, 60 * (2 ** ($delivery->getAttempts() - 1)));
                    $delivery->retryAt($now->modify('+'.$delay.' seconds'), $result->status);
                    ++$counts['deferred'];
                } else {
                    $delivery->finish('failed', $now, $result->status);
                    ++$counts['failed'];
                }
            }
            $this->em->flush();
            // Une cascade en base peut invalider les objets du lot précédent.
            $this->em->clear();
        }
        $this->deliveries->prune($this->now()->modify('-7 days'));

        return $counts;
    }

    private function payload(PushDelivery $delivery): string
    {
        $notice = $delivery->getRecipient()?->getNotification();
        $body = null === $notice ? 'Les notifications sont prêtes sur cet appareil.'
            : $notice->getActorName().' '.$notice->getType()->action($notice->getItemCount())
                .' '.$notice->getSubjectLabel();

        return json_encode([
            'title' => null === $notice ? 'BacklineBridge · Test' : $notice->getBand()->getName(),
            'body' => mb_substr($body, 0, 400),
            'url' => '/notifications',
            'tag' => 'bb-'.$delivery->getId(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
