<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve la mention HT ou TTC du montant convenu sans interpréter les montants existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE concert_conditions ADD fee_basis VARCHAR(10) DEFAULT 'unknown' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert_conditions DROP COLUMN fee_basis');
    }
}
