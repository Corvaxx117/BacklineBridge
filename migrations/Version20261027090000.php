<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les fiches techniques et les sources du patch avec un canal positif et unique par fiche.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Ce premier jalon utilise SQLite.',
        );

        $this->addSql(
            'CREATE TABLE technical_sheet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, band_name VARCHAR(150) NOT NULL, document_label VARCHAR(255) NOT NULL, notices CLOB NOT NULL)',
        );
        $this->addSql(
            "CREATE TABLE patch_line (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, sheet_id INTEGER NOT NULL, channel_number INTEGER NOT NULL CHECK (channel_number > 0), source_name VARCHAR(150) NOT NULL, preferred_microphone VARCHAR(120) DEFAULT NULL, stand VARCHAR(80) DEFAULT NULL, notes VARCHAR(2000) DEFAULT NULL, section VARCHAR(20) DEFAULT 'utility' NOT NULL, CONSTRAINT fk_patch_line_sheet FOREIGN KEY (sheet_id) REFERENCES technical_sheet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)",
        );
        $this->addSql('CREATE INDEX idx_patch_line_sheet ON patch_line (sheet_id)');
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_patch_line_sheet_channel ON patch_line (sheet_id, channel_number)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Ce premier jalon utilise SQLite.',
        );

        $this->addSql('DROP TABLE patch_line');
        $this->addSql('DROP TABLE technical_sheet');
    }
}
