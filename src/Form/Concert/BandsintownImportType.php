<?php

declare(strict_types=1);

namespace App\Form\Concert;

use App\Dto\Concert\BandsintownImportInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<BandsintownImportInput> */
final class BandsintownImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('events', ChoiceType::class, [
                'label' => 'Concerts à importer',
                'choices' => $options['event_choices'],
                'multiple' => true,
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BandsintownImportInput::class,
            'csrf_token_id' => 'bandsintown_import',
            'event_choices' => [],
        ]);
        $resolver->setAllowedTypes('event_choices', 'array');
    }
}
