# Revue ergonomique et technique de Sidebar JLG

## 1. Ergonomie générale & présentation des options

* **Charge cognitive élevée sur l'écran principal** – La page d'administration accumule neuf onglets, un aperçu, un assistant de démarrage et plusieurs accordéons au sein du même gabarit PHP de plus de 1 500 lignes (`includes/admin-page.php`). Les utilisateurs doivent parcourir de longues tables de formulaires avec des champs conditionnels affichés/masqués via des styles inline (`display:none`), sans hiérarchie visuelle forte ni résumé contextuel.【F:sidebar-jlg/includes/admin-page.php†L114-L379】
* **Contrôles hétérogènes** – Les mêmes sections mélangent cases à cocher en tableaux WordPress, contrôles personnalisés « unit-control » et contenus éditoriaux. La densité rend la lecture difficile par rapport à des produits professionnels qui privilégient des panneaux latéraux ou un mode assistant (wizard) progressif.【F:sidebar-jlg/includes/admin-page.php†L284-L379】

**Pistes d'amélioration**

1. Extraire le gabarit en composants (par exemple React + `@wordpress/components`) pour bénéficier de `Panel`, `PanelBody`, `TabPanel` et `SlotFill` en natif, ce qui permet d'afficher des réglages contextualisés et de réduire la densité sur chaque écran.
2. Proposer un mode « configuration guidée » en plusieurs étapes (activation, contenu, style, diffusion) avec sauvegarde incrémentale. Cette approche rapproche l'expérience de celle d'outils SaaS modernes où l'on réduit la surface cognitive au moment opportun.
3. Ajouter une barre de recherche de réglages et des badges d'état (ex. « défaut », « personnalisé ») pour retrouver rapidement les options modifiées.

## 2. UX / UI détaillée

* **Navigation par onglets perfectible** – Les onglets sont rendus via des liens `<a>` et pilotés en jQuery. Les états `aria-selected` sont mis à jour côté client, mais la structure HTML initiale contient déjà `aria-selected="true"` sur plusieurs éléments, ce qui peut provoquer des annonces contradictoires avant l'initialisation JS.【F:sidebar-jlg/includes/admin-page.php†L129-L139】【F:sidebar-jlg/assets/js/admin-script.js†L3240-L3341】
* **Champs conditionnels masqués** – Les options spécifiques (« floating-options-field ») sont uniquement cachées visuellement sans attributs ARIA (`aria-hidden`, `aria-disabled`). Les champs restent focusables pour les technologies d'assistance avant que le script ne corrige l'état, ce qui diverge des standards professionnels.【F:sidebar-jlg/includes/admin-page.php†L301-L379】

**Pistes d'amélioration**

1. Remplacer les ancres par des composants `TabPanel` ou des boutons avec gestion ARIA côté serveur pour éviter les flashes d'états contradictoires. En React/TypeScript, il est possible de centraliser les états dans un store et d'appliquer le rendu côté serveur avec les bons attributs par défaut.
2. Lors du masquage d'options conditionnelles, ajouter `hidden`, `aria-hidden="true"` et désactiver les champs (`disabled` ou `aria-disabled="true"`). En React, un simple wrapper qui gère visibilité + accessibilité limite les régressions.

## 3. Accessibilité

* **Composants personnalisés non standard** – Les contrôles d'unités sont reconstruits manuellement lorsqu'`wp.components.UnitControl` est absent. Ils injectent dynamiquement des inputs et feedbacks, mais la logique repose sur du texte libre et ne fournit pas systématiquement d'étiquettes `for`/`id`, ce qui complique la navigation clavier et la lecture d'écran.【F:sidebar-jlg/assets/js/admin-script.js†L2500-L2585】
* **Aperçu sans fallback textuel** – Le conteneur d'aperçu affiche un `div` vide tant que le script n'a pas chargé l'iframe. Aucun message n'est rendu côté serveur pour informer les utilisateurs sans JS ou lecteurs d'écran, contrairement aux pratiques pro où un `noscript` ou un message initial est fourni.【F:sidebar-jlg/includes/admin-page.php†L141-L160】

**Pistes d'amélioration**

1. Factoriser les contrôles sur base de composants accessibles (`<label>` lié à `<input>` avec `id`) et fournir des messages d'erreur via `aria-live` initialisé côté PHP, pas uniquement via JS.
2. Pré-rendre un texte « Aperçu en attente » dans le DOM ou une balise `<noscript>` explicite pour les cas sans JavaScript.
3. Ajouter des tests `pa11y` ou `axe-core` dans la CI pour contrôler la régression des attributs ARIA.

## 4. Performance

* **Script d'administration monolithique** – `admin-script.js` fait plus de 6 500 lignes, mélange jQuery et API WordPress modernes, et est livré tel quel sans bundler ni découpage par onglet. Le navigateur doit parser l'intégralité du fichier, même si l'utilisateur ne visite que les sections basiques.【F:sidebar-jlg/assets/js/admin-script.js†L1-L6524】
* **Styles dynamiques calculés à chaque requête** – `SidebarRenderer` reconstruit l'intégralité d'une map de variables CSS à chaque affichage front, même si les réglages n'ont pas changé. Sur des sites à fort trafic, la génération répétée de la chaîne `:root { ... }` peut devenir coûteuse et empêche la mise en cache HTTP du CSS.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L15-L205】【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L320-L421】

**Pistes d'amélioration**

1. Migrer le code d'admin vers un projet TypeScript bundlé (Vite/webpack) avec code splitting par onglet. Charger dynamiquement les modules (profiling, audit, presets) uniquement quand l'utilisateur active ces sections.
2. Mettre en place un cache transitoire pour les variables CSS (par exemple via `wp_cache_set`) ou générer un fichier CSS dédié lors de la sauvegarde des réglages. Cela rapproche le fonctionnement de solutions pro qui servent des assets statiques versionnés.
3. Profiter de la REST API déjà exposée (`show_in_rest`) pour déplacer la prévisualisation dans une application découplée et limiter les injections inline.

## 5. Fiabilité

* **Mélange de sources de vérité** – Les réglages sont sauvegardés dans `sidebar_jlg_settings` tandis que les profils disposent d'options séparées. Le contrôleur front-end fusionne profil et réglages en temps réel, mais aucun verrou n'empêche la suppression d'un profil actif, ce qui peut laisser l'option `sidebar_jlg_active_profile` pointer vers un ID inexistant.【F:sidebar-jlg/src/Settings/SettingsRepository.php†L48-L120】【F:sidebar-jlg/src/Admin/SettingsSanitizer.php†L126-L193】
* **Tolérance aux erreurs silencieuse** – `SidebarRenderer::outputSidebar` affiche simplement le HTML et ignore les erreurs de rendu (retour `null`). Aucun log utilisateur ou fallback n'est proposé alors qu'un produit pro proposerait un message ou un mode dégradé.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L922-L966】

**Pistes d'amélioration**

1. Lorsque l'utilisateur supprime un profil, vérifier côté serveur si l'ID était actif et forcer le retour aux réglages globaux avec notification dans l'UI.
2. Ajouter une gestion d'erreur lors du rendu (try/catch autour du template) avec message administrateur via `wp_die` ou admin notice afin de diagnostiquer rapidement les templates surchargés.
3. Introduire des tests d'intégration (PHPUnit + Playwright) pour garantir que le rendu de la sidebar fonctionne avec et sans profils personnalisés.

---

### Synthèse

Le plugin propose une couverture fonctionnelle riche mais s'éloigne des standards UX/UI des applications professionnelles en concentrant trop d'options dans une interface monolithique, en livrant un JavaScript massif non modularisé et en laissant des zones d'accessibilité/performance fragiles. En structurant l'UI autour de composants accessibles, en scindant le code côté client et en renforçant la logique de sauvegarde/caching, l'expérience serait plus proche des attentes d'outils premium.
