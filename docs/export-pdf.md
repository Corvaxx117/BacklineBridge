# Télécharger la fiche technique en PDF

Cliquer sur **Télécharger la fiche PDF** depuis la référence, un concert ou l'espace de la salle.

Le PDF contient le plan de scène enregistré, le patch, le matériel demandé et les notes techniques. **Enregistrer le plan avant de télécharger** pour retrouver la dernière modification.

Les conditions financières, l'hébergement privé, les disponibilités personnelles et les entrées mises à la corbeille ne sont pas inclus.

## Quelle version reçoit la salle ?

Le PDF de la salle reprend la version partagée lors de la création de son invitation. Si le groupe modifie le dossier depuis, créer une nouvelle publication et un nouveau lien pour partager ces changements.

Les réponses de la salle ne remplacent pas les demandes du groupe dans ce PDF. Elles restent visibles dans le suivi du concert. Un lien expiré ou révoqué ne permet plus de télécharger.

## Pour modifier le document

- `templates/pdf/` : présentation du PDF et dessin du plan.
- `src/Service/Pdf/TechnicalDossierFactory.php` : choix des informations à inclure.
- `src/Service/Pdf/StagePlanLayout.php` : positions et rotations sur le plan.
- `src/Service/Pdf/TechnicalPdfRenderer.php` : création du fichier avec la bibliothèque Dompdf.

L'export fonctionne sur le serveur sans navigateur ni service externe. Dompdf est installé avec Composer ; ses fichiers temporaires restent dans `var/cache/`. Il n'y a pas de changement de base à appliquer pour une simple retouche du PDF.
