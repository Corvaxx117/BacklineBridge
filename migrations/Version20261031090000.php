<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identités stables des sources du patch pour les liens du plan de scène.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof SQLitePlatform, 'Migration prévue pour SQLite.');
        $this->addSql('ALTER TABLE patch_line ADD COLUMN source_key VARCHAR(32) DEFAULT NULL');
        $this->addSql('UPDATE patch_line SET source_key = lower(hex(randomblob(16))) WHERE source_key IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patch_line DROP COLUMN source_key');
    }
}
