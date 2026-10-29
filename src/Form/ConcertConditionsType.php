<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\Concert\ConcertConditionsInput;
use App\Enum\Accommodation;
use App\Enum\EngineerPayment;
use App\Enum\PaymentFramework;
use App\Enum\PaymentMethod;
use App\Enum\TransportCoverage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ConcertConditionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (
            [
                'accommodation' => [Accommodation::class, 'Hébergement'],
                'transportCoverage' => [TransportCoverage::class, 'Frais de transport'],
                'paymentFramework' => [PaymentFramework::class, 'Cadre de rémunération'],
                'paymentMethod' => [PaymentMethod::class, 'Moyen de paiement'],
                'engineerPayment' => [EngineerPayment::class, 'Rémunération de l’ingé son'],
                'engineerPaymentFramework' => [
                    PaymentFramework::class,
                    'Cadre de rémunération de l’ingé son',
                ],
            ]
            as $name => [$class, $label]
        ) {
            $builder->add($name, EnumType::class, [
                'class' => $class,
                'label' => $label,
                'choice_label' => static fn($choice): string => $choice->label(),
            ]);
        }
        foreach (
            [
                'feeAmount' => 'Montant convenu pour le concert (EUR)',
                'engineerFeeAmount' => 'Montant pour l’ingé son (EUR)',
            ]
            as $name => $label
        ) {
            $builder->add($name, TextType::class, [
                'label' => $label,
                'required' => false,
                'attr' => [
                    'inputmode' => 'decimal',
                    'placeholder' => 'Ex. : 1500,50',
                    'maxlength' => 10,
                ],
            ]);
        }
        $builder->add('notes', TextareaType::class, [
            'label' => 'Précisions et points à confirmer',
            'required' => false,
            'attr' => ['maxlength' => 2000, 'rows' => 4],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConcertConditionsInput::class,
            'csrf_token_id' => 'concert_conditions',
            'csrf_message' => 'Le formulaire a expiré. Recharge la page puis réessaie.',
        ]);
    }
}
