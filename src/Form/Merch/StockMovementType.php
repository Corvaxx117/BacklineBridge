<?php

declare(strict_types=1);

namespace App\Form\Merch;

use App\Dto\Merch\StockMovementInput;
use App\Enum\{StockMovementKind, TreasuryAccountKind};
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{EnumType, HiddenType, IntegerType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class StockMovementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('kind', EnumType::class, [
            'class' => StockMovementKind::class,
            'choices' => [
                StockMovementKind::Sale,
                StockMovementKind::Restock,
                StockMovementKind::Count,
                StockMovementKind::Gift,
            ],
            'choice_label' => static fn($v): string => $v->label(),
            'label' => 'Opération',
            'attr' => ['data-stock-action' => ''],
        ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => ['min' => 0, 'max' => 1000000],
                'help' =>
                    'Pour un inventaire, indique le nouveau total. Pour les autres opérations, indique la quantité ajoutée ou retirée.',
            ])
            ->add('reason', TextType::class, [
                'label' => 'Précision / motif',
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 250,
                    'placeholder' => 'Ex. : ventes du concert, réassort, comptage…',
                ],
            ])
            ->add('amount', TextType::class, [
                'required' => false,
                'label' => 'Montant total encaissé en euros',
                'attr' => ['inputmode' => 'decimal'],
                'help' => 'Le montant total de la vente, après toute remise.',
            ])
            ->add('account', EnumType::class, [
                'required' => false,
                'class' => TreasuryAccountKind::class,
                'choice_label' => static fn($v): string => $v->label(),
                'label' => 'Compte qui reçoit la vente',
            ])
            ->add('revision', HiddenType::class)
            ->add('treasuryRevision', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => StockMovementInput::class,
            'csrf_token_id' => 'stock_movement',
        ]);
    }
}
