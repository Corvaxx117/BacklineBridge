# Comptes et droits d'accès

## Créer ton premier compte

Dans un terminal placé à la racine de BacklineBridge, sélectionner PHP 8.4 puis lancer :

```bash
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
php bin/console app:user:create --role=manager
```

Avec Symfony CLI :

```bash
symfony console app:user:create --role=manager
```

La commande demande l'adresse e-mail, le nom affiché et deux saisies masquées du mot de passe (12 à 128 caractères). Le mot de passe n'est ni un argument de commande ni une donnée de fixture. Il est haché par Symfony avant l'enregistrement. Aucun compte ni mot de passe par défaut n'est installé.

Le groupe est sélectionné automatiquement s'il n'y en a qu'un. Sinon préciser son identifiant avec `--band=1`. La commande vérifie le groupe, le rôle, la confirmation et les données avant d'enregistrer ensemble le compte et son adhésion.

Ouvrir ensuite [la connexion locale](http://127.0.0.1:8765/connexion). Les fiches et concerts exigent désormais une session authentifiée.

## Créer d'autres comptes pour tester

```bash
php bin/console app:user:create --role=editor
php bin/console app:user:create --role=viewer
```

Chaque commande crée une nouvelle personne avec sa propre adresse. Sans option, le rôle proposé est `viewer`. Les rôles sont désormais détaillés dans `docs/equipe-et-tournee.md` : responsables pour les quatre membres et Marty ; `booker` pour Romain. Les responsables et la régie technique peuvent modifier le patch, mais sa suppression est réservée aux responsables. Seuls les responsables et le tourneur gèrent les dates et leurs conditions commerciales. Les invitations de membres du groupe restent à réaliser ; les accès invités des salles sont décrits dans `docs/salles-et-plan-de-scene.md`.

Le terminal est ici un moyen de provisionnement réservé à la personne qui exploite l'application. L'application ne propose pas d'inscription publique permettant de s'attribuer l'accès au groupe.

## Modèle et responsabilité de chaque partie

- `User` représente une personne. Son rôle global est uniquement `ROLE_USER`. L'adresse est normalisée en minuscules et possède une contrainte d'unicité SQL.
- `Band` possède sa fiche de référence. Les libellés présents dans les copies des concerts restent des données du dossier technique.
- `BandMembership` associe une personne, un groupe et un `BandRole`. La paire groupe/personne est unique.
- `Concert` appartient à un groupe et possède toujours sa copie de fiche. Les identifiants et contenus existants restent conservés par la migration.
- `TechnicalSheetVoter` vérifie le droit de consulter ou modifier la fiche selon l'adhésion au groupe. `ConcertVoter` applique les mêmes décisions au groupe du concert.

Les listes sont filtrées dans les repositories avant leur affichage. Les accès directs à une fiche, un concert ou une source passent aussi par un Voter avant le traitement d'un formulaire. Cacher un bouton ne remplace pas cette vérification serveur.

Les adhésions sont relues lors des décisions. Leur suppression retire donc l'accès aux dossiers sans attendre une déconnexion de la personne. Les droits ne sont pas copiés dans les rôles globaux ou figés dans sa session.

Le modèle peut représenter plusieurs groupes. L'interface de sélection du groupe reste à réaliser : si une personne possède plusieurs adhésions, la liste des concerts couvre ses groupes autorisés, tandis que la fiche de référence et le formulaire de création utilisent le premier groupe par identifiant.

## Suivre une connexion dans le code

1. `config/packages/security.yaml` configure le fournisseur Doctrine, le formulaire de connexion, les sessions, le CSRF et la limitation des tentatives.
2. `Controller/Security/LoginAction.php` affiche le formulaire ; Symfony intercepte le POST et vérifie les identifiants.
3. `Repository/UserRepository.php` charge la personne à partir de l'adresse normalisée et prend en charge l'évolution du hash si nécessaire.
4. Le navigateur reçoit une session. Le hash complet du mot de passe n'est pas sérialisé dans cette session : Symfony compare une empreinte au compte rechargé.
5. Un contrôleur métier charge uniquement les données autorisées ou appelle `denyAccessUnlessGranted()` avant toute modification.
6. Les Voters retrouvent l'adhésion au groupe concerné et accordent ou refusent l'action.

La déconnexion nécessite un POST avec un jeton CSRF valide et invalide la session. Le formulaire de connexion est également protégé par CSRF. Après des échecs répétés, le mécanisme Symfony de limitation des tentatives bloque temporairement de nouveaux essais.

## Lire et modifier le code

Commencer par `TechnicalSheetVoter` et `tests/Functional/Security/AuthorizationTest.php` : les tests décrivent les droits attendus, notamment le refus des URL d'un autre groupe, la lecture seule et la révocation d'un accès en session active.

`Service/Security/UserCreator.php` porte la validation, le hachage et l'enregistrement du compte. `Command/CreateUserCommand.php` se limite aux interactions avec le terminal et aux messages. Les contrôleurs HTTP restent invokables et classés par fonctionnalité.

## Migration et portée du jalon

La migration ajoute les comptes, groupes et adhésions, puis rattache les concerts existants au groupe de la référence. Elle conserve leurs identifiants, les fiches et les sources. Le modèle précédent n'exposait qu'une référence de groupe ; si la base contient plusieurs références indépendantes, la migration s'arrête avant modification pour éviter d'inventer leur rattachement.

Le retour arrière conserve les fiches, les sources et les concerts, mais supprime les comptes, les groupes et les adhésions de ce jalon. Conserver une sauvegarde avant de l'exécuter.

Le provisionnement local ne vérifie pas la possession de l'adresse e-mail. Les invitations de membres avec preuve de possession de l'adresse et la récupération de mot de passe restent à réaliser. Les liens invités limités à une publication de concert sont disponibles, sans vérification d'identité par e-mail. La configuration actuelle vise toujours l'application locale.

## Exercice suivant

Ajouter un test dans lequel la même personne est éditeur du groupe A et lecteur du groupe B. Vérifier qu'elle peut consulter les deux groupes, modifier uniquement A et conserver ces restrictions après rechargement de sa session. Les rôles globaux doivent rester inchangés.
