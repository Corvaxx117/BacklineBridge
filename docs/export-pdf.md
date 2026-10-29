# Télécharger une fiche technique

Le bouton **Télécharger la fiche PDF** est disponible sur le patch de référence, dans chaque dossier de concert et dans l'espace invité d'une salle.

Le document A4 paysage contient :

- le groupe, la version de la fiche et les informations publiques du concert ;
- le plan de scène enregistré, avec ses positions, rotations et tailles ;
- les sources actives du patch, dans l'ordre des canaux, avec les précisions par canal ;
- le matériel demandé, regroupé par catégorie, les quantités et les spécifications ;
- les notes techniques, les informations pratiques publiques du concert et les précisions complètes du plan.

Le PDF reprend les données enregistrées. Il faut donc enregistrer les modifications du plan avant de le télécharger. Les éléments retirés du patch et les conditions internes du concert (rémunération, paiement, transport, hébergement et notes privées des conditions) ne sont pas exportés.

## Accès et publications

Un membre disposant du droit de consulter la fiche peut la télécharger. L'autorisation est vérifiée à chaque téléchargement, y compris si l'adresse du PDF est ouverte directement.

Le PDF d'un invité reprend la publication datée liée à son invitation. Une modification ultérieure du dossier par le groupe ne change pas cette version : il faut publier et transmettre une nouvelle invitation pour partager un autre état. Le téléchargement vérifie la session invitée, l'expiration et la révocation du lien. Les réponses de la salle ne remplacent pas les demandes du groupe dans cette fiche ; elles restent consultables dans le suivi du concert.

## Organisation du code

- `src/Controller/TechnicalSheet/DownloadPdfAction.php` : téléchargement d'une fiche avec le voter existant.
- `src/Controller/Guest/DownloadPdfAction.php` : téléchargement de la publication autorisée pour l'invité.
- `src/Dto/Pdf/TechnicalDossier.php` : données techniques à exporter, sans les conditions internes.
- `src/Service/Pdf/TechnicalDossierFactory.php` : préparation depuis une fiche ou une publication figée.
- `src/Service/Pdf/StagePlanLayout.php` : disposition vectorielle, orientations et cadre du plan.
- `src/Service/Pdf/TechnicalPdfRenderer.php` : génération avec Dompdf, sans accès réseau ni exécution de JavaScript.
- `src/Service/Pdf/TechnicalPdfResponseFactory.php` : réponse de téléchargement, nom de fichier et en-têtes de confidentialité.
- `templates/pdf/` : document, styles et dessin SVG du plan, indépendants de l'interface interactive.
- `tests/Functional/Pdf/` et `tests/Unit/Service/Pdf/` : contenu, autorisations, publications figées et transformations.

Dompdf est installé par `composer install`. Il ne nécessite ni navigateur automatisé ni service tiers en production. Ses fichiers temporaires et caches de polices sont créés sous `var/cache/<environnement>/pdf/`, qui doit être accessible en écriture comme le cache Symfony habituel. Aucune migration de base de données n'est nécessaire pour cette fonctionnalité.
