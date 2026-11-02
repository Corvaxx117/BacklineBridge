# BacklineBridge — performances et maintenance

Revue du 2 novembre 2026. État local : branche `feat/setlists`, commit de base `ca7e615`, avec les setlists non encore commitées. Le code local et la production ne sont donc pas exactement à la même version.

**Avis : une amélioration progressive est pertinente. Les gains prioritaires viennent du cache des fichiers publics et des requêtes d’affichage. L’architecture générale peut être conservée.**

## Périmètre et méthode

La revue couvre PHP, entités et recherches Doctrine, formulaires, templates, JavaScript, CSS, configuration, migrations, déploiement et contrôles automatisés. Inventaire : 259 fichiers sous `src/`, 83 templates, 10 scripts et 13 feuilles CSS propres au projet. Les bibliothèques tierces sont examinées sous l’angle de leur chargement et de leur utilisation.

Les mesures utilisent **des bases SQLite entièrement fictives**, six comptes de test et un dossier comportant le patch, le matériel et le plan. Les jeux de données comprennent 5, 100 puis 500 concerts, répartis entre dates passées et à venir. Un autre jeu contient 100 documents, 100 tâches et 20 invitations. Aucun appel réel à Google ou Bandsintown n’a été effectué.

Chaque scénario a été rendu quatre fois avec `KernelBrowser` : premier passage écarté, médiane des trois suivants, objets Doctrine déchargés entre les passages. Environnement : PHP 8.4.20, Symfony 8.1.9, Doctrine ORM 3.7.5, débogage désactivé, middleware de comptage SQL, sans Xdebug ni OPcache CLI. Ces durées mesurent le traitement local ; elles ne représentent pas le temps total sur un téléphone ou la capacité du serveur alwaysdata.

Six écrans fictifs ont également été examinés dans Chrome à 390 px : connexion, concerts, fiche de référence, détail, agenda et éditeur du plan. Pas d’erreur JavaScript ni de débordement horizontal sur ces rendus. Les dix scripts passent la vérification de syntaxe. La dernière vérification complète, effectuée juste avant cette revue sur le code actuel, était verte : **436 tests PHP / 5 017 assertions, 10 tests JavaScript, PHPStan et contrôles de style**. Elle n’a pas été relancée pour cet audit, qui ne modifie pas le code applicatif.

En production, seuls deux fichiers publics ont été téléchargés pour lire leurs en-têtes HTTP : `app.css` et le JavaScript FullCalendar. Aucune connexion à un compte, aucun changement de données et aucun test de charge sur le site officiel.

## Mesures principales

| Scénario fictif | Requêtes SQL | Traitement local, médiane | Réponse brute |
| --- | ---: | ---: | ---: |
| Liste de 5 concerts | 23 | 8,45 ms | 14,8 ko HTML |
| Liste de 100 concerts | 165 | 44,54 ms | 120,2 ko HTML |
| Liste de 500 concerts | 765 | 214,34 ms | 566,0 ko HTML |
| Fiche de référence, jeu de 5 concerts | 22 | 20,90 ms | 128,2 ko HTML |
| Détail d’un concert, jeu de 5 concerts | 39 | 21,11 ms | 140,0 ko HTML |
| Bibliothèque de 100 documents | 211 | 49,32 ms | 194,1 ko HTML |
| Tableau de 100 tâches | 411 | 86,38 ms | 213,5 ko HTML |
| Page de 20 invitations | 32 | 14,51 ms | 32,4 ko HTML |
| Génération du PDF de référence | 5 | 311,21 ms | 47,0 ko PDF |

Les gros jeux rendent visibles les coûts qui augmentent avec le nombre d’éléments. Ils ne décrivent pas le volume réel du groupe. Les temps locaux restent faibles sur les petits jeux ; le nombre de requêtes offre un indicateur plus reproductible pour suivre une amélioration.

## 1. Priorité haute — permettre au navigateur de conserver les fichiers publics

**Constat vérifié sur le site officiel :** CSS et JavaScript reçoivent `Cache-Control: private, no-store`. Le modèle Apache applique cet en-tête à toutes les réponses, y compris les fichiers statiques. Le navigateur doit donc les récupérer à nouveau lors des navigations suivantes. La compression gzip fonctionne déjà.

Le fichier FullCalendar représente **870 520 octets bruts, 184 991 octets transférés** lors du contrôle de production. `app.css` représente 95 664 octets bruts, 15 130 octets transférés. Les liens actuels utilisent des adresses fixes.

**Amélioration :** réserver `no-store` aux pages et données confidentielles, aux documents et aux PDF. Ajouter une version aux URL des CSS, scripts et icônes, puis leur attribuer une durée de cache adaptée. Un changement de version doit immédiatement charger les nouveaux fichiers. Le composant Asset déjà présent dans Symfony peut gérer cette version ; un nouveau système de construction complet n’est pas indispensable. [Principe de versionnement documenté par Symfony](https://symfony.com/doc/current/frontend/custom_version_strategy.html).

**Gain attendu :** moins de transferts et une navigation mobile plus rapide après la première visite. Le gain exact dépend de la connexion ; il n’a pas été mesuré après correction.

**Fichiers :** [protection officielle](../../deploy/alwaysdata/production.htaccess.template), ligne 24 ; [protection démo](../../deploy/alwaysdata/private.htaccess.template) ; [base interne](../../templates/base.html.twig) ; [base salle](../../templates/guest/base.html.twig) et liens de fichiers dans les autres templates.

## 2. Priorité haute — éviter les mêmes recherches de droits dans une page

**Constat mesuré :** le détail d’un concert effectue 23 recherches dans `band_membership` parmi ses 39 requêtes. La bibliothèque de 100 documents en effectue 207 ; le tableau de 100 tâches, 407. Les templates appellent plusieurs fois les Voters pour chaque carte. Ces Voters relisent l’adhésion à chaque décision ; `MusicianAccess` la relit aussi après une première vérification.

**Amélioration :** calculer les permissions d’affichage communes une fois pour la page et réutiliser le résultat dans ses composants. Si un contexte de droits est introduit, sa durée doit rester limitée à la requête et il doit être isolé par utilisateur et groupe. Les contrôles serveur de chaque lecture protégée et de chaque écriture restent en place. Une révocation doit être effective dès la requête suivante ; aucun droit ne doit être conservé durablement en session ou dans un cache partagé.

**Gain attendu :** faire disparaître la majorité des requêtes répétées sur Documents et To Do sans changer les cartes, boutons ou rôles. Des tests doivent protéger les comptes musiciens, ingénieur son, booker, autres groupes et révocations.

**Fichiers :** [BandMembershipRepository](../../src/Repository/BandMembershipRepository.php), ligne 22 ; [BandVoter](../../src/Security/Voter/BandVoter.php) ; [MusicianAccess](../../src/Service/Security/MusicianAccess.php), ligne 22 ; [MembershipExtension](../../src/Twig/MembershipExtension.php) ; [cartes de documents](../../templates/document/_cards.html.twig) ; [cartes de tâches](../../templates/task/_card.html.twig).

## 3. Priorité haute — alléger la lecture de la liste des concerts

**Constat mesuré :** à 500 dates, la liste déclenche 501 recherches sur `band` et 258 sur `band_membership`. Elle charge aussi les colonnes JSON du plan, des notes et du matériel de chaque fiche, alors que les cartes n’en ont pas besoin. Le chargement de la fiche entraîne notamment une recherche sur son association inverse `referenceBand`.

**Amélioration :** créer une requête de lecture adaptée aux cartes : identifiant, date, salle, ville, statut, nom du groupe, auteur et disponibilité. Un petit objet de lecture typé peut porter ces valeurs sans charger chaque fiche technique complète. Préparer les permissions communes hors de la boucle. La recherche groupée des disponibilités existe déjà et mérite d’être conservée. Doctrine permet les sélections ciblées et les résultats dédiés à la lecture. [Documentation DQL](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/dql-doctrine-query-language.html).

Le plan SQLite actuel utilise un tri temporaire. Après modification de la requête, vérifier l’intérêt d’un index tel que `(band_id, date, id)` avec `EXPLAIN QUERY PLAN`. Son bénéfice doit être confirmé sur la forme finale de la recherche.

**Objectif de contrôle :** le nombre de requêtes de la liste doit devenir pratiquement indépendant du nombre de dates. L’affichage et le tri actuels restent identiques.

**Fichiers :** [ConcertRepository](../../src/Repository/ConcertRepository.php), ligne 21 ; [IndexAction](../../src/Controller/Concert/IndexAction.php) ; [carte concert](../../templates/concert/_card.html.twig) ; [TechnicalSheet](../../src/Entity/TechnicalSheet.php), lignes 93 et 96.

## 4. Priorité moyenne — découper le CSS et son chargement

`app.css` contient **4 611 lignes** et rassemble le socle commun, le plan, l’éditeur, le matériel, les comptes et la trésorerie. La base charge également les styles Documents, To Do et Agenda sur toutes les pages, ainsi que le lecteur du plan. Ces scripts s’arrêtent correctement lorsqu’ils ne trouvent pas leur composant ; leur transfert reste évitable.

Lors du relevé Chrome, environ **8 % des caractères de `app.css` étaient couverts sur la connexion**, 10 % sur Concerts et 32 % sur le détail. Cela décrit seulement les règles mobilisées dans ces rendus : **les autres règles ne sont pas du code mort à supprimer**. Le survol, les erreurs, les autres tailles d’écran et les thèmes nécessitent d’autres règles.

**Amélioration :** conserver un socle commun, puis extraire progressivement les styles du plan, de son éditeur, du matériel et des espaces métier. Charger chaque module sur les pages concernées. Garder la position des règles dans la cascade, leurs couleurs et leurs points de rupture ; les extractions doivent être accompagnées de comparaisons visuelles.

Le calendrier charge déjà ses gros fichiers uniquement sur sa page. Son paquet `all/global.js` pourrait ensuite être remplacé par un assemblage limité aux vues utilisées et une version de livraison minifiée. C’est une optimisation secondaire, après le cache navigateur.

**Fichiers :** [app.css](../../public/styles/app.css) ; [base](../../templates/base.html.twig) ; [responsive.css](../../public/styles/responsive.css) ; [copie des fichiers FullCalendar](../../tools/frontend/calendar-assets.mjs).

## 5. Priorité moyenne — préparer les réponses de salle en groupe

`VenueWorkspace` recherche une réponse pour chaque invitation et recalcule la même empreinte du dossier actuel à chaque tour. Avec 20 invitations, les mesures montrent 20 recherches sur `venue_reply` dans les 32 requêtes de la page.

**Amélioration :** charger les réponses en une recherche groupée, puis calculer une seule fois l’empreinte actuelle du concert. Comparer chaque publication à cette valeur, en conservant les copies historiques propres à chaque invitation. Ce changement reste local au service.

**Fichiers :** [VenueWorkspace](../../src/Service/Venue/VenueWorkspace.php), ligne 37 ; [PublicationBuilder](../../src/Service/Venue/PublicationBuilder.php).

## 6. Priorité moyenne — réduire les attentes possibles de Google Agenda

Les événements bénéficient déjà d’un cache de cinq minutes, et la publication possède une protection contre les doublons. Le jeton Google n’est conservé que dans l’instance PHP du service : une nouvelle requête qui doit contacter Google demande un nouveau jeton. Les délais configurés peuvent atteindre 8 secondes pour l’authentification puis 12 secondes pour un appel calendrier. Ce sont des plafonds du code, **pas des lenteurs observées sur Google pendant cet audit**.

**Amélioration :** conserver le jeton dans un cache serveur privé jusqu’à peu avant son expiration, avec une clé liée au compte de service et aux permissions demandées. La bibliothèque Google fournit [FetchAuthTokenCache](https://docs.cloud.google.com/php/docs/reference/auth/latest/FetchAuthTokenCache). Mettre brièvement en cache les échecs de lecture éviterait aussi qu’une indisponibilité provoque des appels répétés lors du chargement HTML puis JSON. Le message d’erreur et la possibilité de réessayer doivent rester disponibles.

**Fichiers :** [ServiceAccountTokenProvider](../../src/Integration/GoogleCalendar/ServiceAccountTokenProvider.php), lignes 15 et 34 ; [CalendarReader](../../src/Service/Calendar/CalendarReader.php), ligne 41 ; [page agenda](../../src/Controller/Calendar/IndexAction.php).

## 7. Optimisations à prévoir selon l’usage

- **PDF :** la génération représente environ 311 ms sur le Mac et est refaite à chaque téléchargement. Si les téléchargements deviennent fréquents, un cache serveur privé peut réutiliser le PDF pour une empreinte de dossier et une version de rendu données. Vérifier les droits, l’expiration et la révocation avant chaque lecture du cache ; limiter sa taille et sa durée. Garder la séparation entre fiche actuelle et publication historique. [TechnicalPdfRenderer](../../src/Service/Pdf/TechnicalPdfRenderer.php), ligne 23.
- **Graphique de trésorerie :** il charge les écritures complètes des six derniers mois puis additionne les montants en PHP. Une requête qui renvoie les totaux mensuels suffit au graphique. L’historique est déjà limité à 25 lignes par page. [TreasuryRepository](../../src/Repository/TreasuryRepository.php), ligne 69 ; [TreasuryView](../../src/Service/Finance/TreasuryView.php), ligne 35.
- **Quota des documents :** chaque affichage de la bibliothèque parcourt les fichiers du groupe pour recalculer leur taille. C’est simple et fiable avec peu de PDF. Un total courtement mis en cache pourrait aider avec beaucoup de fichiers, en gardant la vérification exacte sous verrou lors d’un ajout. [DocumentStorage](../../src/Service/Document/DocumentStorage.php), ligne 30.
- **Import Bandsintown :** l’aperçu charge toutes les entités Concert du groupe pour rapprocher date, salle et ville. Une sélection limitée à ces champs évitera du chargement inutile lorsque l’historique grandira. Le plafond d’import de 100 événements et le cache existant sont utiles. [ImportPreview](../../src/Service/Concert/Import/Bandsintown/ImportPreview.php), ligne 31.
- **Listes longues :** concerts, documents et tâches sont chargés en entier. Les requêtes ciblées amélioreront le fonctionnement actuel. Une pagination ou un chargement progressif demanderaient une décision d’usage supplémentaire, car ils changeraient la navigation ; ils ne font pas partie d’une refactorisation strictement invisible.

## Refactorisations pour faciliter les prochaines évolutions

Ces changements visent surtout la lisibilité ; leur gain de vitesse n’est pas établi.

1. **Déplacer `MusicianAccess` vers un espace de sécurité commun.** Il sert à la trésorerie, à l’agenda et aux setlists ; son emplacement sous Finance masque ce rôle transversal.
2. **Extraire progressivement l’état et les gestes de l’éditeur du plan.** Le script de 687 lignes mélange historique, sélection, déplacement, propriétés et formulaire. Des modules JavaScript simples suffisent. La limite de 40 éléments borne déjà le travail. Regrouper les mises à jour pendant un déplacement avec `requestAnimationFrame` est une piste à mesurer sur téléphone ; aucun blocage d’animation n’a été démontré ici.
3. **Rendre les modèles de lecture explicites.** Commencer par les cartes de concerts et les permissions de page. Alléger ensuite `Concert/ShowAction` avec un service qui prépare les données d’affichage : il assemble actuellement de nombreuses recherches et le formulaire de disponibilité.
4. **Rassembler la copie des champs du patch là où elle est identique.** La création d’un concert et l’import de référence recopient plusieurs mêmes champs. Conserver leurs règles distinctes sur l’identité des sources, les archives et les liens du plan.
5. **Harmoniser les conflits de formulaires au fil des modifications.** Les réponses 409/422 et la conservation des saisies doivent rester cohérentes. Extraire seulement les séquences réellement identiques ; les verrous de version et les suppressions SQL protégées ont des besoins différents.

Les snapshots destinés aux salles, les données PDF et les données privées doivent garder des listes de champs explicites. Les regrouper dans une sérialisation automatique générale rendrait cette séparation plus difficile à contrôler.

## Ce qui mérite d’être conservé

Les contrôleurs à action unique, les services métier, les formulaires séparés des entités et les Voters donnent une base claire. Les transactions stock/recettes, les versions contre les écrasements concurrents, les clés étrangères, les publications figées et les fichiers hors de `public/` répondent à des besoins réels. Les setlists séparées des données partageables suivent cette logique.

Les thèmes reposent sur des variables CSS, sans duplication complète du site. Le JavaScript reste ciblé, et plusieurs espaces fonctionnent sans lui. Le stockage SQLite reste cohérent avec ce petit outil partagé ; les requêtes observées se corrigent directement dans le code actuel.

La préparation de livraison optimise déjà l’autoload Composer et prévoit le cache Symfony. L’OPcache du serveur web reste à vérifier séparément : son état en ligne n’a pas été inspecté. Les [recommandations Symfony](https://symfony.com/doc/current/performance.html) fournissent la liste de contrôle adaptée ; la valeur CLI locale ne permet pas de conclure sur alwaysdata.

## Ordre de travail recommandé

| Lot | Travaux | Vérification principale |
| --- | --- | --- |
| 1 | Versionner les fichiers publics et corriger leurs en-têtes de cache | Deuxième visite sans retransfert des fichiers inchangés ; une nouvelle livraison charge immédiatement les nouveaux fichiers ; les données privées restent `no-store` |
| 2 | Préparer les droits de page et alléger la liste des concerts | Rejouer les jeux de 5/100/500 dates et de 100 documents/tâches ; stabiliser le nombre de requêtes ; tous les rôles et révocations conservent leurs règles |
| 3 | Découper et charger le CSS par espace | Comparaisons visuelles ordinateur/mobile, clair/sombre, erreurs et formulaires ; mêmes couleurs, espacements et interactions |
| 4 | Regrouper les réponses de salle et mettre en cache le jeton Google | Requêtes stables avec plusieurs invitations ; expiration/renouvellement du jeton ; comportements identiques en cas d’erreur |
| 5 | Simplifier l’éditeur et traiter les optimisations liées au volume | Tests ciblés sur déplacer/pivoter/annuler/enregistrer ; mesures avant de retenir les optimisations PDF et graphiques |

Pour les lots 2 à 5, ajouter des tests de **nombre de requêtes**, plus stables que des seuils en millisecondes en CI. Des objectifs initiaux à valider après implémentation seraient au plus 20 requêtes pour la liste et le détail, et 15 pour Documents et To Do, sur les jeux fictifs décrits ici. Ce sont des cibles proposées, pas des résultats déjà obtenus.

La CI vérifie actuellement la syntaxe de quatre scripts et teste surtout les thèmes et palettes côté JavaScript. Étendre la syntaxe aux dix scripts et ajouter quelques parcours navigateur du plan et de la réponse salle protégerait particulièrement bien les refactorisations prévues. Leurs tests PHP existants restent nécessaires.

Chaque lot peut faire l’objet d’une branche et d’une demande de fusion distinctes, avec les mêmes tests fonctionnels, puis le protocole habituel de préproduction, sauvegarde et déploiement. **Les constats ci-dessus précèdent les travaux. Leur mise en œuvre locale et sa validation sont décrites ci-dessous.**


## Mise en œuvre locale après l’audit

Les changements sont préparés sur `perf/audit-20261102`, en conservant les setlists en cours. Les optimisations n’ont pas encore été déployées sur alwaysdata.

| Mesure sur les mêmes données fictives | Avant | Après |
| --- | ---: | ---: |
| Liste de 5 concerts | 23 requêtes | 9 requêtes |
| Liste de 500 concerts | 765 requêtes | 9 requêtes |
| Détail d’un concert | 39 requêtes | 18 requêtes |
| Référence technique | 22 requêtes | 10 requêtes |
| Bibliothèque de 100 documents | 211 requêtes | 7 requêtes |
| Tableau de 100 tâches | 411 requêtes | 7 requêtes |
| Page de 20 invitations | 32 requêtes | 9 requêtes |
| Calendrier, fichier JavaScript compressé localement | 183,1 ko | 80,8 ko |
| Feuille commune `app.css` | 4 611 lignes | 1 782 lignes |

Le PDF servi depuis le cache privé demande environ 4 ms localement, contre environ 311 ms pour le rendu précédent ; le premier téléchargement doit toujours générer le document. Ces temps restent indicatifs et ne remplacent pas une mesure de production.

Ont aussi été mis en place : cache privé des jetons Google et des PDF, calcul mensuel de trésorerie en SQL, compteur d’espace documentaire courtement conservé, préparation allégée de l’import Bandsintown, services de lecture et de copie du patch, déplacement de `MusicianAccess`, modules d’historique et de géométrie du plan, contrôles des fichiers publics et parcours navigateur en CI.

Les listes gardent leur navigation actuelle. Les recherches ciblées suppriment le coût croissant des chargements inutiles ; une pagination reste une décision d’usage distincte. Aucun index spéculatif n’a été ajouté : les budgets de requêtes sont atteints avec le schéma existant. Les publications privées et leurs listes de champs explicites sont conservées. Les conflits existants restent couverts par leurs tests ; leurs séquences ne sont pas regroupées dans une abstraction générique qui masquerait les différences de verrouillage.

Les commandes de maintenance et les fichiers concernés sont expliqués dans le [guide Performances](../developpement/performance.md). La règle Apache a été vérifiée avec un serveur local isolé : seuls les fichiers statiques versionnés reçoivent la durée de conservation longue ; les pages et PDF privés gardent `no-store`.

Validation finale : **446 tests PHP et 5 101 assertions**, **13 tests JavaScript**, **4 parcours navigateur** (plan et salle sur ordinateur/mobile), PHPStan et contrôles de style passent. Les **56 comparaisons de styles et positions** sur 14 écrans, deux tailles et les deux thèmes sont identiques, sans débordement ni erreur JavaScript. Le contrôle Apache inclut aussi la revalidation HTTP 304. Les données métier locales existantes ont été conservées.

Le calendrier allégé a également été vérifié avec des événements fictifs communs aux vues Mois et Semaine : ouverture du détail et changement de thème validés à 390 et 1 440 px. Le serveur Apache local sert les modules `.mjs` en JavaScript et conserve les bonnes règles de cache, y compris pour les réponses 304.
