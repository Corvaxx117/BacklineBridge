# Voir SQLite dans DBeaver

La base locale est `var/backlinebridge.db`. La démo utilise `var/demo.db` et l’instance officielle `var/production.db`, chacune sur son compte alwaysdata. Ces bases sont indépendantes.

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
| `concert_contact` | Interlocuteurs d’une date |
| `band_document` | Titres, liens et informations des documents partagés |

Sur le serveur officiel, la base se trouve dans `/home/backlinebridgeoffice/backlinebridge/var/production.db`.

Les PDF ajoutés par les membres sont des fichiers privés dans `var/documents/<identifiant du groupe>/`. SQLite conserve leurs informations, pas leur contenu. Pour sauvegarder ou restaurer l’ensemble, garder la base et les PDF correspondants avec le script décrit dans [Maintenance](../exploitation/maintenance.md).

Les mots de passe sont hachés et les informations administratives chiffrées. Les requêtes de [inspecter-base.sql](inspecter-base.sql) évitent de les afficher.

Pour examiner une sauvegarde distante, ouvrir une **copie** dans une connexion distincte. Ne pas remplacer la base locale ni modifier les soldes et droits directement : passer par l’application pour conserver ses contrôles.

[Documentation DBeaver SQLite](https://dbeaver.com/docs/dbeaver/Database-driver-SQLite/)
