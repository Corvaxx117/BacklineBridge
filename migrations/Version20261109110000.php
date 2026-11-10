<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un niveau d’enjeu facultatif et sa note interne aux concerts existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert ADD importance_level SMALLINT DEFAULT NULL CHECK (importance_level IS NULL OR importance_level BETWEEN 1 AND 5)');
        $this->addSql('ALTER TABLE concert ADD importance_note VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert DROP COLUMN importance_note');
        $this->addSql('ALTER TABLE concert DROP COLUMN importance_level');
    }
}
