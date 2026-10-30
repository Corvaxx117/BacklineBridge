<?php

declare(strict_types=1);

namespace App\Form\Venue;

use App\Dto\Venue\InvitationInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<InvitationInput> */
final class InvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Destinataire / équipe',
                'empty_data' => '',
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. : Régie de la salle'],
            ])
            ->add('days', ChoiceType::class, [
                'label' => 'Validité du lien',
                'choices' => ['7 jours' => 7, '30 jours' => 30, '90 jours' => 90],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvitationInput::class,
            'csrf_token_id' => 'venue_invitation',
        ]);
    }
}
