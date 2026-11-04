<?php

declare(strict_types=1);

namespace App\Controller\Notification\Push;

use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\Notification\Push\PushConfiguration;
use App\Service\Notification\Push\PushDeviceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications/appareil', name: 'app_notification_push_status', methods: ['GET'])]
final class StatusAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        PushConfiguration $configuration,
        PushSubscriptionRepository $subscriptions,
        CsrfTokenManagerInterface $csrf,
    ): JsonResponse {
        $available = $configuration->available();
        $device = $subscriptions->device($user, $request->cookies->getString(PushDeviceManager::COOKIE));

        return $this->json([
            'available' => $available,
            'enabled' => $available && null !== $device
                && $device->getVapidPublicKey() === $configuration->publicKey(),
            'publicKey' => $available ? $configuration->publicKey() : null,
            'token' => $csrf->getToken('notification_push')->getValue(),
        ]);
    }
}
