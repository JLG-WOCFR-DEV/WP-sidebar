# Priorisation des recommandations de code

Ce document synthétise les constats des dernières revues (`docs/code-review*.md`) et hiérarchise les chantiers purement "code" à planifier. Les critères retenus :

- **Impact utilisateur** (accessibilité, fiabilité, performance perçue) ;
- **Effort estimé** selon l'architecture actuelle ;
- **Risque de régression** mis en lumière par les audits précédents.

## P0 – À traiter en priorité absolue

1. **Durcir la modale d’aperçu (PreviewCanvas)**
   - Extraire le piégeage de focus (snapshot `inert`, boucle Tab/Maj+Tab, restauration du focus) dans un hook utilitaire (`useFocusTrap`) pour supprimer les duplications et fiabiliser les futures modales.
   - Couvrir la modale par des tests React Testing Library : focus initial, navigation circulaire, fermeture via `Escape`, restauration du focus à la fermeture.
   - Vérifier la présence systématique des attributs `aria-labelledby`/`aria-describedby` lors de la génération dynamique d’identifiants.
   - Fichiers ciblés : `assets/js/admin-app/components/PreviewCanvas.tsx`, nouveau hook dans `assets/js/admin-app/hooks/`.

2. **Neutraliser toutes les icônes décoratives**
   - Généraliser le helper `makeInlineIconDecorative()` pour qu’il s’applique aux icônes des menus, des widgets et des blocs sociaux avant insertion dans le DOM.
   - Ajouter des tests PHPUnit dédiés (ex. `tests/TemplatingTest.php`) qui valident la présence de `aria-hidden="true"`, `focusable="false"` et `role="presentation"` sur chaque SVG inline.
   - S’assurer que les imports d’icônes personnalisées conservent leurs attributs ARIA utiles (labels, descriptions) lorsque l’icône n’est pas purement décorative.

## P1 – Haute priorité (livraison court terme)

1. **Informer explicitement des ouvertures externes**
   - Harmoniser l’annonce "– s’ouvre dans une nouvelle fenêtre" sur tous les liens sociaux (`renderSocialIcons`) et prévoir la traduction correspondante.
   - Compléter les tests PHP pour vérifier le rendu de l’attribut `aria-label` et du `<span class="screen-reader-text">` inséré.

2. **Masquer correctement les champs conditionnels**
   - Ajouter un utilitaire JavaScript (`toggleAriaVisibility(element, isVisible)`) qui pose `hidden`, `aria-hidden`, `aria-disabled` et gère le `tabindex`.
   - Remplacer les manipulations directes de `display: none` dans `assets/js/admin-script.js` par ce helper afin d’éviter les pièges de focus signalés par Pa11y.

## P2 – Priorité moyenne (planifier après stabilisation)

1. **Modulariser `admin-script.js`**
   - Scinder le script monolithique en modules par onglet (profils, analytics, import/export) et introduire un bundler (Vite/webpack) pour générer des chunks différés.
   - Convertir progressivement les blocs jQuery critiques en modules TypeScript testés (Jest) pour fiabiliser l’évolution.

2. **Mettre en cache les variables CSS**
   - Mettre en place un cache (transient/objet) pour les chaînes de styles générées dans `SidebarRenderer`, avec invalidations sur sauvegarde des réglages, changement de version ou purge de profil.
   - Ajout de tests PHPUnit pour garantir la cohérence du cache et des scénarios d’invalidation.

## Méthodologie conseillée

- **Sprint 1** : livrer les correctifs P0 + P1 (focus modale, icônes décoratives, annonces d’ouverture externe, helper d’accessibilité JS).
- **Sprint 2** : lancer la modularisation JS et le cache CSS (P2) tout en instrumentant des tests automatisés.
- **Rythme continu** : chaque PR touchant un composant interactif doit renseigner la checklist accessibilité (`docs/accessibility-checklist.md`) et inclure un test ciblé pour prévenir les régressions.

Cette priorisation rapproche la base de code des standards des solutions professionnelles tout en sécurisant l’expérience actuelle.
