<?php

declare(strict_types=1);

namespace App\Form\Setlist;

use App\Dto\Setlist\ImportSetlistInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<ImportSetlistInput> */
final class ImportSetlistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class)->add('referenceRevision', HiddenType::class);
        if ($options['require_confirmation']) {
            $builder->add('confirmReplacement', CheckboxType::class, [
                'label' => 'Remplacer la setlist de ce concert par la référence affichée.',
                'required' => true,
                'constraints' => [new Assert\IsTrue(message: 'Confirme le remplacement de la setlist de ce concert.')],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ImportSetlistInput::class, 'require_confirmation' => false]);
        $resolver->setAllowedTypes('require_confirmation', 'bool');
    }
}
