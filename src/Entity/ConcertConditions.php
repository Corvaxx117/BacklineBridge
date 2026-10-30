<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Accommodation;
use App\Enum\EngineerPayment;
use App\Enum\PaymentFramework;
use App\Enum\PaymentMethod;
use App\Enum\TransportCoverage;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'concert_conditions')]
class ConcertConditions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Concert $concert;

    #[ORM\Column(length: 30, enumType: Accommodation::class, options: ['default' => 'unknown'])]
    private Accommodation $accommodation = Accommodation::Unknown;

    #[ORM\Column(length: 30, enumType: TransportCoverage::class, options: ['default' => 'unknown'])]
    private TransportCoverage $transportCoverage = TransportCoverage::Unknown;

    #[ORM\Column(length: 30, enumType: PaymentFramework::class, options: ['default' => 'unknown'])]
    private PaymentFramework $paymentFramework = PaymentFramework::Unknown;

    #[ORM\Column(length: 30, enumType: PaymentMethod::class, options: ['default' => 'unknown'])]
    private PaymentMethod $paymentMethod = PaymentMethod::Unknown;

    #[ORM\Column(length: 30, enumType: EngineerPayment::class, options: ['default' => 'unknown'])]
    private EngineerPayment $engineerPayment = EngineerPayment::Unknown;

    #[ORM\Column(
        name: 'engineer_payment_framework',
        length: 30,
        enumType: PaymentFramework::class,
        options: ['default' => 'unknown'],
    ),]
    private PaymentFramework $engineerPaymentFramework = PaymentFramework::Unknown;

    public function getEngineerPaymentFramework(): PaymentFramework
    {
        return $this->engineerPaymentFramework;
    }

    #[ORM\Column(name: 'fee_cents', nullable: true)]
    private ?int $feeCents = null;

    #[ORM\Column(name: 'engineer_fee_cents', nullable: true)]
    private ?int $engineerFeeCents = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    public function __construct(Concert $concert)
    {
        $this->concert = $concert;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeeCents(): ?int
    {
        return $this->feeCents;
    }

    public function getEngineerFeeCents(): ?int
    {
        return $this->engineerFeeCents;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getAccommodation(): Accommodation
    {
        return $this->accommodation;
    }

    public function getTransportCoverage(): TransportCoverage
    {
        return $this->transportCoverage;
    }

    public function getPaymentFramework(): PaymentFramework
    {
        return $this->paymentFramework;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getEngineerPayment(): EngineerPayment
    {
        return $this->engineerPayment;
    }

    public function update(
        Accommodation $accommodation,
        TransportCoverage $transportCoverage,
        PaymentFramework $paymentFramework,
        PaymentMethod $paymentMethod,
        EngineerPayment $engineerPayment,
        PaymentFramework $engineerPaymentFramework,
        ?int $feeCents,
        ?int $engineerFeeCents,
        ?string $notes,
    ): void {
        $this->accommodation = $accommodation;
        $this->transportCoverage = $transportCoverage;
        $this->paymentFramework = $paymentFramework;
        $this->paymentMethod = $paymentMethod;
        $this->engineerPayment = $engineerPayment;
        $this->engineerPaymentFramework = $engineerPaymentFramework;
        $this->feeCents = $feeCents;
        $this->engineerFeeCents = $engineerFeeCents;
        $this->notes = $notes;
    }
}
