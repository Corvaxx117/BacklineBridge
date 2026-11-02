# Code et qualité

Les contrôleurs reçoivent les actions ; les services appliquent les règles ; les templates affichent le résultat.

## Retrouver une fonctionnalité

| Dossier | Contenu |
| --- | --- |
| `src/Controller/` | Une action par classe, rangée par fonctionnalité |
| `src/Service/` | Création de concerts, import, ventes, disponibilités… |
| `src/Entity/`, `src/Repository/` | Données et recherches |
| `src/Form/`, `src/Dto/`, `src/Validator/` | Saisie et vérifications |
| `src/Security/Voter/` | Autorisations côté serveur |
| `src/Integration/Bandsintown/`, `src/Integration/GoogleCalendar/` | Clients API et lecture des réponses |
| `templates/`, `public/styles/`, `public/scripts/` | Pages, styles et interactions |
| `templates/pdf/`, `src/Service/Pdf/` | PDF et dessin du plan, avec Dompdf |
| `migrations/`, `tests/` | Évolution des tables et vérifications automatiques |

Les adaptations aux petits écrans sont regroupées dans `public/styles/responsive.css`. `navigation.js` gère le menu mobile et le sous-menu « Le groupe » (fermeture par Échap ou clic extérieur) ; `stage-viewer.js` cadre et zoome le plan sans modifier ses coordonnées enregistrées.

Les contacts et documents passent par `Controller/Concert/Contact/` et `Controller/Document/`, avec des DTO de saisie et des services dédiés. `DocumentStorage` garde les PDF hors de `public/`, contrôle leur taille et sérialise les changements de fichiers avec un verrou partagé par la sauvegarde. Les formulaires vérifient le type réel du fichier avec Symfony Mime. `DocumentVoter` vérifie l’accès au groupe avant chaque lecture ou retrait.

`ConcertRemover` retire le dossier d’une date dans une transaction, en vérifiant sa version. Les clés étrangères retirent ses contacts et détachent ses documents, qui restent dans la bibliothèque. Les styles de ces espaces sont dans `public/styles/collaboration.css`.

Les éléments du plan peuvent référencer les sources du patch par `patchSources`. La colonne `patch_line.source_key` garde une identité stable, indépendante du numéro de canal et conservée lors d’une copie. `StagePatchLinks` limite les choix à la fiche courante, prépare l’affichage et adapte les liens lors des imports. La publication ne conserve que les repères déjà résolus : elle ne relit pas le patch actuel. Les mêmes symboles SVG sont utilisés dans le navigateur et dans le PDF.

## Présenter le code

PHP-CS-Fixer applique le style Symfony au PHP. Prettier s’occupe de Twig, JavaScript, CSS et de la configuration. Quatre espaces, deux pour GitHub et Docker Compose ; viser 100 caractères par ligne et répartir les longs arguments pour faciliter la lecture.

```bash
composer cs:fix
npm run format
```

Relire les différences avant de les ajouter à Git. Réglages : `.editorconfig`, `.php-cs-fixer.dist.php`, `.prettierrc.json`. Markdown et fichiers privés sont exclus du formatage automatique.

## Vérifier une modification

```bash
composer quality
npm run format:check
composer audit --locked
```

| Commande ciblée | Vérifie |
| --- | --- |
| `composer cs:check` | Style PHP |
| `composer phpstan` | Types, valeurs absentes et usages Symfony/Doctrine |
| `composer lint` | Configuration Symfony, Twig et YAML |
| `composer test` | Résultats des règles et parcours |
| `npm run format:check` | Présentation des fichiers hors PHP |

PHPStan est au niveau **8 pour l’application et les scripts**, **5 pour les tests**. Il n’y a pas de liste globale d’erreurs masquées. Les tests utilisent leurs propres bases temporaires et des réponses Bandsintown et Google fictives ; ils ne touchent pas au compte réel.

## Contrôles GitHub

`.github/workflows/ci.yml` lance trois tâches sur `develop`, `staging` et `main`, ainsi que sur les demandes de fusion vers ces branches : présentation des fichiers, qualité PHP, puis tests avec migrations sur une base SQLite neuve.

Ouvrir **Actions** pour lire un échec. **Ces contrôles ne déploient pas alwaysdata.** Le bouton **Run workflow** permet aussi une exécution manuelle.

Le rapport `coverage-report` est conservé sept jours. En local, avec PCOV :

```bash
composer test:coverage
```

Ouvrir `var/coverage/html/index.html`. La couverture mesure les lignes exécutées, sans garantir l’absence de bugs. Aucun seuil minimal n’est imposé.

## Prendre le code en main

Pour suivre une modification du patch, lire `Controller/Patch/EditAction.php`, `Form/PatchLineType.php`, `Entity/PatchLine.php`, puis `Service/Patch/PatchLineFormHandler.php` sous `src/`, et `templates/patch/form.html.twig`.

Exercice possible, encore à réaliser : proposer le prochain numéro de canal à l’ajout, tout en conservant la vérification des doublons. Commencer par un test du calcul sur un patch vide, continu puis incomplet.

## To Do du groupe

`BandTask` appartient au groupe, sans relation avec un concert. `TaskManager` gère les écritures ; `TaskVoter` contrôle la modification et la suppression. Le responsable est un `MemberProfile`, y compris avant activation de son compte. Les formulaires limitent le choix au même groupe. Les mises à jour vérifient la révision et les suppressions incluent cette révision dans la requête SQL.

## Agenda Google

`CalendarReader` consulte la période demandée et conserve les réponses cinq minutes. Les vues Mois, Semaine et Liste utilisent FullCalendar Standard, sous licence MIT. La consultation est ouverte aux comptes internes du groupe ; le droit de publication est distinct et réservé aux musiciens. Le flux JSON vérifie les droits et limite chaque consultation à 62 jours ; l’ajout d’un concert invalide les différentes périodes en cache. `ConcertPublisher` ajoute volontairement un concert, après vérification du profil musicien. Les contrôleurs restent séparés par action. Le client Google et l’authentification sont remplacés par des doubles de test.

Le compte de service reçoit uniquement les droits accordés par le partage Google. La bibliothèque officielle `google/auth` gère sa signature et l’obtention du jeton. Les appels ne passent pas par le profiler Symfony. La clé reste hors de `public` et de Git.

L’identifiant envoyé à Google est calculé à partir du groupe, du concert et de `GOOGLE_CALENDAR_NAMESPACE`. Après un doublon HTTP 409, le client relit l’événement et vérifie son marqueur privé ; il ne fait jamais de mise à jour ou de suppression. L’identifiant d’installation doit rester stable. La synchronisation dans les deux sens est hors du périmètre actuel.

Les fichiers FullCalendar sont copiés dans `public/vendor/fullcalendar/` et versionnés pour être déployés sans Node.js sur le serveur. Notre code d’interface reste dans `public/scripts/calendar.js` et `public/styles/calendar-grid.css`. Après une mise à jour npm de FullCalendar, lancer `npm run calendar:assets`. La CI vérifie leur conformité au verrou npm avec `npm run calendar:assets:check`. Les fichiers tiers conservent leur licence et sont exclus de Prettier.

## Thèmes clair et sombre

`public/styles/tokens.css` définit les palettes sémantiques : fonds, texte, bordures, actions et statuts. Utiliser ces variables dans les styles des composants. Les illustrations du plan gardent leurs couleurs propres.

`theme.js`, chargé avant les feuilles de style par le composant Twig `theme/_head.html.twig`, applique le choix dès le premier affichage. La préférence `auto`, `light` ou `dark` est mémorisée sous `backlinebridge.theme` dans le navigateur. Sans JavaScript, l’interface claire reste utilisable. Aucun accès au compte ou à la base n’est nécessaire.

Le composant `theme/_switcher.html.twig`, placé hors de la navigation, affiche un switch accessible au clavier et un bouton de retour au mode automatique. `theme.css` gère sa capsule flottante et respecte la réduction des animations.

Le changement émet `backlinebridge:themechange` ; le calendrier y répond en mettant à jour sa palette. Les tests `npm run test:frontend` couvrent la mémorisation, les changements système, les onglets et le stockage désactivé. Ils sont également exécutés dans la CI.

## Vue d’ensemble du concert

`concert/show.html.twig` privilégie la réponse personnelle et les notes. Les sections secondaires utilisent des éléments HTML `details` : ouverture au clavier et fonctionnement sans JavaScript. `concert-details.js` ouvre la section visée par un lien direct ou un retour de formulaire. Les styles propres à cette page sont dans `concert-detail.css`. La présentation ne modifie ni les droits ni les données enregistrées.
