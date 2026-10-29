# BacklineBridge

Application PHP/Symfony pour préparer les dossiers techniques de concerts et suivre les confirmations des salles.

Le dossier réunit le patch des entrées, le matériel et un plan de scène modifiable à la souris. Il comprend les accès par rôle, les conditions de tournée, les disponibilités de l'équipe, les invitations des salles, les réponses au matériel demandé, l'import de la référence par section et l'export PDF.

L'application permet de consulter et modifier la fiche de référence d'Avee Mana, puis de créer des concerts avec une date, une salle et une ville. Chaque concert reçoit une copie indépendante du patch courant, modifiable sans changer la référence ou les autres concerts. Le numéro de canal doit être positif et unique dans sa fiche. L'identifiant d'une source reste stable lorsqu'on la renumérote.

## Démarrer

Prérequis : PHP 8.4.1 ou supérieur dans la branche 8.4, extensions PDO SQLite, mbstring, intl, XML et Composer. La branche PHP est déclarée dans `.php-version` pour les outils compatibles.

Depuis le dossier du projet, sélectionner PHP 8.4 dans la session du terminal. Sur ce Mac :

```bash
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
php -v
```

Puis :

```bash
php -r '
if (!file_exists(".env.local")) {
    file_put_contents(".env.local", "APP_SECRET=".bin2hex(random_bytes(32)).PHP_EOL);
}
'
composer install
composer setup
php bin/console app:user:create --role=manager
composer start
```

Le premier bloc crée un secret de développement unique dans `.env.local`, sans afficher sa valeur et sans écraser un fichier existant. Si ce fichier existe déjà, vérifier qu'il définit `APP_SECRET`. Les fichiers locaux d'environnement, les bases, les caches, les dépendances installées et les exports ne sont pas versionnés. Seuls `.env` (valeurs communes sans secret) et `.env.test` (configuration des tests) sont partagés.

La commande de création du compte demande l'adresse e-mail, le nom affiché et deux saisies masquées du mot de passe. Aucun compte par défaut n'est fourni. Ouvrir ensuite [la connexion BacklineBridge](http://127.0.0.1:8765/connexion).

Avec Symfony CLI, `symfony composer install`, `symfony composer setup` et `symfony composer start` sélectionnent aussi la version déclarée dans `.php-version`. Ce fichier ne change pas à lui seul le PHP du terminal.

`composer setup` applique les migrations et charge les données de départ uniquement si aucune fiche n'existe. Relancer cette commande conserve les modifications déjà effectuées. La base de développement est stockée dans `var/backlinebridge.db`, hors des fichiers à versionner.

Le serveur est lié à `127.0.0.1`. La connexion et les autorisations par groupe sont maintenant actives. Le jalon reste destiné au développement local ; invitations des membres, vérification de l'adresse e-mail et récupération de mot de passe ne sont pas encore disponibles.

## Vérifier

```bash
composer test
php bin/console doctrine:schema:validate
```

Les tests utilisent une base SQLite temporaire distincte de la base de développement. Ils couvrent l'affichage ordonné, la persistance des modifications, les champs facultatifs, les numéros invalides, les doublons, la protection CSRF et la stabilité de l'identité d'une source. Le parcours concert vérifie aussi l'indépendance des copies et l'absence de dossier incomplet en cas d'échec SQL.

## Présentation du code et export PDF

Le formatage PHP, Twig, JavaScript et CSS est partagé dans `.prettierrc.json`. Avec Node.js 22 ou supérieur :

```bash
npm ci --ignore-scripts
npm run format
npm run format:check
```

Les conventions et exclusions sont détaillées dans [le guide de présentation du code](docs/style-code.md).

Le bouton **Télécharger la fiche PDF** exporte le plan de scène enregistré, le patch, le matériel et les notes techniques depuis la référence, un concert ou l'espace invité. Les conditions internes ne sont pas incluses. Un invité télécharge sa publication figée. Voir [le fonctionnement et les services de l'export](docs/export-pdf.md).

## Lire le code

- `src/Entity/TechnicalSheet.php` : la fiche et sa relation avec les sources.
- `src/Entity/PatchLine.php` : données d'une source, validations et unicité.
- `src/Form/PatchLineType.php` : saisie et protection CSRF.
- `src/Controller/Patch/` : contrôleurs invokables `IndexAction`, `CreateAction` et `EditAction`, regroupés par fonctionnalité.
- `src/Service/Patch/PatchLineFormHandler.php` : validation, enregistrement et gestion des collisions de canal, partagés entre ajout et modification.
- `src/Service/Patch/FormSubmissionResult.php` : résultat explicite du traitement du formulaire ; les contrôleurs restent responsables du rendu, des messages et des redirections.
- `src/Repository/` : accès aux données et tri.
- `src/Controller/Concert/` : création, liste et consultation des concerts, avec un contrôleur par action.
- `src/Service/Concert/ConcertCreator.php` : copie indépendante de la fiche et enregistrement du concert.
- `src/Dto/Concert/CreateConcertInput.php` : données validées du formulaire de création.
- `src/Security/Voter/` : décisions d'accès aux fiches et aux concerts selon l'adhésion au groupe.
- `src/Service/Security/UserCreator.php` et `src/Command/CreateUserCommand.php` : création interactive d'un compte avec un mot de passe haché et un accès au groupe.
- `docs/authentification.md` : premier compte, niveaux d'accès, parcours de connexion, limites et exercice.
- `docs/equipe-et-tournee.md` : rôles de l'équipe, tourneur, conditions des concerts et corbeille.
- `docs/equipe-et-reference.md` : créateur, disponibilités personnelles et import par section de la référence.
- `docs/salles-et-plan-de-scene.md` : invitations des salles, publications datées, réponses et édition du plan / matériel.
- `docs/base-dbeaver.md` et `docs/inspecter-base.sql` : connexion à la base SQLite dans DBeaver et requêtes de lecture.
- `docs/concerts.md` : parcours du nouveau jalon, choix de modèle, migration et exercice à réaliser.
- `migrations/` : création de la base et contraintes SQL.
- `data/avee-mana-patch.json` : transcription du patch 2026.3, sans coordonnées.
- `templates/` et `public/styles/app.css` : interface.
- `docs/premier-exercice.md` : prise en main et prochaine modification à réaliser.

## Décisions du premier jalon

**Symfony 8.1 et PHP 8.4** sont les versions choisies pour le projet. Les scripts Composer emploient `@php` pour conserver l’interpréteur qui exécute Composer. Doctrine utilise les objets différés natifs de PHP 8.4. Twig et Doctrine suffisent au parcours initial. SQLite permet de travailler immédiatement sans service de base de données à installer ; la migration initiale est explicitement spécifique à SQLite.

**Validation et contrainte SQL se complètent.** La première affiche un message utile dans le formulaire ; la seconde empêche l'enregistrement d'un doublon même si la validation est contournée. Une collision entre deux enregistrements est également traitée.

**Référence technique 2026.3.** Le patch suit la fiche mise à jour : ordre des claviers, micros personnels, pince CLAMP et références C414 XLS. Les anciennes demandes d’inserts ont disparu du document et du jeu de départ. La mention `FX PERSO` est conservée dans le champ issu de la colonne MIC, sans lui inventer une référence de microphone. Le plan et les demandes de matériel sont décrits dans `data/avee-mana-stage.json` et [le guide de préparation avec la salle](docs/salles-et-plan-de-scene.md).

**Un patch distinct par concert.** Une relation OneToOne lie chaque concert à sa propre fiche. Les sources sont copiées à la création et restent indépendantes de la référence, dans les deux sens. Ce dossier est encore modifiable ; il ne constitue pas une version publiée immuable. Le plan et le backline sont également copiés. Une publication datée distincte fige ce dossier pour les réponses de la salle ; les alternatives restent à discuter avec le groupe.

**Une personne, des droits contextualisés.** `User`, `Band` et `BandMembership` séparent l'identité et l'accès au groupe. Les cinq responsables (les quatre membres et Marty) peuvent gérer le patch, la corbeille, les dates et leurs conditions. Le rôle `booker` permet à Romain de créer, modifier et valider les dates et de gérer leurs conditions, avec un accès en consultation au patch. Les profils de régie technique et de lecture seule n'ont pas accès aux conditions financières. Les liens invités sont limités à une publication de concert ; les invitations de membres du groupe restent à réaliser.

**Des sources retirées, puis restaurables.** Le canal d'une source mise à la corbeille est libéré. L'identifiant reste conservé et la restauration refuse un canal déjà réutilisé. Une modification de la référence n'affecte pas les concerts existants.

## Sources techniques

- [Version Symfony 8.1](https://symfony.com/releases/8.1)
- [Doctrine et bases de données](https://symfony.com/doc/8.1/doctrine.html)
- [Validation Symfony](https://symfony.com/doc/8.1/validation.html)
- [Protection CSRF](https://symfony.com/doc/8.1/security/csrf.html)
