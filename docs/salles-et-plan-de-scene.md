# Préparer le dossier avec la salle

## Modifier le plan

Les quatre musiciens et Marty peuvent ouvrir **Modifier le plan**. Le plan est vu depuis le public.

- Cliquer sur un élément pour afficher sa barre d'outils.
- Le déplacer à la souris ; la poignée ronde le fait pivoter, la carrée change sa taille.
- Utiliser la barre pour préciser l'angle, le nom, dupliquer ou supprimer.
- Ajouter un élément depuis la palette.
- Utiliser **Annuler / Rétablir**, puis **Enregistrer le plan**.

Les flèches déplacent la sélection, Suppr la retire et Échap la désélectionne. Le menu « Sur la scène » aide à sélectionner un élément caché par un autre.

Chaque concert possède son propre plan et son matériel. Modifier la référence ne change pas automatiquement les dates déjà créées.

## Renseigner le matériel

Les équipements sont regroupés en catégories : batterie, amplis, basse, retours, etc. Les responsables peuvent ajouter un équipement, adapter sa quantité et ses précisions, ou créer une catégorie.

Les préférences d'amplis liées au voyage restent des demandes à discuter avec la salle. Le réglage mono ou stéréo de l'IEM de Sylou reste à confirmer.

## Partager le dossier

1. Ouvrir le concert et **Inviter la salle**.
2. Donner un nom au destinataire et choisir une durée de 7, 30 ou 90 jours.
3. Publier le dossier et copier le lien affiché.
4. Transmettre ce lien à la personne concernée. L'application n'envoie pas d'e-mail.

Le lien donne accès à la version du plan, du patch et du matériel publiée à ce moment. Il n'ouvre ni la trésorerie ni les conditions privées. Si le dossier évolue, publier une nouvelle version et un nouveau lien. Les anciens accès restent valables jusqu'à expiration ou révocation.

Créer un lien par interlocuteur. En cas de perte, révoquer l'ancien et en créer un autre. Toute personne qui possède le lien peut l'utiliser : le nom déclaré ne prouve pas son identité.

Un lien de la version locale ne fonctionne que sur le Mac. Celui de la démo hébergée fonctionne à distance, mais demande aussi les identifiants de la protection commune de la démo.

## Réponse de la salle

La salle choisit **À confirmer**, **Disponible**, **Alternative proposée** ou **Indisponible**. Une alternative demande une description. Les détails facultatifs permettent de préciser le modèle, les dimensions, la puissance ou la quantité.

**Tout disponible / Tout indisponible** complète les demandes encore à confirmer d'une catégorie, en conservant les exceptions. **Tout le groupe** permet de remplacer explicitement tous ses choix. La dernière action groupée peut être annulée avant une modification individuelle.

La salle peut enregistrer une réponse partielle, puis revenir avec le même lien. Le groupe la consulte dans **Réponses de la salle**. Un ancien formulaire ne peut pas écraser une réponse plus récente sans rechargement. Une alternative reste à valider avec le groupe.

Les contrôleurs sont dans `src/Controller/Guest/`, `Stage/` et `Equipment/`. Les interactions du plan et des formulaires sont dans `public/scripts/stage-editor.js`, `setup-editor.js` et `venue-reply.js`. Les droits sont aussi contrôlés en PHP.
