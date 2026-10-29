# Vérifier le patch et les concerts

Après sélection de PHP 8.4 dans le terminal, lancer `composer test` depuis la racine du projet (PHP 8.4, extension SQLite).

Chaque test utilise une base SQLite temporaire neuve, nommée `backlinebridge-phpunit-<pid>-<aléatoire>.db`, supprimée en fin de test. `DatabaseTestCase` vérifie l'environnement et le nom de la base, crée les tables Doctrine puis charge `data/avee-mana-patch.json`. Les clés étrangères restent activées, y compris entre les requêtes HTTP. La fiche est insérée dans l'ordre inverse pour vérifier le tri réel du tableau. Les requêtes suivantes et les lectures après vidage de l'EntityManager vérifient la persistance.

Le socle crée également un groupe, un compte et une adhésion de responsable réservés aux tests. Les tests métier emploient `loginUser()` ; les tests de connexion partent sans session et utilisent le vrai formulaire et ses jetons CSRF. Les comptes et mots de passe de test ne sont jamais chargés dans la base de développement.

Le parcours couvre l'affichage des 22 canaux, la modification, la création avec champs facultatifs vides, les numéros dupliqués ou invalides, la protection CSRF, la stabilité de l'identifiant après renumérotation et l'unicité SQL par fiche. Les assertions HTML utilisent les formulaires et le tableau ; elles ne figent pas la présentation graphique.

Le parcours concert couvre la création, les erreurs de saisie et de CSRF, le tri des dates, l'ajout et la modification des sources pour une seule date et les URL inconnues. Les tests d'intégration vérifient tous les champs de la copie et l'indépendance entre référence et concerts après relecture en base. Un déclencheur SQLite provoque une erreur d'insertion pour vérifier qu'aucun concert ni aucune copie partielle ne subsiste après l'échec de la transaction.

Les notes facultatives sont vérifiées à la création et à la modification : persistance, effacement, limite de longueur, indépendance entre concerts, affichage du texte sans exécuter de HTML et maintien des sauts de ligne. Les responsables et le tourneur peuvent les rédiger ; les autres membres autorisés les consultent sans voir les conditions privées.

Le parcours de sécurité couvre la connexion réelle, les identifiants invalides, le CSRF de connexion/déconnexion, la limitation des tentatives et les droits des responsables, éditeurs et lecteurs. Un second groupe vérifie le filtrage des listes et le refus d'accès direct, en GET et POST. La suppression d'une adhésion vérifie sa révocation pendant une session active. La commande interactive est testée avec des entrées synthétiques et une relecture du hash enregistré.

SchemaTool vérifie ici les contraintes définies dans les entités. Les migrations doivent aussi être exécutées et contrôlées séparément sur une base vierge et sur une copie de la base existante avant livraison.

Les tests de concurrence vérifient le refus d'un formulaire concert dépassé, la conservation de ses valeurs affichées et des disponibilités récentes, ainsi qu'une course entre deux EntityManagers. Cette dernière vérifie que la condition SQL sur la révision annule aussi les modifications de disponibilités de l'écriture perdante. Les révisions absentes ou invalides sont refusées.

`SqliteIntegrityTest` vérifie l'activation des clés étrangères après reconnexion, le refus d'une disponibilité orpheline et les effets `CASCADE` / `SET NULL` lors de la suppression d'un compte. `GuestIsolationTest` conserve la même session pour ouvrir deux invitations : réponses, PDF et fermeture restent attachés à leur propre accès. Un formulaire envoyé au mauvais accès est refusé par son CSRF spécifique, et une URL de contexte sans sa session n'autorise rien.

Le suivi de l'équipe vérifie le créateur réellement connecté, les cinq responsables dont l'ingé son, les réponses personnelles, l'arrivée d'un nouveau responsable, les droits du tourneur, les données forgées et le CSRF. Un changement de date remet les réponses à confirmer et bloque les formulaires restés ouverts sur l'ancienne date.

L'import de référence vérifie la sélection explicite des sections, les aperçus périmés des deux côtés, la conservation des identifiants de canal, la corbeille des sources locales, les sections vides, les droits et l'isolation des groupes. Les publications, les réponses de salle, les conditions et les disponibilités restent indépendantes de l'import. `ConcertTeamMigrationTest` exécute aussi la migration et son retour arrière sur une base SQLite en mémoire avec des données liées existantes.

Le parcours salle vérifie la publication stable, l'exclusion des données financières, le jeton stocké uniquement sous forme d'empreinte, l'accès sans compte, l'expiration et la révocation en session. Les réponses partielles et leur modification, les alternatives décrites, les formulaires anciens, les données forgées et le CSRF sont couverts. Les tests du plan et du matériel vérifient les droits des responsables, l'isolation des groupes et des copies, les coordonnées invalides et l'ajout d'un équipement.
