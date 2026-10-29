<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les comptes, les adhésions et le groupe propriétaire des concerts existants, sans créer de compte par défaut.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $references = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM technical_sheet s LEFT JOIN concert c ON c.patch_sheet_id = s.id WHERE c.id IS NULL',
        );
        $this->abortIf(
            $references > 1,
            'Plusieurs références existent : leur rattachement aux concerts doit être explicité avant cette migration du modèle mono-groupe.',
        );

        $this->addSql(
            'CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL)',
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql(
            'CREATE TABLE band (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(150) NOT NULL, reference_sheet_id INTEGER NOT NULL, CONSTRAINT FK_48DFA2EB53185DDA FOREIGN KEY (reference_sheet_id) REFERENCES technical_sheet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_48DFA2EB53185DDA ON band (reference_sheet_id)');
        $this->addSql(
            'INSERT INTO band (name, reference_sheet_id) SELECT s.band_name, s.id FROM technical_sheet s LEFT JOIN concert c ON c.patch_sheet_id = s.id WHERE c.id IS NULL',
        );
        $this->addSql(
            'CREATE TABLE band_membership (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, role VARCHAR(20) NOT NULL, band_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_7DCB8B4249ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7DCB8B42A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_band_user ON band_membership (band_id, user_id)');
        $this->addSql('CREATE INDEX IDX_7DCB8B4249ABEB17 ON band_membership (band_id)');
        $this->addSql('CREATE INDEX IDX_7DCB8B42A76ED395 ON band_membership (user_id)');
        $this->addSql(
            'CREATE TABLE concert_auth (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, venue VARCHAR(150) NOT NULL, city VARCHAR(120) NOT NULL, patch_sheet_id INTEGER NOT NULL, band_id INTEGER NOT NULL, CONSTRAINT FK_D57C02D287209004 FOREIGN KEY (patch_sheet_id) REFERENCES technical_sheet (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D57C02D249ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'INSERT INTO concert_auth (id, date, venue, city, patch_sheet_id, band_id) SELECT id, date, venue, city, patch_sheet_id, (SELECT id FROM band ORDER BY id LIMIT 1) FROM concert',
        );
        $this->addSql('DROP TABLE concert');
        $this->addSql('ALTER TABLE concert_auth RENAME TO concert');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D57C02D287209004 ON concert (patch_sheet_id)');
        $this->addSql('CREATE INDEX IDX_D57C02D249ABEB17 ON concert (band_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('DROP TABLE band_membership');
        $this->addSql(
            'CREATE TABLE concert_before_auth (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, venue VARCHAR(150) NOT NULL, city VARCHAR(120) NOT NULL, patch_sheet_id INTEGER NOT NULL, CONSTRAINT FK_D57C02D287209004 FOREIGN KEY (patch_sheet_id) REFERENCES technical_sheet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'INSERT INTO concert_before_auth (id, date, venue, city, patch_sheet_id) SELECT id, date, venue, city, patch_sheet_id FROM concert',
        );
        $this->addSql('DROP TABLE concert');
        $this->addSql('ALTER TABLE concert_before_auth RENAME TO concert');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D57C02D287209004 ON concert (patch_sheet_id)');
        $this->addSql('DROP TABLE band');
        $this->addSql('DROP TABLE app_user');
    }
}
