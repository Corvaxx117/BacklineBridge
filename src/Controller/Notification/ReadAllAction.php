<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Entity\User;
use App\Service\Notification\NotificationInbox;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications/tout-lire', name: 'app_notification_read_all', methods: ['POST'])]
final class ReadAllAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        NotificationInbox $inbox,
    ): Response {
        if (!$this->isCsrfTokenValid('notification_read_all', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $inbox->markAllRead($user);

        return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
    }
}
