<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire le suivi des disponibilités des membres, sans toucher aux concerts ou aux réponses matérielles des salles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE concert_availability');
    }

    public function down(Schema $schema): void
    {
        // La structure peut être rétablie ; les anciennes réponses nécessitent la sauvegarde préalable.
        $this->addSql(<<<'SQL'
            CREATE TABLE concert_availability (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                status VARCHAR(20) NOT NULL,
                comment VARCHAR(500) DEFAULT NULL,
                answered_at DATETIME DEFAULT NULL,
                concert_date DATE NOT NULL,
                concert_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                FOREIGN KEY (concert_id) REFERENCES concert (id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_availability_concert_user ON concert_availability (concert_id, user_id)');
        $this->addSql('CREATE INDEX IDX_D6B7D9D83C97B2E ON concert_availability (concert_id)');
        $this->addSql('CREATE INDEX IDX_D6B7D9DA76ED395 ON concert_availability (user_id)');
    }
}
