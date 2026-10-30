# Branches et commits

Git conserve l’historique local ; un push l’envoie sur GitHub. La mise en ligne reste une opération séparée.

## Les trois branches durables

| Branche | Usage | Données utilisées pour essayer |
| --- | --- | --- |
| `develop` | Préparer les changements | Locales de développement |
| `staging` | Vérifier une version candidate | Fictives, séparées de l’instance réelle |
| `main` | Conserver les versions prêtes à publier | Réelles seulement sur l’instance officielle |

Au départ, les trois branches pointent sur la même version vérifiée. **Une branche ne crée ni site ni base de données.** Changer de branche sur le Mac ne change pas automatiquement son fichier SQLite. Ne pas essayer une migration risquée sur la seule copie de données réelles.

Parcours : `feat/...` ou `fix/...` → `develop` → `staging` → `main`. La démo de portfolio peut conserver une version stable de `main`, indépendamment du travail en cours.

## Travailler sur un changement

```bash
git switch develop
git pull --ff-only origin develop
git switch -c feat/notes-concert
# Modifier puis vérifier les fichiers.
composer quality
npm run format:check
git status --short
git diff
git add chemin/du/fichier-modifie
git diff --cached
git commit -m "feat(concerts): ajoute les notes pratiques"
git push -u origin feat/notes-concert
```

Sur GitHub, ouvrir une **Pull request** vers `develop` : elle regroupe la proposition et ses contrôles. Fusionner après les contrôles verts. Puis ouvrir `develop` vers `staging`, essayer la version candidate avec des données fictives, et ouvrir `staging` vers `main`.

Garder l’historique des fusions entre les trois branches durables ; réserver éventuellement la fusion condensée aux branches de fonctionnalité. Ne pas forcer les pushes sur ces branches.

## Nommer les commits

Format : **`type(sujet facultatif): description courte`**. Décrire une seule intention, avec un verbe et sans point final. Les descriptions peuvent rester en français.

| Type | Usage | Exemple |
| --- | --- | --- |
| `feat` | Fonction ajoutée | `feat(bandsintown): valide les concerts importés` |
| `fix` | Bug corrigé | `fix(plan): rapproche les toms` |
| `refactor` | Organisation du code sans changement fonctionnel | `refactor(concerts): extrait la création dans un service` |
| `style` | Présentation du code | `style: applique les règles Symfony` |
| `test` | Tests | `test(auth): couvre les codes expirés` |
| `docs` | Documentation | `docs: clarifie la mise à jour alwaysdata` |
| `ci` | Contrôles GitHub | `ci: vérifie aussi staging` |
| `build` | Dépendances et outils de construction | `build: met à jour Dompdf` |
| `chore` | Entretien restant | `chore: complète les fichiers ignorés` |

Éviter `update`, `divers` et les commits mélangeant correction, nouvelle fonction et documentation sans lien. La convention est documentée ; aucun outil supplémentaire ne bloque actuellement les messages.

## Repérer une version livrée

Après validation de `main`, créer une étiquette de version. Exemple, en choisissant un numéro encore libre :

```bash
git switch main
git pull --ff-only origin main
git tag -a v0.2.0 -m "Version validée pour démonstration"
git push origin v0.2.0
```

L’étiquette retrouve le code exact ; la sauvegarde retrouve les données. Conserver les deux dans le journal des mises en ligne. Ne jamais déplacer une étiquette déjà partagée.

## Protéger les branches et le dépôt

Dans **Settings → Rules → Rulesets**, protéger `main` et `staging` : demande de fusion obligatoire, contrôles de la CI requis, suppression et push forcé interdits. Si tu travailles seul, ne pas exiger l’approbation d’une autre personne. Ces règles restent à activer dans GitHub ; la CI seule n’interdit pas un push direct.

Le dépôt est public au 30 octobre 2026. Le passer en privé est raisonnable pendant la préparation de l’usage réel ; cela ne remplace pas la protection du site. Un dépôt public reste possible pour le portfolio si aucun secret, document administratif ou export de données n’est publié. Avec GitHub Free, les règles de protection décrites ici concernent les dépôts publics ; pour les dépôts privés, vérifier l’offre Pro ou une offre adaptée. [Règles GitHub](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets).

Un `.gitignore` ne retire pas un secret déjà enregistré dans l’historique. Si cela arrive : révoquer la clé immédiatement, puis nettoyer l’historique avec une procédure dédiée.
