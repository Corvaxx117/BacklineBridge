<?php

declare(strict_types=1);

namespace App\Controller\Guest;

use App\Service\Venue\GuestAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/invitation/{token}',
    name: 'app_guest_enter',
    requirements: ['token' => '[a-f0-9]{64}'],
    methods: ['GET'],
),]
final class EnterAction extends AbstractController
{
    public function __invoke(Request $request, string $token, GuestAccess $access): Response
    {
        $context = $access->enter($request, $token);

        return $this->redirectToRoute('app_guest_workspace', ['access' => $context], 303);
    }
}
