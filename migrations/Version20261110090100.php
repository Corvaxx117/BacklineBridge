<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sépare les précisions d’hébergement, de transport et de repas dans les conditions du concert.';
    }

    public function up(Schema $schema): void
    {
        foreach (['accommodation_notes', 'transport_notes', 'meal_notes'] as $column) {
            $this->addSql('ALTER TABLE concert_conditions ADD '.$column.' CLOB DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['meal_notes', 'transport_notes', 'accommodation_notes'] as $column) {
            $this->addSql('ALTER TABLE concert_conditions DROP COLUMN '.$column);
        }
    }
}
