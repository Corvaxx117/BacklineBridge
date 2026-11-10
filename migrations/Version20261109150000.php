<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le style et les contacts partageables de la fiche technique, sans données privées dans la migration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band ADD technical_identity CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band DROP COLUMN technical_identity');
    }
}
