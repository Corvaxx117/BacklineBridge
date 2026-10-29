<?php

declare(strict_types=1);

namespace App\Controller\Member\Password;

use App\Dto\Member\ChangePasswordInput;
use App\Entity\User;
use App\Form\Member\ChangePasswordType;
use App\Service\Member\PasswordChanger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/mon-compte/mot-de-passe', name: 'app_account_password', methods: ['GET', 'POST'])]
final class ChangeAction extends AbstractController
{
    public function __invoke(
        #[CurrentUser] User $user,
        Request $request,
        PasswordChanger $changer,
        Security $security,
        #[Autowire(service: 'limiter.account_password_change')] RateLimiterFactory $limiter,
    ): Response {
        $form = $this->createForm(ChangePasswordType::class, new ChangePasswordInput());
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        if ($form->isSubmitted()) {
            $limit = $limiter->create((string) $user->getId())->consume();
            if (!$limit->isAccepted()) {
                $form->addError(new FormError('Trop de tentatives. Réessaie dans 15 minutes.'));
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($form->isValid()) {
                $changer->change($user, $form->getData()->password);
                $security->login($user, 'form_login', 'main');
                $this->addFlash('success', 'Ton mot de passe a été modifié.');

                return $this->redirectToRoute(
                    'app_account_password',
                    status: Response::HTTP_SEE_OTHER,
                );
            }
        }

        return $this->render(
            'member/change_password.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(status: $status),
        );
    }
}
