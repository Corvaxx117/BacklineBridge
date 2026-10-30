<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\Concert\ReferenceImportInput;
use App\Service\Concert\ReferenceImportPreview;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ReferenceImportInput> */
final class ReferenceImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($options['changed_sections'] as $key) {
            $choices[ReferenceImportPreview::LABELS[$key]] = $key;
        }
        $builder
            ->add('sections', ChoiceType::class, [
                'choices' => $choices,
                'expanded' => true,
                'multiple' => true,
                'label' => 'Sections à récupérer',
            ])
            ->add('fingerprint', HiddenType::class)
            ->add('confirmReplacement', CheckboxType::class, [
                'label' => 'Je confirme le remplacement des sections choisies par la référence actuelle.',
                'required' => false,
                'false_values' => [null, '0', false],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('changed_sections');
        $resolver->setAllowedTypes('changed_sections', 'array');
        $resolver->setDefaults([
            'data_class' => ReferenceImportInput::class,
            'csrf_token_id' => 'concert_reference_import',
            'csrf_message' => 'Le formulaire a expiré. Recharge l’aperçu puis réessaie.',
        ]);
    }
}
