# Importer Bandsintown

L’import lit les annonces publiques d’Avee Mana. Il ne publie rien sur Bandsintown et ne tourne pas automatiquement.

## Configurer une fois par installation

Sur le Mac, renseigner `.env.dev.local` ; sur l’instance officielle, son `.env.local` privé :

```dotenv
BANDSINTOWN_BAND_ID=1
BANDSINTOWN_ARTIST_ID=15466975
BANDSINTOWN_APP_ID="cle-personnelle"
```

`BAND_ID` désigne le groupe dans BacklineBridge, `ARTIST_ID` celui de Bandsintown. La clé vient de **Bandsintown for Artists → Settings → General → Get API Key**. Elle reste hors Git et du navigateur. La démo fictive conserve l’intégration désactivée.

Vérifier la connexion sans créer de concert :

```bash
php bin/console app:bandsintown:check 1
```

Le résultat indique les nombres d’annonces disponibles, à venir et écartées. Sur le serveur officiel, ajouter `--env=prod --no-debug`. Aucun secret n’est affiché.

## Choisir les concerts

1. En responsable ou tourneur, ouvrir **Concerts → Importer Bandsintown**.
2. Choisir dates passées, à venir ou toutes, puis charger l’aperçu.
3. Cocher les dates à ajouter ou les annonces à rattacher à une date déjà saisie.
4. Confirmer la sélection, jusqu’à 100 concerts.

**Une annonce Bandsintown est considérée comme validée.** Un nouveau concert est donc validé ; rattacher une annonce valide aussi le concert existant, sans toucher à ses notes, conditions, disponibilités ou dossier technique.

Date, salle et ville servent à la création. Heure locale, adresse, pays, description, artistes et billets sont conservés dans l’encart d’origine. Chaque nouveau dossier technique part de la référence actuelle. Les conditions financières et disponibilités restent à renseigner séparément.

## Doublons et mises à jour

Une annonce déjà importée n’est pas recréée. Un rattachement est proposé si date, salle et ville correspondent sans ambiguïté. Plusieurs correspondances demandent une vérification manuelle.

L’aperçu dure 15 minutes et appartient au compte qui l’a demandé. Les réponses sont gardées cinq minutes pour éviter des appels répétés. Une annonce annulée, diffusée uniquement en ligne ou invalide est écartée.

Modifier ensuite l’annonce sur Bandsintown ne synchronise pas automatiquement le concert interne. Il n’y a pas d’import automatique des annulations.

[Clé artiste et conditions d’accès](https://help.artists.bandsintown.com/en/articles/7053475-what-is-the-bandsintown-api) · [API officielle](https://help.artists.bandsintown.com/en/articles/9186477-api-documentation)
