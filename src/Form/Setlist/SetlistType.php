<?php

declare(strict_types=1);

namespace App\Form\Setlist;

use App\Dto\Setlist\SetlistInput;
use App\ValueObject\SetlistContent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<SetlistInput> */
final class SetlistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('songs', TextareaType::class, [
                'label' => 'Titres dans l’ordre',
                'required' => false,
                'empty_data' => '',
                'help' => 'Un morceau par ligne. Les lignes vides sont ignorées. Laisse le champ vide pour retirer la setlist.',
                'attr' => [
                    'rows' => 10,
                    'maxlength' => SetlistContent::MAX_TEXT_LENGTH,
                    'placeholder' => "Premier morceau\nDeuxième morceau\nRappel…",
                ],
            ])
            ->add('revision', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SetlistInput::class]);
    }
}
