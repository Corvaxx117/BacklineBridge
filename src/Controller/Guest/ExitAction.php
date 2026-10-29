<?php

declare(strict_types=1);

namespace App\Controller\Guest;

use App\Service\Venue\GuestAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/salle/quitter', name: 'app_guest_exit', methods: ['POST'])]
final class ExitAction extends AbstractController
{
    public function __invoke(Request $request, GuestAccess $access): Response
    {
        $context = $access->context($request);
        if (
            !$this->isCsrfTokenValid(
                'guest_exit_' . $context,
                $request->request->getString('_token'),
            )
        ) {
            throw new AccessDeniedHttpException(
                'Le formulaire a expiré. Rechargez cet accès avant de réessayer.',
            );
        }
        $access->leave($request);

        return $this->redirectToRoute('app_login', status: 303);
    }
}
