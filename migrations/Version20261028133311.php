<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028133311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identifie le créateur des nouveaux concerts et suit la disponibilité personnelle des responsables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SQLitePlatform,
            'Cette migration utilise SQLite.',
        );
        // Ajouts en place : aucune reconstruction de concert, référencé par les publications.
        $this->addSql('ALTER TABLE concert ADD COLUMN created_by_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE concert ADD COLUMN created_at DATETIME DEFAULT NULL');
        $this->addSql(
            <<<'SQL'
            ALTER TABLE concert ADD COLUMN created_by_id INTEGER DEFAULT NULL
                REFERENCES app_user (id) ON DELETE SET NULL
            SQL,
        );
        $this->addSql('CREATE INDEX IDX_D57C02D2B03A8386 ON concert (created_by_id)');
        $this->addSql(
            <<<'SQL'
            CREATE TABLE concert_availability (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                status VARCHAR(20) NOT NULL,
                comment VARCHAR(500) DEFAULT NULL,
                answered_at DATETIME DEFAULT NULL,
                concert_date DATE NOT NULL,
                concert_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                CONSTRAINT FK_D6B7D9D83C97B2E FOREIGN KEY (concert_id)
                    REFERENCES concert (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_D6B7D9DA76ED395 FOREIGN KEY (user_id)
                    REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL,
        );
        $this->addSql(
            <<<'SQL'
            CREATE UNIQUE INDEX uniq_availability_concert_user
                ON concert_availability (concert_id, user_id)
            SQL,
        );
        $this->addSql('CREATE INDEX IDX_D6B7D9D83C97B2E ON concert_availability (concert_id)');
        $this->addSql('CREATE INDEX IDX_D6B7D9DA76ED395 ON concert_availability (user_id)');
        // Le créateur historique reste inconnu ; aucune disponibilité n'est déduite du statut.
        $this->addSql(
            <<<'SQL'
            INSERT INTO concert_availability (status, concert_date, concert_id, user_id)
                SELECT 'pending', concert.date, concert.id, membership.user_id
                FROM concert
                JOIN band_membership membership ON membership.band_id = concert.band_id
                WHERE membership.role = 'manager'
            SQL,
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SQLitePlatform,
            'Cette migration utilise SQLite.',
        );
        $this->addSql('DROP TABLE concert_availability');
        $this->addSql('DROP INDEX IDX_D57C02D2B03A8386');
        $this->addSql('ALTER TABLE concert DROP COLUMN created_by_id');
        $this->addSql('ALTER TABLE concert DROP COLUMN created_at');
        $this->addSql('ALTER TABLE concert DROP COLUMN created_by_name');
    }
}
