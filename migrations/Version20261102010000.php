<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harmonise les profils booker et leurs droits, sans modifier les autres membres.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE band_membership SET role = 'booker'
            WHERE role <> 'booker' AND EXISTS (
                SELECT 1 FROM member_profile profile
                WHERE profile.band_id = band_membership.band_id
                  AND profile.user_id = band_membership.user_id
                  AND profile.kind = 'booker'
            )
            SQL);
        $this->addSql("UPDATE member_profile SET function = 'Booker', revision = revision + 1 WHERE kind = 'booker' AND function <> 'Booker'");
    }

    public function down(Schema $schema): void
    {
        // Une ancienne version du code reste compatible. Ne pas rétablir les droits excessifs.
    }
}
