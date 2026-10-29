# Importer les concerts Bandsintown

L’import utilise l’API officielle de Bandsintown, pour le seul artiste configuré. Il fonctionne à la demande depuis **Concerts → Importer Bandsintown**. Aucune synchronisation en arrière-plan et aucun envoi vers Bandsintown ne sont déclenchés.

## Configuration locale

Dans Bandsintown for Artists, ouvrir **Settings → General → Get API Key** ou **Copy API Key**. La clé est liée à l’artiste autorisé. Ne jamais la placer dans le code, les tests, Git, une URL de navigateur ou un message.

Dans `.env.dev.local` pour le développement local, renseigner :

```dotenv
BANDSINTOWN_BAND_ID=1
BANDSINTOWN_ARTIST_ID=15466975
BANDSINTOWN_APP_ID="votre-cle-personnelle"
```

`BANDSINTOWN_BAND_ID` désigne l’identifiant du groupe dans BacklineBridge. `15466975` est l’identifiant public de la page Avee Mana fournie. Pour la production, utiliser des secrets de déploiement et désactiver le mode debug. La clé n’est jamais envoyée au navigateur. Le composant HTTP dédié est isolé du profiler, car l’API attend la clé dans le paramètre `app_id`.

Tant que la clé manque, l’interface affiche les instructions de connexion. Aucun appel réseau n’est effectué.

## Parcours

1. Un responsable ou le booker ouvre l’import et choisit les dates passées, à venir ou toutes les dates.
2. BacklineBridge récupère les annonces et affiche un aperçu valable 15 minutes, propre à cet utilisateur et à ce groupe.
3. Sélectionner les concerts à ajouter ou à rattacher à un concert déjà saisi. Le bouton de sélection globale s’arrête à 100 concerts.
4. Choisir le statut des nouveaux concerts, puis confirmer l’import.

Les nouvelles dates reçoivent un dossier technique indépendant, copié depuis la référence actuelle, y compris pour les concerts passés. Le créateur est la personne qui lance l’import. Le fonctionnement habituel des disponibilités est conservé.

La date, la salle et la ville alimentent le concert. Les autres informations publiques disponibles (heure locale annoncée, adresse, pays, titre, description, artistes, billets et lien de l’annonce) restent dans un encart Bandsintown distinct. Les notes internes, tarifs, conditions d’accueil et réponses de disponibilité ne sont pas fournis par cette API.

L’heure et le jour annoncés restent ceux du lieu : l’absence de fuseau horaire dans une réponse ne provoque pas de conversion implicite vers l’heure française.

## Doublons et protection des données existantes

L’identifiant Bandsintown est conservé avec l’artiste et le groupe, sous une contrainte unique en base. Un nouvel import n’ajoute pas de copie de la même annonce.

Pour une date saisie manuellement, la correspondance utilise le jour, le nom de salle et la ville, après normalisation de la casse, des espaces et de la ponctuation. Le rattachement est explicite dans l’aperçu : il ajoute les informations de source sans modifier la date, la salle, la ville, le statut, les notes, les disponibilités ni le dossier technique existants. Plusieurs correspondances ou un concert déjà associé à une autre annonce sont écartés et signalés.

Une modification ultérieure sur Bandsintown n’écrase pas le concert local. L’encart indique sa date d’import ; il correspond à l’annonce au moment de cette opération. La mise à jour sélective des annonces déjà importées pourra être ajoutée séparément.

Un lot est enregistré dans une seule transaction. Une collision concurrente annule le lot et invite à recharger l’aperçu. Les sélections venant du navigateur sont vérifiées à partir de l’aperçu conservé côté serveur ; elles ne peuvent pas ajouter une annonce arbitraire.

## Client API et limites

Le client Symfony utilise un hôte HTTPS fixe, sans redirections, avec des délais d’attente bornés. Un résultat est conservé cinq minutes ; un artiste introuvable est mémorisé 24 heures ; les erreurs temporaires sont limitées par un cache d’une minute. Aucun balayage d’autres artistes n’est prévu.

La réponse est normalisée avec une liste explicite de champs. Les liens non HTTPS sont écartés ; le paramètre `app_id` est retiré des liens de billetterie ; le lien vers l’annonce est reconstruit sans identifiant d’accès. Les annonces incomplètes, invalides, en ligne ou marquées annulées sont écartées avec un compteur dans l’aperçu.

Un chargement est limité à 2 000 annonces et 4 Mo. Un import est limité à 100 concerts. Les erreurs présentées restent génériques et ne reprennent ni l’URL contenant la clé, ni le corps brut de la réponse.

## Organisation du code

- `Integration/Bandsintown` : configuration, interface du fournisseur, client HTTP, normalisation et objets de données immuables.
- `Service/Concert/Import/Bandsintown` : aperçu, identité des concerts, stockage temporaire et transaction d’import.
- `Controller/Concert/Import/Bandsintown` : préparation et confirmation, chaque contrôleur étant invokable.
- `Entity/BandsintownConcert` : origine externe et informations publiques importées, distinctes des données métier du concert.
- `ConcertCreator::prepare()` : prépare un concert complet dans l’unité de travail ; `create()` conserve l’enregistrement immédiat utilisé ailleurs. L’import valide l’ensemble du lot en une seule fois.

Les tests utilisent des réponses fictives avec Symfony MockHttpClient. Ils couvrent les erreurs et la confidentialité, les dates locales, le cache, les droits par groupe, l’aperçu, la sélection, les doublons, le rattachement et le rollback. Aucun test n’appelle l’API réelle ou ne contient sa clé.

## Sources officielles

- [Documentation de l’API](https://help.artists.bandsintown.com/en/articles/9186477-api-documentation)
- [Obtenir la clé artiste](https://help.artists.bandsintown.com/en/articles/7053475-what-is-the-bandsintown-api)
- [Recommandations d’utilisation de l’API](https://help.artists.bandsintown.com/en/articles/13142424-optimizing-api-usage)
