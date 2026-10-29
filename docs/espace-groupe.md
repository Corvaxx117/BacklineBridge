# Espace privé du groupe

## Accès

Les droits techniques (`BandRole`) restent distincts de la fonction de la personne (`MemberKind`). Un responsable technique dont le profil est `engineer` n’a pas accès à la trésorerie ni au stock. Ces deux espaces exigent une adhésion actuelle de responsable et un profil `musician` dans le même groupe. Un rôle seul ne suffit pas.

| Fonction | Technique | Concerts / conditions | Trésorerie / stock | Fiches administratives |
| --- | --- | --- | --- | --- |
| Musicien responsable | Gestion | Gestion | Gestion | Toutes celles du groupe |
| Ingénieur responsable | Gestion | Gestion | Aucun | La sienne |
| Booker | Lecture | Gestion | Aucun | Toutes celles du groupe, en lecture |
| Invité de salle | Publication invitée | Aucun accès interne | Aucun | Aucune |

Il n’y a pas d’inscription publique : le choix du nom propose uniquement les comptes prévus et en attente d’activation. Un profil sans coordonnées reste visible dans l’équipe, sans compte de connexion.

## Données privées et import

`PROFILE_DATA_KEY` contient 32 octets aléatoires encodés en 64 caractères hexadécimaux. La conserver dans un fichier d’environnement local ignoré par Git (par exemple `.env.dev.local`), avec des permissions restreintes. Ne jamais utiliser la clé des tests ni afficher cette clé dans un journal. Pour la production, utiliser un secret de déploiement dédié et `APP_DEBUG=0`.

Les champs administratifs sont chiffrés avec Sodium XChaCha20-Poly1305. L’identifiant public du profil est authentifié avec le contenu : un chiffrement ne peut pas être réaffecté à un autre profil. La clé doit être sauvegardée séparément de la base ; la perdre rend les fiches illisibles. Les codes d’activation ne sont conservés en base que sous forme de condensat.

L’import se lance localement, depuis un JSON privé situé hors des sources :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:band:import-private /chemin/prive/groupe.json --band=1
```

Format minimal fictif :

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
    "notes": ["Point à vérifier sans effet automatique sur les comptes"],
    "links": [{ "label": "Site", "url": "https://example.test" }]
}
```

Les soldes sont des centimes entiers. `quantity: null` signifie inconnu ; `needsVerification: true` conserve une quantité connue mais à vérifier. Les stocks sont communs, sans détenteur. Pour des produits de même titre mais de formats différents, utiliser des noms distincts.

L’import ne remplace pas les profils liés, mots de passe, produits ou comptes existants. `existingUserId` permet de rattacher explicitement un compte déjà membre du groupe, sans changer son adresse de connexion ni son mot de passe. Pour compléter un profil sans compte, reprendre son nom et ajouter son adresse dans le fichier privé, puis relancer l’import. Il ne s’agit pas d’un outil de modification des profils déjà importés.

Les documents d’origine, JSON privés, clés et codes personnels ne doivent jamais être placés dans `data/`, `public/`, des tests ou une migration. `var/` et les fichiers locaux d’environnement sont ignorés par Git.

## Première connexion

L’import indique le chemin d’un fichier `var/private/activation-codes-….json`, protégé par des permissions locales. Transmettre individuellement son code à chaque personne par un canal privé. Ne pas transmettre le fichier collectif. Le code expire après 30 jours ; il est consommé à la création d’un mot de passe robuste de 12 à 128 caractères avec confirmation.

Ouvrir `/premiere-connexion`, sélectionner son nom, puis saisir le code et le nouveau mot de passe. La connexion est automatique après activation. Les comptes possédant déjà un mot de passe continuent à utiliser `/connexion`.

Pour remplacer un code perdu ou expiré d’un compte jamais activé :

```bash
php bin/console app:member:renew-activation identifiant-public-du-profil
```

L’identifiant public se trouve dans l’URL de sa fiche. La commande indique le chemin d’un nouveau fichier privé et invalide l’ancien code. Elle ne réinitialise pas un compte actif.

## Trésorerie et stock

Une vente enregistre la quantité retirée et le montant total réellement encaissé dans le compte choisi. Un article au stock inconnu nécessite d’abord un inventaire. Un inventaire fixe le total ; un réassort ajoute une quantité ; une sortie offerte retire une quantité sans recette.

Une annulation de vente rétablit le stock et le solde en conservant le mouvement initial barré et son motif. Une opération qui rendrait un compte négatif est refusée. Les montants sont calculés en centimes, sans flottants. Les formulaires et versions ORM protègent les écritures concurrentes ; en cas de conflit, recharger la page avant de saisir à nouveau.

Les notes « à vérifier » n’ont aucun effet sur les soldes. Marquer une note vérifiée ne crée pas un remboursement. Le graphique couvre les six derniers mois et exclut les ouvertures, transferts et opérations annulées ; les montants sont aussi disponibles dans un tableau accessible.

## Code et vérifications

Les contrôleurs sont invokables et rangés par fonction dans `Controller/Finance`, `Controller/Merch` et `Controller/Member`. Les services `TreasuryManager`, `StockManager`, `AccountActivator` et `ProfileCipher` portent les traitements. Les nouvelles tables sont ajoutées sans reconstruire les tables existantes, avec les clés étrangères actives.

Les tests couvrent les restrictions par fonction, le retrait d’adhésion, les mouvements et annulations, les conflits de stock, l’import répété, les champs chiffrés, la lecture administrative du booker et l’activation avec code, confirmation et limitation des tentatives.
