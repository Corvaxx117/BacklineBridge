# Préparer un concert

1. Ouvrir **Concerts → Créer un concert**.
2. Renseigner la date, la salle, la ville et le statut.
3. Ajouter des notes pratiques si besoin.
4. Vérifier le patch, le plan et le matériel dans le dossier créé.

Le nom de la personne qui crée le concert est enregistré automatiquement. Les responsables et le tourneur peuvent créer et modifier les dates.

## Une fiche propre à chaque date

Un nouveau concert reçoit une copie de la fiche de référence du groupe. Modifier cette copie ne change ni la référence ni les autres concerts.

Exemple : la salle propose un autre ampli pour le concert de Lyon. Tu peux adapter le dossier de Lyon sans modifier celui de Nantes.

Si la référence évolue ensuite, **Récupérer la référence** permet de choisir les parties à reprendre. Voir [les disponibilités et l'import de référence](equipe-et-reference.md).

## Les informations du concert

- **Disponibilités** : chacun des quatre musiciens et l'ingénieur son répond pour lui-même.
- **Conditions** : hébergement, transport, rémunération et paiement, réservés aux responsables et au tourneur.
- **Notes pratiques** : informations utiles aux personnes qui consultent le dossier.
- **Invitations** : partager une version du dossier technique avec la salle.

Le statut **Validé** reste un choix de l'équipe : il n'est pas calculé à partir des disponibilités ou des réponses de la salle.

## Où intervenir dans le code ?

`src/Controller/Concert/` reçoit les actions de la page. `ConcertCreator` prépare la copie de la référence ; `ConcertUpdater` gère les modifications. Les formulaires et pages se trouvent dans `src/Form/` et `templates/concert/`.

Pour vérifier le parcours : `composer test`. Les tests contrôlent notamment que les copies restent indépendantes et qu'une création échouée ne laisse pas de demi-dossier en base.
