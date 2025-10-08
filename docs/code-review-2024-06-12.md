# Revue de code – 12 juin 2024

## Points forts
- **Sanitisation des icônes renforcée** : `IconLibrary::sanitizeSvgMarkup()` applique `wp_kses` puis une double validation pour refuser les SVG modifiés ou mal formés, ce qui sécurise les imports tout en fournissant des messages contextualisés pour les rejets.【F:sidebar-jlg/src/Icons/IconLibrary.php†L23-L91】
- **Résolution de contexte factorisée** : `ProfileSelector` et `SidebarRenderer` partagent `RequestContextResolver`, garantissant que le ciblage des profils et les états actifs reposent sur la même normalisation d’URL et de taxonomies.【F:sidebar-jlg/src/Frontend/ProfileSelector.php†L17-L89】【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L1520-L1577】

## Problèmes identifiés
1. **Vidage de cache quadratic et verbeux** : `MenuCache::clear()` itère sur chaque entrée d’index et appelle `delete()` qui relit/écrit l’option `sidebar_jlg_cached_locales` à chaque tour. Avec `n` combinaisons locale/profil, on effectue ~`n²/2` lectures + `n` `update_option`, ce qui devient coûteux lorsque plusieurs profils/langues sont utilisés.【F:sidebar-jlg/src/Cache/MenuCache.php†L84-L159】
2. **Revalidation systématique des menus** : `SettingsRepository::revalidateStoredOptions()` déclenche `wp_get_nav_menu_object()` pour chaque élément `nav_menu` à chaque passage (ex. preview admin). Sur un site avec 20 menus ciblés, on émet 20 requêtes SQL par chargement d’écran, alors que la présence du menu varie rarement.【F:sidebar-jlg/src/Settings/SettingsRepository.php†L293-L368】
3. **Écriture synchrone des analytics** : `Endpoints::ajax_track_event()` accepte des requêtes authentifiées par nonce côté public et chaque hit appelle `AnalyticsRepository::recordEvent()`, qui persiste les compteurs via `update_option`. Une campagne très cliquée peut saturer `wp_options` (verrou, purge cache objet) faute de buffer ou de job différé.【F:sidebar-jlg/src/Ajax/Endpoints.php†L758-L804】【F:sidebar-jlg/src/Analytics/AnalyticsRepository.php†L57-L137】

## Recommandations
- Refactorer `MenuCache::clear()` pour supprimer les entrées en lot (`delete_transient` via clés collectées) et ne réécrire l’index qu’une fois. Une structure `delete_many` ou une mise en cache en mémoire (groupe `wp_cache`) réduirait les accès base.【F:sidebar-jlg/src/Cache/MenuCache.php†L84-L159】
- Mémoriser les résolutions de menus dans `revalidateStoredOptions()` (cache statique ou `wp_cache_get`) et ne revalider qu’à l’import/activation. On peut stocker l’ID du menu et vérifier sa validité via un seul `get_terms` périodique.【F:sidebar-jlg/src/Settings/SettingsRepository.php†L293-L368】
- Introduire une file (Action Scheduler, cron) ou un buffer transitoire pour `recordEvent()` afin de limiter les `update_option` en rafale. Une agrégation toutes les 30–60 secondes ou l’usage d’une table dédiée soulagerait la base lors des pics.【F:sidebar-jlg/src/Ajax/Endpoints.php†L758-L804】【F:sidebar-jlg/src/Analytics/AnalyticsRepository.php†L57-L137】

## Suivi proposé
- Ajouter un test de performance qui mesure le temps de `MenuCache::clear()` avec 50 entrées afin d’objectiver le refactor.
- Documenter dans `AUDIT.md` la stratégie de batching analytics + planifier un lot « Observabilité » dans la roadmap QA.
- Préparer une histoire Jira « Optimiser la revalidation des menus » (lot Sprint 1 – Fiabilisation) avec estimation et critères d’acceptation basés sur les métriques SQL.
