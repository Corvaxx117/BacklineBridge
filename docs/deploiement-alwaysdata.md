# Comprendre la démo et la mettre à jour

## Les trois copies du projet

| Emplacement | À quoi il sert |
| --- | --- |
| Le Mac, dans VS Code | Modifier le code et essayer les changements en local |
| GitHub | Garder l'historique du code et le retrouver ailleurs |
| alwaysdata | Faire tourner la copie accessible sur Internet, Mac éteint |

Enregistrer un fichier dans VS Code modifie le Mac. Un `git commit` garde une étape de travail ; un `git push` l'envoie sur GitHub. **Aucun de ces gestes ne met actuellement alwaysdata à jour.** L'envoi du site reste une opération séparée.

## Ce qui a été fait pour la première mise en ligne

1. Vérifier que le serveur propose PHP 8.4 et les fonctions utilisées par l'application.
2. Préparer une copie du code avec les bibliothèques PHP nécessaires. Les installer avant l'envoi évite de surcharger le petit compte gratuit.
3. Envoyer cette copie sur alwaysdata par **SFTP**, un transfert de fichiers protégé.
4. Créer sur le serveur une configuration et une base propres à la démo, avec six profils et des exemples fictifs. La base réelle du Mac n'a pas été envoyée.
5. Faire pointer l'adresse web vers `public/`, le dossier qui sert d'entrée au site. Le dossier `www` du compte renvoie vers ce dossier ; l'ancienne page d'accueil a été conservée.
6. Fermer l'accès aux personnes non invitées, utiliser HTTPS et vérifier les connexions, les droits et le PDF.

**SSH** permet de lancer une commande sur le serveur. **SFTP** permet d'y copier des fichiers. La clé de connexion est déjà préparée sur ce Mac dans `var/private/alwaysdata/` ; elle reste hors GitHub.

## Exemple : changer un texte dans la liste des concerts

Cet exemple convient à un petit changement dans un fichier déjà présent. Il montre aussi où lancer chaque commande.

### 1. Sur le Mac : modifier et vérifier

Dans VS Code, ouvrir `templates/concert/index.html.twig`, modifier le texte, puis regarder le résultat sur la version locale. Dans le terminal de VS Code, depuis le dossier du projet :

```bash
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
composer test
npm run format:check
git diff
```

Si tout est correct, enregistrer cette étape de travail :

```bash
git add templates/concert/index.html.twig
git commit -m "Clarifie le titre de la liste des concerts"
```

`git push` peut ensuite sauvegarder ce commit sur GitHub. Cette étape est indépendante de l'envoi chez alwaysdata.

### 2. Sur le serveur : sauvegarder avant de remplacer

Depuis le terminal du Mac :

```bash
ssh -F var/private/alwaysdata/ssh_config backlinebridge-demo
```

Le terminal commande maintenant le serveur. Y lancer :

```bash
cd /home/backlinebridge/backlinebridge
php deploy/alwaysdata/backup.php
cp templates/concert/index.html.twig var/private/concert-index-avant-maj-01.html.twig
exit
```

La commande PHP crée une copie cohérente de la base et des accès privés, dans un nouveau dossier daté sous `var/private/backups/`. Elle n'inclut pas le code ; `cp` conserve ici le fichier que l'on va remplacer. Si la sauvegarde échoue, s'arrêter. Pour une autre mise à jour, choisir un autre numéro que `01` pour conserver cette ancienne version.

`exit` ramène le terminal sur le Mac. Garder aussi une copie des sauvegardes hors du serveur pour pouvoir les récupérer si le compte devient inaccessible.

### 3. Sur le Mac : envoyer le fichier

```bash
sftp -F var/private/alwaysdata/ssh_config backlinebridge-demo
```

À l'invite `sftp>`, saisir :

```text
put templates/concert/index.html.twig /home/backlinebridge/backlinebridge/templates/concert/index.html.twig
bye
```

`put` copie le fichier du Mac vers le serveur. `bye` ferme le transfert. Le chemin de départ est local, celui d'arrivée est chez alwaysdata.

### 4. Sur le serveur : prendre en compte le nouveau fichier

```bash
ssh -F var/private/alwaysdata/ssh_config backlinebridge-demo
```

Puis :

```bash
cd /home/backlinebridge/backlinebridge
php bin/console cache:clear --env=prod --no-debug
exit
```

Le **cache** rassemble des versions préparées par Symfony pour répondre plus vite. Cette commande les reconstruit avec le code envoyé. Ouvrir ensuite https://backlinebridge.alwaysdata.net/concerts et vérifier le résultat.

Pour une correction CSS, transférer plutôt `public/styles/app.css` vers le même chemin dans le projet distant. Actualiser complètement la page si le navigateur affiche encore l'ancien style.

## Et pour une modification plus importante ?

| Modification | Ce qu'il faut prévoir en plus |
| --- | --- |
| Plusieurs fichiers PHP ou Twig liés | Sauvegarder et envoyer l'ensemble dans une courte interruption de la démo, puis reconstruire le cache |
| Nouvelle classe PHP | Actualiser aussi la liste des classes avec `composer dump-autoload --no-dev --optimize` |
| Nouvelle bibliothèque PHP | Envoyer `composer.json` et `composer.lock`, puis installer les versions prévues avec `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` |
| Nouvelle colonne ou table | Préparer et tester une **migration**, c'est-à-dire un script qui adapte les tables en conservant les données |

Pour une nouvelle bibliothèque, vérifier d'abord que Composer peut terminer avec la mémoire du compte. Sinon, préparer les bibliothèques dans un dossier séparé sur le Mac et envoyer ce dossier `vendor/`, comme lors de la première mise en ligne. Ne pas envoyer le `vendor/` de développement tel quel.

Après sauvegarde et envoi d'un changement de base, lancer sur le serveur :

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod --no-debug
php bin/console doctrine:schema:validate --env=prod
```

Pour ces changements liés, réserver un moment sans testeurs et suspendre le site dans **Web → Sites** pendant l'envoi. Le remplacement manuel d'un seul fichier convient à un petit correctif ; un envoi complet automatisé et préparé à l'avance serait la prochaine amélioration.

## Les fichiers à conserver sur place

Ne pas remplacer `.env.local`, `public/.htaccess` ni `var/` par leurs versions locales : ils contiennent les réglages, la protection d'accès et les données propres au serveur. Ne pas envoyer `.git/`, `node_modules/`, les bases du Mac ou les documents administratifs. Les fichiers d'exemple dans `data/` ne servent pas à remettre à zéro la démo lors d'une mise à jour.

**Ne pas relancer `prepare.php` ni les imports de départ pour mettre à jour le code.** Ils servent à l'installation initiale.

## Si le petit changement ne convient pas

Pour revenir au fichier sauvegardé dans l'exemple, se reconnecter en SSH et lancer :

```bash
cd /home/backlinebridge/backlinebridge
cp var/private/concert-index-avant-maj-01.html.twig templates/concert/index.html.twig
php bin/console cache:clear --env=prod --no-debug
```

En cas d'erreur 500, consulter le journal PHP privé `/home/backlinebridge/admin/logs/php/php.log`. Pour revenir après un changement de base, il faut traiter ensemble le code et la base ; remettre seulement un ancien fichier peut ne pas suffire.

## Repères

- Projet distant : `/home/backlinebridge/backlinebridge`.
- Base de la démo : `/home/backlinebridge/backlinebridge/var/demo.db`.
- Base du Mac : `var/backlinebridge.db`, indépendante de celle de la démo.
- Accès privés sur ce Mac : `var/private/alwaysdata/ACCES-DEMO.md`.
- Serveur de connexion : `ssh-backlinebridge.alwaysdata.net`, utilisateur `backlinebridge`.

[Transfert SFTP chez alwaysdata](https://help.alwaysdata.com/fr/docs/hebergement-web/acces-distant/sftp/) · [Étapes d'un déploiement Symfony](https://symfony.com/doc/current/deployment.html)
