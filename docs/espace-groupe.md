# Trésorerie, stock et profils

## Accès de l'équipe

Les quatre musiciens disposent de **Trésorerie & stock**. Marty et Romain n'y ont pas accès. Être responsable du dossier technique ne suffit pas : le compte doit aussi être lié à un profil de musicien du groupe.

Dans **Équipe & liens**, chacun peut lire sa fiche administrative. Les quatre musiciens et Romain peuvent consulter celles du groupe ; Marty voit seulement la sienne. Voir [le tableau des droits](equipe-et-tournee.md).

## Enregistrer du merch

- **Vente** : retirer la quantité vendue et ajouter le montant encaissé au compte choisi.
- **Réassort** : ajouter une quantité.
- **Inventaire** : fixer la quantité totale après comptage.
- **Sortie offerte** : retirer une quantité sans recette.

Un stock inconnu demande d'abord un inventaire. Annuler une vente rétablit le stock et le solde en conservant la trace de l'opération et son motif. Le stock est commun au groupe : il n'est pas réparti par détenteur.

## Suivre l'argent

La caisse physique et le compte de l'association ont chacun leur solde. Les opérations gardent leur auteur. Une opération qui rendrait un compte négatif est refusée.

Le graphique montre les recettes et dépenses des six derniers mois. Les soldes de départ, transferts et opérations annulées sont exclus du graphique. Le tableau permet aussi de lire les montants.

Une note « à vérifier » ne change aucun solde. La marquer vérifiée ne crée pas un remboursement.

## Préparer les profils et les données de départ

Cette opération sert à initialiser le groupe, pas à mettre le site à jour. Les exemples du dépôt sont fictifs. Les documents administratifs et les données réelles restent dans un fichier privé, hors du code partagé.

Définir d'abord une clé `PROFILE_DATA_KEY` propre à cette installation dans sa configuration locale. Elle permet de lire les données administratives chiffrées : conserver cette clé lors des mises à jour et des sauvegardes.

Depuis le dossier du projet :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:band:import-private /chemin/prive/groupe.json --band=1
```

Exemple de contenu fictif :

```json
{
    "bandName": "Mon groupe",
    "members": [
        {
            "name": "Membre exemple",
            "function": "Basse",
            "kind": "musician",
            "email": "membre@example.test",
            "administrative": { "Contact": "Informations privées" }
        }
    ],
    "accounts": { "cash": 10000, "bank": 50000 },
    "products": [
        {
            "name": "T-shirt exemple",
            "category": "T-shirt",
            "variants": [
                { "label": "M", "quantity": 10 },
                { "label": "XL", "quantity": null }
            ]
        }
    ],
    "notes": ["Point à vérifier"],
    "links": [{ "label": "Site", "url": "https://example.test" }]
}
```

Les montants sont en centimes : `10000` signifie 100 €. `quantity: null` signifie « stock inconnu ». `needsVerification: true` marque une quantité à vérifier.

L'import conserve les comptes, mots de passe, stocks et soldes déjà présents. Il peut compléter un profil sans compte en reprenant son nom avec une adresse e-mail ; `existingUserId` rattache explicitement un compte déjà membre du groupe. Il ne sert pas à modifier les fiches déjà importées.

Il crée un fichier privé `var/private/activation-codes-….json`. Donner à chaque personne uniquement son code, puis suivre [Première connexion](authentification.md). Ne pas partager le fichier collectif ni l'envoyer sur GitHub.

Les principaux traitements sont dans `src/Service/Finance/`, `src/Service/Merch/` et `src/Service/Member/`. Les formulaires refusent d'écraser une modification récente ; recharger la page si une autre personne a enregistré entre-temps.
