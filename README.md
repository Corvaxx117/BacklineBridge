# BacklineBridge

BacklineBridge aide Avee Mana à préparer ses concerts : fiche technique, plan de scène, matériel à confirmer avec la salle, disponibilités, trésorerie et stock de merch.

**Démo privée : https://backlinebridge.alwaysdata.net.** Elle fonctionne chez alwaysdata, même si le Mac est éteint. Ses comptes, concerts et montants sont fictifs.

## Travailler sur ce Mac

Ouvrir le dossier `BacklineBridge` dans VS Code, puis son terminal :

```bash
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
composer start
```

Ouvrir http://127.0.0.1:8765. Les changements faits ici concernent uniquement la version locale.

## Installer le projet sur une nouvelle machine

Il faut PHP 8.4.1 ou plus récent dans la branche 8.4, Composer et les extensions SQLite, mbstring, intl, XML et Sodium.

Depuis le dossier du projet, créer les clés locales une seule fois :

```bash
php -r '
if (!file_exists(".env.local")) {
    $configuration = "APP_SECRET=".bin2hex(random_bytes(32)).PHP_EOL;
    $configuration .= "PROFILE_DATA_KEY=".bin2hex(random_bytes(32)).PHP_EOL;
    file_put_contents(".env.local", $configuration);
    chmod(".env.local", 0600);
}
'
composer install
composer setup
php bin/console app:user:create --role=manager
composer start
```

`composer setup` prépare les tables et la fiche de départ. La base locale se trouve dans `var/backlinebridge.db`. La création de compte demande une adresse, un nom et un mot de passe ; aucun compte n'est fourni par défaut. Les comptes des membres déjà importés s'activent depuis **Première connexion**.

## Avant de partager une modification

```bash
composer test
npm run format:check
```

Les tests utilisent leurs propres bases et conservent les données de l'application. Pour installer l'outil de présentation du code : `npm ci --ignore-scripts`. Pour corriger la présentation : `npm run format`.

**Envoyer sur GitHub ne met pas la démo à jour.** [Le guide alwaysdata](docs/deploiement-alwaysdata.md) explique l'envoi avec un exemple complet.

## Choisir un guide

| Besoin | Guide |
| --- | --- |
| Comprendre la mise en ligne et envoyer une modification | [alwaysdata](docs/deploiement-alwaysdata.md) |
| Se connecter ou changer son mot de passe | [Comptes et accès](docs/authentification.md) |
| Savoir qui peut faire quoi | [Équipe et tournée](docs/equipe-et-tournee.md) |
| Utiliser la trésorerie, le stock et les profils | [Espace groupe](docs/espace-groupe.md) |
| Préparer une date | [Concerts](docs/concerts.md) |
| Suivre les disponibilités et reprendre la référence | [Équipe et référence](docs/equipe-et-reference.md) |
| Inviter la salle ou modifier le plan | [Salle et plan de scène](docs/salles-et-plan-de-scene.md) |
| Importer les annonces publiques | [Bandsintown](docs/bandsintown.md) |
| Télécharger une fiche technique | [PDF](docs/export-pdf.md) |
| Voir les tables dans DBeaver | [Base de données](docs/base-dbeaver.md) |
| Comprendre l'organisation du code | [Présentation du code](docs/style-code.md) |
| Faire un premier exercice | [Exercice](docs/premier-exercice.md) |

## Retrouver le code

- `src/Controller/` : une classe par action, rangée par fonctionnalité.
- `src/Service/` : traitements partagés, comme créer un concert ou enregistrer une vente.
- `src/Entity/` et `src/Repository/` : données enregistrées et recherches dans la base.
- `src/Form/`, `src/Dto/` et `src/Validator/` : saisie et vérification des formulaires.
- `templates/` : pages ; `public/styles/` et `public/scripts/` : présentation et interactions.
- `migrations/` : changements de structure de la base ; `tests/` : vérifications automatiques.

Les bases, documents privés, clés et mots de passe restent hors Git. Les données administratives sont chiffrées : conserver leur clé `PROFILE_DATA_KEY` avec les sauvegardes.
