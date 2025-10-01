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

### Bloc Gutenberg "Sidebar JLG – Recherche"

- Le bloc dynamique `jlg/sidebar-search` permet d'insérer l'encart de recherche de la sidebar dans l'éditeur du site.
- Les attributs `enable_search`, `search_method`, `search_alignment` et `search_shortcode` sont synchronisés avec les options globales du plugin via le `SettingsRepository` pour garantir la rétrocompatibilité avec les installations existantes.
- Le bloc affiche un aperçu direct dans l'éditeur grâce au script d'édition (`sidebar-jlg/assets/js/blocks/sidebar-search.js`) et réutilise le rendu PHP (`render_callback`) pour conserver la logique de la sidebar en front.
- Les scripts générés sont exposés via `block.json` et chargés automatiquement par WordPress depuis `sidebar-jlg/assets/build`. Après compilation, vérifiez que les fichiers `sidebar-search(.asset).php` et `sidebar-search-view(.asset).php` sont bien présents afin de garantir le chargement du bloc aussi bien dans l'éditeur que sur le site public.
- Pour recompiler les scripts du bloc après modification, exécutez `npm run build`, puis (optionnel) contrôlez dans la console du navigateur WordPress que le bloc `jlg/sidebar-search` n'émet aucun avertissement de script manquant.

### Icônes personnalisées

- Seuls les fichiers au format `.svg` sont chargés par le plugin. Chaque fichier est contrôlé avec `wp_check_filetype()` avant d'être ajouté à la bibliothèque.
- Le contenu SVG est validé via `wp_kses`. Les fichiers dont le contenu est altéré par le nettoyage ou qui contiennent des éléments non autorisés sont ignorés pour éviter toute contamination.
- Un contrôle supplémentaire inspecte les attributs `href`/`xlink:href` des balises `<use>` afin de s'assurer qu'ils pointent uniquement vers un identifiant local ou vers un média de la bibliothèque (`wp-content/uploads`). Les validations spécifiques sont centralisées dans `Sidebar\JLG\Icons\IconLibrary::validateSanitizedSvg()` pour simplifier l'ajout de nouvelles règles de sécurité.
- Les attributs ARIA usuels (`aria-label`, `aria-describedby`, etc.) sont désormais préservés lors de la validation afin de faciliter l'accessibilité des icônes.

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
