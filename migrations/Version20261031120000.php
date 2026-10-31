<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tableau des tâches du groupe, sans lien avec les concerts.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform,
            'Migration prévue pour SQLite.',
        );
        $this->addSql(<<<'SQL'
            CREATE TABLE band_task (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                revision INTEGER DEFAULT 1 NOT NULL,
                title VARCHAR(180) NOT NULL,
                description CLOB DEFAULT NULL,
                due_at DATE DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                completed_at DATETIME DEFAULT NULL,
                created_by_name VARCHAR(100) NOT NULL,
                assignee_id INTEGER DEFAULT NULL,
                band_id INTEGER NOT NULL,
                created_by_id INTEGER DEFAULT NULL,
                CONSTRAINT FK_9483B7E59EC7D60 FOREIGN KEY (assignee_id)
                    REFERENCES member_profile (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_9483B7E49ABEB17 FOREIGN KEY (band_id)
                    REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_9483B7EB03A8386 FOREIGN KEY (created_by_id)
                    REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL);
        $this->addSql('CREATE INDEX idx_task_board ON band_task (band_id, status, due_at)');
        $this->addSql('CREATE INDEX IDX_9483B7E59EC7D60 ON band_task (assignee_id)');
        $this->addSql('CREATE INDEX IDX_9483B7E49ABEB17 ON band_task (band_id)');
        $this->addSql('CREATE INDEX IDX_9483B7EB03A8386 ON band_task (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE band_task');
    }
}
