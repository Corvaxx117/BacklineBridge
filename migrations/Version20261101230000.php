<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Horaire facultatif des concerts et reprise des horaires Bandsintown existants.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof SQLitePlatform, 'Migration prévue pour SQLite.');
        $this->addSql('ALTER TABLE concert ADD COLUMN start_time VARCHAR(5) DEFAULT NULL');
        // Ne reprendre l’horaire que si le concert est toujours au jour de l’annonce importée.
        $this->addSql(<<<'SQL'
            UPDATE concert SET start_time = (
                SELECT json_extract(source.snapshot, '$.localTime')
                FROM bandsintown_concert source
                WHERE source.concert_id = concert.id
                  AND json_valid(source.snapshot)
                  AND json_extract(source.snapshot, '$.date') = concert.date
                  AND json_extract(source.snapshot, '$.localTime') GLOB '[0-2][0-9]:[0-5][0-9]'
                  AND substr(json_extract(source.snapshot, '$.localTime'), 1, 2) < '24'
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert DROP COLUMN start_time');
    }
}
