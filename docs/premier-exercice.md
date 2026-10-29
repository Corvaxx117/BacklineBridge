# Un premier exercice sur le projet

## Essayer le parcours

Sur la version locale, ajouter une source au patch, modifier sa note, puis recharger la page. Vérifier que l'enregistrement est conservé et qu'un canal déjà occupé est refusé.

## Retrouver le traitement

Lire dans cet ordre :

1. `src/Controller/Patch/EditAction.php` : reçoit l'action de la page.
2. `src/Form/PatchLineType.php` : décrit les champs du formulaire.
3. `src/Entity/PatchLine.php` : représente une entrée et ses règles.
4. `src/Service/Patch/PatchLineFormHandler.php` : vérifie et enregistre.
5. `templates/patch/form.html.twig` : affiche les champs et les erreurs.

La vérification dans le formulaire donne un message compréhensible. La règle dans la base empêche aussi un doublon si deux personnes enregistrent au même moment.

## Évolution proposée : suggérer le prochain canal

À l'ouverture de « Ajouter une source », proposer le numéro suivant le plus grand canal utilisé. La personne doit pouvoir le changer.

| Patch actuel | Proposition |
| --- | --- |
| 1 à 22 | 23 |
| 1, 2, 30 | 31 |
| Vide | 1 |

Commencer par un test, placer le calcul hors du template, puis essayer le formulaire. Garder le contrôle des doublons : deux personnes peuvent recevoir la même proposition avant l'enregistrement.

L'exercice peut fonctionner sur la référence et sur un concert, avec un calcul propre à chaque fiche. Il reste à réaliser ; les comptes, invitations des salles et confirmations existent déjà.

Pour envoyer ensuite ce changement sur la démo, suivre [le guide de mise à jour](deploiement-alwaysdata.md).
