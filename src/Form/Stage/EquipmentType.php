<?php

declare(strict_types=1);

namespace App\Form\Stage;

use App\Dto\Stage\EquipmentInput;
use App\Dto\Stage\EquipmentItemInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CollectionType, HiddenType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EquipmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class)->add('items', CollectionType::class, [
            'entry_type' => EquipmentItemType::class,
            'entry_options' => ['label' => false],
            'prototype_data' => new EquipmentItemInput(),
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EquipmentInput::class,
            'csrf_token_id' => 'equipment',
        ]);
    }
}
