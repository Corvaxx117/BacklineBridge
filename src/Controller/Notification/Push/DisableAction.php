<?php

declare(strict_types=1);

namespace App\Controller\Notification\Push;

use App\Entity\User;
use App\Service\Notification\Push\PushDeviceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/notifications/appareil', name: 'app_notification_push_disable', methods: ['DELETE'])]
#[IsCsrfTokenValid('notification_push', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
final class DisableAction extends AbstractController
{
    public function __invoke(Request $request, #[CurrentUser] User $user, PushDeviceManager $devices): JsonResponse
    {
        $devices->disable($user, $request->cookies->getString(PushDeviceManager::COOKIE));

        return $this->json(['enabled' => false]);
    }
}
