# Sidebar JLG

Une extension WordPress qui fournit une sidebar animée et entièrement personnalisable, pensée pour les équipes qui veulent un rendu professionnel sans renoncer à la simplicité d'administration.

## Fonctionnalités

### Activation et intégration natives

- Vérification des permissions lors de l'activation et création automatique du dossier `uploads/sidebar-jlg/icons/` afin de préparer l'import d'icônes personnalisées. En cas d'échec, le message d'erreur est journalisé et affiché dans l'administration pour guider la remise en route. 
- Chargement automatique du text-domain et enregistrement d'un menu dédié dans l'administration pour garder l'expérience cohérente avec WordPress. 

### Interface d'administration complète

- Page de configuration unique avec regroupement logique des réglages (contenu, styles, comportements) et champs spécialisés : color picker natif, sélection d'icônes, tri et drag & drop des éléments de menu. 
- Prévisualisation embarquée de la sidebar, synchronisée via AJAX (`jlg_render_preview`) pour valider instantanément les variations graphiques et la sélection de profil actif. 
- Bibliothèque d'icônes prête à l'emploi (manifest JSON), outils de téléversement sécurisé (limites MIME/taille) et récupération du SVG pour les besoins spécifiques. 
- Outils d'import/export JSON versionnés, remise à zéro contrôlée et sélection directe de contenus WordPress (articles, pages, catégories) grâce aux points d'entrée AJAX dédiés. 
- Préréglages de style (`style_presets`) comprenant visuels de comparaison avant/après pour accélérer la mise en production. 

### Personnalisation visuelle avancée

- Contrôle complet des dimensions (largeurs par appareil, marges, rayons, etc.), typographies (graisse, capitalisation, interlignage), jeux de couleurs (dégradés sur texte/fond/boutons) et animations (vitesse, flou, effet néon). 
- Gestion granulaire du bouton hamburger (position, taille, couleur, offset) pour respecter le contraste et les contraintes de marque. 
- Overlay responsive avec contrôle de l'opacité et du flou sur mobile pour conserver une lisibilité optimale. 

### Contenu et navigation

- Constructeur d'éléments de menu mixant pages, articles, catégories, menus WordPress, liens personnalisés et séparateurs, chacun pouvant recevoir une icône (bibliothèque, SVG inline ou URL contrôlée). 
- Filtrage des menus WordPress importés (tous les éléments, niveau 1 uniquement, branche courante) et limitation de profondeur pour proposer des expériences adaptées au contexte. 
- Gestion des icônes sociales avec validation stricte (URL obligatoires, SVG vérifié), possibilité d'ajouter ses propres pictogrammes depuis la médiathèque. 
- Bloc Gutenberg dynamique `jlg/sidebar-search` synchronisé avec les réglages globaux pour insérer le module de recherche où vous le souhaitez. 

### Accessibilité et expérience utilisateur

- Bouton toggle dédié aux sous-menus (`menu-item-has-children`) avec attributs ARIA (`aria-expanded`, `aria-controls`) et libellés dynamiques selon l'état. 
- Navigation clavier complète : fermeture via `Esc`, flèche bas pour ouvrir, conservation du focus et différenciation mobile/desktop pour éviter les pièges lors du balayage tactile. 
- États de focus alignés sur les hover, prise en charge automatique des contrastes pour la recherche (schémas clair/sombre) et préservation des attributs ARIA des icônes importées. 

### Profils conditionnels et ciblage contextuel

- Profils multiples hiérarchisés par priorité et spécificité permettant d'appliquer des réglages différents selon le contexte courant (page, type de contenu, taxonomie, rôles utilisateurs, statut de connexion, langue, appareil, horaires et jours). 
- Résolution centralisée du contexte via `RequestContextResolver` (URL normalisée, post courant, taxonomies, langues, rôles, détection mobile) pour que le rendu et la sélection de profil partagent la même source de vérité. 
- Sélecteur d'horaires (timepicker, jours de semaine) pour activer des campagnes temporelles spécifiques (ex. promotion week-end). 

### Outils d'import/export et maintenance

- Export JSON annoté avec la version du plugin et l'URL source pour faciliter la traçabilité en multi-environnements. 
- Import avec validation complète (nonce, typage, filtrage des icônes, recalcul des menus) et remontée d'erreurs contextualisées. 
- Réinitialisation guidée des réglages (hook `jlg_reset_settings`) pour repartir des valeurs par défaut en un clic. 

### Performance et fiabilité

- Cache HTML des rendus de sidebar, segmenté par locale et suffixe de profil, avec invalidation automatique lors des mises à jour de contenu, menus, icônes ou version du plugin. 
- Normalisation et sanitation poussée de tous les réglages (couleurs, dimensions, opacités, champs texte) pour éviter les injections et assurer la cohérence des données. 
- Gestion des erreurs lors des téléversements d'icônes (journalisation, notices administrateur, rollback) pour que les incidents n'altèrent pas l'expérience de vos utilisateurs. 

## Installation

1. Télécharger l'archive du plugin ou cloner ce dépôt.
2. Copier le dossier `sidebar-jlg` dans le répertoire `wp-content/plugins` de votre site WordPress.
3. Activer **Sidebar - JLG** dans l'administration WordPress.

## Configuration

Après activation, un menu "Sidebar JLG" apparait dans l'administration. Vous pouvez :

- Activer ou désactiver la sidebar.
- Choisir le style : couleurs, typographie, effets d'animation, marges…
- Ajuster la position et la couleur du bouton hamburger pour garantir le contraste.
- Profiter d'états de focus alignés sur les effets de survol pour les liens, icônes et boutons afin d'améliorer la navigation au clavier.
- Renseigner les dimensions en utilisant des unités classiques (`px`, `rem`, `vh`, etc.) ou des expressions `calc()` composées d'opérateurs arithmétiques autorisés.
- Ajouter des éléments de menu (pages, articles, catégories, liens personnalisés), des menus WordPress, des séparateurs et des icônes sociales.
- Activer une recherche intégrée et personnaliser son affichage.
- Importer vos propres icônes SVG en les plaçant dans le dossier `wp-content/uploads/sidebar-jlg/icons/`.
- Créer des profils ciblés (pays, rôles, appareils, horaires) et en activer un par défaut pour couvrir les cas généraux.

### Export / Import des réglages

- Depuis l’onglet **Outils** de la page d’administration, utilisez le bouton **Exporter les réglages** pour générer un fichier JSON contenant la configuration actuelle (`sidebar_jlg_settings`).
- Conservez ce fichier dans votre système de contrôle de versions ou partagez-le avec votre équipe pour synchroniser les environnements.
- Sur l’environnement cible, sélectionnez ce fichier via le bouton **Importer les réglages** puis confirmez l’action. Les options existantes sont validées et remplacées avant que la page ne soit automatiquement rechargée.
- Les exports incluent la version du plugin et l’URL du site source pour faciliter les audits et vérifier que l’import a été réalisé sur le bon environnement.

### Bloc Gutenberg "Sidebar JLG – Recherche"

- Le bloc dynamique `jlg/sidebar-search` permet d'insérer l'encart de recherche de la sidebar dans l'éditeur du site.
- Les attributs `enable_search`, `search_method`, `search_alignment` et `search_shortcode` sont synchronisés avec les options globales du plugin via le `SettingsRepository` pour garantir la rétrocompatibilité avec les installations existantes.
- Le bloc affiche un aperçu direct dans l'éditeur grâce au script d'édition (`sidebar-jlg/assets/js/blocks/sidebar-search.js`) et interroge le rendu dynamique (`wp/v2/block-renderer/jlg/sidebar-search`) afin de refléter le résultat côté public, y compris pour les shortcodes et hooks personnalisés.
- L'aperçu côté éditeur gère un état de chargement/erreur et conserve les classes `sidebar-search` ainsi que l'attribut `data-sidebar-search-align` pour que les scripts d'habillage appliquent les mêmes styles que sur le front.
- Les scripts générés sont exposés via `block.json` et chargés automatiquement par WordPress depuis `sidebar-jlg/assets/build`. Après compilation, vérifiez que les fichiers `sidebar-search(.asset).php` et `sidebar-search-view(.asset).php` sont bien présents afin de garantir le chargement du bloc aussi bien dans l'éditeur que sur le site public.
- Pour recompiler les scripts du bloc après modification, exécutez `npm run build`, puis (optionnel) contrôlez dans la console du navigateur WordPress que le bloc `jlg/sidebar-search` n'émet aucun avertissement de script manquant.
- Afin d'assurer une lisibilité optimale dans l'éditeur, la feuille de style `sidebar-search-editor.scss` applique désormais par défaut `var(--wp-admin-theme-color)` (ou une teinte sombre) lorsque `--sidebar-text-color` n'est pas défini. Le script de vue ajoute automatiquement une classe `sidebar-search--editor` ou `sidebar-search--frontend` pour conserver une palette cohérente entre l'édition et l'affichage public.
- Le bloc ajoute automatiquement la classe `sidebar-search--scheme-light` et l'attribut `data-sidebar-search-scheme="auto"`. Le script de vue mesure la luminance de `color` pour basculer vers `sidebar-search--scheme-dark` lorsque le texte doit contraster avec un fond sombre. Sur un fond foncé explicitement configuré dans votre mise en page, ajoutez simultanément la classe `sidebar-search--scheme-dark` et `data-sidebar-search-scheme="dark"` (ou fixez `color` via CSS) afin de forcer la variante claire du formulaire.
- Usage recommandé : insérez le bloc dans des zones dont la couleur de texte est déjà cohérente avec le reste du contenu. Pour les héro ou sections pleine largeur sombres, appliquez la classe `sidebar-search--scheme-dark` sur le conteneur (ou sur un bloc groupe englobant) et vérifiez le contraste en prévisualisation ; pour les zones claires, la configuration automatique suffit et reste synchronisée avec `currentColor`.

### Icônes personnalisées

- Seuls les fichiers au format `.svg` sont chargés par le plugin. Chaque fichier est contrôlé avec `wp_check_filetype()` avant d'être ajouté à la bibliothèque.
- Le contenu SVG est validé via `wp_kses`. Les fichiers dont le contenu est altéré par le nettoyage ou qui contiennent des éléments non autorisés sont ignorés pour éviter toute contamination.
- Un contrôle supplémentaire inspecte les attributs `href`/`xlink:href` des balises `<use>` afin de s'assurer qu'ils pointent uniquement vers un identifiant local ou vers un média de la bibliothèque (`wp-content/uploads`). Les validations spécifiques sont centralisées dans `Sidebar\\JLG\\Icons\\IconLibrary::validateSanitizedSvg()` pour simplifier l'ajout de nouvelles règles de sécurité.
- Les attributs ARIA usuels (`aria-label`, `aria-describedby`, etc.) sont désormais préservés lors de la validation afin de faciliter l'accessibilité des icônes.

### Navigation imbriquée et UX

- Les éléments `menu-item-has-children` affichent désormais un bouton dédié (toggle) placé à droite du lien parent. Le bouton expose les attributs `aria-expanded`, `aria-controls` et met à jour son libellé automatiquement pour refléter l'état du sous-menu.
- Les sous-menus sont masqués par défaut via une classe `is-open` et bénéficient d'une indentation, de séparateurs visuels et de transitions douces. Les états ouverts sont conservés pour les éléments de navigation correspondant à la page courante et leurs hauteurs sont recalculées dynamiquement (ResizeObserver) pour suivre les variations de contenu.
- Le script public gère les interactions clavier (Esc pour refermer, flèche bas pour ouvrir et focaliser le premier lien) et différencie mobile/desktop : sur les écrans tactiles étroits, l'ouverture d'un sous-menu referme ses frères afin de limiter le défilement. Les sous-menus fermés sont rendus inactifs (`pointer-events: none`) pour éviter les pièges de focus.
- Vérification manuelle : navigation testée en mode tactile (émulation iPhone 12 Pro Max dans Chromium) pour valider les appuis successifs sur les toggles, la fluidité des transitions et la conservation du focus clavier.

## Comparaison avec des solutions professionnelles et pistes d'amélioration

Les constructeurs professionnels (Elementor Pro, JetMenu, Max Mega Menu, etc.) proposent des expériences hors-canvas riches. Sidebar JLG se démarque par :

- **Une intégration WordPress native** (réglages, profils, bloc Gutenberg) là où les solutions premium utilisent souvent des builders propriétaires.
- **Un ciblage contextuel fin** basé sur les rôles, les terminaux, les langues et la temporalité, fonctionnalité généralement réservée aux offres haut de gamme.
- **Une gouvernance des actifs SVG** stricte (manifest, validation, notifications) rarement aussi poussée dans les alternatives.

Pour atteindre le niveau de finition des meilleures suites professionnelles, les axes suivants sont recommandés :

1. **Éditeur visuel temps réel** : proposer un mode WYSIWYG drag & drop (à la manière de l'éditeur Elementor) permettant de modifier la structure et les contenus sans quitter la page visitée.
2. **Bibliothèque de composants** : ajouter des modules prêts à l'emploi (CTA, formulaires, flux sociaux) avec styles cohérents et options de tracking.
3. **Analyse et personnalisation dynamique** : exposer des métriques d'engagement (taux d'ouverture, clics) et déclencheurs conditionnels (scroll depth, temps passé) pour rivaliser avec des solutions comme ConvertBox.
4. **Internationalisation avancée** : intégrer automatiquement les traductions via WPML/Polylang et synchroniser les profils linguistiques.
5. **Automatisation qualité** : fournir des commandes de tests E2E (Playwright/Cypress) et une suite de linting CI pour sécuriser les contributions open source.

## Désinstallation

La désinstallation supprime les options enregistrées par le plugin.

## Développement

Le plugin est écrit en PHP, HTML, CSS et JavaScript. Les fichiers principaux se trouvent dans :

- `sidebar-jlg/sidebar-jlg.php`
- `sidebar-jlg/includes/`
- `sidebar-jlg/assets/`

### Tests JavaScript

Installez les dépendances npm et exécutez les tests unitaires JSDOM avec :

```bash
npm install
npm run test:js
```

### Service `RequestContextResolver`

Le service `JLG\\Sidebar\\Frontend\\RequestContextResolver` centralise toutes les informations de contexte utilisées par le rendu de la sidebar et par la sélection de profil (URL normalisée, contenus consultés, taxonomies, rôles, langue, appareil, horaires, etc.). Il est injecté dans `SidebarRenderer` et `ProfileSelector` afin de garantir que les deux composants s’appuient sur la même source de vérité.

Pour ajouter un nouveau signal de contexte :

1. **Compléter le service** : étendre la méthode `resolve()` (et, si nécessaire, les méthodes privées associées) pour collecter et exposer la nouvelle donnée. Conservez un format simple (types scalaires ou tableaux) et documentez toute transformation appliquée.
2. **Mettre à jour les consommateurs** : adaptez `ProfileSelector` et/ou `SidebarRenderer` uniquement si un traitement supplémentaire est nécessaire pour exploiter le nouveau signal. Le contexte partagé doit rester la source unique de lecture.
3. **Renforcer les tests** : ajoutez une assertion explicite dans `tests/request_context_resolver_test.php` pour couvrir le nouveau signal et mettez à jour, le cas échéant, les scénarios des tests `profile_selector_*` ou `render_sidebar_*` qui reposent sur ces données.
4. **Synchroniser la documentation** : si le signal influence le comportement public du plugin, complétez ce README ou les notes d’implémentation pertinentes pour faciliter les futurs ajouts.

## Licence

Distribué sous licence [GPL v2 ou ultérieure](https://www.gnu.org/licenses/gpl-2.0.html).
