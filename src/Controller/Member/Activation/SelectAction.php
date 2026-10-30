<?php

declare(strict_types=1);

namespace App\Controller\Member\Activation;

use App\Entity\MemberProfile;
use App\Repository\MemberProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/premiere-connexion', name: 'app_activation_select', methods: ['GET', 'POST'])]
final class SelectAction extends AbstractController
{
    public function __invoke(Request $request, MemberProfileRepository $profiles): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_concert_index');
        }
        $choices = $profiles->activationChoices();
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'activation_select'])
            ->add('profile', ChoiceType::class, [
                'label' => 'Qui es-tu ?',
                'choices' => $choices,
                'choice_label' => static fn (MemberProfile $p): string => $p->getPublicName().
                    ' · '.
                    $p->getFunction(),
                'choice_value' => static fn (?MemberProfile $p): string => $p?->getPublicId() ?? '',
                'placeholder' => 'Choisir mon nom',
            ])
            ->getForm()
            ->handleRequest($request);
        if (
            $form->isSubmitted()
            && $form->isValid()
            && $form->get('profile')->getData() instanceof MemberProfile
        ) {
            return $this->redirectToRoute(
                'app_activation_create_password',
                ['publicId' => $form->get('profile')->getData()->getPublicId()],
                303,
            );
        }

        return $this->render(
            'member/select.html.twig',
            ['form' => [] !== $choices ? $form->createView() : null],
            new Response(status: $form->isSubmitted() ? 422 : 200),
        );
    }
}
