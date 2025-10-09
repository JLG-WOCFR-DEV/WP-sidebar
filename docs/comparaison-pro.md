# Comparaison avec les suites professionnelles et axes d'amélioration

Cette note fait le point sur l'écart entre Sidebar JLG et les constructeurs de barres latérales haut de gamme (Elementor Pro, Max Mega Menu, ConvertBox…). Elle se concentre sur les options, l'UX/UI, la navigation mobile, l'accessibilité et l'intégration WordPress, puis propose des compléments concrets.

### Lecture rapide des écarts clés

| Domaine | Situation actuelle | Standards des suites pro | Gap prioritaire |
| --- | --- | --- | --- |
| Configuration | Formulaires tabulaires structurés par onglets avec prévisualisation multi-vues.【F:sidebar-jlg/includes/admin-page.php†L60-L132】【F:sidebar-jlg/includes/admin-page.php†L96-L229】 | Builders visuels en drag & drop avec édition inline et historique d'actions. | Introduire un mode d'édition frontale couplé à un historique Undo/Redo et des panneaux contextuels. |
| Personnalisation | Préréglages complets (couleurs, animations, responsive) et CTA enrichis.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L7-L143】【F:sidebar-jlg/includes/sidebar-template.php†L129-L229】 | Variantes guidées par segment + automatisations (A/B, triggers). | Étendre le schéma de réglages aux scénarios comportementaux et à la duplication rapide de variations. |
| Mobile & interactions | Gestes de base, focus trap, recalcul dynamique des sous-menus.【F:sidebar-jlg/assets/js/public-script.js†L45-L362】 | Micro-interactions haptiques, mémorisation d'état, transitions personnalisables. | Ajouter stockage local, feedback haptique optionnel et choix d'animations par preset. |
| Accessibilité & QA | Respect des rôles/ARIA et préférence `prefers-reduced-motion`, audit manuel possible via script Pa11y.【F:sidebar-jlg/assets/js/public-script.js†L45-L210】【F:package.json†L8-L16】 | Audit continu (contraste, rapports) et check-lists intégrées. | Fournir des alertes en temps réel et un tableau de bord d'accessibilité embarqué. |

### Résumé visuel dans l'administration

Un module « Comparatif Pro » a été ajouté au-dessus des onglets pour visualiser d'un coup d'œil la situation actuelle face aux suites haut de gamme.【F:sidebar-jlg/includes/admin-page.php†L96-L145】【F:sidebar-jlg/assets/css/admin-style.css†L97-L163】

- **UI & UX** : badge « Atout » pour valoriser l'aperçu multi-breakpoints, les presets et la commande contextuelle tout en rappelant l'objectif canvas/Undo-Redo.
- **Accessibilité** : badge « En progrès » qui renvoie vers les travaux d'automatisation des audits contrastes/Pa11y.
- **Fiabilité** : badge « Atout » consacré au cache multi-profils, à la sanitation et au rollback des médias.
- **Visuel** : badge « En progrès » soulignant la bibliothèque de presets inspirée de Headless UI/Radix/Shadcn et les futures micro-interactions.

Chaque item mentionne le prochain jalon pour garder l'équipe alignée avec la feuille de route et s'appuie sur une mise en forme accessible (articles listitem + badges ARIA) afin d'être compréhensible par les lecteurs d'écran.
| Analytics & gouvernance | Tableau Insights tabulaire, cache menu par profil via transients.【F:sidebar-jlg/includes/admin-page.php†L942-L1099】【F:sidebar-jlg/src/Cache/MenuCache.php†L7-L172】 | Dashboards narratifs, API analytics, suivi des performances par campagne. | Transformer les données en storytelling actionnable et exposer des API/webhooks. |

## 1. Options & gouvernance produit

**Forces actuelles**

- L'interface regroupe les réglages dans des onglets thématiques (général, profils, styles, contenu, social, effets, outils) et expose un aperçu multi-vues pour valider les changements sans quitter l'écran.【F:sidebar-jlg/includes/admin-page.php†L60-L95】
- Les préréglages de style fournissent un point de départ cohérent et couvrent couleurs, typographies, animations et paramètres mobiles, ce qui rapproche l'expérience des offres premium.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L7-L143】
- Le moteur de menu accepte des CTA riches (titre, description, bouton, shortcode) et conserve des attributs de données pour d'éventuels branchements analytiques.【F:sidebar-jlg/includes/sidebar-template.php†L129-L229】
- Des déclencheurs comportementaux combinant timer, profondeur de scroll, exit intent et inactivité rapprochent l'orchestration marketing des solutions pro sans nécessiter de code.【F:sidebar-jlg/includes/admin-page.php†L708-L764】【F:sidebar-jlg/assets/js/public-script.js†L200-L470】

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
- Les notifications sont désormais gérées via un centre de toasts accessible (ARIA live, temporisation pausable, actions contextuelles) aligné sur les standards pro, tout en conservant des améliorations possibles pour la personnalisation par marque.【F:sidebar-jlg/includes/admin-page.php†L170-L180】【F:sidebar-jlg/assets/js/admin-script.js†L2800-L3074】【F:sidebar-jlg/assets/css/admin-style.css†L1-L166】
- Ajouter un mode « brouillon » des réglages pour préparer des campagnes sans publier immédiatement : stocker des ensembles d'options dans la base (`post_meta` ou CPT dédié) puis les pousser côté front lorsqu'ils sont validés, à l'image des environnements de staging proposés par ConvertBox.

## 3. Navigation mobile & interactions

**Forces actuelles**

- Le script public gère la position, les préférences de réduction de mouvement, le verrouillage du scroll et l'accessibilité clavier (focus trap, close sur `Esc`).【F:sidebar-jlg/assets/js/public-script.js†L1-L555】
- Les sous-menus se replient automatiquement sur mobile et recalculent leur hauteur via `ResizeObserver`, ce qui évite les débordements courants.【F:sidebar-jlg/assets/js/public-script.js†L246-L362】
- Des gestes tactiles configurables (ouverture depuis le bord, fermeture par glissement) rapprochent l'expérience mobile de celle des apps premium tout en restant désactivables pour les contextes sensibles.【F:sidebar-jlg/includes/admin-page.php†L404-L459】【F:sidebar-jlg/assets/js/public-script.js†L600-L1269】

**Écarts & pistes**

- Les gestes restent limités à un swipe latéral : pas de feedback haptique, de détection d'intention (tirage du bord vers l'intérieur pour annuler) ni d'indicateurs visuels contextuels. Ajouter des vibrations optionnelles (`navigator.vibrate`), un retour visuel progressif et des seuils adaptatifs selon le preset permettrait d'égaler les offres mobiles premium.
- Pas de logique de persistance d'état (sidebar ré-ouverte au rechargement, souvenir du dernier sous-menu) ni de délai configurable d'autoclose. Introduire un stockage local léger (localStorage/sessionStorage) et des timers optionnels alignerait l'expérience sur les produits pro.
- La gestion du bouton hamburger reste unique : pas de variantes (icônes animées, badges d'alerte, positionnement contextuel). Prévoir un sélecteur d'icônes animées et un système d'état (ex. badge pour promotions) améliorerait la perception.
- Créer une couche de télémétrie front (temps passé ouvert, interactions gestuelles) alimentant le module Insights pour rapprocher la finesse d'analyse des plateformes marketing.

## 4. Accessibilité

**Forces actuelles**

- Le HTML embarque des libellés ARIA personnalisables, des boutons de toggle pour les sous-menus et l'attribut `aria-hidden` est synchronisé avec l'état visuel.【F:sidebar-jlg/includes/sidebar-template.php†L170-L229】
- Le script respecte le focus clavier, applique les libellés dynamiques et expose une préférence `prefers-reduced-motion` pour couper les animations.【F:sidebar-jlg/assets/js/public-script.js†L45-L210】

**Écarts & pistes**

- Aucune vérification automatique du contraste ni rapport d'accessibilité n'est exposé. Intégrer un audit rapide (Lighthouse/pa11y) dans l'onglet Outils ou afficher des alertes en temps réel aiderait les utilisateurs moins experts.
- Les options ne prévoient pas de mode « texte large », de bascule thème clair/sombre ou de gabarits compatibles lecteurs d'écran (ex. ordre de tabulation custom). Ajouter ces contrôles dans `DefaultSettings::all` et l'UI rapprocherait le niveau de conformité des solutions certifiées WCAG.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L149-L200】
- La documentation d'accessibilité dans le back-office est absente (pas de check-list, pas de rappels ARIA). Un encart d'aide et des tests unitaires dédiés renforceraient la posture pro.
- Automatiser la génération d'un rapport Pa11y/Lighthouse après chaque sauvegarde via une action asynchrone (`wp_cron` ou Action Scheduler) puis afficher les résultats dans l'onglet Outils afin d'éviter les audits manuels ponctuels.

## 5. Apparence WordPress & éditeur visuel

**Forces actuelles**

- Le plugin fournit un bloc Gutenberg dédié à la recherche synchronisé avec les réglages globaux et un habillage éditeur cohérent (fichiers JS/CSS spécifiques).【F:sidebar-jlg/assets/js/blocks/sidebar-search.js†L1-L145】【F:sidebar-jlg/assets/css/sidebar-search-editor.scss†L1-L66】
- L'aperçu en administration offre des vues mobile/tablette/desktop et un mode comparaison pour contrôler la régression visuelle.【F:sidebar-jlg/includes/admin-page.php†L70-L95】

**Écarts & pistes**

- Aucun bloc ou modèle ne permet d'assembler la sidebar complète dans l'éditeur visuel (seul le module de recherche est disponible). Créer un bloc « Sidebar complète » avec prévisualisation live ou des patterns Gutenberg alignerait le plugin sur les éditeurs modernes.【F:sidebar-jlg/assets/js/blocks/sidebar-search.js†L1-L145】
- Les styles d'éditeur se limitent au composant de recherche ; les préréglages de la sidebar ne sont pas reflétés dans l'éditeur de site (pas de CSS généré côté Gutenberg). Étendre les feuilles `sidebar-search-editor.scss` et injecter les variables de thème renforcerait la parité visuelle.【F:sidebar-jlg/assets/css/sidebar-search-editor.scss†L1-L90】
- Le mode aperçu ne se connecte pas au front-end en contexte multi-langue/profil (pas de switch direct entre profils). Ajouter une palette de contextes (profil actif, langue, rôle) dans l'UI offrirait une vision réaliste, comparable aux suites pro.
- Offrir un « mode maquette » Gutenberg : synchroniser les préréglages `DefaultSettings::STYLE_PRESETS` avec des variations de block patterns pour prévisualiser la sidebar complète dans l'éditeur de site.【F:sidebar-jlg/src/Settings/DefaultSettings.php†L7-L143】

## 6. Performance & maintenance

**Forces actuelles**

- Les assets publics ne sont chargés que lorsque la sidebar est effectivement activée et sont versionnés, avec injection conditionnelle de styles dynamiques pour éviter les allers-retours serveur inutiles.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L288-L344】
- Le cache HTML par profil repose sur des transients avec TTL et un index normalisé des locales, limitant les recalculs coûteux côté front lorsque la structure du menu reste stable.【F:sidebar-jlg/src/Cache/MenuCache.php†L7-L172】

**Écarts & pistes**

- La distribution reste monolithique (un couple `public-style.css` / `public-script.js` sans dépendances ni découpage conditionnel), ce qui pénalise les projets qui visent des scores Core Web Vitals serrés. Un bundler (Vite, esbuild) pourrait produire des variantes minifiées, charger paresseusement les modules optionnels (effets, animations) et exposer un mode « performance » dans les réglages.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L311-L344】
- Le cache par transient utilise une expiration fixe de 24 h et un `clear()` global qui vide tous les profils/locales. Ajouter une purge différentielle (invalidation par profil/localisation), un pré-chargement à l'activation et une instrumentation (statistiques de hit/miss) alignerait la maintenance sur les offres pro orientées équipe.【F:sidebar-jlg/src/Cache/MenuCache.php†L7-L135】
- Aucun test automatisé n'analyse la taille/scope des assets générés ; intégrer un audit de bundle et des seuils d'alerte dans la CI garantirait la dérive maîtrisée après chaque version.
- Les transients sont purgés globalement sans ciblage fin : `delete_transient` est appelé par identifiant mais sans suivi différentiel des profils/langues, ce qui limite les purges intelligentes sur gros catalogues.【F:sidebar-jlg/src/Cache/MenuCache.php†L63-L135】 Définir un index par profil et locale puis une purge différentielle rapprocherait la maintenance des solutions pro.
- Aucun pré-chargement n'est déclenché après modification majeure ; les entrées du cache ne sont créées qu'à la première requête. Ajouter un « warmup » (cron ou action asynchrone) après sauvegarde éviterait les pics de latence observés dans les suites pro.
- Les scripts publics et admin ne sont pas audités automatiquement (pas de mesure de bundle ou d'éligibilité HTTP/2 push). Intégrer un rapport Lighthouse/Bundle Analyzer dans la CI mettrait Sidebar JLG au niveau des solutions premium en matière de suivi de performance.
- Mettre en place une instrumentation simple (compteur hit/miss, durée de génération) stockée dans les transients ou via une table dédiée fournirait des insights pour le futur dashboard observabilité.

## 7. Interopérabilité & écosystème

**Forces actuelles**

- Les endpoints AJAX internes couvrent la recherche de contenus, l'import/export des réglages et la génération de prévisualisations, ce qui facilite déjà plusieurs workflows d'administration avancés.【F:sidebar-jlg/src/Ajax/Endpoints.php†L50-L159】

**Écarts & pistes**

- Les hooks exposés restent cantonnés à `wp_ajax_*` côté authentifié : aucune déclinaison REST ou `wp_ajax_nopriv_*` ne permet d'intégrer la sidebar dans des architectures headless ou des parcours personnalisés. Fournir une API REST (ou GraphQL) avec schéma documenté et permissions granulaires élargirait l'écosystème (connecteurs CRM, automatisations marketing).【F:sidebar-jlg/src/Ajax/Endpoints.php†L50-L159】
- Aucun SDK ni documentation de Webhooks n'est proposé pour relier des suites analytiques ou des outils d'orchestration (Zapier, Make). Publier une feuille de route publique, un espace développeur et des exemples d'intégration aiderait à rivaliser avec les plateformes pro à forte communauté.
- Cartographier les dépendances WordPress existantes (hooks, CPT, options) et publier un schéma d'extension clarifie les points d'ancrage pour les agences, à l'image des « Developer Resources » d'Elementor.

## 8. Synthèse UI/UX & design

**Constats UI actuels**

- L'interface admin reste organisée autour d'un unique tableau de réglages par onglet (`<table class="form-table">`) sans hiérarchie secondaire, ce qui impose un long scroll comparé aux builders professionnels qui segmentent par panneaux/contextes.【F:sidebar-jlg/includes/admin-page.php†L96-L229】
- Les composants visuels s'appuient majoritairement sur les styles WordPress par défaut (nav tabs, tables, boutons) avec un habillage ponctuel (`admin-style.css`) limité à quelques cartes/presets ; l'identité du produit n'est pas affirmée.【F:sidebar-jlg/assets/css/admin-style.css†L1-L102】
- Les aperçus utilisent un fond blanc et des boutons système, sans rappel des couleurs configurées, ce qui rend la validation visuelle moins immédiate qu'une prévisualisation sur fond contextualisé ou avec un mockup d'appareil.【F:sidebar-jlg/includes/admin-page.php†L60-L95】

**Améliorations proposées**

- Introduire un layout en deux colonnes avec une barre latérale persistante (états, raccourcis, documentation) et transformer chaque groupe de réglages en cartes pliables/accumulables pour réduire la charge cognitive ; prévoir un champ de recherche de réglages similaire à Elementor Pro pour naviguer rapidement.【F:sidebar-jlg/includes/admin-page.php†L96-L229】
- Construire un design system léger (palette, typographies, composants de formulaire) et remplacer les tables par des `fieldset` modulaires avec aides contextuelles, badges d'état et contrôles illustrés ; appliquer ces styles via `admin-style.css` pour dépasser la simple surcharge des classes WordPress.【F:sidebar-jlg/assets/css/admin-style.css†L1-L180】
- Enrichir l'aperçu avec des cadres de devices (mobile/tablette/desktop), un fond personnalisable et des overlays d'accessibilité (contraste, focus) afin de rapprocher l'expérience de tests visuels des solutions premium ; connecter ces contrôles à la toolbar existante pour rester dans le même flux de travail.【F:sidebar-jlg/includes/admin-page.php†L60-L95】
- Mettre en place une bibliothèque de composants réutilisables (cartes de menu, tuiles d'icônes) avec des états hover/focus documentés, afin d'aligner la cohérence visuelle entre l'administration et le rendu public et d'accélérer la création d'un éditeur visuel ultérieur.【F:sidebar-jlg/includes/sidebar-template.php†L129-L229】【F:sidebar-jlg/assets/css/admin-style.css†L103-L180】

---

En priorisant un éditeur visuel temps réel, des scénarios conditionnels avancés et un accompagnement accessibilité/analytics, Sidebar JLG pourra rivaliser avec les leaders tout en capitalisant sur son intégration WordPress native.

## 9. Approfondissements UX/UI inspirés des suites professionnelles

### 9.1 Palette de commande et regroupements contextuels

Les onglets actuels reposent sur un ruban `nav-tab` classique et de longues tables de réglages, ce qui oblige à scroller pour retrouver un champ précis lors des itérations rapides.【F:sidebar-jlg/includes/admin-page.php†L96-L229】 Les constructeurs premium comme Elementor ou JetMenu proposent désormais une palette de commande (`Cmd/Ctrl + K`) et un moteur de recherche de réglages. Pour rapprocher Sidebar JLG de ces standards :

**Objectif UX**
- Offrir un accès instantané à n'importe quel réglage par la saisie clavier ou via des favoris contextuels.
- Réduire le temps passé à naviguer entre les onglets lorsque l'utilisateur effectue des tests A/B successifs.

**Solutions proposées**
- Ajouter une barre de recherche persistante en haut de l'interface qui filtre dynamiquement les champs visibles par mot-clé, catégorie ou type d'option. Les résultats doivent être accessibles au clavier (flèches + Entrée) et mettre en évidence le champ ciblé dans le panneau principal.
- Introduire une palette de commande (`Cmd/Ctrl + K`) qui ouvre un modal léger listant les actions rapides : aller à un onglet, basculer un preset, dupliquer un profil, ouvrir l'aperçu. Le modal doit être alimenté par un index JSON des réglages pour garantir des performances instantanées.
- Ajouter une section « Favoris » ou « Récents » qui mémorise les groupes utilisés lors des sessions précédentes. On peut stocker ces préférences côté navigateur (`localStorage`) afin d'offrir une expérience personnalisée sans impacter le serveur.
- Prévoir un mode compact qui réorganise les champs en deux colonnes responsives, avec des labels au-dessus et des descriptions en tooltip, à la manière des panneaux latéraux de Figma. Ce mode pourrait être activé via un toggle dans la barre d'outils.

**Livrables & prochaines étapes**
- Maquettes haute fidélité du header de configuration (search + palette + toggle mode compact).
- Spécification fonctionnelle du moteur de recherche (structure de l'index, logique de scoring, raccourcis clavier).
- Prototype interactif (Figma ou Storybook) pour tester les animations d'ouverture/fermeture de la palette.

### 9.2 Mode "canvas" immersif pour l'aperçu

L'aperçu AJAX actuel offre déjà des boutons de breakpoint et une bascule comparaison, mais reste encapsulé dans un panneau statique blanc.【F:sidebar-jlg/includes/admin-page.php†L107-L132】 Pour proposer une expérience équivalente aux simulateurs de Framer ou ConvertBox :

**Objectif UX**
- Faire de l'aperçu une zone d'édition immersive qui réduit les allers-retours entre configuration et validation.
- Permettre aux profils avancés de tester des variations visuelles dans un environnement quasi-réel.

**Solutions proposées**
- Créer un mode plein écran « canvas » qui masque temporairement le reste de l'interface et verrouille l'édition contextuelle. L'utilisateur peut cliquer directement sur les textes, icônes ou boutons pour ouvrir des popovers d'édition inline (titre, couleur, icône).
- Intégrer des cadres d'appareils (smartphone, tablette, desktop, widescreen) avec la possibilité de choisir un fond (couleur, image) afin de vérifier le contraste et la lisibilité. Les cadres doivent être responsive et mémoriser le dernier appareil utilisé.
- Ajouter des overlays d'analyse tels que : heatmap des clics récents, surlignage des zones à faible contraste, affichage du chemin de focus clavier. Ces overlays pourraient être alimentés par les métriques internes et se superposer via des calques activables.
- Prévoir une mini-barre d'outils flottante (breakpoints, undo/redo, historique) accessible dans ce mode canvas pour limiter les interactions avec le panneau principal.

**Livrables & prochaines étapes**
- Diagramme d'état du mode canvas (entrée/sortie, verrouillage, raccourcis clavier).
- Spécification des APIs nécessaires côté aperçu (édition inline, synchronisation des modifications en direct avec les formulaires).
- Prototype de transitions (CSS/JS) pour animer le passage au canvas et l'affichage des overlays.

### 9.3 Tableau de bord narratif

Le tab « Insights & Analytics » synthétise déjà les totaux, l'historique 7 jours et la répartition par profil au format tableau.【F:sidebar-jlg/includes/admin-page.php†L942-L1099】 Pour se rapprocher de la narration visuelle des solutions marketing (OptinMonster, ConvertBox) :

**Objectif UX**
- Transformer les données brutes en storytelling opérationnel qui suggère des actions concrètes.

## 10. Synthèse des chantiers à court terme

- **Éditeur visuel** : prioriser le mode canvas immersif et la palette de commandes pour réduire l'écart d'expérience avec les suites premium.
- **Scénarios conditionnels** : ajouter des déclencheurs comportementaux (scroll depth, temporisation, sortie) et un A/B testing léger pour se rapprocher des offres marketing.
- **Accessibilité** : intégrer l'audit Pa11y et afficher des alertes contextuelles lorsque les contrastes ou les raccourcis clavier sont incomplets.
- **Analytics narratifs** : transformer le tableau Insights en storytelling actionnable (cartes synthèse, recommandations) et préparer des exports automatisables.
- **Interopérabilité** : cadrer une API REST + webhooks pour exposer les événements clés et faciliter les intégrations (Zapier, Make, CRM).
- Faciliter la comparaison temporelle sans obliger l'utilisateur à exporter les données.

**Solutions proposées**
- Convertir les KPI clés (ouvertures, conversions, taux de clic) en cartes visuelles avec sparklines et indications de tendance (+/- vs période précédente). Chaque carte doit comporter une recommandation automatique (ex. « Boostez ce CTA sur desktop ») générée selon des seuils configurables.
- Mettre en avant les profils ou surfaces en surperformance via des badges de couleur, des icônes et des libellés descriptifs. Une section « Opportunités » suggère des actions (dupliquer un profil, ajuster un horaire) avec un bouton direct vers l'action correspondante.
- Offrir une timeline interactive qui recense les événements (ouverture, clic CTA, fermeture automatique) avec un filtre par déclencheur (scroll depth, temporisation, segment). Les pics sont annotés pour expliquer les variations (campagne spécifique, changement de design).
- Prévoir une exportation rapide (PDF/PNG) du dashboard narratif pour les réunions d'équipe.

**Livrables & prochaines étapes**
- Wireframe du dashboard réorganisé (grille de cartes, timeline, section opportunités).
- Schéma de données nécessaires pour alimenter les annotations automatiques et les comparaisons temporelles.
- Plan de tests utilisateurs (questions de compréhension, temps pour identifier une opportunité).

### 9.4 Atelier de profils contextuels

L'éditeur de profils propose déjà un planificateur horaire, des filtres par taxonomie et des actions de clonage, mais sans visualisation synthétique des cibles ni prévisualisation contextuelle.【F:sidebar-jlg/includes/admin-page.php†L900-L923】 Pour atteindre le niveau des « Audience Builders » professionnels :

**Objectif UX**
- Simplifier la compréhension des segments actifs et éviter les configurations conflictuelles.
- Offrir une validation visuelle immédiate du rendu associé à chaque profil.

**Solutions proposées**
- Ajouter un résumé graphique en tête de chaque profil : badges colorés pour les critères (pays, rôles, devices), icônes d'état (actif, en attente, expiré) et alertes lorsque des règles se chevauchent. Les alertes renvoient vers un panneau latéral expliquant le conflit.
- Intégrer une prévisualisation instantanée couplée à l'aperçu principal : sélectionner un profil recharge le canvas avec les contenus et styles correspondants, sans quitter l'onglet. Une bascule « comparer » pourrait juxtaposer deux profils.
- Proposer une bibliothèque de profils préconfigurés (Week-end mobile, VIP connectés, Nouveaux visiteurs) avec descriptions et indicateurs de performance attendue. L'utilisateur peut les activer et personnaliser rapidement.
- Introduire un système de tags et de recherche dans la liste des profils pour retrouver instantanément un segment.

**Livrables & prochaines étapes**
- User flow détaillé de la création/modification de profil intégrant la prévisualisation en direct.
- Kit UI des badges, alertes et cartes de profils (états par couleur, icônes normalisées).
- Backlog technique listant les dépendances (API d'aperçu, stockage des presets, gestion des conflits).

### 9.5 Design system cohérent et composables

Les styles admin reposent encore majoritairement sur les classes WordPress (`form-table`, `widefat`) avec quelques cartes/presets dédiées.【F:sidebar-jlg/assets/css/admin-style.css†L1-L180】 Pour renforcer la cohérence et préparer une future interface SPA :

**Objectif UX/UI**
- Créer un langage visuel distinctif et unifié entre l'administration, l'aperçu et le front.
- Garantir la réutilisabilité des composants lors de l'ajout d'un builder visuel.

**Solutions proposées**
- Définir des tokens de design (palette, typographies, rayons, ombres, espacement) et documenter leur usage dans un guide accessible depuis l'admin. Ces tokens seront injectés via CSS Custom Properties pour simplifier leur réutilisation.
- Remplacer progressivement les tables par des `fieldset` modulaires ou des cartes avec titres collants, descriptions contextuelles, helpers inline et états (succès, attention). Chaque composant doit exister en version light/dark pour anticiper un mode sombre.
- Décliner les préréglages actuels en cartes interactives réutilisables dans plusieurs contextes (sélection de menus, CTA, badges d'état). Ces cartes afficheront un aperçu miniature et des métadonnées (nombre de liens, style principal) pour faciliter les décisions.
- Préparer une librairie de composants (React ou Web Components) dans Storybook pour tester et documenter les interactions, en vue d'une éventuelle migration vers une interface type SPA.

**Livrables & prochaines étapes**
- Fondation du design system (fichier Figma + tokens exportables).
- Documentation Storybook initiale (boutons, champs, cartes, badges, overlays).
- Plan de migration progressive des écrans existants vers les nouveaux composants.

### 9.6 Micro-interactions front & mobile

Le script public gère déjà le tracking Analytics, les CTA et l'accessibilité clavier, mais reste focalisé sur les clics et l'ouverture via le bouton toggle.【F:sidebar-jlg/assets/js/public-script.js†L36-L219】 Pour atteindre le niveau d'apps mobiles premium :

**Objectif UX**
- Fluidifier la perception de la sidebar sur mobile et offrir des retours sensoriels riches.
- Augmenter l'engagement tout en respectant les préférences d'accessibilité.

**Solutions proposées**
- Ajouter des gestes tactiles natifs (swipe depuis le bord, swipe pour fermer, pull-to-refresh du contenu) gérés via `PointerEvent`/`TouchEvent`, avec seuils de déclenchement clairs et animations liées. En complément, déclencher des retours haptiques légers via l'API `navigator.vibrate` lorsque disponible.
- Implémenter un « smart reopen » : mémoriser dans `localStorage` le dernier sous-menu ouvert, la position de scroll et les CTA cliqués pour permettre à l'utilisateur de retrouver l'état précédent après un rechargement ou une navigation interne.
- Synchroniser les animations avec `prefers-reduced-motion`, mais proposer une palette de transitions contextuelles (rebond, slide, zoom léger) choisies selon le preset actif. Les utilisateurs peuvent sélectionner leur style d'animation dans les réglages.
- Ajouter des micro-indicateurs (glow autour du CTA actif, badge animé lorsqu'une promotion est disponible, pulsation sur le bouton hamburger) en respectant les règles d'accessibilité (aria-live, contraste).

**Livrables & prochaines étapes**
- Storyboard des gestes mobiles et retours haptiques associés.
- Prototype technique des interactions (CodePen/Storybook) validant la performance sur appareils à faible puissance.
- Plan de tests QA incluant la compatibilité avec `prefers-reduced-motion`, VoiceOver/TalkBack et différents navigateurs mobiles.

