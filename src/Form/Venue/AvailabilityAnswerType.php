<?php

declare(strict_types=1);

namespace App\Form\Venue;

use App\Dto\Venue\AvailabilityAnswerInput;
use App\Enum\Availability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{EnumType, TextareaType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AvailabilityAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => Availability::class,
                'choice_label' => static fn(Availability $status): string => $status->label(),
                'expanded' => true,
                'label' => 'Disponibilité',
            ])
            ->add('details', TextareaType::class, [
                'label' => 'Modèle / précisions',
                'required' => false,
                'attr' => [
                    'maxlength' => 600,
                    'rows' => 2,
                    'placeholder' => 'Référence, dimensions, puissance, quantité disponible…',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AvailabilityAnswerInput::class]);
    }
}
