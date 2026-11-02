<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261101230000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ConcertTimeMigrationTest extends TestCase
{
    public function testExistingConcertsArePreservedAndOnlyMatchingImportHoursAreRecovered(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261101230000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $connection->executeStatement('CREATE TABLE concert (id INTEGER PRIMARY KEY, date DATE, notes TEXT)');
            $connection->executeStatement('CREATE TABLE bandsintown_concert (id INTEGER PRIMARY KEY, concert_id INTEGER, snapshot CLOB)');
            foreach ([1, 2, 3, 4] as $id) {
                $connection->insert('concert', ['id' => $id, 'date' => '2026-12-12', 'notes' => 'Note conservée']);
            }
            foreach ([[2, '2026-12-12', '21:30'], [3, '2026-12-11', '19:00'], [4, '2026-12-12', '27:00']] as [$id, $date, $hour]) {
                $connection->insert('bandsintown_concert', [
                    'concert_id' => $id, 'snapshot' => json_encode(['date' => $date, 'localTime' => $hour], JSON_THROW_ON_ERROR),
                ]);
            }
            $migration = new Version20261101230000($connection, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
            }
            self::assertSame([1 => null, 2 => '21:30', 3 => null, 4 => null], $connection->fetchAllKeyValue('SELECT id, start_time FROM concert ORDER BY id'));
            self::assertSame(4, $connection->fetchOne("SELECT COUNT(*) FROM concert WHERE notes = 'Note conservée'"));
            $rollback = new Version20261101230000($connection, new NullLogger());
            $rollback->down(new Schema());
            foreach ($rollback->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
            }
            self::assertSame(4, $connection->fetchOne('SELECT COUNT(*) FROM concert'));
        } finally {
            $connection->close();
        }
    }
}
