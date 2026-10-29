# Présentation du code

Le formatage couvre `src/`, les tests, les migrations, les contrôleurs, les templates Twig, les scripts JavaScript, les styles et la configuration. Les contrôleurs restent organisés par fonctionnalité, avec une action par classe ; les traitements partagés restent dans des services dédiés.

Les conventions sont enregistrées dans `.editorconfig` et `.prettierrc.json` :

- quatre espaces, sans tabulations ; deux espaces pour Docker Compose et les workflows GitHub ;
- une largeur cible de 100 caractères, avec des retours à la ligne pour les arguments et les chaînes de configuration longues ;
- des blocs PHP développés, des tableaux espacés et une indentation régulière ;
- des objets Twig et des attributs HTML répartis pour rendre les formulaires lisibles ;
- des règles CSS et des fonctions JavaScript présentées sur plusieurs lignes.

Prettier utilise les extensions PHP et Twig. Le style des accolades PHP suit la convention PER prise en charge par l'extension. Le formatage ne remplace pas une analyse de code ou une revue des choix de conception.

## Utilisation

Avec Node.js 22 ou supérieur, depuis la racine du projet :

```bash
npm ci --ignore-scripts
npm run format
npm run format:check
```

`format` réécrit les fichiers pris en charge. `format:check` vérifie leur présentation sans les modifier ; ce contrôle est aussi présent dans la CI. Node.js sert uniquement à l'outillage de développement : l'application et l'export PDF fonctionnent en PHP.

Les dépendances, caches, fichiers d'environnement, références générées et fichiers de verrouillage sont exclus dans `.prettierignore`. Les documents Markdown restent rédigés manuellement pour éviter une modification générale de la documentation.

Après une modification, vérifier également le comportement concerné avec `composer test`. Le formateur ne garantit pas à lui seul qu'une expression Twig conserve le comportement souhaité : les conditions et calculs complexes ont leur place dans un service ou un objet de présentation.
