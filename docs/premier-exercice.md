# Premier exercice : comprendre puis faire évoluer une source

## Préparer le terminal

Sur ce Mac, depuis le dossier du projet :

```bash
export PATH="/usr/local/opt/php@8.4/bin:$PATH"
php -v
```

La version affichée doit appartenir à PHP 8.4. Avec Symfony CLI, `symfony composer test` utilise également la version de `.php-version`.

## Manipuler le parcours existant

1. Ouvrir le patch et compter les 22 sources.
2. Modifier une note, enregistrer, recharger la page : vérifier que la modification est conservée.
3. Essayer d'ajouter une nouvelle source sur le canal 17 : vérifier le refus et le message du formulaire.
4. Ajouter une source en 23 sans microphone, pied ni note : vérifier que ces champs sont bien facultatifs.
5. Exécuter `composer test`. Les tests utilisent leur propre base, sans réinitialiser les données manipulées dans l'application.

## Suivre une requête

Lire dans cet ordre :

1. `Controller/Patch/EditAction::__invoke()` : retrouver la source à partir de son identifiant et traiter la requête HTTP.
2. `PatchLineType` : comprendre le formulaire associé à l'entité.
3. `PatchLine` : repérer les contraintes qui seront vérifiées.
4. `Service/Patch/PatchLineFormHandler::handle()` : suivre la validation, l’enregistrement et le traitement d’un conflit.
5. Revenir dans `EditAction` : suivre le message de succès, la redirection ou le rendu avec le statut adapté.
6. `templates/patch/form.html.twig` : retrouver le champ et l'erreur affichée.

Questions à pouvoir expliquer avec ses propres mots :

- Pourquoi le numéro de canal et l'identifiant de la source sont-ils deux choses différentes ?
- Pourquoi utiliser une contrainte SQL en plus de `UniqueEntity` ?
- Pourquoi le formulaire autorise-t-il un microphone vide ?
- Pourquoi rediriger après un enregistrement réussi ?
- Pourquoi le traitement partagé du formulaire est-il un service alors que le rendu et la redirection restent dans chaque contrôleur ?
- Qu'empêche le jeton CSRF ? Quelle différence avec une vérification des droits utilisateur ?

## Première évolution à réaliser : faciliter l'ajout

À l'ouverture de « Ajouter une source », proposer le prochain numéro libre après le plus grand numéro utilisé. La personne garde la possibilité de choisir un autre canal.

Exemples attendus :

- patch 1 à 22 : proposer 23 ;
- patch 1, 2, 30 : proposer 31 ;
- patch vide : proposer 1.

Cette aide ne remplace ni la validation ni la contrainte SQL. Deux personnes pourraient recevoir la même proposition avant d'enregistrer.

Méthode : écrire d'abord un test décrivant le comportement, chercher où placer le calcul, ajouter une requête si nécessaire, puis vérifier l'interface. Garder le calcul hors du template Twig.

Cette évolution n'est volontairement pas préimplémentée : elle constitue le prochain exercice sur cette base fonctionnelle.

## Suite du parcours

Le parcours concert est désormais disponible : `docs/concerts.md` explique comment chaque date reçoit sa propre copie du patch. L'exercice de proposition du prochain canal peut être réalisé sur la référence et sur les concerts. Les prochaines étapes produit sont les comptes et droits d'accès, puis les invitations et confirmations ; les retours et besoins matériels enrichiront ensuite les demandes.
