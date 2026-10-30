<?php

declare(strict_types=1);

namespace App\Form\Concert;

use App\Dto\Concert\BandsintownFetchInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<BandsintownFetchInput> */
final class BandsintownFetchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('period', ChoiceType::class, [
            'label' => 'Quelles dates récupérer ?',
            'choices' => [
                'Toutes les dates' => 'all',
                'Dates à venir' => 'upcoming',
                'Dates passées' => 'past',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BandsintownFetchInput::class,
            'csrf_token_id' => 'bandsintown_fetch',
        ]);
    }
}
