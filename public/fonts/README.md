# Police de l’interface

Les grands titres et le nom de l’application utilisent **DM Serif Display Regular**, de Colophon Foundry, sous [licence libre SIL Open Font License 1.1](OFL-DM-Serif-Display.txt). Elle peut être utilisée et distribuée avec l’application, y compris pour un usage commercial, en conservant sa licence.

La police provient du [dépôt officiel Google Fonts](https://github.com/google/fonts/tree/main/ofl/dmserifdisplay). Le fichier TTF a été converti en WOFF2 sans retirer de caractères. Le fichier et sa licence sont inclus dans le dépôt et suivent le déploiement habituel.

La feuille `public/styles/typography.css` indique le fichier chargé. Son nom contient une empreinte de son contenu : lors d’un remplacement, renouveler ce nom et le chemin CSS, puis lancer `npm run assets`. Le chargement est local, sans appel aux serveurs Google Fonts.
