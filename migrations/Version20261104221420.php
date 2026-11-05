<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104221420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les appareils volontaires et la file d’envoi des notifications push.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE push_delivery (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                state VARCHAR(12) NOT NULL,
                attempts INTEGER NOT NULL,
                next_attempt_at DATETIME NOT NULL,
                finished_at DATETIME DEFAULT NULL,
                last_status INTEGER DEFAULT NULL,
                created_at DATETIME NOT NULL,
                subscription_id INTEGER NOT NULL,
                recipient_id INTEGER DEFAULT NULL,
                CONSTRAINT FK_53D1014A9A1887DC FOREIGN KEY (subscription_id)
                    REFERENCES push_subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_53D1014AE92F8F78 FOREIGN KEY (recipient_id)
                    REFERENCES notification_recipient (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL);
        $this->addSql('CREATE INDEX idx_push_pending ON push_delivery (state, next_attempt_at, id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_push_delivery ON push_delivery (recipient_id, subscription_id)');
        $this->addSql('CREATE INDEX IDX_53D1014A9A1887DC ON push_delivery (subscription_id)');
        $this->addSql('CREATE INDEX IDX_53D1014AE92F8F78 ON push_delivery (recipient_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE push_subscription (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                endpoint_hash VARCHAR(64) NOT NULL,
                last_test_at DATETIME DEFAULT NULL,
                device_hash VARCHAR(64) NOT NULL,
                endpoint VARCHAR(2048) NOT NULL,
                public_key VARCHAR(100) NOT NULL,
                auth_token VARCHAR(30) NOT NULL,
                vapid_public_key VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                user_id INTEGER NOT NULL,
                CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id)
                    REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_push_endpoint ON push_subscription (endpoint_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_push_device ON push_subscription (device_hash)');
        $this->addSql('CREATE INDEX IDX_562830F3A76ED395 ON push_subscription (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_delivery');
        $this->addSql('DROP TABLE push_subscription');
    }
}
