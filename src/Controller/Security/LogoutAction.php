<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
final class LogoutAction
{
    public function __invoke(): Response
    {
        throw new \LogicException('La déconnexion est interceptée par le pare-feu Symfony.');
    }
}
