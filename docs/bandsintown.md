# Importer les concerts Bandsintown

L'import récupère les annonces publiques d'Avee Mana. Il se lance depuis **Concerts → Importer Bandsintown**. Rien n'est envoyé vers Bandsintown et aucune mise à jour automatique ne tourne en arrière-plan.

## Brancher le compte artiste

Récupérer la clé dans Bandsintown for Artists, **Settings → General → Get API Key**. Sur le Mac, compléter `.env.dev.local` :

```dotenv
BANDSINTOWN_BAND_ID=1
BANDSINTOWN_ARTIST_ID=15466975
BANDSINTOWN_APP_ID="votre-cle-personnelle"
```

Le premier nombre désigne le groupe dans BacklineBridge ; le second est l'identifiant public d'Avee Mana. La clé reste privée, hors du code et de GitHub. Sans clé, l'import reste désactivé. La démo hébergée ne possède pas de clé réelle.

## Importer une sélection

1. Un responsable ou le booker choisit les dates passées, à venir ou toutes.
2. Lire l'aperçu, valable 15 minutes.
3. Cocher les concerts à ajouter, ou rattacher une annonce à une date déjà saisie.
4. Choisir le statut des nouvelles dates et confirmer. Un envoi est limité à 100 concerts.

La date, la salle et la ville remplissent le nouveau concert. Les autres informations publiques disponibles restent dans un encart : heure du lieu, adresse, billets, description et lien d'origine. Les nouveaux concerts reçoivent une copie de la référence technique actuelle.

Les tarifs, conditions d'accueil et disponibilités personnelles ne viennent pas de Bandsintown.

## Si le concert existe déjà

Une annonce déjà importée n'est pas ajoutée une deuxième fois. L'aperçu peut proposer de la rattacher à un concert saisi manuellement pour le même jour, la même salle et la même ville.

Ce rattachement ajoute l'origine Bandsintown sans modifier le dossier interne. Une annonce modifiée plus tard sur Bandsintown ne remplace pas automatiquement les informations dans BacklineBridge.

Le code se trouve dans `src/Integration/Bandsintown/` et `src/Service/Concert/Import/Bandsintown/`. Les tests utilisent des réponses fictives et n'appellent pas le compte réel.

[Obtenir une clé artiste](https://help.artists.bandsintown.com/en/articles/7053475-what-is-the-bandsintown-api) · [Documentation officielle](https://help.artists.bandsintown.com/en/articles/9186477-api-documentation)
