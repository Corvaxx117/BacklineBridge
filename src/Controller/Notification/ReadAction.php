<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Entity\User;
use App\Service\Notification\NotificationInbox;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/notifications/{id}/lecture',
    name: 'app_notification_read',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
)]
final class ReadAction extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        #[CurrentUser] User $user,
        NotificationInbox $inbox,
    ): Response {
        if (!$this->isCsrfTokenValid('notification_read_'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $read = match ($request->getPayload()->getString('state')) {
            'read' => true,
            'unread' => false,
            default => throw new BadRequestHttpException('État de lecture invalide.'),
        };
        $inbox->setRead($user, $id, $read);

        return $this->redirectToRoute('app_notification_index', [
            'view' => 'unread' === $request->getPayload()->getString('view') ? 'unread' : 'all',
        ], Response::HTTP_SEE_OTHER);
    }
}
