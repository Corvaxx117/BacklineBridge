# Installer et démarrer

Le site utilise PHP 8.4, Symfony 8.1 et SQLite. Node.js 22 ou plus récent sert uniquement aux outils de présentation du code.

## Ce Mac

```bash
cd /Users/Julien/Documents/Sites/Developpeur/BacklineBridge
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
php -v
composer start
```

Ouvrir http://127.0.0.1:8765. Garder le terminal ouvert ; `Ctrl+C` arrête le serveur local.

## Nouvelle installation

Installer Composer et PHP 8.4 avec SQLite, mbstring, intl, XML, ctype, iconv et Sodium. Cloner puis ouvrir le projet :

```bash
git clone https://github.com/Corvaxx117/BacklineBridge.git
cd BacklineBridge
git switch develop
```

Créer les clés locales **une seule fois**, avant d’importer des profils :

```bash
php -r '
if (file_exists(".env.local")) {
    exit("Le fichier existe déjà : conserver ses clés.\n");
}
file_put_contents(".env.local", "APP_SECRET=".bin2hex(random_bytes(32)).PHP_EOL
    ."PROFILE_DATA_KEY=".bin2hex(random_bytes(32)).PHP_EOL);
chmod(".env.local", 0600);
'
composer install
npm ci --ignore-scripts
composer setup
php bin/console app:user:create --role=manager
composer start
```

`setup` crée les tables et la référence technique de départ. Il n’importe pas les profils privés ni leurs soldes. Pour l’équipe réelle, suivre [l’initialisation](../exploitation/initialisation-groupe.md).

La base est `var/backlinebridge.db`. Conserver les clés existantes lors d’une réinstallation : une nouvelle `PROFILE_DATA_KEY` ne déchiffre pas les anciennes fiches.

## VS Code affiche d’anciennes erreurs

Ouvrir la palette avec `⌘⇧P` :

1. **Developer: Reload Window** recharge l’éditeur et ses extensions.
2. Si le PHP reste mal reconnu : **Intelephense: Index workspace**, puis attendre la fin de l’indexation.

La version d’Intelephense installée sur ce Mac propose l’indexation ; elle ne propose pas de commande distincte « Clear Cache ».

Pour des classes ou pages réellement modifiées, depuis le terminal :

```bash
composer dump-autoload
php bin/console cache:clear --env=dev
```

Ces commandes actualisent Composer et Symfony. Elles ne remplacent pas l’analyse de l’erreur si elle apparaît aussi dans `composer quality`.
