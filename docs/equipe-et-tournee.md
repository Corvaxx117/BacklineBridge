# Équipe, tournée et corbeille

## Comptes de l'équipe

Les quatre membres d'Avee Mana et Marty ont le même rôle `manager` (Responsable). Plusieurs responsables sont autorisés dans un même groupe ; aucun compte n'a de privilège lié à son nom ou à son identifiant.

Depuis le dossier du projet, créer chaque compte personnel avec :

```bash
symfony console app:user:create --role=manager
```

La commande demande une adresse, un nom et deux saisies masquées du mot de passe. Le premier compte peut être celui de Julien. Répéter pour les trois autres membres et Marty, avec des adresses distinctes. Aucune adresse ni aucun mot de passe n'est inventé ou chargé automatiquement.

Pour Romain :

```bash
symfony console app:user:create --role=booker
```

L'application dispose ensuite d'une connexion commune ; l'adhésion au groupe détermine les actions et les informations visibles. La création d'un compte par invitation avec choix du mot de passe dans le navigateur reste une évolution à réaliser. L'inscription publique libre n'attribue aucun droit au groupe.

## Droits appliqués

| Action | Responsable | Tourneur | Régie technique | Lecture seule |
| --- | --- | --- | --- | --- |
| Consulter le dossier technique | Oui | Oui | Oui | Oui |
| Ajouter ou modifier une source | Oui | Non | Oui | Non |
| Supprimer ou restaurer une source | Oui | Non | Non | Non |
| Créer et modifier un concert | Oui | Oui | Non | Non |
| Passer un concert en attente ou validé | Oui | Oui | Non | Non |
| Consulter les notes pratiques du concert | Oui | Oui | Oui | Oui |
| Rédiger ou effacer les notes pratiques | Oui | Oui | Non | Non |
| Consulter et modifier les conditions d'accueil et de rémunération | Oui | Oui | Non | Non |

Les décisions passent par `BandVoter`, puis `ConcertVoter` ou `TechnicalSheetVoter`. Les contrôleurs les vérifient avant de traiter un formulaire. Les interfaces utilisent les mêmes décisions pour afficher leurs actions. Un tourneur ne peut donc pas modifier le patch en envoyant directement une requête.

Le rôle est affiché près du nom. Pour le tourneur, les informations de tournée et les conditions viennent en premier ; le dossier technique est replié. Pour les autres profils, le dossier technique s'ouvre directement. Les données financières ne sont pas chargées ni affichées pour les profils techniques limités ou en lecture seule.

## Concerts et conditions

Le statut `pending` signifie « En attente » ; `validated` signifie « Validé ». Le tourneur et tous les responsables peuvent changer ce statut. Les concerts existants commencent en attente : leur validation ne peut pas être déduite des anciennes données.

Le formulaire général permet de modifier la date, la salle, la ville, le statut et les notes pratiques facultatives (2 000 caractères maximum). Ces notes s'affichent dans le dossier uniquement si elles sont renseignées, avec leurs sauts de ligne. Les vider retire la section. Elles sont propres à chaque date et visibles par toutes les personnes ayant accès au concert ; les accords confidentiels restent dans les conditions privées.

Le formulaire général transporte la révision du concert au moment de son ouverture. Si quelqu'un a modifié le concert entre-temps, l'enregistrement est refusé sans écraser les informations ni réinitialiser les disponibilités. Les valeurs saisies restent affichées et un lien permet de recharger la version actuelle. Un verrou optimiste Doctrine protège aussi deux enregistrements simultanés. Cette protection concerne les informations générales du concert ; les conditions disposent de leur formulaire distinct.

Les conditions sont saisies dans un formulaire distinct :

- hébergement sur place, ailleurs, non fourni ou à confirmer ;
- transport pris en charge, partiellement, à notre charge ou à confirmer ;
- montant convenu pour le concert en euros ;
- cadre de rémunération : facture association, cachets, autre ou à confirmer ;
- moyen de paiement : virement, chèque, espèces ou à confirmer ;
- rémunération de l'ingé son incluse, séparée, non prévue ou à confirmer, avec cadre propre (cachets, facture association, autre ou à confirmer) et montant facultatif ;
- notes pour préciser l'accord et les points à négocier.

Le cadre de rémunération et le moyen de paiement sont distincts. Un règlement en espèces n'indique pas à lui seul le cadre de l'accord ; les particularités se décrivent dans les notes. Les montants sont enregistrés en centimes entiers pour éviter les approximations des nombres flottants. Un champ vide signifie inconnu, et reste distinct de zéro. Aucun calcul de taxes ou de paie n'est effectué.

Les conditions sont attachées uniquement à leur concert et ne sont pas copiées lors de la création d'une autre date. Le statut validé de la date est indépendant de la confirmation du matériel et de la complétude des conditions.

## Suppression réversible des sources

Un responsable utilise « Supprimer » dans le patch. La source est retirée immédiatement du tableau et une action « Annuler » permet de la restaurer. La corbeille reste accessible pour chaque patch.

La suppression conserve la source et son identifiant. Elle ne renumérote pas les autres entrées et ne touche pas aux copies des autres concerts. Un nouveau concert copie uniquement les sources actives de la référence.

Le canal libéré peut être réutilisé. Si cela arrive, restaurer l'ancienne entrée est refusé tant que le canal est occupé. Aucune source n'est écrasée. L'index unique SQL inclut le marqueur `active` : `1` pour une source active, `NULL` dans la corbeille. Plusieurs anciennes sources d'un même canal peuvent ainsi être conservées.

Suppression et restauration nécessitent un POST, un jeton CSRF valide et le rôle de responsable dans le bon groupe. La corbeille n'expose pas d'action de suppression définitive.

La migration conserve les lignes et identifiants déjà présents. Son retour arrière supprime les nouveaux statuts et les conditions ; il refuse de s'exécuter si la corbeille contient des sources, afin de ne pas les réactiver ni les perdre silencieusement. Conserver une sauvegarde avant tout retour arrière.

## Pistes à décider ensemble

1. Une invitation des membres par lien permettrait à chacun de choisir son mot de passe dans le navigateur, sans passer par le terminal.
2. Une date limite de réponse pour les concerts en attente éviterait de conserver des options sans échéance.
3. Un acompte et une échéance de règlement rendraient les conditions plus utiles au suivi de tournée.
4. Un historique des changements de statut et de conditions permettrait de retrouver qui a validé quel accord.

Ces pistes ne sont pas encore implémentées dans ce jalon.
