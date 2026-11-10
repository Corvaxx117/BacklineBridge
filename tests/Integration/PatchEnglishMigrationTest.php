<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261109130000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PatchEnglishMigrationTest extends TestCase
{
    public function testOnlyKnownAveeManaLabelsChangeWhileKeysAndPublishedCopiesRemainUntouched(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261109130000.php';
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $db->executeStatement('CREATE TABLE technical_sheet (id INTEGER PRIMARY KEY, band_name TEXT)');
            $db->executeStatement('CREATE TABLE patch_line (id INTEGER PRIMARY KEY, sheet_id INTEGER, source_key TEXT, channel_number INTEGER, source_name TEXT, preferred_microphone TEXT, notes TEXT)');
            $db->executeStatement('CREATE TABLE technical_publication (snapshot TEXT)');
            $db->executeStatement("INSERT INTO technical_sheet VALUES (1, 'AVEE MANA'), (2, 'Autre groupe')");
            $db->executeStatement("INSERT INTO patch_line VALUES (1, 1, 'stable', 19, 'CLIC', 'BSS', 'MICRO PERSO'), (2, 1, 'custom', 23, 'VOIX INVITEE', 'Custom', 'MICRO PERSO avec adaptateur'), (3, 2, 'other', 19, 'CLIC', 'BSS', 'MICRO PERSO')");
            $db->executeStatement("INSERT INTO technical_publication VALUES ('published CLIC MICRO PERSO')");
            $migration = new Version20261109130000($db, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
            }
            self::assertSame(['source_key' => 'stable', 'channel_number' => 19, 'source_name' => 'CLICK', 'preferred_microphone' => 'BSS', 'notes' => 'OWN MIC'],
                $db->fetchAssociative('SELECT source_key, channel_number, source_name, preferred_microphone, notes FROM patch_line WHERE id=1'));
            self::assertSame('MICRO PERSO avec adaptateur', $db->fetchOne('SELECT notes FROM patch_line WHERE id=2'));
            self::assertSame('CLIC', $db->fetchOne('SELECT source_name FROM patch_line WHERE id=3'));
            self::assertSame('published CLIC MICRO PERSO', $db->fetchOne('SELECT snapshot FROM technical_publication'));
        } finally {
            $db->close();
        }
    }
}
