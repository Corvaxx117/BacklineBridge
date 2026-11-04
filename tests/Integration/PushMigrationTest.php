<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261104221420;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PushMigrationTest extends TestCase
{
    public function testDeviceAndQueueMigrationPreservesHistoryAndCascadesRevocation(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261104221420.php';
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $db->executeStatement('PRAGMA foreign_keys = ON');
            foreach (['app_user', 'notification_recipient'] as $table) {
                $db->executeStatement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
                $db->insert($table, ['id' => 1]);
            }
            $migration = new Version20261104221420($db, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
            $db->insert('push_subscription', [
                'id' => 1, 'user_id' => 1, 'endpoint_hash' => str_repeat('a', 64), 'device_hash' => str_repeat('b', 64),
                'endpoint' => 'https://web.push.apple.com/Q/test', 'public_key' => 'test', 'auth_token' => 'test',
                'vapid_public_key' => 'test', 'created_at' => '2026-11-04 12:00:00',
            ]);
            $row = [
                'subscription_id' => 1, 'recipient_id' => 1, 'state' => 'pending', 'attempts' => 0,
                'next_attempt_at' => '2026-11-04 12:00:00', 'created_at' => '2026-11-04 12:00:00',
            ];
            $db->insert('push_delivery', $row);
            try {
                $db->insert('push_delivery', $row);
                self::fail('Un seul envoi par action et appareil.');
            } catch (UniqueConstraintViolationException) {
                self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM push_delivery'));
            }
            $db->executeStatement('DELETE FROM push_subscription');
            self::assertSame(0, $db->fetchOne('SELECT COUNT(*) FROM push_delivery'));
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM notification_recipient'));
            self::assertSame([], $db->fetchAllAssociative('PRAGMA foreign_key_check'));
            $down = new Version20261104221420($db, new NullLogger());
            $down->down(new Schema());
            foreach ($down->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
            self::assertSame(1, $db->fetchOne('SELECT COUNT(*) FROM app_user'));
        } finally {
            $db->close();
        }
    }
}
