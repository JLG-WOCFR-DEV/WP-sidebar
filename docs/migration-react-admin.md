# Migration de l’interface d’administration vers React

La version actuelle introduit une application React/TypeScript montée via `@wordpress/scripts` pour piloter les onglets principaux de la sidebar (Général, Styles, Profils). L’ancien formulaire PHP reste disponible comme repli non-JS à l’intérieur du conteneur `#sidebar-jlg-legacy-settings`.

## Points clés

- Un bundle `sidebar-jlg/assets/js/admin-app.tsx` est généré dans `assets/build/admin-app.js` et chargé sur la page d’options.
- L’état partagé (options, profils, onboarding) est centralisé dans un store Zustand (`sidebar-jlg/assets/js/admin-app/store/optionsStore.ts`).
- Le canvas plein écran consomme l’endpoint AJAX existant `jlg_render_preview` pour afficher l’aperçu et prendre en charge l’édition inline (titres, CTA, couleur du bouton) avec Undo/Redo.
- Un assistant de démarrage en cinq étapes est déclenché lors du premier accès. Sa progression est stockée dans l’option `sidebar_jlg_onboarding_state` (exposée dans l’API REST).
- Le formulaire historique est conservé comme fallback et sera supprimé dans une version future après validation de l’interface React.

## Actions requises pour les contributions

1. **Scripts de build** : lancer `npm run build` (ou `npm run start:assets`) pour produire le bundle `admin-app`. Les dépendances JS incluent désormais `zustand` et `@testing-library/*` pour les tests.
2. **Tests** : exécuter `npm run test:js` qui couvre les nouveaux tests Jest (`__tests__/optionsStore.test.ts`, `__tests__/OnboardingModal.test.tsx`).
3. **Extensibilité** : toute évolution des réglages doit mettre à jour le store Zustand et, si nécessaire, l’assistant (`strings.onboardingSteps`). Le fallback PHP doit être gardé en cohérence tant que la migration n’est pas finalisée.

Pour une intégration progressive, vérifiez que `sidebarJLGApp` contient bien les données sérialisées par `MenuPage::enqueueAssets` et que le build génère `admin-app.asset.php` avec les dépendances attendues.
