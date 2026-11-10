<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire définitivement les setlists internes, sans modifier les fiches techniques ou les concerts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE setlist');
    }

    public function down(Schema $schema): void
    {
        // Seule la structure est rétablie ; récupérer les titres nécessite la sauvegarde préalable.
        $this->addSql(<<<'SQL'
            CREATE TABLE setlist (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                revision INTEGER DEFAULT 1 NOT NULL,
                tracks CLOB NOT NULL,
                updated_at DATETIME NOT NULL,
                updated_by_name VARCHAR(100) NOT NULL,
                updated_by_id INTEGER DEFAULT NULL,
                sheet_id INTEGER NOT NULL,
                CONSTRAINT FK_710BEA2A896DBBDE FOREIGN KEY (updated_by_id)
                    REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_710BEA2A8B1206A5 FOREIGN KEY (sheet_id)
                    REFERENCES technical_sheet (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_710BEA2A8B1206A5 ON setlist (sheet_id)');
        $this->addSql('CREATE INDEX IDX_710BEA2A896DBBDE ON setlist (updated_by_id)');
    }
}
