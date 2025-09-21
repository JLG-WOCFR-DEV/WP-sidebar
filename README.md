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
- Renseigner les dimensions en utilisant des unités classiques (`px`, `rem`, `vh`, etc.) ou des expressions `calc()` composées d'opérateurs arithmétiques autorisés.
- Ajouter des éléments de menu (pages, articles, catégories, liens personnalisés) et des icônes sociales.
- Activer une recherche intégrée et personnaliser son affichage.
- Importer vos propres icônes SVG en les plaçant dans le dossier `wp-content/uploads/sidebar-jlg/icons/`.

### Icônes personnalisées

- Seuls les fichiers au format `.svg` sont chargés par le plugin. Chaque fichier est contrôlé avec `wp_check_filetype()` avant d'être ajouté à la bibliothèque.
- Le contenu SVG est validé via `wp_kses`. Les fichiers dont le contenu est altéré par le nettoyage ou qui contiennent des éléments non autorisés sont ignorés pour éviter toute contamination.
- Un contrôle supplémentaire inspecte les attributs `href`/`xlink:href` des balises `<use>` afin de s'assurer qu'ils pointent uniquement vers un identifiant local ou vers un média de la bibliothèque (`wp-content/uploads`). Les validations spécifiques sont centralisées dans `Sidebar\JLG\Icons\IconLibrary::validateSanitizedSvg()` pour simplifier l'ajout de nouvelles règles de sécurité.

## Désinstallation

La désinstallation supprime les options enregistrées par le plugin.

## Développement

Le plugin est écrit en PHP, HTML, CSS et JavaScript. Les fichiers principaux se trouvent dans :

- `sidebar-jlg/sidebar-jlg.php`
- `sidebar-jlg/includes/`
- `sidebar-jlg/assets/`

## Licence

Distribué sous licence [GPL v2 ou ultérieure](https://www.gnu.org/licenses/gpl-2.0.html).
