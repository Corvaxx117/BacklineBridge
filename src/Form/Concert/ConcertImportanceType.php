<?php

declare(strict_types=1);

namespace App\Form\Concert;

use App\Dto\Concert\ConcertImportanceInput;
use App\Enum\ConcertImportance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ConcertImportanceInput> */
final class ConcertImportanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class, ['empty_data' => ''])
            ->add('level', EnumType::class, ['class' => ConcertImportance::class, 'label' => 'Enjeu du concert',
                'required' => false, 'placeholder' => 'À évaluer',
                'choice_label' => static fn (ConcertImportance $level): string => $level->value.' / 5 · '.$level->label(),
                'invalid_message' => 'Choisis un niveau de 1 à 5 ou « À évaluer ».'])
            ->add('note', TextareaType::class, ['label' => 'Pourquoi cet enjeu ?', 'required' => false,
                'help' => 'Appréciation interne : public attendu, pros présents, potentiel, conditions… 500 caractères maximum.',
                'attr' => ['rows' => 3, 'maxlength' => 500]])
            ->add('returnSort', HiddenType::class, ['empty_data' => 'date'])
            ->add('returnSearch', HiddenType::class, ['empty_data' => ''])
            ->add('returnTo', HiddenType::class, ['empty_data' => 'list']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ConcertImportanceInput::class, 'csrf_token_id' => 'concert_importance']);
    }
}
