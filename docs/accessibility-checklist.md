# Checklist d’accessibilité WCAG 2.2

Cette checklist accompagne l’onglet **Accessibilité & WCAG 2.2** de l’administration du plugin. Elle couvre les principaux critères AA à vérifier avant publication d’un profil ou d’une campagne.

## Critères suivis

1. **Contraste du texte et des composants** – Ratio minimal 4,5:1 (3:1 pour les composants UI) pour toutes les variantes de la sidebar.
2. **Focus visible et non masqué** – Les indicateurs de focus demeurent visibles sur tous les éléments interactifs, même lorsque la sidebar est ouverte.
3. **Navigation clavier complète** – L’ouverture, la fermeture et la navigation des sous-menus sont possibles sans souris ni piège de focus.
4. **Cibles tactiles suffisantes** – Bouton hamburger, icônes sociales et liens répondent au minimum de 24×24 px ou disposent d’un espacement équivalent.
5. **Alternative aux actions de glisser-déposer** – Chaque action drag & drop possède une commande clavier ou un bouton équivalent.
6. **Aide et labels cohérents** – Textes d’aide, infobulles et labels restent identiques quel que soit le profil actif.
7. **Animations sûres et préférences utilisateur** – Les effets respectent la préférence « réduire les animations » et évitent les scintillements.

Chaque item renvoie vers la documentation officielle du W3C afin de faciliter la revue détaillée.

## Utilisation recommandée

- **Avant mise en production** : parcourir chaque item et cocher la case correspondante lorsque le critère est vérifié sur le site cible.
- **Partage d’état** : les cases cochées sont stockées dans l’option `sidebar_jlg_accessibility_checklist`. Exportez vos réglages pour synchroniser l’avancement avec votre équipe ou votre client.
- **Audit automatisé** : lancez `npm run audit:accessibility` pour exécuter Pa11y sur la page de démonstration (`docs/demo.html`). Ajoutez vos propres URLs dans `pa11y.config.json` pour couvrir des scénarios supplémentaires.

## Aller plus loin

- Intégrer la checklist aux revues de QA avant chaque release.
- Compléter l’audit automatisé avec des revues manuelles (lecteurs d’écran, navigation mobile, contraste en conditions réelles).
- Documenter les écarts éventuels et créer des tickets de suivi lorsque la checklist n’est pas totalement validée.

## Actions en préparation

- Ajouter une alerte proactive dans l'onglet **Accessibilité & WCAG 2.2** lorsque `npm run audit:accessibility` n'a pas été exécuté depuis 30 jours (stockage de l'horodatage côté option).
- Fournir un modèle de rapport Pa11y (`docs/a11y-report-template.md`, à créer) pour standardiser la remontée des écarts.
- Étendre les tests manuels à la navigation via lecteurs d'écran (NVDA, VoiceOver) et consigner les résultats dans le référentiel QA (`docs/axes-roadmap.md`).
