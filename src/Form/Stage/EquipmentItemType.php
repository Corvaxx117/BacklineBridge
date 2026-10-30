<?php

declare(strict_types=1);

namespace App\Form\Stage;

use App\Dto\Stage\EquipmentItemInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<EquipmentItemInput> */
final class EquipmentItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class, ['required' => false])
            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'empty_data' => '',
                'attr' => ['maxlength' => 60],
            ])
            ->add('label', TextType::class, [
                'label' => 'Équipement',
                'empty_data' => '',
                'attr' => ['maxlength' => 100],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => ['min' => 1, 'max' => 100],
            ])
            ->add('specification', TextareaType::class, [
                'label' => 'Préférences / conditions',
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => 500, 'rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EquipmentItemInput::class]);
    }
}
