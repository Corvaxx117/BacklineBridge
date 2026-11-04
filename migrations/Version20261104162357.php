<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104162357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les notifications internes et leur lecture personnelle, sans reprendre les anciennes actions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE band_notification (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                actor_name VARCHAR(100) NOT NULL,
                type VARCHAR(30) NOT NULL,
                subject_label VARCHAR(300) NOT NULL,
                created_at DATETIME NOT NULL,
                context_label VARCHAR(200) DEFAULT NULL,
                item_count INTEGER DEFAULT 1 NOT NULL,
                band_id INTEGER NOT NULL,
                actor_id INTEGER DEFAULT NULL,
                concert_id INTEGER DEFAULT NULL,
                document_id INTEGER DEFAULT NULL,
                CONSTRAINT FK_2958252749ABEB17 FOREIGN KEY (band_id)
                    REFERENCES band (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_2958252710DAF24A FOREIGN KEY (actor_id)
                    REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_2958252783C97B2E FOREIGN KEY (concert_id)
                    REFERENCES concert (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_29582527C33F7837 FOREIGN KEY (document_id)
                    REFERENCES band_document (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_band_notification_created ON band_notification (band_id, created_at, id)');
        $this->addSql('CREATE INDEX IDX_2958252749ABEB17 ON band_notification (band_id)');
        $this->addSql('CREATE INDEX IDX_2958252710DAF24A ON band_notification (actor_id)');
        $this->addSql('CREATE INDEX IDX_2958252783C97B2E ON band_notification (concert_id)');
        $this->addSql('CREATE INDEX IDX_29582527C33F7837 ON band_notification (document_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_recipient (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                read_at DATETIME DEFAULT NULL,
                notification_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                CONSTRAINT FK_CEEDBC16EF1A9D84 FOREIGN KEY (notification_id)
                    REFERENCES band_notification (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_CEEDBC16A76ED395 FOREIGN KEY (user_id)
                    REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_notification_inbox ON notification_recipient (user_id, read_at, notification_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_user ON notification_recipient (notification_id, user_id)');
        $this->addSql('CREATE INDEX IDX_CEEDBC16EF1A9D84 ON notification_recipient (notification_id)');
        $this->addSql('CREATE INDEX IDX_CEEDBC16A76ED395 ON notification_recipient (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_recipient');
        $this->addSql('DROP TABLE band_notification');
    }
}
