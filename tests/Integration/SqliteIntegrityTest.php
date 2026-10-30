<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Concert;
use App\Entity\User;
use App\Service\Concert\ConcertCreator;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

final class SqliteIntegrityTest extends DatabaseTestCase
{
    public function testForeignKeysAreEnabledAgainAfterReconnecting(): void
    {
        $connection = $this->entityManager()->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
        $connection->close();
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
    }

    public function testAnOrphanAvailabilityCannotBeInserted(): void
    {
        $connection = $this->entityManager()->getConnection();
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $connection->insert('concert_availability', [
            'status' => 'pending',
            'concert_date' => '2026-12-12',
            'concert_id' => 999999,
            'user_id' => $this->userId,
        ]);
    }

    public function testDeletingTheCreatorPreservesTheirNameAndCascadesAvailability(): void
    {
        $em = $this->entityManager();
        $creator = $em->find(User::class, $this->userId);
        $name = $creator->getDisplayName();
        $concert = static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $this->sheet(),
                new \DateTimeImmutable('2026-12-12'),
                'Salle',
                'Lyon',
                createdBy: $creator,
            );
        $id = $concert->getId();
        $connection = $em->getConnection();
        self::assertSame(
            1,
            (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM concert_availability WHERE concert_id = ?',
                [$id],
            ),
        );
        // Les adhésions n'ont volontairement pas de cascade : les retirer avant de supprimer un compte.
        $connection->delete('band_membership', ['user_id' => $this->userId]);
        $connection->delete('app_user', ['id' => $this->userId]);
        $em->clear();
        $saved = $em->find(Concert::class, $id);
        self::assertNull($saved->getCreatedBy());
        self::assertSame($name, $saved->getCreatedByName());
        self::assertSame(
            0,
            (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM concert_availability WHERE concert_id = ?',
                [$id],
            ),
        );
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
    }
}
