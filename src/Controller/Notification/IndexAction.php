<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use App\Service\Notification\NotificationTarget;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications', name: 'app_notification_index', methods: ['GET'])]
final class IndexAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        NotificationRecipientRepository $recipients,
        NotificationTarget $targets,
    ): Response {
        $unreadOnly = 'unread' === $request->query->getString('view');
        $unread = $recipients->unreadCount($user);
        $total = $unreadOnly ? $unread : $recipients->countVisible($user);
        $pages = max(1, (int) ceil($total / 20));
        $page = max(1, min($request->query->getInt('page', 1), $pages));
        $items = array_map(
            static fn (NotificationRecipient $receipt): array => [
                'receipt' => $receipt,
                'available' => null !== $targets->url($receipt->getNotification()),
            ],
            $recipients->pageFor($user, $page, $unreadOnly, 20),
        );

        return $this->render('notification/index.html.twig', [
            'items' => $items, 'unread' => $unread, 'unreadOnly' => $unreadOnly,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]);
    }
}
