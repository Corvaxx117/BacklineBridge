<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\Concert\AvailabilityInput;
use App\Enum\AvailabilityStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<AvailabilityInput> */
final class ConcertAvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => AvailabilityStatus::class,
                'choice_label' => static fn (AvailabilityStatus $status): string => $status->label(),
                'expanded' => true,
                'label' => 'Ma disponibilité',
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Une précision ? (facultatif)',
                'required' => false,
                'attr' => ['maxlength' => 500, 'rows' => 2],
                'help' => 'Visible par l’équipe du groupe, jamais par la salle.',
            ])
            ->add('expectedDate', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AvailabilityInput::class,
            'csrf_token_id' => 'concert_availability',
            'csrf_message' => 'Le formulaire a expiré. Recharge le concert puis réessaie.',
        ]);
    }
}
