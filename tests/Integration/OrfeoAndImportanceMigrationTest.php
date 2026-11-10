<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261109100000;
use DoctrineMigrations\Version20261109110000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OrfeoAndImportanceMigrationTest extends TestCase
{
    public function testExistingConcertsSurviveMigrationAndRollbackWithSourceUniquenessAndCascade(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261109100000.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261109110000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
            $connection->executeStatement('CREATE TABLE concert (id INTEGER PRIMARY KEY, notes TEXT)');
            $connection->executeStatement("INSERT INTO concert VALUES (1, 'Note conservée'), (2, 'Autre note')");
            $before = $connection->fetchAllAssociative('SELECT * FROM concert ORDER BY id');
            foreach ([Version20261109100000::class, Version20261109110000::class] as $class) {
                $migration = new $class($connection, new NullLogger());
                $migration->up(new Schema());
                foreach ($migration->getSql() as $query) {
                    $connection->executeStatement($query->getStatement());
                }
            }
            self::assertSame([1 => null, 2 => null], $connection->fetchAllKeyValue('SELECT id, importance_level FROM concert ORDER BY id'));
            self::assertSame($before, $connection->fetchAllAssociative('SELECT id, notes FROM concert ORDER BY id'));
            $insert = "INSERT INTO orfeo_concert (project_id, performance_id, spectacle_id, snapshot, contact_links, synced_at, concert_id) VALUES (901, 1001, 301, '{}', '{}', '2026-11-09 12:00:00', ?)";
            $connection->executeStatement($insert, [1]);
            try {
                $connection->executeStatement($insert, [2]);
                self::fail('The same Orfeo performance cannot belong to two concerts.');
            } catch (UniqueConstraintViolationException) {
                self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM orfeo_concert'));
            }
            $connection->executeStatement('DELETE FROM concert WHERE id=1');
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM orfeo_concert'));
            self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
            foreach ([Version20261109110000::class, Version20261109100000::class] as $class) {
                $rollback = new $class($connection, new NullLogger());
                $rollback->down(new Schema());
                foreach ($rollback->getSql() as $query) {
                    $connection->executeStatement($query->getStatement());
                }
            }
            self::assertSame([$before[1]], $connection->fetchAllAssociative('SELECT * FROM concert ORDER BY id'));
        } finally {
            $connection->close();
        }
    }
}
