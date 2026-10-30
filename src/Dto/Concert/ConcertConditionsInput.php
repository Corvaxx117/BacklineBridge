<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Entity\ConcertConditions;
use App\Enum\Accommodation;
use App\Enum\EngineerPayment;
use App\Enum\PaymentFramework;
use App\Enum\PaymentMethod;
use App\Enum\TransportCoverage;
use App\Service\Money\EuroAmount;
use Symfony\Component\Validator\Constraints as Assert;

final class ConcertConditionsInput
{
    #[Assert\NotNull(message: 'Choisissez une option proposée.')]
    public ?Accommodation $accommodation = Accommodation::Unknown;

    #[Assert\NotNull(message: 'Choisissez une option proposée.')]
    public ?TransportCoverage $transportCoverage = TransportCoverage::Unknown;

    #[Assert\NotNull(message: 'Choisissez une option proposée.')]
    public ?PaymentFramework $paymentFramework = PaymentFramework::Unknown;

    #[Assert\NotNull(message: 'Choisissez une option proposée.')]
    public ?PaymentMethod $paymentMethod = PaymentMethod::Unknown;

    #[Assert\NotNull(message: 'Choisissez une option proposée.')]
    public ?EngineerPayment $engineerPayment = EngineerPayment::Unknown;

    #[Assert\NotNull(message: 'Choisissez une option proposée.')]
    public ?PaymentFramework $engineerPaymentFramework = PaymentFramework::Unknown;

    #[Assert\Regex(
        pattern: '/^\d{1,7}(?:[,.]\d{1,2})?$/D',
        message: 'Indique un montant positif avec deux décimales au maximum (ex. : 1500,50).',
    ),]
    public ?string $feeAmount = null;

    #[Assert\Regex(
        pattern: '/^\d{1,7}(?:[,.]\d{1,2})?$/D',
        message: 'Indique un montant positif avec deux décimales au maximum (ex. : 1500,50).',
    ),]
    public ?string $engineerFeeAmount = null;

    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les précisions ne peuvent pas dépasser {{ limit }} caractères.',
    ),]
    public ?string $notes = null;

    public static function fromConditions(?ConcertConditions $conditions): self
    {
        $input = new self();
        if (null === $conditions) {
            return $input;
        }
        $input->accommodation = $conditions->getAccommodation();
        $input->transportCoverage = $conditions->getTransportCoverage();
        $input->paymentFramework = $conditions->getPaymentFramework();
        $input->paymentMethod = $conditions->getPaymentMethod();
        $input->engineerPayment = $conditions->getEngineerPayment();
        $input->engineerPaymentFramework = $conditions->getEngineerPaymentFramework();
        $input->feeAmount = EuroAmount::fromCents($conditions->getFeeCents());
        $input->engineerFeeAmount = EuroAmount::fromCents($conditions->getEngineerFeeCents());
        $input->notes = $conditions->getNotes();

        return $input;
    }
}
