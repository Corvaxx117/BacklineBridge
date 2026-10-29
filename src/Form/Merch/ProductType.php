<?php

declare(strict_types=1);

namespace App\Form\Merch;

use App\Dto\Merch\ProductInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{TextType, ChoiceType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('name', TextType::class, [
            'label' => 'Nom du produit',
            'empty_data' => '',
            'attr' => ['maxlength' => 160],
        ])
            ->add('category', ChoiceType::class, [
                'label' => 'Format',
                'choices' => array_combine(ProductInput::CATEGORIES, ProductInput::CATEGORIES),
            ])
            ->add('variants', TextType::class, [
                'label' => 'Tailles ou variantes (facultatif)',
                'required' => false,
                'empty_data' => '',
                'help' =>
                    'Sépare les tailles par des virgules, par exemple S, M, L, XL. Laisse vide pour un stock unique.',
            ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['data_class' => ProductInput::class, 'csrf_token_id' => 'merch_product']);
    }
}
