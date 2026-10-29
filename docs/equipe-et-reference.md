# Équipe, créateur et reprise de la référence

## Qui a créé la date ?

Les nouveaux concerts enregistrent le compte connecté qui les crée, qu'il s'agisse d'un responsable ou du tourneur. Son nom apparaît sur la liste des concerts et dans le dossier. Le nom est conservé comme trace de création et n'est pas modifiable par le formulaire du concert.

Les anciens concerts n'avaient pas cette information : ils affichent « Créateur non renseigné ». La migration n'attribue pas rétroactivement ces dates à une personne au hasard.

## Disponibilités personnelles

Les comptes **Responsable** du groupe constituent l'équipe attendue : pour Avee Mana, les quatre membres et Marty doivent donc chacun avoir ce rôle et leur propre compte. Romain conserve le rôle **Tourneur** : il consulte les réponses, sans être compté dans l'équipe à confirmer et sans répondre à la place d'un membre.

À la création d'un concert, toutes les réponses commencent à **À confirmer**, y compris celle du créateur. Chaque responsable peut choisir **Disponible**, **Indisponible** ou revenir à **À confirmer**, puis enregistrer sa réponse. Une précision facultative de 500 caractères maximum peut être ajoutée. Elle reste visible dans l'équipe, sans apparaître dans le dossier invité ou le PDF technique.

Le compteur indique les responsables inscrits disponibles sur le nombre de responsables inscrits. Il ne garantit pas que tous les membres réels du groupe ont déjà un compte. Un nouveau responsable rejoint aussi le suivi des concerts existants : il apparaît à confirmer, puis sa réponse est enregistrée lorsqu'il la saisit.

Un changement de **date** remet les réponses à confirmer et efface les précisions associées à l'ancienne date. Modifier seulement le lieu, le statut ou les notes conserve les disponibilités. Une réponse envoyée depuis un formulaire encore ouvert sur l'ancienne date est refusée. Le statut « Validé » du concert reste indépendant des disponibilités ; aucune disponibilité n'est déduite de ce statut.

Pour créer les comptes manquants, après avoir sélectionné PHP 8.4, utiliser la commande interactive existante :

```bash
php bin/console app:user:create --role=manager
```

Elle demande l'adresse e-mail, le nom et le mot de passe. Utiliser `--role=booker` pour Romain. Aucun mot de passe partagé ou compte de démonstration n'est ajouté à la base de développement.

## Reprendre une référence mise à jour

Dans le dossier technique d'un concert, **Récupérer la référence** ouvre une comparaison avec la fiche du même groupe. Cette action est réservée aux responsables.

Les sections sont indépendantes :

- patch des entrées ;
- plan de scène, positions, orientations et tailles comprises ;
- matériel à prévoir ;
- version, nom du groupe et notes techniques.

Ouvrir les sections pour examiner les deux versions, cocher celles à reprendre, puis confirmer le remplacement. Aucune section n'est présélectionnée. Seules les sections différentes sont proposées à l'import.

**L'import remplace intégralement les sections sélectionnées.** Il ne fusionne pas les adaptations locales avec la référence. Les sections non sélectionnées restent intactes, tout comme la date, le lieu, le créateur, les notes propres au concert, les conditions et les disponibilités.

Dans le patch, les lignes encore présentes au même numéro de canal conservent leur identifiant ; leurs champs reprennent la référence. Les nouvelles sources sont copiées avec un nouvel identifiant. Les sources absentes de la référence active passent dans la corbeille du concert : elles ne sont pas effacées définitivement. Les sources déjà archivées restent archivées.

L'aperçu contient une empreinte des deux versions techniques. Si l'une change avant la confirmation, l'import est bloqué et propose de recharger l'aperçu. Le traitement est enregistré dans une seule transaction.

Les publications déjà transmises aux salles, leurs PDF et leurs réponses restent figés. Une nouvelle invitation permet de transmettre un nouvel état du concert. Modifier la référence seule ne change jamais automatiquement un concert, passé ou à venir.

## Organisation technique

- `Concert` : auteur, nom enregistré et date de création, facultatifs pour les données historiques.
- `ConcertAvailability`, `AvailabilityStatus` et son repository : une réponse par concert et par personne, liée à la date concernée.
- `ConcertAvailabilityManager` : préparation des réponses, équipe attendue, réponse personnelle et remise à confirmer.
- `ConcertUpdater` : modification des informations et remise à confirmer lors d'un changement de date.
- `ReferenceImportPreview` et `ReferenceImporter` : comparaison et remplacement des sections choisies.
- `Controller/Concert/Availability/RespondAction` et `Controller/Concert/Reference/ImportAction` : contrôleurs à action unique.
- Les nouveaux droits du `ConcertVoter` s'appuient sur l'adhésion au groupe et sont vérifiés aussi sur les requêtes directes.

La migration `Version20261028133311` ajoute les colonnes d'auteur et la table des disponibilités sans reconstruire la table des concerts. Elle initialise les concerts existants à confirmer pour leurs responsables actuels. Les tests de migration vérifient la conservation des publications, invitations et réponses de salle en montée et en retour arrière sur une base de test.

L'application mobile, les notifications et Google Agenda restent des étapes ultérieures. Cette version collecte et présente les informations dans l'application sans envoyer de messages ni utiliser de service externe.
