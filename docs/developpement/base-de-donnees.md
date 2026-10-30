# Voir SQLite dans DBeaver

La base locale est `var/backlinebridge.db`. Elle est indépendante de la base hébergée `var/demo.db` de la démo.

1. Dans DBeaver, choisir **Nouvelle connexion → SQLite**.
2. Sélectionner le fichier dans **Path / Chemin**, accepter le pilote si demandé.
3. Tester, terminer et nommer la connexion **BacklineBridge local**.
4. Ouvrir une table, onglet **Données**, puis actualiser après une saisie dans l’application.

Aucun serveur SQL ni compte de base n’est nécessaire. phpMyAdmin gère MySQL/MariaDB, pas ce fichier SQLite.

| Tables | Contenu |
| --- | --- |
| `app_user`, `band_membership`, `member_profile` | Comptes, droits et profils |
| `concert`, `concert_conditions`, `concert_availability` | Dates, accords et disponibilités |
| `technical_sheet`, `patch_line` | Référence et copies de dossiers |
| `bandsintown_concert` | Origine des concerts importés |

Les mots de passe sont hachés et les informations administratives chiffrées. Les requêtes de [inspecter-base.sql](inspecter-base.sql) évitent de les afficher.

Pour examiner une sauvegarde distante, ouvrir une **copie** dans une connexion distincte. Ne pas remplacer la base locale ni modifier les soldes et droits directement : passer par l’application pour conserver ses contrôles.

[Documentation DBeaver SQLite](https://dbeaver.com/docs/dbeaver/Database-driver-SQLite/)
