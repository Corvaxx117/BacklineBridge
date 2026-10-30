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
| `src/Integration/Bandsintown/` | Client API et lecture des réponses |
| `templates/`, `public/styles/`, `public/scripts/` | Pages, styles et interactions |
| `templates/pdf/`, `src/Service/Pdf/` | PDF et dessin du plan, avec Dompdf |
| `migrations/`, `tests/` | Évolution des tables et vérifications automatiques |

Les adaptations aux petits écrans sont regroupées dans `public/styles/responsive.css`. `navigation.js` gère le menu repliable ; `stage-viewer.js` cadre et zoome le plan sans modifier ses coordonnées enregistrées.

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

PHPStan est au niveau **8 pour l’application et les scripts**, **5 pour les tests**. Il n’y a pas de liste globale d’erreurs masquées. Les tests utilisent leurs propres bases temporaires et des réponses Bandsintown fictives ; ils ne touchent pas au compte réel.

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
