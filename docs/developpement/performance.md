# Performances et fichiers publics

Les optimisations conservent les écrans et les droits existants. Les résultats avant/après sont dans le [rapport du 2 novembre](../audits/2026-11-02-performances.md).

## Après une modification

Les outils frontend et navigateur demandent **Node.js 22 ou plus récent**. Le serveur alwaysdata continue à utiliser PHP ; Node.js n’y est pas nécessaire.

```bash
composer cs:fix
npm run format
npm run assets
composer quality
npm run lint:frontend
npm run test:frontend
npm run test:browser
npm run format:check
```

`npm run assets` actualise les versions des CSS, scripts et icônes dans `public/asset-manifest.json`, ainsi que les imports des modules du plan. Conserver ces changements avec le code. La CI refuse un manifeste périmé avec `npm run assets:check`.

Après une mise à jour de FullCalendar, lancer d’abord `npm run calendar:assets`, puis `npm run assets`. Le calendrier livré regroupe les vues Mois et Semaine, le thème et le français. Le code généré de la bibliothèque reste dans `public/vendor/` ; les fichiers propres à BacklineBridge restent lisibles et séparés.

## Où se trouvent les changements ?

- `ConcertCard` et `ConcertRepository` préparent les cartes sans charger les dossiers techniques complets.
- `ReadRequestCache` conserve les lectures de droits uniquement dans la requête GET courante, par utilisateur et groupe. Les écritures et commandes relisent toujours la base.
- `ConcertViewBuilder` assemble le détail ; les contrôleurs restent à action unique. `PatchLineCopier` partage la copie des champs sans mélanger les règles de création et d’import.
- `branding/_styles.html.twig` choisit les feuilles par espace, dans l’ordre prévu. Les palettes sont toujours dans `tokens.css`.
- `stage/history.mjs` et `stage/geometry.mjs` isolent l’historique et les gestes. L’éditeur regroupe le dessin des déplacements par image.

## Les caches

Les pages et documents privés restent `no-store`. Seuls les fichiers publics munis de leur version peuvent être conservés par le navigateur. Lors d’un déploiement, le changement d’URL force la récupération des nouveaux fichiers.

Les jetons Google sont conservés dans `var/cache/<environnement>/private/google-tokens/`, protégé en accès. La bibliothèque Google contrôle leur durée de vie. Les erreurs de lecture de l’agenda restent en cache 20 secondes pour éviter les appels répétés ; les événements conservent leur durée de cinq minutes.

Les PDF sont conservés hors de `public/`, une heure maximum : 32 fichiers de 2 Mo au plus, soit 64 Mo. L’empreinte du dossier et la version des fichiers de rendu déterminent la copie utilisée. Les droits et liens invités sont toujours vérifiés avant lecture. Ce cache est désactivé en mode débogage.

Le compteur d’espace des documents est conservé 15 secondes pour l’affichage et invalidé lors des changements. Chaque ajout vérifie le total réel sur disque, sous verrou.

## Vérifier sans données réelles

Les tests PHP de `tests/Functional/Performance/` limitent le nombre de requêtes sur les concerts, documents, tâches et invitations. Ils utilisent leurs propres bases temporaires et ne comparent pas des durées dépendant de l’ordinateur.

`npm run test:browser` démarre un serveur local sur le port 8877 avec une base fictive neuve dans `var/browser/`. Les parcours couvrent le plan et la réponse salle, sur ordinateur et mobile. Le serveur de test refuse toute autre base. Les traces d’un échec restent dans `var/browser/report/`, hors Git. En local, Chrome doit être installé ; la CI installe Chromium. `BB_BROWSER_PHP` permet de choisir le chemin de PHP 8.4 si nécessaire.

La mise à jour du cache Apache sur un site existant passe par `php deploy/alwaysdata/update-asset-cache.php`, après la sauvegarde et le transfert du code. Le script préserve l’authentification de la démo et les autres protections, conserve une sauvegarde privée et remplace uniquement son bloc de cache. Voir les [étapes officielles](../exploitation/deploiement-officiel.md).

## Animations de l’interface

`motion.css` regroupe les retours visuels courts : apparitions, survols et flèches des sections. Les mouvements respectent le réglage système « Réduire les animations », sans bibliothèque supplémentaire.

Les sections de référence utilisent les éléments natifs `details` et `summary`, avec un raccourci « Tout déplier ». `technical-sections.js`, chargé seulement sur cette page, conserve les sections ouvertes dans l’onglet pendant la navigation. Les liens directs restent prioritaires. L’ouverture animée est réservée aux navigateurs compatibles ; le plan de scène ne reçoit aucune transformation supplémentaire.

[Animation performante](https://web.dev/articles/animations-guide)
