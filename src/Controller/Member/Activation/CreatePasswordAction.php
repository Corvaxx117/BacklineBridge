<?php

declare(strict_types=1);

namespace App\Controller\Member\Activation;

use App\Entity\MemberProfile;
use App\Dto\Member\ActivationInput;
use App\Form\Member\ActivationType;
use App\Service\Member\AccountActivator;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/premiere-connexion/{publicId}',
        name: 'app_activation_create_password',
        requirements: ['publicId' => '[a-f0-9]{32}'],
        methods: ['GET', 'POST'],
    ),
]
final class CreatePasswordAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(mapping: ['publicId' => 'publicId'])] MemberProfile $profile,
        Request $request,
        AccountActivator $activator,
        Security $security,
        #[Autowire(service: 'limiter.member_activation')] RateLimiterFactory $limiter,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_concert_index');
        }
        if (!$profile->canActivate()) {
            throw $this->createNotFoundException('Cet accès d’activation est indisponible.');
        }
        $input = new ActivationInput();
        $form = $this->createForm(ActivationType::class, $input, [
            'csrf_token_id' => 'activation_' . $profile->getPublicId(),
        ])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted()) {
            $limit = $limiter->create((string) $request->getClientIp())->consume();
            if (!$limit->isAccepted()) {
                $form->addError(
                    new FormError('Trop de tentatives. Réessaie dans quelques minutes.'),
                );
                $status = 429;
            } elseif ($form->isValid()) {
                try {
                    $activator->activate($profile, $input->code, $input->password);
                    $security->login($profile->getUser(), 'form_login', 'main');
                    $this->addFlash('success', 'Ton compte est activé. Bienvenue !');
                    return $this->redirectToRoute(
                        'app_profile_show',
                        ['publicId' => $profile->getPublicId()],
                        303,
                    );
                } catch (\DomainException $error) {
                    $form->addError(new FormError($error->getMessage()));
                } catch (OptimisticLockException) {
                    $form->addError(
                        new FormError(
                            'Ce code vient d’être utilisé. Connecte-toi avec ton mot de passe.',
                        ),
                    );
                    $status = 409;
                }
            }
        }
        return $this->render(
            'member/activate.html.twig',
            ['profile' => $profile, 'form' => $form->createView()],
            new Response(status: $status),
        );
    }
}
