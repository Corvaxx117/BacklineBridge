<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les concerts, chacun lié à sa propre copie de fiche technique, sans modifier les sources existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Ce jalon utilise SQLite.',
        );

        $this->addSql(
            'CREATE TABLE concert (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, patch_sheet_id INTEGER NOT NULL, date DATE NOT NULL, venue VARCHAR(150) NOT NULL, city VARCHAR(120) NOT NULL, CONSTRAINT FK_concert_patch_sheet FOREIGN KEY (patch_sheet_id) REFERENCES technical_sheet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D57C02D287209004 ON concert (patch_sheet_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Ce jalon utilise SQLite.',
        );

        // Supprimer aussi les copies évite de les confondre avec des références après le retour arrière.
        $this->addSql(
            'CREATE TEMPORARY TABLE backlinebridge_concert_sheets AS SELECT patch_sheet_id AS id FROM concert',
        );
        $this->addSql('DELETE FROM concert');
        $this->addSql(
            'DELETE FROM patch_line WHERE sheet_id IN (SELECT id FROM backlinebridge_concert_sheets)',
        );
        $this->addSql(
            'DELETE FROM technical_sheet WHERE id IN (SELECT id FROM backlinebridge_concert_sheets)',
        );
        $this->addSql('DROP TABLE concert');
        $this->addSql('DROP TABLE backlinebridge_concert_sheets');
    }
}
