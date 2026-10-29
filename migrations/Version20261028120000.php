<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028120000 extends AbstractMigration
{
    // Copie figée de la transcription 2026.3, pour rendre la migration indépendante des futurs fichiers de données.
    private const SETUP = <<<'JSON'
    {
      "stagePlan": [
        {
          "label": "Praticable batterie",
          "kind": "riser",
          "x": 35,
          "y": 23,
          "notes": "200 × 200 × 30 cm"
        },
        {
          "label": "SYLOU · Batterie",
          "kind": "drums",
          "x": 35,
          "y": 24,
          "notes": "Patch 1–9 et 19 · IEM filaire mono/stéréo à confirmer"
        },
        {
          "label": "Ampli guitare Ju",
          "kind": "amp",
          "x": 9,
          "y": 46,
          "notes": "Patch 12 · support ≥ 80 cm"
        },
        {
          "label": "Ampli basse",
          "kind": "amp",
          "x": 74,
          "y": 38,
          "notes": "Patch 10–11"
        },
        {
          "label": "Ampli guitare Rémi",
          "kind": "amp",
          "x": 91,
          "y": 64,
          "notes": "Patch 13 · support ≥ 80 cm"
        },
        {
          "label": "JU",
          "kind": "person",
          "x": 19,
          "y": 65,
          "notes": "Chant 17 · IEM mono sans fil"
        },
        {
          "label": "RÉMI",
          "kind": "person",
          "x": 55,
          "y": 65,
          "notes": "Chant 18 · IEM mono sans fil"
        },
        {
          "label": "FRANKY",
          "kind": "person",
          "x": 77,
          "y": 58,
          "notes": "Basse"
        },
        {
          "label": "MicroKorg / Minilogue",
          "kind": "keys",
          "x": 17,
          "y": 83,
          "notes": "Patch 14–16"
        },
        {
          "label": "Wedge 1",
          "kind": "wedge",
          "x": 55,
          "y": 22,
          "notes": "Mix 1 · Sylou"
        },
        {
          "label": "Wedge 2",
          "kind": "wedge",
          "x": 31,
          "y": 88,
          "notes": "Mix 3 · Ju"
        },
        {
          "label": "Wedge 3",
          "kind": "wedge",
          "x": 48,
          "y": 88,
          "notes": "Mix 5 · Rémi"
        },
        {
          "label": "Wedge 4",
          "kind": "wedge",
          "x": 65,
          "y": 88,
          "notes": "Mix 5 lié · Rémi"
        },
        {
          "label": "Wedge 5",
          "kind": "wedge",
          "x": 82,
          "y": 79,
          "notes": "Mix 7 · Franky"
        }
      ],
      "equipment": [
        {
          "id": "kick",
          "category": "Batterie",
          "label": "Grosse caisse",
          "quantity": 1,
          "specification": "22 pouces · kit type Gretsch Catalina ou équivalent"
        },
        {
          "id": "rack_tom",
          "category": "Batterie",
          "label": "Tom suspendu",
          "quantity": 1,
          "specification": "13 pouces · peaux type Coated Ambassador"
        },
        {
          "id": "floor_tom",
          "category": "Batterie",
          "label": "Tom basse",
          "quantity": 1,
          "specification": "16 pouces · peaux type Coated Ambassador"
        },
        {
          "id": "snare_stand",
          "category": "Batterie",
          "label": "Pied de caisse claire",
          "quantity": 1,
          "specification": ""
        },
        {
          "id": "cymbal_stands",
          "category": "Batterie",
          "label": "Pieds perche de cymbale",
          "quantity": 2,
          "specification": ""
        },
        {
          "id": "hh_stand",
          "category": "Batterie",
          "label": "Pied de charley",
          "quantity": 1,
          "specification": ""
        },
        {
          "id": "drum_stool",
          "category": "Batterie",
          "label": "Tabouret de batterie",
          "quantity": 1,
          "specification": ""
        },
        {
          "id": "drum_rug",
          "category": "Batterie",
          "label": "Tapis de batterie",
          "quantity": 1,
          "specification": ""
        },
        {
          "id": "bass_cab",
          "category": "Amplification",
          "label": "Cab basse",
          "quantity": 1,
          "specification": "4 × 10 ou équivalent · 500 W / 4 Ω ou 250 W / 8 Ω"
        },
        {
          "id": "guitar_amps",
          "category": "Amplification",
          "label": "Amplis guitare",
          "quantity": 2,
          "specification": "Si voyage en train ou en avion : Fender Hot Rod ou équivalent, clean avec beaucoup de headroom. À confirmer pour cette date."
        },
        {
          "id": "amp_supports",
          "category": "Scène",
          "label": "Supports amplis guitare",
          "quantity": 2,
          "specification": "Cubes ou fly · hauteur minimale 80 cm"
        },
        {
          "id": "riser",
          "category": "Scène",
          "label": "Praticable batterie",
          "quantity": 1,
          "specification": "200 × 200 × 30 cm · centré sur scène"
        },
        {
          "id": "keyboard_stand",
          "category": "Scène",
          "label": "Stand clavier",
          "quantity": 1,
          "specification": "RTX avec extensions ou équivalent"
        },
        {
          "id": "guitar_stands",
          "category": "Scène",
          "label": "Stands guitare",
          "quantity": 3,
          "specification": ""
        },
        {
          "id": "wedges",
          "category": "Retours",
          "label": "Wedges",
          "quantity": 5,
          "specification": "Mix 1 Sylou ; mix 3 Ju ; mix 5 Rémi : 2 wedges liés ; mix 7 Franky"
        },
        {
          "id": "iem_sylou",
          "category": "Retours",
          "label": "IEM filaire Sylou",
          "quantity": 1,
          "specification": "Mix 2 · préciser mono ou stéréo dans la proposition pour cette salle"
        },
        {
          "id": "iem_wireless",
          "category": "Retours",
          "label": "IEM mono sans fil",
          "quantity": 2,
          "specification": "Mix 4 Ju · mix 6 Rémi"
        },
        {
          "id": "foh_console",
          "category": "Façade",
          "label": "Console FOH",
          "quantity": 1,
          "specification": "DM7 / CL5 / M32 ou équivalent · préciser le modèle"
        },
        {
          "id": "pa",
          "category": "Façade",
          "label": "Système de diffusion",
          "quantity": 1,
          "specification": "Adapté à la salle et au style · préférence : au moins 2 tops et 1 sub par côté, alignés et calibrés"
        }
      ]
    }
    JSON;

    public function getDescription(): string
    {
        return 'Ajoute le plan de scène, le backline, les publications et les accès / réponses des salles.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql(
            'CREATE TABLE venue_reply (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, answers CLOB NOT NULL, respondent VARCHAR(150) NOT NULL, notes VARCHAR(2000) DEFAULT NULL, updated_at DATETIME NOT NULL, revision INTEGER DEFAULT 1 NOT NULL, invitation_id INTEGER NOT NULL, CONSTRAINT FK_2513CB37A35D7AF0 FOREIGN KEY (invitation_id) REFERENCES venue_invitation (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_reply_invitation ON venue_reply (invitation_id)');
        $this->addSql(
            'CREATE TABLE technical_publication (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, snapshot CLOB NOT NULL, fingerprint VARCHAR(64) NOT NULL, published_at DATETIME NOT NULL, concert_id INTEGER NOT NULL, CONSTRAINT FK_CD89FC7783C97B2E FOREIGN KEY (concert_id) REFERENCES concert (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql('CREATE INDEX idx_publication_concert ON technical_publication (concert_id)');
        $this->addSql(
            'CREATE TABLE venue_invitation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(150) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, publication_id INTEGER NOT NULL, CONSTRAINT FK_B84844D038B217A7 FOREIGN KEY (publication_id) REFERENCES technical_publication (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );
        $this->addSql(
            'CREATE INDEX idx_invitation_publication ON venue_invitation (publication_id)',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_token ON venue_invitation (token_hash)');
        $this->addSql('ALTER TABLE technical_sheet ADD COLUMN stage_plan CLOB DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE technical_sheet ADD COLUMN equipment_requests CLOB DEFAULT NULL',
        );
        $setup = json_decode(self::SETUP, true, flags: JSON_THROW_ON_ERROR);
        $this->addSql(
            'UPDATE technical_sheet SET stage_plan = ?, equipment_requests = ? WHERE band_name = ?',
            [
                json_encode($setup['stagePlan'], JSON_THROW_ON_ERROR),
                json_encode($setup['equipment'], JSON_THROW_ON_ERROR),
                'AVEE MANA',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Cette migration utilise SQLite.',
        );
        $this->addSql('DROP TABLE venue_reply');
        $this->addSql('DROP TABLE venue_invitation');
        $this->addSql('DROP TABLE technical_publication');
        $this->addSql('ALTER TABLE technical_sheet DROP COLUMN stage_plan');
        $this->addSql('ALTER TABLE technical_sheet DROP COLUMN equipment_requests');
    }
}
