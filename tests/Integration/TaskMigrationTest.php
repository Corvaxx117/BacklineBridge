<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261031120000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TaskMigrationTest extends TestCase
{
    public function testMigrationPreservesExistingDataAndEnforcesForeignKeys(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261031120000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');
        foreach (['band', 'member_profile', 'app_user', 'concert'] as $table) {
            $connection->executeStatement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY, name TEXT)');
            $connection->insert($table, ['id' => 1, 'name' => 'Donnée à conserver']);
        }
        $migration = new Version20261031120000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertSame('Donnée à conserver', $connection->fetchOne('SELECT name FROM concert'));
        $connection->insert('band_task', [
            'title' => 'Mission indépendante', 'band_id' => 1, 'assignee_id' => 1,
            'created_by_id' => 1, 'created_by_name' => 'Auteur', 'status' => 'todo',
            'created_at' => '2026-10-31 12:00:00', 'updated_at' => '2026-10-31 12:00:00',
        ]);
        $connection->executeStatement('DELETE FROM member_profile WHERE id = 1');
        $connection->executeStatement('DELETE FROM app_user WHERE id = 1');
        $connection->executeStatement('DELETE FROM concert WHERE id = 1');
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM band_task'));
        self::assertNull($connection->fetchOne('SELECT assignee_id FROM band_task'));
        self::assertNull($connection->fetchOne('SELECT created_by_id FROM band_task'));
        self::assertSame('Auteur', $connection->fetchOne('SELECT created_by_name FROM band_task'));
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        $rollback = new Version20261031120000($connection, new NullLogger());
        $rollback->down(new Schema());
        foreach ($rollback->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertSame('Donnée à conserver', $connection->fetchOne('SELECT name FROM band'));
        $connection->close();
    }
}
