<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
final class LoginAction extends AbstractController
{
    public function __invoke(AuthenticationUtils $authentication): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_concert_index');
        }

        return $this->render('security/login.html.twig', [
            'lastUsername' => $authentication->getLastUsername(),
            'error' => $authentication->getLastAuthenticationError(),
        ]);
    }
}
