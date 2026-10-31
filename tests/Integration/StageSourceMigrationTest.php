<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261031090000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class StageSourceMigrationTest extends TestCase
{
    public function testExistingSourcesReceiveDistinctKeysWithoutChangingTheirData(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261031090000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE patch_line (id INTEGER PRIMARY KEY, source_name TEXT, channel_number INTEGER)');
        $connection->executeStatement("INSERT INTO patch_line VALUES (1, 'Grosse caisse', 1), (2, 'Clavier gauche', 12)");
        $before = $connection->fetchAllAssociative('SELECT * FROM patch_line ORDER BY id');
        $migration = new Version20261031090000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        $keys = $connection->fetchFirstColumn('SELECT source_key FROM patch_line ORDER BY id');
        self::assertCount(2, array_unique($keys));
        foreach ($keys as $key) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $key);
        }
        self::assertSame($before, $connection->fetchAllAssociative('SELECT id, source_name, channel_number FROM patch_line ORDER BY id'));
        $rollback = new Version20261031090000($connection, new NullLogger());
        $rollback->down(new Schema());
        foreach ($rollback->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM patch_line ORDER BY id'));
        $connection->close();
    }
}
