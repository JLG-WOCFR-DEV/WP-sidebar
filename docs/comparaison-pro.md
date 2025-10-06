# Comparaison avec les suites professionnelles et axes d'amélioration

Cette note fait le point sur l'écart entre Sidebar JLG et les constructeurs de barres latérales haut de gamme (Elementor Pro, Max Mega Menu, ConvertBox…). Elle se concentre sur les options, l'UX/UI, la navigation mobile, l'accessibilité et l'intégration WordPress, puis propose des compléments concrets.

## 1. Options & gouvernance produit

**Forces actuelles**

- L'interface regroupe les réglages dans des onglets thématiques (général, profils, styles, contenu, social, effets, outils) et expose un aperçu multi-vues pour valider les changements sans quitter l'écran.【F:sidebar-jlg/includes/admin-page.php†L60-L95】
- Les préréglages de style fournissent un point de départ cohérent et couvrent couleurs, typographies, animations et paramètres mobiles, ce qui rapproche l'expérience des offres premium.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L7-L143】
- Le moteur de menu accepte des CTA riches (titre, description, bouton, shortcode) et conserve des attributs de données pour d'éventuels branchements analytiques.【F:sidebar-jlg/includes/sidebar-template.php†L129-L229】

**Écarts face aux suites pro & pistes**

- L'ensemble des réglages reste déclaratif (cases à cocher, sélecteurs) sans builder visuel ni manipulation directe du contenu, contrairement aux éditeurs WYSIWYG de référence. Ajouter un mode édition frontale (drag & drop, inline editing) réduirait la distance entre configuration et rendu réel.【F:sidebar-jlg/includes/admin-page.php†L60-L137】
- Les options n'incluent pas de segmentation comportementale, de scénarios conditionnels (scroll depth, délais, sorties) ni de pilotage analytique/A-B testing, là où les solutions pro disposent de déclencheurs marketing et tableaux de bord. Étendre le schéma des réglages (`DefaultSettings::all`) avec des triggers événementiels, des fréquences d'affichage et des connecteurs métriques comblerait ce manque.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L149-L200】
- Aucun flux d'onboarding guidé n'est prévu (check-list, assistant de configuration). Un wizard qui active les étapes clés (sources de menu, style, profils, publication) améliorerait l'appropriation.

## 2. UX/UI d'administration

**Forces actuelles**

- L'aperçu AJAX multi-breakpoints et la barre d'outils permettent de vérifier rapidement la cohérence visuelle.【F:sidebar-jlg/includes/admin-page.php†L70-L95】
- Les contrôles typographiques et dimensionnels valident les unités et normalisent les valeurs, limitant les erreurs de saisie (ex. `sidebar_jlg_prepare_dimension_option`).【F:sidebar-jlg/includes/admin-page.php†L103-L118】

**Écarts & pistes**

- Les formulaires sont très denses : un grand tableau de réglages par onglet sans regroupement visuel secondaire, ce qui fatigue lors des longues sessions. Introduire des blocs accordéon, des presets contextuels ou un moteur de recherche interne simplifierait la navigation.
- Les statuts et comparaisons de versions d'options ne sont pas surfacés (pas d'historique, pas de rollback visuel). Ajouter un diff visuel (avant/après) et des métadonnées d'auteur alignerait l'expérience sur les suites pro orientées équipe.
- Les notifications sont centralisées via un conteneur vierge `#sidebar-jlg-js-notices` sans design dédié. Prévoir un système de toast/progression renforcerait le feedback utilisateur.【F:sidebar-jlg/includes/admin-page.php†L48-L52】

## 3. Navigation mobile & interactions

**Forces actuelles**

- Le script public gère la position, les préférences de réduction de mouvement, le verrouillage du scroll et l'accessibilité clavier (focus trap, close sur `Esc`).【F:sidebar-jlg/assets/js/public-script.js†L1-L555】
- Les sous-menus se replient automatiquement sur mobile et recalculent leur hauteur via `ResizeObserver`, ce qui évite les débordements courants.【F:sidebar-jlg/assets/js/public-script.js†L246-L362】

**Écarts & pistes**

- Aucune gestuelle native (swipe pour ouvrir/fermer, tirage du bord) ni haptic feedback n'est implémentée, alors que les solutions mobiles premium en font un standard. Étendre `public-script.js` avec des listeners pointer/touch dédiés permettrait de capter ces interactions.【F:sidebar-jlg/assets/js/public-script.js†L556-L727】
- Pas de logique de persistance d'état (sidebar ré-ouverte au rechargement, souvenir du dernier sous-menu) ni de délai configurable d'autoclose. Introduire un stockage local léger (localStorage/sessionStorage) et des timers optionnels alignerait l'expérience sur les produits pro.
- La gestion du bouton hamburger reste unique : pas de variantes (icônes animées, badges d'alerte, positionnement contextuel). Prévoir un sélecteur d'icônes animées et un système d'état (ex. badge pour promotions) améliorerait la perception.

## 4. Accessibilité

**Forces actuelles**

- Le HTML embarque des libellés ARIA personnalisables, des boutons de toggle pour les sous-menus et l'attribut `aria-hidden` est synchronisé avec l'état visuel.【F:sidebar-jlg/includes/sidebar-template.php†L170-L229】
- Le script respecte le focus clavier, applique les libellés dynamiques et expose une préférence `prefers-reduced-motion` pour couper les animations.【F:sidebar-jlg/assets/js/public-script.js†L45-L210】

**Écarts & pistes**

- Aucune vérification automatique du contraste ni rapport d'accessibilité n'est exposé. Intégrer un audit rapide (Lighthouse/pa11y) dans l'onglet Outils ou afficher des alertes en temps réel aiderait les utilisateurs moins experts.
- Les options ne prévoient pas de mode « texte large », de bascule thème clair/sombre ou de gabarits compatibles lecteurs d'écran (ex. ordre de tabulation custom). Ajouter ces contrôles dans `DefaultSettings::all` et l'UI rapprocherait le niveau de conformité des solutions certifiées WCAG.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L149-L200】
- La documentation d'accessibilité dans le back-office est absente (pas de check-list, pas de rappels ARIA). Un encart d'aide et des tests unitaires dédiés renforceraient la posture pro.

## 5. Apparence WordPress & éditeur visuel

**Forces actuelles**

- Le plugin fournit un bloc Gutenberg dédié à la recherche synchronisé avec les réglages globaux et un habillage éditeur cohérent (fichiers JS/CSS spécifiques).【F:sidebar-jlg/assets/js/blocks/sidebar-search.js†L1-L145】【F:sidebar-jlg/assets/css/sidebar-search-editor.scss†L1-L66】
- L'aperçu en administration offre des vues mobile/tablette/desktop et un mode comparaison pour contrôler la régression visuelle.【F:sidebar-jlg/includes/admin-page.php†L70-L95】

**Écarts & pistes**

- Aucun bloc ou modèle ne permet d'assembler la sidebar complète dans l'éditeur visuel (seul le module de recherche est disponible). Créer un bloc « Sidebar complète » avec prévisualisation live ou des patterns Gutenberg alignerait le plugin sur les éditeurs modernes.【F:sidebar-jlg/assets/js/blocks/sidebar-search.js†L1-L145】
- Les styles d'éditeur se limitent au composant de recherche ; les préréglages de la sidebar ne sont pas reflétés dans l'éditeur de site (pas de CSS généré côté Gutenberg). Étendre les feuilles `sidebar-search-editor.scss` et injecter les variables de thème renforcerait la parité visuelle.【F:sidebar-jlg/assets/css/sidebar-search-editor.scss†L1-L90】
- Le mode aperçu ne se connecte pas au front-end en contexte multi-langue/profil (pas de switch direct entre profils). Ajouter une palette de contextes (profil actif, langue, rôle) dans l'UI offrirait une vision réaliste, comparable aux suites pro.

---

En priorisant un éditeur visuel temps réel, des scénarios conditionnels avancés et un accompagnement accessibilité/analytics, Sidebar JLG pourra rivaliser avec les leaders tout en capitalisant sur son intégration WordPress native.
