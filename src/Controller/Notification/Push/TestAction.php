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

#[Route('/notifications/appareil/test', name: 'app_notification_push_test', methods: ['POST'])]
#[IsCsrfTokenValid('notification_push', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
final class TestAction extends AbstractController
{
    public function __invoke(Request $request, #[CurrentUser] User $user, PushDeviceManager $devices): JsonResponse
    {
        $devices->test($user, $request->cookies->getString(PushDeviceManager::COOKIE));

        return $this->json(['queued' => true], 202);
    }
}
