<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Dto\Notification\PushSubscriptionInput;
use App\Entity\PushDelivery;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final readonly class PushDeviceManager
{
    public const COOKIE = 'bb_push_device';

    public function __construct(
        private EntityManagerInterface $em,
        private PushSubscriptionRepository $subscriptions,
        private PushConfiguration $configuration,
        private ClockInterface $clock,
    ) {
    }

    public function enable(User $user, string $token, PushSubscriptionInput $input): void
    {
        if (!$this->configuration->available()) {
            throw new ServiceUnavailableHttpException(null, 'Les notifications ne sont pas encore configurées.');
        }
        $existing = $this->subscriptions->findOneBy(['endpointHash' => hash('sha256', $input->endpoint)]);
        $device = $this->subscriptions->device($user, $token);
        if (null !== $existing && $existing !== $device) {
            throw new ConflictHttpException('Réactivez les notifications pour rattacher cet appareil à votre compte.');
        }
        if (null !== $device && $device->getEndpoint() === $input->endpoint
            && $device->getPublicKey() === $input->publicKey
            && $device->getAuthToken() === $input->authToken
            && $device->getVapidPublicKey() === $this->configuration->publicKey()
        ) {
            return;
        }
        $this->em->wrapInTransaction(function () use ($device, $user, $token, $input): void {
            if (null !== $device) {
                $this->em->remove($device);
                // Libérer les contraintes uniques et annuler les anciens envois avant le remplacement.
                $this->em->flush();
            }
            $this->em->persist(new PushSubscription(
                $user, hash('sha256', $token), $input->endpoint, $input->publicKey, $input->authToken,
                $this->configuration->publicKey(), $this->now(),
            ));
            $this->em->flush();
        });
    }

    public function disable(User $user, string $token): void
    {
        $device = $this->subscriptions->device($user, $token);
        if (null !== $device) {
            $this->em->remove($device);
            $this->em->flush();
        }
    }

    public function test(User $user, string $token): void
    {
        $device = $this->subscriptions->device($user, $token);
        if (null === $device || !$this->configuration->available()
            || $device->getVapidPublicKey() !== $this->configuration->publicKey()
        ) {
            throw new ConflictHttpException('Activez d’abord les notifications sur cet appareil.');
        }
        $now = $this->now();
        if (null !== $device->getLastTestAt() && $device->getLastTestAt() > $now->modify('-1 minute')) {
            throw new TooManyRequestsHttpException(60, 'Attendez une minute avant un nouveau test.');
        }
        $device->recordTest($now);
        $this->em->persist(new PushDelivery($device, $now));
        $this->em->flush();
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
