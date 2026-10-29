<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029011643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profils privés chiffrés, activation des comptes, trésorerie et stock du groupe.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !(
                $this->connection->getDatabasePlatform() instanceof
                \Doctrine\DBAL\Platforms\SQLitePlatform
            ),
            'Migration prévue pour SQLite.',
        );
        $this->addSql(
            'CREATE TABLE finance_note (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, text VARCHAR(2000) NOT NULL, resolved_by_id INTEGER DEFAULT NULL, band_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_60A901146713A32B FOREIGN KEY (resolved_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_60A9011449ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_60A90114B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE INDEX IDX_60A901146713A32B ON finance_note (resolved_by_id)');
        $this->addSql('CREATE INDEX IDX_60A9011449ABEB17 ON finance_note (band_id)');
        $this->addSql('CREATE INDEX IDX_60A90114B03A8386 ON finance_note (created_by_id)');
        $this->addSql(
            'CREATE TABLE member_profile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id VARCHAR(32) NOT NULL, revision INTEGER DEFAULT 1 NOT NULL, administrative_ciphertext CLOB DEFAULT NULL, activation_hash VARCHAR(64) DEFAULT NULL, activation_expires_at DATETIME DEFAULT NULL, public_name VARCHAR(100) NOT NULL, function VARCHAR(120) NOT NULL, kind VARCHAR(20) NOT NULL, band_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_3EA31D9B49ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3EA31D9BA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3EA31D9BB5B48B91 ON member_profile (public_id)');
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_profile_band_user ON member_profile (band_id, user_id)',
        );
        $this->addSql('CREATE INDEX IDX_3EA31D9B49ABEB17 ON member_profile (band_id)');
        $this->addSql('CREATE INDEX IDX_3EA31D9BA76ED395 ON member_profile (user_id)');
        $this->addSql(
            'CREATE TABLE merch_product (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(160) NOT NULL, category VARCHAR(30) NOT NULL, band_id INTEGER NOT NULL, CONSTRAINT FK_4F5FAAFE49ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_merch_band_name ON merch_product (band_id, name)');
        $this->addSql('CREATE INDEX IDX_4F5FAAFE49ABEB17 ON merch_product (band_id)');
        $this->addSql(
            'CREATE TABLE merch_variant (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, revision INTEGER DEFAULT 1 NOT NULL, quantity INTEGER DEFAULT NULL, needs_verification BOOLEAN DEFAULT 0 NOT NULL, label VARCHAR(30) NOT NULL, product_id INTEGER NOT NULL, CONSTRAINT FK_6D5611FE4584665A FOREIGN KEY (product_id) REFERENCES merch_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_merch_product_variant ON merch_variant (product_id, label)',
        );
        $this->addSql('CREATE INDEX IDX_6D5611FE4584665A ON merch_variant (product_id)');
        $this->addSql(
            'CREATE TABLE stock_movement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, kind VARCHAR(20) NOT NULL, before_quantity INTEGER DEFAULT NULL, after_quantity INTEGER NOT NULL, reason VARCHAR(250) NOT NULL, variant_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_BB1BC1B53B69A9AF FOREIGN KEY (variant_id) REFERENCES merch_variant (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BB1BC1B5B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE INDEX IDX_BB1BC1B53B69A9AF ON stock_movement (variant_id)');
        $this->addSql('CREATE INDEX IDX_BB1BC1B5B03A8386 ON stock_movement (created_by_id)');
        $this->addSql(
            'CREATE TABLE treasury_account (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, revision INTEGER DEFAULT 1 NOT NULL, balance_cents INTEGER NOT NULL, kind VARCHAR(10) NOT NULL, band_id INTEGER NOT NULL, CONSTRAINT FK_8C6CD84749ABEB17 FOREIGN KEY (band_id) REFERENCES band (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_treasury_band_kind ON treasury_account (band_id, kind)',
        );
        $this->addSql('CREATE INDEX IDX_8C6CD84749ABEB17 ON treasury_account (band_id)');
        $this->addSql(
            'CREATE TABLE treasury_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, revision INTEGER DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, cancel_reason VARCHAR(250) DEFAULT NULL, kind VARCHAR(15) NOT NULL, amount_cents INTEGER NOT NULL, label VARCHAR(160) NOT NULL, category VARCHAR(30) NOT NULL, occurred_on DATE NOT NULL, cancelled_by_id INTEGER DEFAULT NULL, account_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, destination_id INTEGER DEFAULT NULL, stock_movement_id INTEGER DEFAULT NULL, CONSTRAINT FK_F96FB5ED187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F96FB5ED9B6B5FBA FOREIGN KEY (account_id) REFERENCES treasury_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F96FB5EDB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F96FB5ED816C6140 FOREIGN KEY (destination_id) REFERENCES treasury_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F96FB5EDFD50D693 FOREIGN KEY (stock_movement_id) REFERENCES stock_movement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_F96FB5EDFD50D693 ON treasury_entry (stock_movement_id)',
        );
        $this->addSql('CREATE INDEX IDX_F96FB5ED187B2D12 ON treasury_entry (cancelled_by_id)');
        $this->addSql('CREATE INDEX IDX_F96FB5ED9B6B5FBA ON treasury_entry (account_id)');
        $this->addSql('CREATE INDEX IDX_F96FB5EDB03A8386 ON treasury_entry (created_by_id)');
        $this->addSql('CREATE INDEX IDX_F96FB5ED816C6140 ON treasury_entry (destination_id)');
        $this->addSql("ALTER TABLE band ADD COLUMN useful_links CLOB NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        // Respecte les dépendances avec les clés étrangères activées.
        $this->addSql('DROP TABLE treasury_entry');
        $this->addSql('DROP TABLE treasury_account');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('DROP TABLE merch_variant');
        $this->addSql('DROP TABLE merch_product');
        $this->addSql('DROP TABLE member_profile');
        $this->addSql('DROP TABLE finance_note');
        $this->addSql('ALTER TABLE band DROP COLUMN useful_links');
    }
}
