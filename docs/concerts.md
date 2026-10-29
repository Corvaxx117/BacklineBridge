# Concerts : une copie du patch pour chaque date

## Essayer le parcours

Depuis la navigation « Concerts », créer une date avec une salle et une ville. La fiche actuelle du groupe est copiée avec ses sources, ses préférences, ses notes et le libellé du document d'origine.

Dans le dossier du concert, modifier le microphone d'une source ou ajouter une entrée. Revenir à « Fiche du groupe » : sa référence doit rester identique. Modifier ensuite la référence : le concert déjà créé garde sa copie. Un nouveau concert partira de la référence actualisée.

Les dates passées sont acceptées pour pouvoir aussi saisir un concert déjà joué. La date représente un jour de concert, sans horaire ni conversion de fuseau.

## Suivre le code

1. `src/Controller/Concert/CreateAction.php` reçoit la requête et décide du rendu ou de la redirection.
2. `src/Form/ConcertType.php` construit le formulaire avec son jeton CSRF.
3. `src/Dto/Concert/CreateConcertInput.php` porte les données et leurs contraintes, avant toute création d'entité.
4. `src/Service/Concert/ConcertCreator.php` copie la fiche et ses lignes puis enregistre le concert. Le service ne dépend ni de Twig ni de la requête HTTP.
5. `src/Entity/Concert.php` possède une relation OneToOne vers son propre `TechnicalSheet`. La contrainte SQL unique empêche deux concerts de partager cette fiche.
6. `src/Controller/Concert/ShowAction.php` affiche ce patch, trié par canal via le repository existant.
7. `src/Controller/Concert/Patch/CreateAction.php` réutilise le formulaire et le service d'enregistrement des sources. `Controller/Patch/EditAction` retrouve le concert depuis la fiche de la source pour rediriger au bon endroit.

Une fiche sans concert représente ici une référence. Elle appartient désormais à un `Band`. `TechnicalSheetRepository::findReferenceForUser()` recherche une référence accessible à la personne connectée ; `findReference()` reste réservé au chargement initial. Le modèle des comptes et droits est expliqué dans `docs/authentification.md`.

## Pourquoi copier explicitement ?

Réutiliser les mêmes lignes aurait propagé les adaptations d'une salle à tous les concerts. Un simple `clone` PHP aurait encore partagé les objets de la collection. Le service construit donc de nouvelles entités avec de nouveaux identifiants et recopie tous les champs utiles, sans renuméroter les sources de la référence.

Un seul `flush()` enregistre le concert, sa fiche et ses sources dans une transaction Doctrine. En cas d'erreur SQL, il ne reste pas de dossier incomplet. Le test d'intégration provoque volontairement une erreur pour vérifier ce comportement.

La copie reste modifiable. Ce mécanisme n'est pas encore une publication de version immuable : le libellé du PDF indique l'origine de la copie, sans prouver qu'un patch adapté lui est toujours identique. Les réponses de la salle sont désormais attachées à une `TechnicalPublication` distincte, qui fige la copie de concert à sa date de publication.

## Migration

`php bin/console doctrine:migrations:migrate --no-interaction` ajoute la table `concert` et sa relation unique vers les fiches. Les sources déjà présentes restent en place. Cette migration conserve le choix SQLite du premier jalon.

Le retour arrière de cette migration supprime les concerts et leurs copies de fiches et de sources ; il conserve les références du groupe. Sauvegarder la base avant de l'utiliser sur des données à garder.

## Vérifications

`composer test` couvre le formulaire, les données invalides, la protection CSRF, le tri des concerts et des sources, l'ajout et la modification pour un seul concert, l'indépendance des copies dans les deux sens et l'annulation complète d'une création en cas d'erreur SQL. La base de développement n'est pas utilisée par les tests.

## Exercice à réaliser soi-même

Proposer automatiquement le prochain canal disponible dans le patch que l'on est en train de modifier. Le calcul doit fonctionner aussi bien sur la référence que sur un concert ; une source ajoutée au concert ne doit pas changer la proposition sur la référence. Conserver le choix manuel, la validation et la contrainte d'unicité.

## Prochaine étape produit

Les invitations limitées à une publication de concert, les réponses de la salle et les plans modifiables sont désormais disponibles. Le parcours est décrit dans `docs/salles-et-plan-de-scene.md`. L'acceptation formelle des alternatives par le groupe et l'envoi automatique d'e-mails restent à réaliser.
