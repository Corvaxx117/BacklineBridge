<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConcertConditionsUpdater
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function save(Concert $concert, ConcertConditionsInput $input): void
    {
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
