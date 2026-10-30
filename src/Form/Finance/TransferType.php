<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\TransferInput;
use App\Enum\TreasuryAccountKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TransferInput> */
final class TransferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        foreach (['source' => 'Depuis', 'destination' => 'Vers'] as $name => $label) {
            $b->add($name, EnumType::class, [
                'class' => TreasuryAccountKind::class,
                'choice_label' => static fn ($v): string => $v->label(),
                'label' => $label,
            ]);
        }
        $b->add('amount', TextType::class, [
            'label' => 'Montant en euros',
            'empty_data' => '',
            'attr' => ['inputmode' => 'decimal'],
        ])
            ->add('label', TextType::class, ['label' => 'Libellé', 'empty_data' => ''])
            ->add('occurredOn', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('revision', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => TransferInput::class,
            'csrf_token_id' => 'treasury_transfer',
        ]);
    }
}
