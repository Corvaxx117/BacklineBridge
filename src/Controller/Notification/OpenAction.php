<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Entity\User;
use App\Service\Notification\NotificationInbox;
use App\Service\Notification\NotificationTarget;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/notifications/{id}/ouvrir',
    name: 'app_notification_open',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
)]
final class OpenAction extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        #[CurrentUser] User $user,
        NotificationInbox $inbox,
        NotificationTarget $targets,
    ): Response {
        if (!$this->isCsrfTokenValid('notification_open_'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $recipient = $inbox->setRead($user, $id, true);
        $url = $targets->url($recipient->getNotification());
        if (null === $url) {
            $this->addFlash('error', 'Cet élément a été retiré. Son ajout reste dans l’historique.');

            return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirect($url, Response::HTTP_SEE_OTHER);
    }
}
