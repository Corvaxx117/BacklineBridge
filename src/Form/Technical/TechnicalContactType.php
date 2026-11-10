<?php

declare(strict_types=1);

namespace App\Form\Technical;

use App\Dto\Technical\TechnicalContactInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TechnicalContactInput> */
final class TechnicalContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fields = [
            'name' => ['Nom', TextType::class, 100],
            'role' => ['Rôle', TextType::class, 100],
            'email' => ['E-mail', EmailType::class, 180],
            'phone' => ['Téléphone', TelType::class, 40],
        ];
        foreach ($fields as $field => [$label, $type, $length]) {
            $builder->add($field, $type, [
                'label' => $label,
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => $length],
            ]);
        }
        $builder->add('remove', CheckboxType::class, ['label' => 'Retirer ce contact', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TechnicalContactInput::class]);
    }
}
