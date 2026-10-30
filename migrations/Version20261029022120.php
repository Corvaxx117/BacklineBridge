<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029022120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Origine et informations publiques des concerts importés depuis Bandsintown.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform,
            'Migration prévue pour SQLite.',
        );
        $this->addSql(
            'CREATE TABLE bandsintown_concert (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, artist_id VARCHAR(30) NOT NULL, event_id VARCHAR(30) NOT NULL, snapshot CLOB NOT NULL, imported_at DATETIME NOT NULL, band_id INTEGER NOT NULL, concert_id INTEGER NOT NULL, CONSTRAINT FK_73E573BD49ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_73E573BD83C97B2E FOREIGN KEY (concert_id) REFERENCES concert (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_73E573BD83C97B2E ON bandsintown_concert (concert_id)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_bit_band_artist_event ON bandsintown_concert (band_id, artist_id, event_id)',
        );
        $this->addSql('CREATE INDEX IDX_73E573BD49ABEB17 ON bandsintown_concert (band_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bandsintown_concert');
    }
}
