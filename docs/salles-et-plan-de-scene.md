# Préparer un concert avec la salle

## Plan et matériel

La fiche du groupe et chaque concert affichent un plan de scène et une liste de matériel. Les responsables (les quatre membres et Marty) peuvent les modifier ; le tourneur les consulte, ainsi que les réponses de la salle.

Le plan reprend l'implantation Avee Mana 2026.3, vue depuis le public. L'IEM filaire de Sylou reste mono/stéréo à confirmer. **Modifier le plan** ouvre un éditeur visuel : cliquer sur un élément fait apparaître sa barre d'outils. Le déplacer à la souris, utiliser la poignée ronde pour le faire pivoter et la poignée carrée pour changer sa taille. La barre propose aussi la rotation par pas de 15°, un angle précis, le nom / les précisions, la duplication et la suppression.

Ajouter un élément depuis la palette par clic ou glisser-déposer. **Annuler / Rétablir** permet de revenir sur les manipulations avant l'enregistrement. Les flèches déplacent la sélection ; Suppr la retire et Échap la désélectionne. Le menu « Sur la scène » aide à sélectionner un élément masqué par un autre. **Enregistrer le plan** conserve le résultat ; un avertissement protège les changements non enregistrés si l'on quitte la page.

Rotation et taille sont conservées dans le plan et dans les nouvelles publications invitées. Les anciens plans et publications restent lisibles avec une rotation nulle et une taille normale par défaut. Aucune migration SQL n'est nécessaire : ces propriétés sont stockées dans les données JSON existantes.

Le matériel distingue les fûts, le hardware, le cab basse, les amplis, les supports, les retours et la façade. Le formulaire des responsables regroupe les équipements dans des catégories repliables, avec leurs icônes et compteurs. Chaque catégorie permet d'ajouter directement un équipement ; une catégorie peut aussi être créée ou changée depuis une ligne. Le dernier retrait peut être annulé avant l'enregistrement. Quantités et descriptions sont adaptables. Les amplis guitare conservent leur condition de voyage en train ou en avion : ces préférences ne deviennent pas des exigences permanentes.

Un nouveau concert copie le plan et le matériel de référence. Les modifications restent ensuite indépendantes, dans les deux sens.

## Inviter la régie

1. Ouvrir le concert puis **Inviter la salle** (responsables uniquement).
2. Nommer le destinataire et choisir une durée de 7, 30 ou 90 jours.
3. **Publier le dossier et créer le lien**, puis copier le lien affiché. Aucun e-mail n'est envoyé.
4. Transmettre le lien au destinataire. Il n'est affiché qu'à sa création ; en cas de perte, révoquer l'ancien accès et en créer un autre.

Toute personne possédant le lien peut consulter cette publication et enregistrer une réponse. Il s'agit d'un accès au porteur sans vérification par e-mail ; le nom déclaré n'est pas une identité authentifiée. Créer un lien par interlocuteur facilite le suivi. Expiration et révocation s'appliquent aussi à une session déjà ouverte.

Chaque invitation ouverte possède son contexte de session dans l'URL de l'espace salle. Ouvrir une autre invitation dans le même navigateur ne change ni la cible du formulaire, ni celle du PDF du premier onglet. Le bouton « Quitter cet accès » ferme uniquement cet accès. Le contexte d'URL ne donne aucun droit sans la session correspondante ; pour transmettre un accès à quelqu'un, utiliser le lien d'invitation d'origine. Après expiration de la session, rouvrir ce lien.

La publication contient les notes pratiques partagées, le plan, le patch et le matériel. Elle exclut les conditions privées d'hébergement, de transport et de rémunération. Une modification ultérieure ne change pas ce qui a été envoyé : publier un nouveau dossier pour partager la nouvelle version. Les anciennes réponses restent consultables et les anciens accès ne sont pas révoqués automatiquement. Un avertissement côté groupe signale les publications qui diffèrent du dossier courant.

**En local**, un lien `127.0.0.1` ne fonctionne que sur ce Mac. Pour travailler avec une salle distante, l'application devra être hébergée sur une adresse accessible en HTTPS. Ce jalon n'effectue aucun déploiement.

## Réponse de la salle

Sans inscription, la salle consulte le plan et choisit, pour chaque équipement et source : **À confirmer**, **Disponible**, **Alternative proposée** ou **Indisponible**. Les demandes sont regroupées dans des catégories repliables, séparées du patch, avec un compteur par catégorie et une progression d'ensemble.

Les boutons **Tout disponible / Tout indisponible** complètent par défaut uniquement les demandes **À confirmer** de la catégorie. Les exceptions déjà renseignées et toutes les précisions sont conservées. Le sélecteur **Tout le groupe** permet de remplacer explicitement les disponibilités de la catégorie. **Annuler cette action** rétablit les statuts précédant la dernière action groupée ; modifier ensuite un statut individuel termine cette possibilité d'annulation pour la catégorie. Aucun bouton groupé ne crée d'alternatives sans précisions.

Les quatre choix de disponibilité sont directement visibles sur chaque ligne. **Préciser le matériel** ouvre les champs facultatifs de modèle, dimensions, puissance et quantité. Choisir une alternative ouvre automatiquement ses précisions ; elles sont obligatoires côté serveur. Une alternative reste une proposition à discuter avec le groupe. Le compteur ne considère une alternative comme renseignée que lorsque sa description est complétée. Les sections contenant une erreur s'ouvrent lors du retour du formulaire.

La réponse peut être partielle, enregistrée puis complétée avec le même lien. Un nom ou une fonction et un message facultatif l'accompagnent. Le groupe la retrouve dans **Réponses de la salle**. Un formulaire ancien ne peut pas écraser une réponse plus récente sans être rechargé.

## Organisation du code

- `Controller/Guest/` : entrée, consultation / réponse et sortie, un contrôleur par action.
- `PublicationBuilder` : copie explicite des seules informations partageables et empreinte du dossier.
- `TechnicalPublication`, `VenueInvitation`, `VenueReply` : publication immuable, accès et réponse séparés.
- `InvitationIssuer` : jeton aléatoire de 256 bits ; seule son empreinte SHA-256 est enregistrée en base.
- `GuestAccess` : contrôle de l'accès à chaque requête ; redirection vers une URL sans jeton après l'entrée.
- `ReplyRecorder` : enregistrement et refus des formulaires anciens, complétés par une contrainte SQL et le verrouillage optimiste Doctrine pour les écritures concurrentes.
- `Controller/Stage/`, `Controller/Equipment/`, `Service/Stage/` : édition réservée aux responsables.
- `public/scripts/stage-editor.js` : sélection, déplacements, poignées, palette, historique local et préparation de l'enregistrement ; validations et autorisations restent côté serveur.
- `public/scripts/setup-editor.js` : catégories, ajout et retrait des demandes, annulation du dernier retrait dans le formulaire du matériel.
- `public/scripts/venue-reply.js` : actions groupées, annulation, progression et affichage des précisions côté régie ; aucune écriture avant l'enregistrement normal du formulaire Symfony.

Les accès ne créent ni utilisateur ni adhésion. Les pages invitées et celles affichant le lien ne sont pas mises en cache et n'envoient pas de référent. Le jeton initial peut apparaître dans l'historique du navigateur ou les journaux HTTP : masquer ce chemin dans les journaux du futur hébergement.

La migration initialise les nouveaux champs des fiches Avee Mana sans modifier les sources et conditions existantes. Son retour arrière supprime plans, matériel, liens et réponses : conserver une sauvegarde.
