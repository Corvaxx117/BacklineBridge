<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Service\Money\EuroAmount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ConcertConditionsUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Concert $concert, ConcertConditionsInput $input): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($concert, $input): void {
            $currentDate = $this->entityManager->getConnection()->fetchOne(
                'SELECT date FROM concert WHERE id = ?', [$concert->getId()],
            );
            if (null !== $input->expectedDate && $input->expectedDate !== $currentDate) {
                throw new \DomainException('La date a changé. Recharge les conditions avant de confirmer la présence de l’ingé son.');
            }
            $conditions = $this->entityManager->getRepository(ConcertConditions::class)->findOneBy(['concert' => $concert]);
            if (null !== $conditions) {
                $this->entityManager->refresh($conditions);
            }
            if (null === $input->revision || !hash_equals(ConditionsVersion::fingerprint($conditions), $input->revision)) {
                throw new ConflictHttpException('Les conditions ont changé depuis l’ouverture du formulaire. Conserve tes modifications puis recharge la page.');
            }
            assert(null !== $input->engineerPresence);
            assert(null !== $input->feeBasis);
            assert(null !== $input->accommodation);
            assert(null !== $input->transportCoverage);
            assert(null !== $input->paymentFramework);
            assert(null !== $input->paymentMethod);
            assert(null !== $input->engineerPayment);
            assert(null !== $input->engineerPaymentFramework);
            $conditions ??= new ConcertConditions($concert);
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
                $input->engineerPresence,
                $input->feeBasis,
            );
            $conditions->updateLogistics(
                $this->optional($input->accommodationNotes),
                $this->optional($input->transportNotes),
                $this->optional($input->mealNotes),
            );
            $this->entityManager->persist($conditions);
            $this->entityManager->flush();
        });
    }

    private function optional(?string $text): ?string
    {
        return null === $text || '' === trim($text) ? null : trim($text);
    }
}
