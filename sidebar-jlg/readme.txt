=== Sidebar - JLG ===
Contributors: jlg
Tags: sidebar, navigation, menu, customizer, analytics
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 4.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Une sidebar professionnelle, animée et entièrement personnalisable pour WordPress.

== Description ==

Sidebar - JLG fournit une expérience hors-canvas haut de gamme entièrement intégrée à WordPress.

* **Activation et intégration natives** : vérification des permissions, chargement du text domain et menu d'administration dédié.
* **Interface d'administration complète** : panneau unique avec prévisualisation AJAX, gestion avancée des icônes et import/export JSON.
* **Personnalisation visuelle avancée** : contrôles détaillés des couleurs, typographies, animations et comportement du bouton hamburger.
* **Accessibilité et UX** : navigation clavier complète, options WCAG 2.2, onglet checklist et audit Pa11y intégrés.
* **Profils ciblés** : profils hiérarchisés activés selon le contexte (page, taxonomie, rôles, horaires, langues, appareils).
* **Performance et fiabilité** : cache HTML versionné, sanitation stricte des réglages, validations sécurisées des SVG.
* **Insights & Analytics** : module d'analyse embarqué pour suivre les ouvertures, clics et conversions CTA.

Consultez le fichier `README.md` du dépôt pour une documentation exhaustive, y compris la comparaison avec des solutions professionnelles et les pistes d'amélioration.

== Installation ==

1. Télécharger l'archive du plugin ou cloner ce dépôt.
2. Copier le dossier `sidebar-jlg` dans `wp-content/plugins`.
3. Activer **Sidebar - JLG** depuis l'administration WordPress.
4. Rendez-vous dans *Sidebar JLG* afin de configurer vos profils, votre menu et vos styles.

== Frequently Asked Questions ==

= Le plugin est-il compatible avec Gutenberg ? =

Oui. Le bloc dynamique `jlg/sidebar-search` est fourni pour intégrer la recherche directement dans l'éditeur. Les assets sont chargés automatiquement via `block.json`.

= Puis-je importer mes propres icônes ? =

Placez vos SVG dans `wp-content/uploads/sidebar-jlg/icons/`. Chaque fichier est validé via `wp_kses` et les attributs ARIA sont préservés pour l'accessibilité.

= Comment fonctionne le module Insights & Analytics ? =

Activez la collecte depuis l'onglet Général. Les statistiques (ouvertures, clics, conversions CTA) sont agrégées par profil et sur 7/30 jours.

== Screenshots ==

1. Tableau de bord Sidebar JLG.
2. Prévisualisation AJAX des réglages de style.
3. Module Insights & Analytics.
4. Checklist Accessibilité & WCAG 2.2.

== Changelog ==

= 4.10.0 =
* Version initiale disponible dans ce dépôt public.

== Upgrade Notice ==

= 4.10.0 =
Cette version inaugure la diffusion open source du plugin Sidebar - JLG avec profils ciblés, module analytics et outils d'accessibilité intégrés.
