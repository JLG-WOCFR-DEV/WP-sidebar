# Audit des points d'amélioration

Cette analyse met en évidence des fonctions dont l'implémentation pourrait être renforcée pour s'aligner sur les standards des applications professionnelles, ainsi qu'un plan de tests ciblant les zones les plus sensibles.

## Fonctions prioritaires à améliorer

### `Plugin::register`
- **Constat :** la méthode invalide la totalité du cache et relance la revalidation des options à chaque chargement du plugin, y compris en frontal, avant d'enregistrer une grande quantité de hooks. Sur des installations importantes, cette séquence peut allonger le temps de réponse initial et déclencher des écritures concurrentes sur la base.【F:sidebar-jlg/src/Plugin.php†L69-L105】
- **Piste pro :** déclencher `maybeInvalidateCacheOnVersionChange()` et `revalidateStoredOptions()` dans un hook dédié à l'activation ou à l'upgrade (`upgrader_process_complete`), puis différer l'enregistrement des hooks non critiques (`admin_notices`, revalidation) via `wp_lazy_loading_run` ou en les encapsulant derrière des garde-fous basés sur le contexte (front/admin). Cela aligne le comportement sur les plugins premium qui séparent net les opérations coûteuses du cycle de requête utilisateur.

### `SidebarRenderer::buildDynamicStyles`
- **Constat :** la génération du CSS inline concatène les variables à chaque requête sans mise en cache granulaire, alors que la transformation `STYLE_VARIABLE_MAP` parcourt systématiquement toutes les options.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L341-L393】
- **Piste pro :** pré-calculer les styles par profil et par jeu d'options (hash du tableau) avec stockage court-terme (`wp_cache_set`) et invalider ce cache uniquement lors d'une modification pertinente. Les solutions professionnelles ajoutent également un préfixe de scope (`.sidebar-jlg` au lieu de `:root`) pour éviter les collisions CSS et faciliter l'injection conditionnelle.

### `SidebarRenderer::renderSidebarToHtml`
- **Constat :** le rendu repose sur `require` direct du template et sur un simple log en cas de fichier manquant, sans gestion de surcharge par thème ni filet de sécurité autour du buffer (pas de `try/finally`).【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L762-L800】
- **Piste pro :** exposer un filtre `apply_filters( 'sidebar_jlg_template_path', ... )`, supporter `locate_template()` pour permettre aux thèmes enfants de personnaliser la vue, et encapsuler la mise en tampon dans une structure `try { ... } finally { ob_end_clean(); }` afin de garantir la libération du buffer même en cas d'exception.

### `SidebarRenderer::render`
- **Constat :** la méthode écrit directement dans la sortie (`echo`) avant de retourner le HTML et repose sur un cache basé sur `transients`. Lorsqu'un filtre désactive le cache, l'index des locales est effacé de manière globale, ce qui peut invalider d'autres profils en parallèle.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L802-L849】
- **Piste pro :** renvoyer le HTML sans l'afficher (la vue pourrait gérer l'écho), utiliser un cache persistant (`wp_cache_get` avec groupe custom) ou une couche d'invalidation par profil, et remplacer l'appel à `forgetLocaleIndex()` par une suppression ciblée de l'entrée concernée pour éviter les effets de bord.

### `SettingsRepository::getOptionsWithRevalidation`
- **Constat :** en frontal, chaque appel peut déclencher `update_option('sidebar_jlg_settings', ...)` lorsqu'une revalidation ajuste une valeur, même si la requête est anonyme. Cela multiplie les écritures concurrentes sur la table `wp_options` et peut créer des contentions sur des sites à fort trafic.【F:sidebar-jlg/src/Settings/SettingsRepository.php†L118-L135】
- **Piste pro :** déplacer la revalidation vers des jobs différés (`wp_schedule_single_event`) ou vers l'administration, et ne sauver que si la requête est authentifiée ou provient d'un contexte de maintenance. Les plugins professionnels s'appuient sur des verrous (`update_option` conditionnel, transients de verrouillage) pour éviter les races.

### `MenuCache`
- **Constat :** l'index des locales est stocké dans une option globale et effacé en bloc lors d'un simple `clear()`, ce qui peut supprimer des caches valides pour d'autres profils. Par ailleurs, aucun mécanisme de surveillance des transients expirés n'est présent pour nettoyer l'index automatiquement.【F:sidebar-jlg/src/Cache/MenuCache.php†L66-L172】
- **Piste pro :** stocker l'index dans un objet de cache persistant (groupe `sidebar_jlg`) ou utiliser un schéma `locale => [profile => key]` permettant de supprimer finement une seule entrée. Ajouter un cron de maintenance qui purge l'index des combinaisons expirées rapproche le fonctionnement des solutions professionnelles.

## Plan de débogage et tests recommandés

1. **`tests/menu_cache_index_management_test.php` (nouveau)** – Vérifie la déduplication de l'index des locales et la bonne suppression des transients lors d'un `clear()`. Ce test met en évidence les risques d'effacement global du cache et sert de base pour les futures optimisations de ciblage.【F:tests/menu_cache_index_management_test.php†L1-L86】
2. **`tests/sidebar_profile_cache_isolation_test.php`** – À relancer après toute modification du cache pour s'assurer que les profils restent isolés dans leurs transients et que l'index ne fuit pas entre profils.【F:tests/sidebar_profile_cache_isolation_test.php†L1-L120】
3. **`tests/render_sidebar_active_state_test.php`** – Garantit que les scénarios dynamiques (page, article, catégorie, URL) continuent à désactiver la mise en cache. Utile pour vérifier les ajustements de `is_sidebar_output_dynamic()` lors de l'ajout de nouveaux cas (shortcodes, CTA dynamiques, etc.).【F:tests/render_sidebar_active_state_test.php†L1-L165】
4. **Tests manuels** – Activer le mode debug de WordPress (`WP_DEBUG`) puis naviguer sur un environnement de préproduction en surveillant le temps de réponse et le nombre de requêtes SQL pendant l'initialisation du plugin. Comparer les mesures avant/après implémentation des pistes précédentes pour valider le gain de performances.

Ces recommandations fournissent une feuille de route pour rapprocher le plugin des standards observés dans les produits professionnels tout en sécurisant les évolutions grâce à une couverture de tests ciblée.

## Suivi des chantiers

- **Cache & initialisation** : ouverture d'un ticket pour déplacer `maybeInvalidateCacheOnVersionChange()` et `revalidateStoredOptions()` dans des hooks d'activation/mise à jour. L'objectif est de réduire les écritures concurrentes sur `wp_options` et de préparer un cache persistant par profil.
- **Templates surchargeables** : plan d'action pour introduire un filtre `sidebar_jlg_template_path`, supporter `locate_template()` et encapsuler le buffer de sortie dans un `try/finally`. Cette évolution débloque la personnalisation par thème enfant et sécurise la libération du buffer.
- **Index de cache granulaire** : spécification d'un stockage `locale => [profil => key]` avec purge différentielle et instrumentation des hits/miss. Un test d'intégration (nouveau `tests/menu_cache_index_management_test.php`) est prêt à valider le comportement.
- **Observabilité des performances** : étude en cours pour ajouter des métriques (temps de rendu, taille du HTML) et un hook de monitoring. Ces données alimenteront le futur tableau de bord QA évoqué dans `docs/axes-roadmap.md`.
