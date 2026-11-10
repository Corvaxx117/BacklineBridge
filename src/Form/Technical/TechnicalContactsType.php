<?php

declare(strict_types=1);

namespace App\Form\Technical;

use App\Dto\Technical\TechnicalContactsInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TechnicalContactsInput> */
final class TechnicalContactsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class)->add('contacts', CollectionType::class, [
            'entry_type' => TechnicalContactType::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'by_reference' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TechnicalContactsInput::class]);
    }
}
