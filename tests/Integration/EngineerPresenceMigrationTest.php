<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261104040000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class EngineerPresenceMigrationTest extends TestCase
{
    public function testExistingConditionsAndAvailabilityArePreserved(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261104040000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $connection->executeStatement('CREATE TABLE concert_conditions (id INTEGER PRIMARY KEY, concert_id INTEGER, fee_cents INTEGER, notes TEXT)');
            $connection->executeStatement('CREATE TABLE concert_availability (id INTEGER PRIMARY KEY, status TEXT, comment TEXT)');
            $connection->insert('concert_conditions', ['id' => 1, 'concert_id' => 10, 'fee_cents' => 150000, 'notes' => 'À conserver']);
            $connection->insert('concert_availability', ['id' => 1, 'status' => 'available', 'comment' => 'Ancienne réponse']);
            $before = $connection->fetchAllAssociative('SELECT * FROM concert_conditions');
            $availability = $connection->fetchAllAssociative('SELECT * FROM concert_availability');
            $migration = new Version20261104040000($connection, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
            }
            self::assertSame('unknown', $connection->fetchOne('SELECT engineer_presence FROM concert_conditions'));
            self::assertSame($before, $connection->fetchAllAssociative('SELECT id, concert_id, fee_cents, notes FROM concert_conditions'));
            self::assertSame($availability, $connection->fetchAllAssociative('SELECT * FROM concert_availability'));
            $rollback = new Version20261104040000($connection, new NullLogger());
            $rollback->down(new Schema());
            foreach ($rollback->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
            }
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM concert_conditions'));
        } finally {
            $connection->close();
        }
    }
}
