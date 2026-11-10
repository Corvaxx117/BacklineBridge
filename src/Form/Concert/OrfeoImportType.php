<?php

declare(strict_types=1);

namespace App\Form\Concert;

use App\Dto\Concert\OrfeoImportInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<OrfeoImportInput> */
final class OrfeoImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('events', ChoiceType::class, ['label' => 'Dates à récupérer',
            'choices' => $options['event_choices'], 'multiple' => true, 'expanded' => true]);
        $targets = $builder->create('targets', FormType::class, ['data_class' => null, 'label' => false]);
        foreach ($options['target_keys'] as $key) {
            $targets->add($key, ChoiceType::class, ['label' => 'Rattachement dans BB',
                'required' => false, 'placeholder' => 'Choisir un rattachement',
                'choices' => $options['concert_choices']]);
        }
        $builder->add($targets)
            ->add('updateExisting', CheckboxType::class, ['label' => 'Mettre aussi à jour les informations générales des concerts existants',
                'required' => false, 'help' => 'Date, heure, salle, ville et statut présentés dans cet aperçu. Les notes BB, conditions, enjeux et dossiers techniques sont conservés.'])
            ->add('importContacts', CheckboxType::class, ['label' => 'Récupérer les contacts disponibles',
                'required' => false, 'help' => 'Les contacts saisis ou retouchés dans BB sont conservés.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrfeoImportInput::class, 'csrf_token_id' => 'orfeo_import',
            'event_choices' => [], 'concert_choices' => [], 'target_keys' => []]);
        foreach (['event_choices', 'concert_choices', 'target_keys'] as $option) {
            $resolver->setAllowedTypes($option, 'array');
        }
    }
}
