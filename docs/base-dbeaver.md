# Voir la base dans DBeaver

La base locale est un fichier SQLite dans `var/backlinebridge.db` du projet.

1. Choisir **Nouvelle connexion → SQLite**.
2. Sélectionner ce fichier dans **Path / Chemin**.
3. Tester et terminer la connexion ; accepter le pilote SQLite si DBeaver le propose.
4. Nommer la connexion **BacklineBridge local**.
5. Ouvrir **Tables**, puis l'onglet **Données** d'une table. Actualiser après une modification dans l'application.

Aucun serveur de base ni utilisateur SQL n'est à créer. phpMyAdmin concerne MySQL et MariaDB ; DBeaver ouvre directement ce fichier SQLite.

| Table | Contenu |
| --- | --- |
| `app_user` | Comptes et mots de passe protégés |
| `band` et `band_membership` | Groupe et droits des comptes |
| `member_profile` | Profils ; les informations administratives sont chiffrées |
| `concert` et `concert_conditions` | Dates et conditions |
| `technical_sheet` et `patch_line` | Fiches et entrées du patch |

`docs/inspecter-base.sql` contient des exemples de requêtes de lecture.

La démo utilise une autre base : `/home/backlinebridge/backlinebridge/var/demo.db`, chez alwaysdata. Les changements locaux ne modifient pas ses données. Pour examiner une copie de la démo, télécharger une sauvegarde et lui donner une connexion distincte dans DBeaver ; ne pas remplacer la base locale.

[Connexion SQLite dans DBeaver](https://dbeaver.com/docs/dbeaver/Database-driver-SQLite/)
