# Se connecter et gérer son mot de passe

## Première connexion

1. Ouvrir **Première connexion** et choisir son nom.
2. Saisir le code personnel reçu.
3. Choisir un mot de passe et le confirmer.

Le mot de passe doit contenir **10 caractères minimum, une majuscule, un chiffre et un symbole**, comme `!`, `?` ou `@`. La limite haute est de 128 caractères. Un espace ne compte pas comme symbole. Le même contrôle s'applique aux comptes créés par le terminal.

Le code est personnel, valable 30 jours et utilisable une seule fois. Après activation, se connecter avec son adresse e-mail et son mot de passe sur `/connexion`.

En cas d'erreur dans le formulaire, ressaisir le code et les deux mots de passe. Les champs sont vidés volontairement ; le code reste utilisable tant que l'activation n'a pas réussi.

## Changer son mot de passe

Une fois connecté, cliquer sur son nom en haut de la page. Saisir le mot de passe actuel, puis le nouveau et sa confirmation. Cela change uniquement son propre compte. La connexion reste ouverte dans ce navigateur ; les autres sessions devront se reconnecter.

Un mot de passe déjà enregistré reste valable après une mise à jour de l'application. La nouvelle règle s'applique au prochain choix de mot de passe.

## Les deux accès de la démo

La fenêtre d'accès à la démo demande un identifiant commun et son mot de passe. Ensuite, BacklineBridge demande le compte personnel. Cette première protection ferme l'ensemble du site aux visiteurs non invités ; elle est distincte du mot de passe de chaque compte.

Les accès de démonstration se trouvent sur ce Mac dans `var/private/alwaysdata/ACCES-DEMO.md`. Ce fichier est privé et absent de GitHub.

## Pour la personne qui gère les comptes

Créer un compte depuis le dossier du projet :

```bash
php bin/console app:user:create --role=manager
```

Utiliser `--role=booker` pour un tourneur. Cela crée un accès ; les profils de musiciens nécessaires à la trésorerie sont préparés avec [l'import privé](espace-groupe.md).

Pour renouveler le code d'un compte jamais activé :

```bash
php bin/console app:member:renew-activation identifiant-public-du-profil
```

Cet identifiant apparaît dans l'adresse de la fiche de la personne. La commande donne le chemin du nouveau code et invalide l'ancien. Elle ne change pas le mot de passe d'un compte actif. La récupération automatique d'un mot de passe oublié reste à ajouter.

Le code concerné se trouve dans `src/Controller/Member/`, `src/Service/Member/`, `src/Form/Member/` et `src/Validator/AccountPassword.php`.
