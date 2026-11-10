<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261109150000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TechnicalIdentityMigrationTest extends TestCase
{
    public function testMigrationAndRollbackPreserveExistingBandDataAndStartWithNoPublicContact(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261109150000.php';
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $db->executeStatement('CREATE TABLE band (id INTEGER PRIMARY KEY, name TEXT)');
            $db->executeStatement("INSERT INTO band VALUES (1, 'Groupe fictif')");
            $before = $db->fetchAllAssociative('SELECT * FROM band');
            $migration = new Version20261109150000($db, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
            self::assertNull($db->fetchOne('SELECT technical_identity FROM band'));
            $rollback = new Version20261109150000($db, new NullLogger());
            $rollback->down(new Schema());
            foreach ($rollback->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
            self::assertSame($before, $db->fetchAllAssociative('SELECT * FROM band'));
        } finally {
            $db->close();
        }
    }
}
