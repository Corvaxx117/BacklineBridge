<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications/compteur', name: 'app_notification_count', methods: ['GET'])]
final class CountAction
{
    public function __invoke(
        #[CurrentUser] User $user,
        NotificationRecipientRepository $recipients,
    ): JsonResponse {
        return new JsonResponse(['unread' => $recipients->unreadCount($user)]);
    }
}
