<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute des notes pratiques facultatives à chaque concert.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('ALTER TABLE concert ADD COLUMN notes VARCHAR(2000) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('ALTER TABLE concert DROP COLUMN notes');
    }
}
