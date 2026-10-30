# BacklineBridge

Préparer les concerts d’Avee Mana : dossier technique, plan de scène, échanges avec les salles, disponibilités, trésorerie et stock.

**[Démo privée](https://backlinebridge.alwaysdata.net)** — comptes et données fictifs, accès sur invitation.

## Démarrer

PHP 8.4, Symfony 8.1 et SQLite. Sur le Mac déjà installé :

```bash
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
composer start
```

Ouvrir http://127.0.0.1:8765. Pour une nouvelle machine, suivre [l’installation](docs/developpement/installation.md).

## Documentation

Commencer par **[l’index des guides](docs/README.md)**.

- [Utiliser les concerts et le dossier technique](docs/utilisation/concerts.md).
- [Comptes et droits](docs/utilisation/acces.md).
- [Importer Bandsintown](docs/utilisation/bandsintown.md).
- [Branches et messages de commit](docs/developpement/git.md).
- [Mettre la démo à jour, commande par commande](docs/exploitation/deploiement-demo.md).
- [Séparer démo et application officielle](docs/exploitation/demo-et-production.md).

## Avant un push

```bash
composer quality
npm run format:check
```

[Les contrôles](docs/developpement/code-et-qualite.md) utilisent des données fictives. **Un push ne déploie pas le site.**

Les bases, sauvegardes, mots de passe, clés API et documents administratifs restent hors Git. Les informations administratives sont chiffrées ; conserver leur clé avec les sauvegardes privées.
