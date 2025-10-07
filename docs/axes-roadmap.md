# Feuille de route des axes d'amélioration

Ce document décline les axes stratégiques identifiés dans le README en lots actionnables. Chaque axe est ventilé en objectifs court terme, jalons et livrables pour guider les itérations à venir.

| Axe | Objectif stratégique | Prochain lot livrable | Pré-requis / dépendances | Indicateurs de réussite |
| --- | --- | --- | --- | --- |
| 1. Éditeur visuel temps réel | Réduire le fossé entre configuration déclarative et rendu final. | Prototype canvas plein écran avec édition inline sur titres/boutons. | Inventaire des champs éditables, API d'aperçu temps réel. | Temps moyen de configuration < 50 % par rapport à l'interface actuelle ; tests utilisateurs positifs. |
| 2. Bibliothèque de composants | Offrir des modules prêts à l'emploi (CTA, formulaires, flux). | Conception de 3 cartes CTA réutilisables + tracking standardisé. | Design system établi, gabarits de données pour CTA. | Taux d'adoption des CTA sur 3 projets pilotes ; absence de tickets de support majeurs. |
| 3. Personnalisation dynamique & analytics | Déclencher la sidebar selon le comportement et mesurer l'impact. | Ajout de triggers scroll depth + timer et rapport comparatif 7j/30j. | Évolution du schéma des réglages, stockage analytics étendu. | Augmentation des conversions CTA de 10 % sur profils instrumentés. |
| 4. Internationalisation avancée | Simplifier le déploiement multi-langues. | Synchronisation automatique des profils WPML/Polylang + duplication rapide. | Audit des APIs WPML/Polylang, mapping des options multilingues. | Temps d'onboarding d'un site bilingue < 1h ; absence d'incohérences de langues en QA. |
| 5. Automatisation qualité | Sécuriser les contributions externes. | Pipeline GitHub Actions (lint PHP/JS + tests unitaires). | Configuration des linters (PHPCS, ESLint), conteneur CI. | Builds principaux verts sur 5 exécutions consécutives ; baisse des régressions signalées. |
| 6. Accessibilité renforcée | Atteindre un niveau WCAG AA documenté. | Checklist WCAG 2.2 intégrée + script Pa11y prêt pour `docs/demo.html`. | Inventaire des composants à tester, scripts pa11y/axe. | Score Lighthouse Accessibilité ≥ 90 ; checklist complétée sur 100 % des releases. |
| 7. Monétisation & support | Structurer l'offre et rassurer les équipes. | Publication d'une roadmap publique + canal Discord/Forum. | Définition du positionnement commercial, charte support. | Nombre d'inscriptions au canal support, feedback positif des bêta-testeurs. |
| 8. Onboarding guidé | Accélérer la découverte des fonctionnalités clés. | Assistant 5 étapes (menu, styles, profils, analytics, publication). | Scripting du tour produit, contenu tutoriel. | 80 % des nouveaux utilisateurs complètent l'onboarding ; baisse des tickets "comment commencer ?". |
| 9. Compatibilité thèmes & builders | Anticiper les spécificités CSS des thèmes majeurs. | Pack de presets pour Astra, Divi, Bricks avec snippets CSS. | Tests de rendu sur environnements de démo, documentation. | Validation QA sur 3 thèmes populaires sans régression CSS. |
| 10. Observabilité produit | Ouvrir les données au reste de l'écosystème. | Webhooks (ouvertures, clics) + docs REST v1. | Conception du schéma d'événements, sécurisation via clés API. | Intégrations réussies avec 2 outils externes (Zapier/Make). |
| 11. Expérience mobile améliorée | Améliorer la perception sur terminaux tactiles. | Gestes swipe + mode split-view dans l'aperçu admin. | Prototype d'interactions tactiles, tests compatibilité. | Score de satisfaction mobile > 4/5 sur panel interne. |
| 12. Marketplace d'extensions | Créer un levier communautaire. | Spécification d'un manifest d'extensions + SDK PHP minimal. | API d'enregistrement des extensions, guide développeur. | 3 extensions communautaires publiées ; temps moyen d'intégration < 1 jour. |

## Gouvernance

- **Responsable produit** : consolide les feedbacks, hiérarchise les lots et valide la feuille de route à chaque release.
- **Lead technique** : arbitre les dépendances (API, stockage, performance) et garantit la cohérence avec le noyau actuel.
- **Designer UX/UI** : produit les maquettes haute fidélité, prototypes interactifs et veille à la cohérence du design system.
- **QA & Accessibilité** : définit les scénarios de tests, entretient la checklist WCAG et prépare les scripts d'automatisation.

## Cadence de revue

- **Hebdomadaire** : point d'avancement sur les lots en cours, identification des blocages et ajustement des priorités.
- **Mensuel** : revue des métriques (adoption, satisfaction) et actualisation des indicateurs de réussite.
- **Par release** : rétrospective croisant objectifs atteints, feedbacks utilisateurs et axes à intégrer dans le prochain cycle.

