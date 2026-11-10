# Importer depuis Orfeo

Dans **Concerts → Importer des concerts → Depuis Orfeo**, les responsables, le manager et le booker peuvent récupérer les dates du groupe. Aucun changement n’est envoyé à Orfeo.

1. Choisir **À venir**, **Passées** ou **Toutes**, puis charger l’aperçu.
2. Cocher les représentations à récupérer. Pour une date déjà présente dans BB, vérifier le rattachement proposé ; si nécessaire, choisir le bon concert dans le sélecteur.
3. Laisser **Mettre aussi à jour les informations des concerts existants** décoché pour conserver leurs date, salle, ville, horaire et statut. Le cocher permet de reprendre ces cinq informations depuis Orfeo.
4. Choisir si les contacts doivent être ajoutés, puis appliquer la sélection.

L’aperçu expire après 15 minutes. Une modification concurrente oblige à le recharger. Un même couple projet / représentation ne crée pas de doublon lors d’un nouvel import. Plusieurs représentations d’un même projet produisent plusieurs concerts.

## Ce qui est repris

- Date, salle, ville, horaire s’il est connu et statut.
- Contacts rattachés au projet et coordonnées de l’organisateur, lorsqu’ils sont renseignés.
- Lien interne de suivi Orfeo et date du dernier import, dans le dossier du concert.

Les statuts Orfeo configurés comme **confirmés** deviennent **Validé**. Les autres restent **En attente**. Une option Orfeo ne rétrograde pas un concert déjà validé sur Bandsintown. Les représentations « toute la journée » conservent un horaire inconnu ; BB n’invente pas une heure de concert.

Les projets archivés, sans représentation datée ou sans salle / ville sont écartés avec un compteur. Une annulation est signalée dans l’aperçu, sans suppression ni import automatique.

## Ce qui reste dans BB

La fiche technique, le plan, la setlist, les conditions, les notes et l’enjeu du concert sont conservés. Les contacts saisis ou modifiés manuellement dans BB ne sont pas écrasés. Un contact supprimé manuellement n’est pas recréé par une actualisation de son lien existant.

Les contrats transmettent désormais uniquement les propositions utiles : hébergement, transport, repas et montants HT / TTC. Elles se consultent dans **Modifier les conditions → Propositions Orfeo** et ne remplacent aucune saisie sans validation. Les documents restent hors de cet import. Le lien **Vérifier les mises à jour Orfeo** recharge un aperçu ; il ne lance pas de synchronisation automatique.

## Configuration pour le développeur

Sur le Mac, utiliser **`.env.dev.local`** ; sur le serveur officiel, **`.env.local`**. Ces fichiers restent hors de Git.

```dotenv
ORFEO_API_KEY="cle-privee"
ORFEO_BAND_ID=1
ORFEO_SPECTACLE_IDS=61907,61908
ORFEO_CONFIRMED_STATUS_IDS=15962
ORFEO_TIMEZONE=Europe/Paris
```

Ces identifiants correspondent au compte Avee Mana vérifié lors de la mise en place. Sur une autre installation, vérifier l’identifiant du groupe BB, les spectacles et les statuts autorisés avant de les reprendre. Une liste de spectacles explicite empêche de récupérer les projets d’autres artistes du booker.

Vérifier la lecture sans créer de concert :

```bash
php bin/console app:orfeo:check 1 --env=dev --no-debug
```

Le résultat ne contient que des compteurs, sans clé ni coordonnées. Les lectures sont conservées cinq minutes dans un cache privé, et limitées à deux appels par seconde, sans rafale initiale de huit appels. L’import est désactivé en mode démo.

Référence : [documentation officielle de l’API Orfeo](https://orfeoapp.com/api/doc/).
