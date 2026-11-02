<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\Concert\CreateConcertInput;
use App\Enum\ConcertStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<CreateConcertInput> */
final class ConcertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['editing']) {
            $builder->add('revision', HiddenType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Recharge le formulaire avant de réessayer.'),
                    new Assert\Regex(
                        pattern: '/^[1-9][0-9]*$/D',
                        message: 'Recharge le formulaire avant de réessayer.',
                    ),
                ],
            ]);
        }

        $builder
            ->add('date', DateType::class, [
                'label' => 'Date du concert',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => 'Indique une date valide.',
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure du concert (facultatif)',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
                'required' => false,
                'invalid_message' => 'Indique une heure valide.',
                'help' => 'Sans horaire renseigné, l’ajout à Google Agenda utilisera 20 h.',
            ])
            ->add('venue', TextType::class, [
                'label' => 'Salle',
                'empty_data' => '',
                'attr' => ['maxlength' => 150, 'placeholder' => 'Nom de la salle ou du festival'],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'empty_data' => '',
                'attr' => ['maxlength' => 120, 'placeholder' => 'Ex. : Marseille'],
            ])
            ->add('status', EnumType::class, [
                'class' => ConcertStatus::class,
                'choice_label' => static fn (ConcertStatus $status): string => $status->label(),
                'label' => 'Statut du concert',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes (facultatif)',
                'required' => false,
                'help' => 'Précisions pratiques visibles par toutes les personnes ayant accès au concert. Réservez les informations confidentielles aux conditions du concert. 2 000 caractères maximum.',
                'attr' => [
                    'maxlength' => 2000,
                    'rows' => 5,
                    'placeholder' => 'Ex. : Accès artistes par la cour, repas sur place à 19 h, départ de l’hébergement avant 10 h.',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateConcertInput::class,
            'editing' => false,
            'csrf_protection' => true,
            'csrf_token_id' => 'concert_create',
            'csrf_message' => 'Le formulaire a expiré. Recharge la page puis réessaie.',
        ]);
        $resolver->setAllowedTypes('editing', 'bool');
    }
}
