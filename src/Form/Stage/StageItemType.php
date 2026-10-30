<?php

declare(strict_types=1);

namespace App\Form\Stage;

use App\Dto\Stage\StageItemInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<StageItemInput> */
final class StageItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Élément',
                'empty_data' => '',
                'attr' => ['maxlength' => 70],
            ])
            ->add('kind', ChoiceType::class, [
                'label' => 'Type',
                'choices' => StageItemInput::KINDS,
            ])
            ->add('x', NumberType::class, [
                'label' => 'Position horizontale (%)',
                'html5' => true,
                'scale' => 1,
                'attr' => ['min' => 0, 'max' => 100, 'step' => 1],
            ])
            ->add('y', NumberType::class, [
                'label' => 'Position verticale (%)',
                'html5' => true,
                'scale' => 1,
                'attr' => ['min' => 0, 'max' => 100, 'step' => 1],
            ])
            ->add('rotation', NumberType::class, [
                'label' => 'Rotation (°)',
                'html5' => true,
                'scale' => 1,
                'attr' => ['min' => 0, 'max' => 360, 'step' => 1],
            ])
            ->add('scale', NumberType::class, [
                'label' => 'Taille',
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => 0.5, 'max' => 2.5, 'step' => 0.1],
            ])
            ->add('notes', TextType::class, [
                'label' => 'Précision',
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => 250],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => StageItemInput::class]);
    }
}
