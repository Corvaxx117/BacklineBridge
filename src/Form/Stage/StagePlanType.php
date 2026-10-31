<?php

declare(strict_types=1);

namespace App\Form\Stage;

use App\Dto\Stage\StageItemInput;
use App\Dto\Stage\StagePlanInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<StagePlanInput> */
final class StagePlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class)->add('items', CollectionType::class, [
            'entry_type' => StageItemType::class,
            'entry_options' => ['label' => false, 'patch_choices' => $options['patch_choices']],
            'prototype_data' => new StageItemInput(),
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StagePlanInput::class,
            'csrf_token_id' => 'stage_plan',
            'patch_choices' => [],
        ]);
        $resolver->setAllowedTypes('patch_choices', 'array');
    }
}
