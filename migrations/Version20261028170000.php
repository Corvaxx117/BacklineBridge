<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Protège les informations des concerts contre les modifications concurrentes.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('ALTER TABLE concert ADD COLUMN revision INTEGER DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('ALTER TABLE concert DROP COLUMN revision');
    }
}
