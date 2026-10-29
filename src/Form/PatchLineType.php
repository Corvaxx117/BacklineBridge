<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PatchLine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PatchLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channelNumber', IntegerType::class, [
                'label' => 'Numéro de canal',
                'attr' => ['min' => 1, 'inputmode' => 'numeric'],
                'invalid_message' => 'Indique un numéro de canal entier.',
            ])
            ->add('sourceName', TextType::class, [
                'label' => 'Source',
                'empty_data' => '',
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. : voix Ju'],
            ])
            ->add('section', ChoiceType::class, [
                'label' => 'Section',
                'choices' => array_flip(PatchLine::SECTION_LABELS),
                'invalid_message' => 'Choisis une section proposée.',
            ])
            ->add('preferredMicrophone', TextType::class, [
                'label' => 'Microphone ou DI souhaité',
                'required' => false,
                'attr' => ['maxlength' => 120],
            ])
            ->add('stand', TextType::class, [
                'label' => 'Pied',
                'required' => false,
                'attr' => ['maxlength' => 80],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['maxlength' => 2000, 'rows' => 4],
                'help' => 'Précise ici une particularité de cette source ou un point à vérifier.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PatchLine::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'patch_line',
            'csrf_message' => 'Le formulaire a expiré. Recharge la page puis réessaie.',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'patch_line';
    }
}
