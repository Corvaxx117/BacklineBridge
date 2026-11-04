<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261104162357;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class NotificationMigrationTest extends TestCase
{
    public function testMigrationPreservesExistingRowsAndDefinesSafeDeletionAndUniqueRecipients(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261104162357.php';
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $db->executeStatement('PRAGMA foreign_keys = ON');
            foreach (['band', 'app_user', 'concert', 'band_document'] as $table) {
                $db->executeStatement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
                $db->insert($table, ['id' => 1]);
            }
            $db->insert('app_user', ['id' => 2]);
            $migration = new Version20261104162357($db, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM concert'));
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM band_document'));
            $db->insert('band_notification', [
                'id' => 1, 'band_id' => 1, 'actor_id' => 1, 'actor_name' => 'Auteur conservé',
                'concert_id' => 1, 'document_id' => 1, 'type' => 'document_added',
                'subject_label' => 'Document conservé', 'created_at' => '2026-11-04 12:00:00',
            ]);
            $db->insert('notification_recipient', ['id' => 1, 'notification_id' => 1, 'user_id' => 1]);
            $db->insert('notification_recipient', ['id' => 2, 'notification_id' => 1, 'user_id' => 2]);
            try {
                $db->insert('notification_recipient', ['notification_id' => 1, 'user_id' => 2]);
                self::fail('A user must receive an event only once.');
            } catch (UniqueConstraintViolationException) {
                self::assertSame(2, $db->fetchOne('SELECT COUNT(*) FROM notification_recipient'));
            }
            $db->executeStatement('DELETE FROM band_document WHERE id = 1');
            self::assertNull($db->fetchOne('SELECT document_id FROM band_notification'));
            $db->executeStatement('DELETE FROM app_user WHERE id = 1');
            self::assertNull($db->fetchOne('SELECT actor_id FROM band_notification'));
            self::assertSame('Auteur conservé', $db->fetchOne('SELECT actor_name FROM band_notification'));
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM notification_recipient'));
            self::assertSame([], $db->fetchAllAssociative('PRAGMA foreign_key_check'));
            $rollback = new Version20261104162357($db, new NullLogger());
            $rollback->down(new Schema());
            foreach ($rollback->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM concert'));
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM band'));
        } finally {
            $db->close();
        }
    }
}
