# Sidebar JLG

Une extension WordPress qui fournit une sidebar animée et entièrement personnalisable.

## Installation

1. Télécharger l'archive du plugin ou cloner ce dépôt.
2. Copier le dossier `sidebar-jlg` dans le répertoire `wp-content/plugins` de votre site WordPress.
3. Activer **Sidebar - JLG** dans l'administration WordPress.

## Configuration

Après activation, un menu "Sidebar JLG" apparait dans l'administration. Vous pouvez :

- Activer ou désactiver la sidebar.
- Choisir le style : couleurs, typographie, effets d'animation, marges…
- Ajuster la position et la couleur du bouton hamburger pour garantir le contraste.
- Profiter d'états de focus alignés sur les effets de survol pour les liens, icônes et boutons afin d'améliorer la navigation au clavier.
- Renseigner les dimensions en utilisant des unités classiques (`px`, `rem`, `vh`, etc.) ou des expressions `calc()` composées d'opérateurs arithmétiques autorisés.
- Ajouter des éléments de menu (pages, articles, catégories, liens personnalisés) et des icônes sociales.
- Activer une recherche intégrée et personnaliser son affichage.
- Importer vos propres icônes SVG en les plaçant dans le dossier `wp-content/uploads/sidebar-jlg/icons/`.

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

### Icônes personnalisées

- Seuls les fichiers au format `.svg` sont chargés par le plugin. Chaque fichier est contrôlé avec `wp_check_filetype()` avant d'être ajouté à la bibliothèque.
- Le contenu SVG est validé via `wp_kses`. Les fichiers dont le contenu est altéré par le nettoyage ou qui contiennent des éléments non autorisés sont ignorés pour éviter toute contamination.
- Un contrôle supplémentaire inspecte les attributs `href`/`xlink:href` des balises `<use>` afin de s'assurer qu'ils pointent uniquement vers un identifiant local ou vers un média de la bibliothèque (`wp-content/uploads`). Les validations spécifiques sont centralisées dans `Sidebar\JLG\Icons\IconLibrary::validateSanitizedSvg()` pour simplifier l'ajout de nouvelles règles de sécurité.
- Les attributs ARIA usuels (`aria-label`, `aria-describedby`, etc.) sont désormais préservés lors de la validation afin de faciliter l'accessibilité des icônes.

### Navigation imbriquée et UX

- Les éléments `menu-item-has-children` affichent désormais un bouton dédié (toggle) placé à droite du lien parent. Le bouton expose les attributs `aria-expanded`, `aria-controls` et met à jour son libellé automatiquement pour refléter l'état du sous-menu.
- Les sous-menus sont masqués par défaut via une classe `is-open` et bénéficient d'une indentation, de séparateurs visuels et de transitions douces. Les états ouverts sont conservés pour les éléments de navigation correspondant à la page courante et leurs hauteurs sont recalculées dynamiquement (ResizeObserver) pour suivre les variations de contenu.
- Le script public gère les interactions clavier (Esc pour refermer, flèche bas pour ouvrir et focaliser le premier lien) et différencie mobile/desktop : sur les écrans tactiles étroits, l'ouverture d'un sous-menu referme ses frères afin de limiter le défilement. Les sous-menus fermés sont rendus inactifs (`pointer-events: none`) pour éviter les pièges de focus.
- Vérification manuelle : navigation testée en mode tactile (émulation iPhone 12 Pro Max dans Chromium) pour valider les appuis successifs sur les toggles, la fluidité des transitions et la conservation du focus clavier.

## Désinstallation

La désinstallation supprime les options enregistrées par le plugin.

## Développement

Le plugin est écrit en PHP, HTML, CSS et JavaScript. Les fichiers principaux se trouvent dans :

- `sidebar-jlg/sidebar-jlg.php`
- `sidebar-jlg/includes/`
- `sidebar-jlg/assets/`

### Tests JavaScript

Installez les dépendances npm et exécutez les tests unitaires JSDOM avec :

```bash
npm install
npm run test:js
```

## Licence

Distribué sous licence [GPL v2 ou ultérieure](https://www.gnu.org/licenses/gpl-2.0.html).
