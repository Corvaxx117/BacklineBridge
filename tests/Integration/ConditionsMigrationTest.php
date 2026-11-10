<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261110090000;
use DoctrineMigrations\Version20261110090100;
use DoctrineMigrations\Version20261110090200;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ConditionsMigrationTest extends TestCase
{
    public function testRemovalAndLogisticsMigrationPreserveConcertsAndExistingConditions(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261110090000.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261110090100.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261110090200.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
            $connection->executeStatement('CREATE TABLE app_user (id INTEGER PRIMARY KEY)');
            $connection->executeStatement('CREATE TABLE concert (id INTEGER PRIMARY KEY, venue TEXT)');
            $connection->executeStatement('CREATE TABLE concert_conditions (id INTEGER PRIMARY KEY, concert_id INTEGER, fee_cents INTEGER, notes TEXT)');
            $connection->executeStatement('CREATE TABLE concert_availability (id INTEGER PRIMARY KEY, concert_id INTEGER, user_id INTEGER)');
            $connection->executeStatement("INSERT INTO concert VALUES (1, 'Salle conservée')");
            $connection->executeStatement("INSERT INTO concert_conditions VALUES (1, 1, 150050, 'Accord conservé')");
            $connection->executeStatement('INSERT INTO concert_availability VALUES (1, 1, 1)');
            foreach ([Version20261110090000::class, Version20261110090100::class, Version20261110090200::class] as $class) {
                $migration = new $class($connection, new NullLogger());
                $migration->up(new Schema());
                foreach ($migration->getSql() as $sql) {
                    $connection->executeStatement($sql->getStatement());
                }
            }
            self::assertFalse($connection->createSchemaManager()->tablesExist(['concert_availability']));
            self::assertSame('unknown', $connection->fetchOne('SELECT fee_basis FROM concert_conditions'));
            self::assertSame('Salle conservée', $connection->fetchOne('SELECT venue FROM concert'));
            self::assertSame(['fee_cents' => 150050, 'notes' => 'Accord conservé', 'meal_notes' => null], $connection->fetchAssociative('SELECT fee_cents, notes, meal_notes FROM concert_conditions'));
            foreach ([Version20261110090200::class, Version20261110090100::class, Version20261110090000::class] as $class) {
                $migration = new $class($connection, new NullLogger());
                $migration->down(new Schema());
                foreach ($migration->getSql() as $sql) {
                    $connection->executeStatement($sql->getStatement());
                }
            }
            self::assertTrue($connection->createSchemaManager()->tablesExist(['concert_availability']));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM concert_availability'));
            self::assertSame(150050, $connection->fetchOne('SELECT fee_cents FROM concert_conditions'));
            self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        } finally {
            $connection->close();
        }
    }
}
