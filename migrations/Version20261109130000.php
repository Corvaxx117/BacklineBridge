<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harmonise en anglais les libellés standards du patch Avee Mana, sans toucher aux ajouts personnalisés ni aux publications.';
    }

    public function up(Schema $schema): void
    {
        $translations = [
            'source_name' => [
                'SNARE BOTT' => 'SNARE BOTTOM',
                'HH' => 'HI-HAT',
                'OH L' => 'OVERHEAD L',
                'OH R' => 'OVERHEAD R',
                'GUIT JU' => 'GUITAR JU',
                'GUIT REMI' => 'GUITAR REMI',
                'CLIC' => 'CLICK',
            ],
            'preferred_microphone' => ['FX PERSO' => 'OWN FX'],
            'notes' => [
                'MICRO PERSO' => 'OWN MIC',
                'FOURNIR UNE SORTIE LIGNE ET DEUX RETOURS LIGNE (JACK TRS OU XLR)(FOH)' => 'PROVIDE 1 LINE OUTPUT AND 2 LINE RETURNS (TRS JACK OR XLR) AT FOH',
            ],
        ];
        foreach ($translations as $column => $values) {
            foreach ($values as $previous => $translated) {
                $this->addSql(sprintf(<<<'SQL'
                    UPDATE patch_line SET %1$s = ?
                    WHERE %1$s = ? AND sheet_id IN (
                        SELECT id FROM technical_sheet
                        WHERE UPPER(TRIM(band_name)) = 'AVEE MANA'
                    )
                    SQL, $column), [$translated, $previous]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les libellés traduits peuvent avoir été modifiés depuis. Restaurer la sauvegarde pour retrouver leur rédaction antérieure.');
    }
}
