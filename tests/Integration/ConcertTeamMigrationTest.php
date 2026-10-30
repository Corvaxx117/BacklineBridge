<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261028133311;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ConcertTeamMigrationTest extends TestCase
{
    public function testMigrationPreservesExistingConcertsAndTheirGuestDataInBothDirections(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261028133311.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');
        foreach (
            [
                'CREATE TABLE app_user (id INTEGER PRIMARY KEY, display_name TEXT)',
                'CREATE TABLE band (id INTEGER PRIMARY KEY)',
                'CREATE TABLE band_membership (band_id INTEGER, user_id INTEGER, role TEXT)',
                'CREATE TABLE concert (id INTEGER PRIMARY KEY, date DATE NOT NULL, band_id INTEGER NOT NULL REFERENCES band(id))',
                'CREATE TABLE technical_publication (id INTEGER PRIMARY KEY, concert_id INTEGER REFERENCES concert(id), snapshot TEXT)',
                'CREATE TABLE venue_invitation (id INTEGER PRIMARY KEY, publication_id INTEGER REFERENCES technical_publication(id))',
                'CREATE TABLE venue_reply (id INTEGER PRIMARY KEY, invitation_id INTEGER REFERENCES venue_invitation(id), answers TEXT)',
                "INSERT INTO app_user VALUES (1, 'Julien'), (2, 'Marty'), (3, 'Romain'), (4, 'Autre groupe')",
                'INSERT INTO band VALUES (1), (2)',
                "INSERT INTO band_membership VALUES (1, 1, 'manager'), (1, 2, 'manager'), (1, 3, 'booker'), (2, 4, 'manager')",
                "INSERT INTO concert VALUES (10, '2026-12-12', 1), (20, '2026-12-13', 2)",
                "INSERT INTO technical_publication VALUES (11, 10, '{\"frozen\":true}')",
                'INSERT INTO venue_invitation VALUES (12, 11)',
                "INSERT INTO venue_reply VALUES (13, 12, '{\"gear\":\"available\"}')",
            ] as $sql
        ) {
            $connection->executeStatement($sql);
        }
        $tables = [
            'app_user',
            'band',
            'band_membership',
            'concert',
            'technical_publication',
            'venue_invitation',
            'venue_reply',
        ];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $connection->fetchAllAssociative(
                'SELECT * FROM '.$table.' ORDER BY rowid',
            );
        }
        $this->migrate($connection, 'up');
        self::assertSame(
            3,
            (int) $connection->fetchOne('SELECT COUNT(*) FROM concert_availability'),
        );
        self::assertSame(
            [
                [
                    'concert_id' => 10,
                    'user_id' => 1,
                    'status' => 'pending',
                    'concert_date' => '2026-12-12',
                ],
                [
                    'concert_id' => 10,
                    'user_id' => 2,
                    'status' => 'pending',
                    'concert_date' => '2026-12-12',
                ],
                [
                    'concert_id' => 20,
                    'user_id' => 4,
                    'status' => 'pending',
                    'concert_date' => '2026-12-13',
                ],
            ],
            $connection->fetchAllAssociative(
                'SELECT concert_id, user_id, status, concert_date FROM concert_availability ORDER BY concert_id, user_id',
            ),
        );
        self::assertNull(
            $connection->fetchOne('SELECT created_by_name FROM concert WHERE id = 10'),
        );
        foreach ($before as $table => $rows) {
            $columns = implode(', ', array_keys($rows[0]));
            self::assertSame(
                $rows,
                $connection->fetchAllAssociative(
                    'SELECT '.$columns.' FROM '.$table.' ORDER BY rowid',
                ),
            );
        }
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        $connection->executeStatement(
            "UPDATE concert SET created_by_id = 3, created_by_name = 'Romain' WHERE id = 10",
        );
        $connection->executeStatement('DELETE FROM app_user WHERE id = 3');
        self::assertNull($connection->fetchOne('SELECT created_by_id FROM concert WHERE id = 10'));
        self::assertSame(
            'Romain',
            $connection->fetchOne('SELECT created_by_name FROM concert WHERE id = 10'),
        );
        $this->migrate($connection, 'down');
        self::assertSame(
            $before['concert'],
            $connection->fetchAllAssociative('SELECT * FROM concert ORDER BY rowid'),
        );
        foreach (['technical_publication', 'venue_invitation', 'venue_reply'] as $table) {
            self::assertSame(
                $before[$table],
                $connection->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY rowid'),
            );
        }
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        $connection->close();
    }

    private function migrate(Connection $connection, string $direction): void
    {
        $migration = new Version20261028133311($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement(
                $query->getStatement(),
                $query->getParameters(),
                $query->getTypes(),
            );
        }
    }
}
