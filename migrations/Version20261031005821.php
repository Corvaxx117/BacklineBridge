<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031005821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contacts des concerts et bibliothèque privée de PDF et de liens du groupe.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform,
            'Migration prévue pour SQLite.',
        );
        $this->addSql(<<<'SQL'
            CREATE TABLE band_document (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                created_at DATETIME NOT NULL,
                created_by_name VARCHAR(100) NOT NULL,
                title VARCHAR(160) NOT NULL,
                category VARCHAR(30) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                external_url VARCHAR(2048) DEFAULT NULL,
                storage_key VARCHAR(36) DEFAULT NULL,
                original_filename VARCHAR(255) DEFAULT NULL,
                size_bytes INTEGER NOT NULL,
                band_id INTEGER NOT NULL,
                concert_id INTEGER DEFAULT NULL,
                created_by_id INTEGER DEFAULT NULL,
                CONSTRAINT FK_1822CB7C49ABEB17 FOREIGN KEY (band_id)
                    REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_1822CB7C83C97B2E FOREIGN KEY (concert_id)
                    REFERENCES concert (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_1822CB7CB03A8386 FOREIGN KEY (created_by_id)
                    REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_1822CB7C49ABEB17 ON band_document (band_id)');
        $this->addSql('CREATE INDEX IDX_1822CB7C83C97B2E ON band_document (concert_id)');
        $this->addSql('CREATE INDEX IDX_1822CB7CB03A8386 ON band_document (created_by_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE concert_contact (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                revision INTEGER DEFAULT 1 NOT NULL,
                name VARCHAR(120) NOT NULL,
                role VARCHAR(30) NOT NULL,
                email VARCHAR(180) DEFAULT NULL,
                phone VARCHAR(40) DEFAULT NULL,
                notes VARCHAR(500) DEFAULT NULL,
                concert_id INTEGER NOT NULL,
                CONSTRAINT FK_D6785A9E83C97B2E FOREIGN KEY (concert_id)
                    REFERENCES concert (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_D6785A9E83C97B2E ON concert_contact (concert_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE band_document');
        $this->addSql('DROP TABLE concert_contact');
    }
}
