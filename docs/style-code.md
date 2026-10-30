# Garder le code lisible

Les contrôleurs sont rangés par fonctionnalité, avec une action par classe. Les traitements partagés vont dans `src/Service/` ; les templates affichent les données.

PHP-CS-Fixer applique les règles Symfony au PHP, avec quatre espaces et sans corrections risquées. Prettier présente Twig, JavaScript, CSS et la configuration. Les deux outils travaillent sur des fichiers distincts.

Les réglages sont dans `.editorconfig`, `.php-cs-fixer.dist.php` et `.prettierrc.json` :

- quatre espaces, avec une exception de deux pour les fichiers GitHub et Docker Compose ;
- une largeur visée de 100 caractères ;
- des arguments, tableaux et attributs répartis sur plusieurs lignes quand cela aide la lecture.

Avec Node.js 22 ou plus récent :

```bash
composer cs:fix
composer cs:check
npm ci --ignore-scripts
npm run format
npm run format:check
```

`cs:fix` et `format` remettent les fichiers en forme ; `cs:check` et `format:check` contrôlent sans les modifier. Le contrôle est aussi exécuté sur GitHub. Les dépendances, fichiers privés et documents Markdown sont exclus du formatage automatique.

Le formateur aide à lire le code. Les tests vérifient son comportement : lancer `composer test` après une modification. Node.js sert aux outils du développeur ; le site hébergé fonctionne en PHP.

Les commandes complètes sont décrites dans [Qualité et CI](qualite-et-ci.md).
