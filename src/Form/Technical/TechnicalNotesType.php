<?php

declare(strict_types=1);

namespace App\Form\Technical;

use App\Dto\Technical\TechnicalNotesInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TechnicalNotesInput> */
final class TechnicalNotesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class)
            ->add('notices', TextareaType::class, [
                'label' => 'Notes techniques',
                'required' => false,
                'empty_data' => '',
                'help' => 'Sépare les notes par une ligne vide. Tu peux conserver des retours à la ligne dans chaque note.',
                'attr' => ['rows' => 10, 'maxlength' => 12000],
            ])
            ->add('style', TextType::class, [
                'label' => 'Style musical',
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => 150],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Présentation du live',
                'required' => false,
                'empty_data' => '',
                'attr' => ['rows' => 3, 'maxlength' => 300],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TechnicalNotesInput::class]);
    }
}
