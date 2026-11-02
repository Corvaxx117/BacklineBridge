# Comptes et accès

L’équipe utilise des comptes personnels. Les salles reçoivent un lien limité à un dossier technique.

## Première connexion

1. Ouvrir **Première connexion** et choisir son nom.
2. Entrer son code personnel, choisir un mot de passe et le confirmer.
3. Les fois suivantes, utiliser son adresse e-mail et son mot de passe sur `/connexion`.

Le mot de passe doit avoir **10 à 128 caractères, une majuscule, un chiffre et un symbole** (`!`, `?`, `@`…). Un espace ne compte pas comme symbole.

Le code est valable 30 jours et utilisable une seule fois. Un formulaire refusé ne le consomme pas ; ressaisir les champs confidentiels pour réessayer.

## Qui peut faire quoi ?

| Compte | Technique et plan | Concerts et conditions | Trésorerie et stock | Fiches administratives |
| --- | --- | --- | --- | --- |
| Quatre musiciens | Modifier | Modifier | Modifier | Toutes celles du groupe |
| Marty, ingénieur son | Modifier | Modifier | Aucun accès | La sienne |
| Romain, booker | Consulter | Modifier | Aucun accès | Toutes, en lecture |
| Salle invitée | Lire la version partagée et répondre | Aucun accès interne | Aucun accès | Aucune |

Musiciens et ingénieur son ont le rôle **Responsable**. Le profil **Musicien** ouvre en plus la trésorerie. Romain a le rôle **Booker**. Ces droits sont vérifiés sur le serveur.

Les six membres de l’équipe accèdent aussi au [To Do partagé](taches.md) pour créer et répartir les missions du groupe. Les salles invitées n’y ont pas accès.

Les rôles `editor` et `viewer` servent aussi aux essais : modification du patch sans suppression pour le premier, lecture pour le second, sans gestion des conditions privées.

## Mot de passe et code perdus

Pour changer son mot de passe, cliquer sur son nom dans l’en-tête. Le mot de passe actuel est demandé. Les autres navigateurs devront ensuite se reconnecter.

Pour renouveler le code d’un compte jamais activé, l’administrateur lance :

```bash
php bin/console app:member:renew-activation identifiant-public-du-profil
```

L’identifiant figure dans l’URL du profil. La commande écrit un nouveau fichier privé et invalide l’ancien code. Elle ne réinitialise pas un compte déjà actif. **La récupération d’un mot de passe oublié reste à implémenter.**

## Accès supplémentaire à la démo

La démo demande d’abord des identifiants communs, puis un compte personnel. Les accès sont conservés sur le Mac dans `var/private/alwaysdata/ACCES-DEMO.md`, hors Git. Cette protection commune concerne aussi les liens de salle.

Pour préparer les vrais comptes, suivre [l’initialisation privée](../exploitation/initialisation-groupe.md).

## Apparence

La capsule en bas à droite contient un **switch soleil/lune** pour passer du clair au sombre. Le bouton **écran** réactive le mode automatique ; il est coloré lorsque ce mode est actif. Ces commandes restent accessibles sur téléphone, sur la page de connexion et dans l’espace salle.

**Automatique** suit le thème du téléphone ou de l’ordinateur. Le choix est mémorisé dans ce navigateur, sans modifier le compte ; chaque appareil garde sa préférence. Les PDF utilisent leur présentation imprimable.
