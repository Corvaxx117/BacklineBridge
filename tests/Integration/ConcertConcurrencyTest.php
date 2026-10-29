<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dto\Concert\CreateConcertInput;
use App\Entity\{Concert, ConcertAvailability};
use App\Enum\AvailabilityStatus;
use App\Service\Concert\ConcertUpdater;
use App\Tests\VenueTestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\{EntityManager, OptimisticLockException};

final class ConcertConcurrencyTest extends VenueTestCase
{
    public function testARaceAfterLoadingTheFormStillRollsBackTheWholeLosingWrite(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $em = $this->entityManager();
        $connection = $em->getConnection();
        $record = $em->getRepository(ConcertAvailability::class)->findOneBy(['concert' => $id]);
        $record->respond(AvailabilityStatus::Available, 'Confirmation à conserver.');
        $em->flush();

        $otherConnection = DriverManager::getConnection(
            $connection->getParams(),
            $connection->getConfiguration(),
        );
        $otherEm = new EntityManager($otherConnection, $em->getConfiguration());
        try {
            $otherConcert = $otherEm->find(Concert::class, $id);
            $otherConcert->updateDetails(
                $otherConcert->getDate(),
                'Nouvelle salle',
                'Paris',
                $otherConcert->getStatus(),
                'Notes récentes',
            );
            $otherEm->flush();

            // L'EntityManager initial n'a pas encore vu cette écriture : seule la condition SQL de version protège la course.
            $input = CreateConcertInput::fromConcert($concert);
            $input->date = new \DateTimeImmutable('2026-12-20');
            try {
                static::getContainer()->get(ConcertUpdater::class)->update($concert, $input);
                self::fail('Une écriture concurrente doit être refusée.');
            } catch (OptimisticLockException) {
                self::assertSame(
                    'Nouvelle salle',
                    $connection->fetchOne('SELECT venue FROM concert WHERE id = ?', [$id]),
                );
                self::assertSame(
                    '2026-12-12',
                    $connection->fetchOne('SELECT date FROM concert WHERE id = ?', [$id]),
                );
                self::assertSame(
                    'Notes récentes',
                    $connection->fetchOne('SELECT notes FROM concert WHERE id = ?', [$id]),
                );
                self::assertSame(
                    'available',
                    $connection->fetchOne(
                        'SELECT status FROM concert_availability WHERE concert_id = ?',
                        [$id],
                    ),
                );
                self::assertSame(
                    'Confirmation à conserver.',
                    $connection->fetchOne(
                        'SELECT comment FROM concert_availability WHERE concert_id = ?',
                        [$id],
                    ),
                );
            }
        } finally {
            $otherEm->close();
            $otherConnection->close();
        }
    }
}
