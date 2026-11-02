<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261102183945;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SetlistMigrationTest extends TestCase
{
    public function testMigrationPreservesDataAndEnforcesSetlistOwnership(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261102183945.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');
        foreach (['app_user', 'technical_sheet'] as $table) {
            $connection->executeStatement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY, name TEXT)');
            $connection->insert($table, ['id' => 1, 'name' => 'Référence conservée']);
            $connection->insert($table, ['id' => 2, 'name' => 'Concert conservé']);
        }
        $migration = new Version20261102183945($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM setlist'));
        self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM technical_sheet'));
        $data = [
            'tracks' => '["Premier morceau"]',
            'updated_at' => '2026-11-02 18:00:00',
            'updated_by_name' => 'Auteur conservé',
            'updated_by_id' => 1,
        ];
        foreach ([1, 2] as $sheetId) {
            $connection->insert('setlist', $data + ['sheet_id' => $sheetId]);
        }
        try {
            $connection->insert('setlist', $data + ['sheet_id' => 1]);
            self::fail('Une fiche ne peut pas avoir deux setlists.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM setlist'));
        }
        $connection->delete('app_user', ['id' => 1]);
        self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM setlist WHERE updated_by_id IS NULL'));
        self::assertSame('Auteur conservé', $connection->fetchOne('SELECT updated_by_name FROM setlist WHERE sheet_id = 1'));
        self::assertSame(1, $connection->fetchOne('SELECT revision FROM setlist WHERE sheet_id = 1'));
        $connection->delete('technical_sheet', ['id' => 2]);
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM setlist'));
        self::assertSame('["Premier morceau"]', $connection->fetchOne('SELECT tracks FROM setlist WHERE sheet_id = 1'));
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
        $rollback = new Version20261102183945($connection, new NullLogger());
        $rollback->down(new Schema());
        foreach ($rollback->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertFalse($connection->createSchemaManager()->tablesExist(['setlist']));
        self::assertSame('Référence conservée', $connection->fetchOne('SELECT name FROM technical_sheet'));
        self::assertSame('Concert conservé', $connection->fetchOne('SELECT name FROM app_user'));
        $connection->close();
    }
}
