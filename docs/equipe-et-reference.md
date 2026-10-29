# Disponibilités et fiche de référence

## Qui a créé le concert ?

Le nom de la personne connectée est enregistré à la création et affiché sur le concert. Les anciennes dates sans auteur indiquent « Créateur non renseigné ».

## Répondre à une date

Les quatre musiciens et Marty répondent chacun **Disponible**, **Indisponible** ou **À confirmer**. Ils peuvent ajouter une précision courte. Romain consulte les réponses ; il ne répond pas à leur place.

Toutes les réponses commencent à confirmer, y compris celle du créateur. Si la date change, elles reviennent à confirmer et les précisions de l'ancienne date sont retirées. Un changement de salle ou de notes conserve les réponses.

Le compteur porte sur les responsables qui ont un compte dans le groupe. Une date **Validée** ne confirme pas automatiquement leurs disponibilités.

## Récupérer une référence mise à jour

Depuis un concert, un responsable ouvre **Récupérer la référence** :

1. Comparer le dossier du concert avec la référence actuelle.
2. Choisir les parties à reprendre : patch, plan, matériel ou informations et notes techniques.
3. Confirmer le remplacement des parties cochées.

Exemple : reprendre uniquement le nouveau dessin de batterie conserve les adaptations du patch pour cette salle.

Les parties cochées sont remplacées. Les conditions, disponibilités, réponses de la salle et versions déjà partagées restent conservées. Si le dossier change pendant la comparaison, recharger l'aperçu avant de confirmer.

Le code principal se trouve dans `src/Service/Concert/` : `AvailabilityTracker`, `ConcertUpdater` et les services d'import de référence. Les contrôleurs sont rangés dans `src/Controller/Concert/Availability/` et `src/Controller/Concert/Reference/`.

Les notifications, Google Agenda et une application mobile restent à ajouter.
