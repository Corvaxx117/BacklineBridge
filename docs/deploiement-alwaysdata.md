# Démonstration privée sur alwaysdata Free

L'instance de démonstration fonctionne indépendamment du Mac. Elle utilise PHP 8.4, le mode production de Symfony, une base SQLite persistante séparée et des identités, stocks et soldes fictifs. La fiche technique de départ vient des fichiers déjà présents dans `data/` ; ce n'est pas une copie de la base locale du groupe.

## Limites et responsabilité

L'offre Free annonce 1 Go de stockage, 256 Mo de mémoire, un quart de CPU et trois jours de sauvegardes. Seule l'adresse `compte.alwaysdata.net` est disponible pour le site. Elle est destinée à un usage personnel non lucratif : faire confirmer l'éligibilité avant de transformer cette démonstration en outil d'exploitation réelle des concerts et du merch. L'hébergeur gère le système ; les accès et mises à jour de BacklineBridge restent à notre charge.

Le contrôle des extensions et l'export PDF doivent réussir sur le serveur. Les vérifications sur le Mac ne remplacent pas ce contrôle. Garder le site désactivé dans **Web → Sites** pendant sa préparation.

## 1. Accès au compte

Créer l'offre **Free**, puis activer l'authentification à deux facteurs du compte d'administration. Dans **Accès distant → SSH/SFTP**, ajouter une clé publique dédiée à l'utilisateur voulu. La clé privée reste sur le Mac et hors Git. Les empreintes du serveur SSH sont affichées dans ce même menu : les comparer avant la première connexion. Ne pas désactiver le contrôle de l'identité du serveur.

Le compte de cette démonstration est `backlinebridge` :

```text
Hôte SSH : ssh-backlinebridge.alwaysdata.net
Utilisateur : backlinebridge
Adresse web : https://backlinebridge.alwaysdata.net
Répertoire du projet : /home/backlinebridge/backlinebridge
Racine web : /home/backlinebridge/backlinebridge/public
```

## 2. Envoyer les sources sans les données locales

Préparer une archive à partir d'une version relue du code et ajouter les dépendances de production installées depuis `composer.lock`. Aucun fichier `.env.local`, `.env.dev.local`, base SQLite locale, répertoire `var/`, `.git/`, `node_modules/` ou document administratif ne doit être envoyé. Les fichiers `.env` et `.env.test` versionnés contiennent seulement des valeurs par défaut et des valeurs fictives.

Pour un déploiement depuis Git, utiliser une révision qui contient les fichiers `deploy/alwaysdata/` et `data/demo/group.json`. Le compte GitHub ne donne aucun accès automatique à alwaysdata. On peut également transférer une archive par SFTP, sans donner l'accès au dépôt.

Créer un nouveau dossier `/home/backlinebridge/backlinebridge` et y placer les sources. Ne pas remplacer un ancien dossier d'application sans conserver une sauvegarde.

Dans **Environnement → PHP**, choisir la branche **8.4**, puis vérifier la version également en SSH :

```bash
cd /home/backlinebridge/backlinebridge
php -v
composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction
php deploy/alwaysdata/check.php
```

`--no-scripts` évite de démarrer Symfony avant la configuration de ses secrets. Si la mémoire du compte empêche Composer de terminer, installer les dépendances de production localement dans un dossier de livraison isolé, puis envoyer son `vendor/`. Node.js n'est pas nécessaire sur le serveur.

## 3. Préparer les secrets et fermer l'accès anonyme

Depuis ce dossier neuf, lancer :

```bash
php deploy/alwaysdata/prepare.php backlinebridge
```

Cette commande :

- refuse un dossier qui possède déjà une configuration, une base ou des fichiers privés ;
- génère deux clés distinctes pour Symfony et les profils, dans `.env.local`, avec des permissions `0600` ;
- configure `APP_ENV=prod`, `APP_DEBUG=0`, `APP_DEMO_MODE=1` et `var/demo.db` ;
- limite les hôtes HTTP au domaine du compte ;
- installe une authentification Apache devant toutes les pages, y compris le choix des profils et les invitations de salles ;
- conserve le mot de passe de cette première protection uniquement dans `var/private/demo-access.json`, jamais dans la sortie de commande.

La commande commence par fermer l'accès HTTP. Si sa préparation échoue en cours de route, garder le site désactivé et diagnostiquer l'erreur ; ne pas retirer la protection pour contourner le problème. Elle ne réinitialise pas une configuration déjà utilisée et ne doit jamais être lancée dans le dossier de développement du Mac.

La protection Apache utilise le fichier `var/private/.htpasswd`. Les sessions applicatives imposent des cookies HTTPS, HttpOnly et SameSite=Lax. Le bandeau de démonstration ne constitue pas une protection d'accès.

L'accès HTTP est refusé par la protection Apache avant toute demande d'identifiants. Le proxy alwaysdata fournit un en-tête `X-Forwarded-Proto` contrôlé ; la protection vérifie qu'il vaut exactement `https`, puis configure la variable CGI `HTTPS` pour Symfony. Le `%{HTTPS}` d'Apache reste à `off` derrière ce proxy, même sur une connexion HTTPS extérieure. Un contrôle réel avec un en-tête forgé en HTTP doit confirmer que le proxy le retire. Si une redirection HTTPS est activée dans le panneau alwaysdata, elle s'effectue en amont ; sinon l'adresse HTTP reste volontairement inaccessible.

## 4. Initialiser seulement les exemples

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-avee-mana
php bin/console app:band:import-private data/demo/group.json
php bin/console cache:clear --env=prod --no-debug
php bin/console doctrine:schema:validate
php -d memory_limit=128M deploy/alwaysdata/verify.php
```

Le jeu de démonstration fournit quatre musiciens, un ingénieur son et un booker, tous fictifs. Les comptes en `example.test` ne reçoivent pas d'e-mails. Les quatre musiciens disposent de la trésorerie et du stock ; l'ingénieur et le booker conservent leurs restrictions habituelles. Les clés et codes sont propres à cette instance.

Les codes d'activation sont dans le nouveau fichier `var/private/activation-codes-….json` indiqué par l'import. Consulter ces fichiers uniquement en SSH/SFTP. Attribuer un profil fictif à chaque testeur et lui transmettre individuellement son code. Chaque testeur crée son propre mot de passe sur `/premiere-connexion`. Le premier accès Apache et le compte BacklineBridge sont deux étapes différentes.

Créer ensuite un concert fictif depuis l'interface pour tester les disponibilités, les conditions et la publication vers une salle. L'import Bandsintown reste désactivé, sans clé réelle ni appel réseau.

## 5. Régler le site, puis vérifier

Dans **Web → Sites**, configurer le site :

| Champ | Valeur |
| --- | --- |
| Adresse | `backlinebridge.alwaysdata.net` |
| Type | PHP |
| Version | PHP 8.4, ou version par défaut du compte si réglée sur 8.4 |
| Répertoire racine | `/home/backlinebridge/backlinebridge/public` |
| HTTPS | Actif, avec redirection forcée depuis HTTP |
| Cache HTTP de l'hébergeur | Désactivé pour cette démonstration privée |

Activer le site une fois sa préparation terminée. Avant de transmettre le lien :

Lorsque le site initial pointe déjà vers `/home/backlinebridge/www/` et autorise les liens symboliques, une autre possibilité consiste à conserver ce réglage : renommer le dossier d'accueil initial pour le sauvegarder, puis créer un lien `www` vers `backlinebridge/public`. Vérifier d'abord que `www` ne contient que la page d'accueil par défaut. Les sources et données restent hors de la racine web ; revenir au dossier sauvegardé annule la bascule.

1. Une fenêtre privée sans identifiants doit demander l'authentification Apache ; `/connexion`, `/premiere-connexion` et `/styles/app.css` doivent tous être protégés.
2. Un mauvais mot de passe Apache doit être refusé. Avec le bon accès, la page de connexion doit afficher le bandeau de démonstration.
3. Les URL `/.env.local`, `/var/demo.db`, `/var/private/demo-access.json` et `/vendor/autoload.php` ne doivent jamais exposer leur contenu, même après authentification. Le dossier `public/` est la seule racine web.
4. Activer un profil fictif, vérifier ses droits et exporter le PDF avec le plan de scène. Relever la mémoire dans le tableau de consommation alwaysdata ; limiter les exports simultanés si nécessaire.
5. Vérifier les cookies `Secure`, `HttpOnly`, `SameSite=Lax`, l'absence de détails techniques en cas d'erreur et les en-têtes `Cache-Control: private, no-store`.
6. Tester également l'adresse HTTP : elle doit être refusée sans demander d'identifiants, ou rediriger vers HTTPS si cette redirection est configurée chez l'hébergeur.

Une erreur 500 Apache peut venir des permissions du fichier `.htpasswd` ou d'une directive non prise en charge : lire les logs Apache sans ouvrir l'accès anonyme. Les scripts PHP de préparation doivent rester hors de `public/`.

## 6. Sauvegarder et mettre à jour

Les données de l'instance sont dans `var/demo.db` ; les clés restent dans `.env.local`. Sauvegarder séparément la base et les secrets. Une copie cohérente de SQLite peut être créée avec son API de sauvegarde ou `VACUUM INTO`, sans copier à chaud le fichier ouvert par l'application. Ne jamais remplacer cette base par celle du Mac au détour d'un déploiement.

Pour une mise à jour : sauvegarder la base et les secrets, envoyer les sources relues sans écraser `.env.local`, `public/.htaccess` ni `var/`, appliquer les migrations, reconstruire le cache puis vérifier connexion, droits et PDF. Ne pas relancer `prepare.php` : cela changerait les clés. Les erreurs de production se diagnostiquent dans les logs privés, sans activer le debug public.

Les sauvegardes de l'hébergeur ont une rétention limitée. Garder une copie extérieure avant une mise à jour. La désactivation du site dans alwaysdata permet d'interrompre la démonstration sans supprimer ses données.

## Références

- [Offre Free et conditions](https://help.alwaysdata.com/fr/docs/admin-facturation/facturation/prix-cloud-public/)
- [Configuration de PHP](https://help.alwaysdata.com/fr/docs/hebergement-web/langages/php/configuration/)
- [SSH et empreintes du serveur](https://help.alwaysdata.com/fr/docs/hebergement-web/acces-distant/ssh/)
- [Protection par fichier .htaccess](https://help.alwaysdata.com/fr/docs/hebergement-web/sites/serveurs-http/fichier-htaccess/)
- [Configuration du serveur web Symfony](https://symfony.com/doc/current/setup/web_server_configuration.html)
