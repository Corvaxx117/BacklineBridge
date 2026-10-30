<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Service\Money\EuroAmount;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConcertConditionsUpdater
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Concert $concert, ConcertConditionsInput $input): void
    {
        assert(null !== $input->accommodation);
        assert(null !== $input->transportCoverage);
        assert(null !== $input->paymentFramework);
        assert(null !== $input->paymentMethod);
        assert(null !== $input->engineerPayment);
        assert(null !== $input->engineerPaymentFramework);
        $conditions =
            $this->entityManager
                ->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $concert]) ?? new ConcertConditions($concert);
        $conditions->update(
            $input->accommodation,
            $input->transportCoverage,
            $input->paymentFramework,
            $input->paymentMethod,
            $input->engineerPayment,
            $input->engineerPaymentFramework,
            EuroAmount::toCents($input->feeAmount),
            EuroAmount::toCents($input->engineerFeeAmount),
            $input->notes,
        );
        $this->entityManager->persist($conditions);
        $this->entityManager->flush();
    }
}
