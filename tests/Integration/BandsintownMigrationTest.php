<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261029022120;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class BandsintownMigrationTest extends TestCase
{
    public function testMigrationPreservesConcertsAndEnforcesSourceUniqueness(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261029022120.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (
            [
                'PRAGMA foreign_keys = ON',
                'CREATE TABLE band (id INTEGER PRIMARY KEY)',
                'CREATE TABLE concert (id INTEGER PRIMARY KEY, band_id INTEGER REFERENCES band(id), notes TEXT)',
                'INSERT INTO band VALUES (1)',
                "INSERT INTO concert VALUES (1, 1, 'Internal note'), (2, 1, 'Another note')",
            ] as $sql
        ) {
            $connection->executeStatement($sql);
        }
        $before = $connection->fetchAllAssociative('SELECT * FROM concert ORDER BY id');
        $up = new Version20261029022120($connection, new NullLogger());
        $up->up(new Schema());
        foreach ($up->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        $connection->executeStatement(
            "INSERT INTO bandsintown_concert (artist_id, event_id, snapshot, imported_at, band_id, concert_id) VALUES ('123', '456', '{}', '2026-10-29 12:00:00', 1, 1)",
        );
        try {
            $connection->executeStatement(
                "INSERT INTO bandsintown_concert (artist_id, event_id, snapshot, imported_at, band_id, concert_id) VALUES ('123', '456', '{}', '2026-10-29 12:00:00', 1, 2)",
            );
            self::fail('The same source must not be imported twice.');
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM bandsintown_concert'));
        }
        $down = new Version20261029022120($connection, new NullLogger());
        $down->down(new Schema());
        foreach ($down->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertSame(
            $before,
            $connection->fetchAllAssociative('SELECT * FROM concert ORDER BY id'),
        );
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        $connection->close();
    }
}
