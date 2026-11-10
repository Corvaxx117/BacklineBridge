<?php

declare(strict_types=1);

namespace App\Form\Concert;

use App\Dto\Concert\OrfeoFetchInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<OrfeoFetchInput> */
final class OrfeoFetchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('period', ChoiceType::class, ['label' => 'Quelles dates récupérer ?',
            'choices' => ['À venir' => 'upcoming', 'Passées' => 'past', 'Toutes' => 'all']])
            ->add('eventId', HiddenType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrfeoFetchInput::class, 'csrf_token_id' => 'orfeo_fetch']);
    }
}
