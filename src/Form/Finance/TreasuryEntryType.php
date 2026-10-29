<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\TreasuryEntryInput;
use App\Enum\{TreasuryEntryKind, TreasuryAccountKind};
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{
    ChoiceType,
    DateType,
    EnumType,
    HiddenType,
    TextType,
};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TreasuryEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('kind', EnumType::class, [
            'class' => TreasuryEntryKind::class,
            'choices' => [TreasuryEntryKind::Income, TreasuryEntryKind::Expense],
            'choice_label' => static fn($value): string => $value->label(),
            'label' => 'Type de mouvement',
        ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'empty_data' => '',
                'attr' => ['maxlength' => 160],
            ])
            ->add('amount', TextType::class, [
                'label' => 'Montant en euros',
                'empty_data' => '',
                'attr' => ['inputmode' => 'decimal', 'placeholder' => 'Ex. : 25,00'],
            ])
            ->add('account', EnumType::class, [
                'class' => TreasuryAccountKind::class,
                'choice_label' => static fn($value): string => $value->label(),
                'label' => 'Compte',
            ])
            ->add('category', ChoiceType::class, [
                'choices' => array_combine(
                    TreasuryEntryInput::CATEGORIES,
                    TreasuryEntryInput::CATEGORIES,
                ),
                'label' => 'Catégorie',
            ])
            ->add('occurredOn', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de l’opération',
            ])
            ->add('revision', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => TreasuryEntryInput::class,
            'csrf_token_id' => 'treasury_entry',
        ]);
    }
}
