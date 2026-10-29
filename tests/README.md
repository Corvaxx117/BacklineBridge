# Vérifier l'application

Depuis le dossier du projet, avec PHP 8.4 :

```bash
composer test
```

Chaque test utilise une base temporaire qui est supprimée ensuite. La base de travail et celle de la démo restent conservées.

## Lancer un parcours seulement

```bash
php vendor/bin/phpunit tests/Functional/Member
php vendor/bin/phpunit tests/Functional/Concert
php vendor/bin/phpunit tests/Integration/Deployment
```

Les dossiers regroupent les tests par fonction :

- `Functional/` : parcours depuis les formulaires, connexion, droits, concerts, invités, trésorerie et PDF.
- `Integration/` : enregistrement réel dans une base de test, copie des dossiers, changements de tables et préparation du déploiement.
- `Unit/` : règles isolées, comme le mot de passe, le placement du plan et la lecture des réponses Bandsintown.

Les tests vérifient aussi les champs vides, les doublons et le refus d'écraser une modification plus récente. Les données sont fictives et aucun test n'appelle le vrai compte Bandsintown.

Avant une mise en ligne, lancer aussi `npm run format:check` et essayer le parcours concerné sur le site. Pour un changement de tables, vérifier les migrations sur une copie des données avant de toucher à la démo.
