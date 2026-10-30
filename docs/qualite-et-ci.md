# Vérifier le projet avant un push

Depuis le dossier du projet, avec PHP 8.4 :

```bash
composer quality
npm run format:check
```

La première commande vérifie le PHP et exécute les tests. La seconde vérifie la présentation de Twig, JavaScript, CSS et de la configuration.

## Quel outil fait quoi ?

| Outil | Son rôle | Commande |
| --- | --- | --- |
| PHP-CS-Fixer | Vérifier le style PHP selon les règles Symfony | `composer cs:check` |
| PHPStan | Repérer des erreurs de types et des valeurs potentiellement absentes | `composer phpstan` |
| Symfony | Vérifier la configuration et la syntaxe Twig/YAML | `composer lint` |
| PHPUnit | Essayer les parcours et vérifier les résultats | `composer test` |
| Prettier | Vérifier la présentation des autres fichiers | `npm run format:check` |

Pour remettre les fichiers en forme : `composer cs:fix` pour le PHP, `npm run format` pour les autres fichiers. Les corrections restent sur le Mac et peuvent être relues avant d'être enregistrées dans Git.

PHPStan utilise le **niveau 8 pour l'application et les scripts**, et le **niveau 5 pour les tests**. Il connaît Symfony, Doctrine et PHPUnit grâce à leurs extensions. Les tests peuvent monter progressivement au niveau 8 en précisant davantage leurs données de préparation. Aucune liste globale d'erreurs existantes n'est masquée.

Le contrôle utilise le cache Symfony de test et ses données de configuration fictives. Il ne demande ni accès à alwaysdata ni données administratives réelles.

## Ce que GitHub lance

Le fichier `.github/workflows/ci.yml` démarre après un push sur `main` ou `develop`, ou une demande de fusion vers l'une de ces branches. Le bouton **Run workflow** permet aussi de le lancer à la main.

Trois contrôles tournent séparément :

1. Présentation de Twig, JavaScript, CSS et de la configuration.
2. Style PHP, analyse PHPStan, configuration Symfony et vérification des bibliothèques connues comme vulnérables.
3. Création d'une base SQLite neuve, migrations, vérification des tables et tests avec rapport de couverture.

Les téléchargements Composer et npm sont conservés entre les exécutions pour aller plus vite. Les tests créent eux-mêmes leurs exemples : il n'y a ni PostgreSQL ni import des données privées du groupe dans la CI.

Dans GitHub, ouvrir **Actions**, puis une exécution. Un contrôle rouge indique l'étape à corriger ; ouvrir cette étape pour lire le message. Ces contrôles vérifient le code : ils n'envoient pas le site sur alwaysdata.

## Voir la couverture des tests

Le rapport indique les lignes PHP exécutées pendant les tests. Il aide à trouver les zones peu essayées ; un pourcentage élevé ne garantit pas à lui seul l'absence de bugs.

Sur GitHub, télécharger **coverage-report** depuis les fichiers proposés à la fin de l'exécution, puis ouvrir l'`index.html` du rapport. Le fichier `clover.xml` est fourni pour les autres outils. Les rapports restent disponibles sept jours.

En local, avec l'extension PCOV activée :

```bash
composer test:coverage
```

Le rapport se trouve dans `var/coverage/html/index.html`. La couverture est informative pour le moment ; aucun pourcentage minimal ne bloque la CI.
