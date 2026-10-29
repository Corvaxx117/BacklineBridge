<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le statut et les conditions des concerts, ainsi que la corbeille réversible des sources.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('ALTER TABLE patch_line ADD COLUMN active BOOLEAN DEFAULT 1');
        $this->addSql('ALTER TABLE patch_line ADD COLUMN deleted_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_patch_line_sheet_channel');
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_patch_line_sheet_channel ON patch_line (sheet_id, channel_number, active)',
        );
        $this->addSql(
            "ALTER TABLE concert ADD COLUMN status VARCHAR(20) DEFAULT 'pending' NOT NULL",
        );
        $this->addSql(
            "CREATE TABLE concert_conditions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, accommodation VARCHAR(30) DEFAULT 'unknown' NOT NULL, transport_coverage VARCHAR(30) DEFAULT 'unknown' NOT NULL, payment_framework VARCHAR(30) DEFAULT 'unknown' NOT NULL, payment_method VARCHAR(30) DEFAULT 'unknown' NOT NULL, engineer_payment VARCHAR(30) DEFAULT 'unknown' NOT NULL, engineer_payment_framework VARCHAR(30) DEFAULT 'unknown' NOT NULL, fee_cents INTEGER DEFAULT NULL, engineer_fee_cents INTEGER DEFAULT NULL, notes VARCHAR(2000) DEFAULT NULL, concert_id INTEGER NOT NULL, CONSTRAINT FK_9C81302F83C97B2E FOREIGN KEY (concert_id) REFERENCES concert (id) NOT DEFERRABLE INITIALLY IMMEDIATE)",
        );
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_9C81302F83C97B2E ON concert_conditions (concert_id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->abortIf(
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM patch_line WHERE active IS NULL',
            ) > 0,
            'La corbeille contient des sources : le retour arrière est refusé pour éviter de les réactiver ou de les perdre.',
        );
        $this->addSql('DROP TABLE concert_conditions');
        $this->addSql('ALTER TABLE concert DROP COLUMN status');
        $this->addSql('DROP INDEX uniq_patch_line_sheet_channel');
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_patch_line_sheet_channel ON patch_line (sheet_id, channel_number)',
        );
        $this->addSql('ALTER TABLE patch_line DROP COLUMN active');
        $this->addSql('ALTER TABLE patch_line DROP COLUMN deleted_at');
    }
}
