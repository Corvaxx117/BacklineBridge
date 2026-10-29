# Ouvrir la base de BacklineBridge dans DBeaver

La base de développement est un fichier SQLite :

```text
<dossier-du-projet>/var/backlinebridge.db
```

1. Dans DBeaver, choisir **Nouvelle connexion** puis **SQLite**.
2. Dans le champ **Path / Chemin**, sélectionner le fichier ci-dessus.
3. Cliquer sur **Tester la connexion**. Si DBeaver propose de télécharger son pilote SQLite, terminer cette installation.
4. Terminer la connexion et la nommer **BacklineBridge local**.
5. Déplier la connexion puis **Tables**. Ouvrir une table et son onglet **Données** pour voir ses lignes. Actualiser après une modification effectuée dans l'application.

Si une connexion existe déjà dans DBeaver, modifier son chemin pour pointer vers ce fichier et actualiser son nom en **BacklineBridge local**.

Il n'y a pas de serveur SQLite à démarrer ni d'utilisateur SQL à saisir pour ce fichier local non chiffré. Selon la version de DBeaver, la configuration peut aussi accepter cette URL JDBC :

```text
jdbc:sqlite:/chemin/vers/BacklineBridge/var/backlinebridge.db
```

Tables principales :

| Table | Contenu |
| --- | --- |
| `app_user` | Comptes personnels, avec mot de passe haché |
| `band` | Groupe et fiche de référence |
| `band_membership` | Compte, groupe et rôle (`manager`, `booker`, `editor`, `viewer`) |
| `concert` | Date, salle, ville, statut et copie du patch |
| `concert_conditions` | Accueil, rémunération et modalités propres au concert |
| `technical_sheet` | Fiche de référence et copies pour les concerts |
| `patch_line` | Sources du patch, actives ou dans la corbeille |

`docs/inspecter-base.sql` propose des requêtes de lecture, notamment pour retrouver les rôles et les sources actives. Les bases temporaires utilisées par les tests sont distinctes de ce fichier.

phpMyAdmin est destiné aux serveurs MySQL/MariaDB ; il ne remplace pas cette connexion SQLite. Une migration vers un autre moteur n'a pas été effectuée.

Sources : [Connexion SQLite dans DBeaver](https://dbeaver.com/docs/dbeaver/Database-driver-SQLite/), [périmètre de phpMyAdmin](https://docs.phpmyadmin.net/en/latest/intro.html).
