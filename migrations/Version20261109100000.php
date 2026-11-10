<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relie les concerts aux représentations Orfeo et conserve leur dernier import.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE orfeo_concert (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                revision INTEGER DEFAULT 1 NOT NULL,
                project_id INTEGER NOT NULL,
                performance_id INTEGER NOT NULL,
                spectacle_id INTEGER NOT NULL,
                snapshot CLOB NOT NULL,
                contact_links CLOB NOT NULL,
                synced_at DATETIME NOT NULL,
                concert_id INTEGER NOT NULL,
                CONSTRAINT fk_orfeo_concert FOREIGN KEY (concert_id) REFERENCES concert (id)
                    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_26E5B77E83C97B2E ON orfeo_concert (concert_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_orfeo_performance ON orfeo_concert (project_id, performance_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE orfeo_concert');
    }
}
