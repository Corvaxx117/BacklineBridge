<?php

declare(strict_types=1);
namespace App\Tests\Integration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261029011643;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
final class FinanceMigrationTest extends TestCase
{
    public function testExistingBandAndConcertSurviveMigrationWithForeignKeysEnabled(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/Version20261029011643.php';
        $c = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (
            [
                'PRAGMA foreign_keys = ON',
                'CREATE TABLE app_user (id INTEGER PRIMARY KEY)',
                'CREATE TABLE band (id INTEGER PRIMARY KEY, name TEXT NOT NULL)',
                'CREATE TABLE concert (id INTEGER PRIMARY KEY, band_id INTEGER NOT NULL REFERENCES band(id))',
                "INSERT INTO band VALUES (1, 'Test')",
                'INSERT INTO concert VALUES (1, 1)',
            ]
            as $sql
        ) {
            $c->executeStatement($sql);
        }
        foreach (['up', 'down'] as $direction) {
            $migration = new Version20261029011643($c, new NullLogger());
            $migration->$direction(new Schema());
            foreach ($migration->getSql() as $query) {
                $c->executeStatement(
                    $query->getStatement(),
                    $query->getParameters(),
                    $query->getTypes(),
                );
            }
            self::assertSame('Test', $c->fetchOne('SELECT name FROM band'));
            self::assertSame(1, $c->fetchOne('SELECT COUNT(*) FROM concert'));
            self::assertSame([], $c->fetchAllAssociative('PRAGMA foreign_key_check'));
            if ($direction === 'up') {
                self::assertSame('[]', $c->fetchOne('SELECT useful_links FROM band'));
            }
        }
        $c->close();
    }
}
