<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la présence de l’ingé son aux conditions des concerts importés de Bandsintown, sans supprimer les disponibilités existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE concert_conditions ADD COLUMN engineer_presence VARCHAR(20) DEFAULT 'unknown' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert_conditions DROP COLUMN engineer_presence');
    }
}
