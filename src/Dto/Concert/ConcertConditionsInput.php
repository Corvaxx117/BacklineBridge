<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Entity\ConcertConditions;
use App\Enum\Accommodation;
use App\Enum\EngineerPayment;
use App\Enum\EngineerPresence;
use App\Enum\FeeBasis;
use App\Enum\PaymentFramework;
use App\Enum\PaymentMethod;
use App\Enum\TransportCoverage;
use App\Service\Concert\ConditionsVersion;
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
    public ?EngineerPresence $engineerPresence = EngineerPresence::Unknown;

    public ?string $expectedDate = null;

    #[Assert\NotNull]
    public ?FeeBasis $feeBasis = FeeBasis::Unknown;

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

    #[Assert\NotBlank, Assert\Regex(pattern: '/^[a-f0-9]{64}$/D')]
    public ?string $revision;

    public function __construct()
    {
        $this->revision = ConditionsVersion::fingerprint(null);
    }

    #[Assert\Length(max: 4000), Assert\Regex(pattern: '/\\x00/', match: false)]
    public ?string $accommodationNotes = null;

    #[Assert\Length(max: 4000), Assert\Regex(pattern: '/\\x00/', match: false)]
    public ?string $transportNotes = null;

    #[Assert\Length(max: 4000), Assert\Regex(pattern: '/\\x00/', match: false)]
    public ?string $mealNotes = null;

    public static function fromConditions(
        ?ConcertConditions $conditions,
        ?string $expectedDate = null,
    ): self {
        $input = new self();
        $input->expectedDate = $expectedDate;
        $input->revision = ConditionsVersion::fingerprint($conditions);
        if (null === $conditions) {
            return $input;
        }
        $input->accommodation = $conditions->getAccommodation();
        $input->transportCoverage = $conditions->getTransportCoverage();
        $input->paymentFramework = $conditions->getPaymentFramework();
        $input->paymentMethod = $conditions->getPaymentMethod();
        $input->engineerPresence = $conditions->getEngineerPresence();
        $input->engineerPayment = $conditions->getEngineerPayment();
        $input->engineerPaymentFramework = $conditions->getEngineerPaymentFramework();
        $input->feeAmount = EuroAmount::fromCents($conditions->getFeeCents());
        $input->feeBasis = $conditions->getFeeBasis();
        $input->engineerFeeAmount = EuroAmount::fromCents($conditions->getEngineerFeeCents());
        $input->notes = $conditions->getNotes();
        $input->accommodationNotes = $conditions->getAccommodationNotes();
        $input->transportNotes = $conditions->getTransportNotes();
        $input->mealNotes = $conditions->getMealNotes();

        return $input;
    }
}
