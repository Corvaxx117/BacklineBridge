# Garder le code lisible

Les contrôleurs sont rangés par fonctionnalité, avec une action par classe. Les traitements partagés vont dans `src/Service/` ; les templates affichent les données.

La présentation commune est définie dans `.editorconfig` et `.prettierrc.json` :

- quatre espaces, avec une exception de deux pour les fichiers GitHub et Docker Compose ;
- une largeur visée de 100 caractères ;
- des arguments, tableaux et attributs répartis sur plusieurs lignes quand cela aide la lecture.

Avec Node.js 22 ou plus récent :

```bash
npm ci --ignore-scripts
npm run format
npm run format:check
```

`format` remet les fichiers en forme ; `format:check` contrôle sans les modifier. Le contrôle est aussi exécuté sur GitHub. Les dépendances, fichiers privés et documents Markdown sont exclus du formatage automatique.

Le formateur aide à lire le code. Les tests vérifient son comportement : lancer `composer test` après une modification. Node.js sert aux outils du développeur ; le site hébergé fonctionne en PHP.
