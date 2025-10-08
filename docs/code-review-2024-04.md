# Audit de code – Avril 2024

## 1. Initialisation du plugin et hooks
- `Plugin::register()` appelle `maybeInvalidateCacheOnVersionChange()` puis enregistre l'ensemble des hooks à chaque requête (front et admin). Cela déclenche systématiquement une revalidation des options via `SettingsRepository::revalidateStoredOptions()` avant même de vérifier le contexte, ce qui entraîne des écritures répétées dans `wp_options` sur du trafic anonyme.【F:sidebar-jlg/src/Plugin.php†L84-L121】【F:sidebar-jlg/src/Settings/SettingsRepository.php†L118-L220】
- **Pistes :** déplacer la revalidation vers les événements d'activation/mise à jour (`register_activation_hook`, `upgrader_process_complete`) et différer l'enregistrement des hooks lourds (admin notices, audit) via des garde-fous (`is_admin()`, `wp_doing_ajax()`).

## 2. Gestion du cache de menu
- `MenuCache::rememberLocale()` écrit dans une option autoload=false à chaque génération de cache et `forgetLocaleIndex()` supprime l'index complet dès qu'un profil est recalculé. Sur des installations multi-profils, cela invalide inutilement les caches des autres profils.【F:sidebar-jlg/src/Cache/MenuCache.php†L66-L172】
- **Pistes :** stocker l'index en cache persistant (`wp_cache_set` avec un groupe dédié) ou utiliser une structure clé/valeur (`locale|profil`). Le `clear()` devrait retirer uniquement les entrées concernées.

## 3. Rendu et flux du HTML
- `SidebarRenderer::render()` mélange la récupération du cache et l'écriture directe (`echo`) via `outputSidebar()`. Lorsque le cache est désactivé par un filtre, l'appel à `forgetLocaleIndex()` efface toute la table d'index, forçant un rebuild global.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L848-L905】
- **Pistes :** renvoyer systématiquement le HTML sans `echo` (laisser le thème décider) et supprimer l'invalidation globale au profit d'une suppression ciblée (`MenuCache::delete`).

## 4. Génération des styles dynamiques
- `buildDynamicStyles()` reconstruit une chaîne `:root { ... }` à chaque requête en parcourant toute `STYLE_VARIABLE_MAP` sans mémoïsation. Pour les profils identiques, cela pourrait être mutualisé dans un cache court-terme (`wp_cache_get`) basé sur un hash des options.【F:sidebar-jlg/src/Frontend/SidebarRenderer.php†L378-L429】
- **Pistes :** calculer un identifiant stable (hash JSON) par profil et mémoriser le CSS ; exploiter `wp_add_inline_style` seulement après cache miss.

## 5. Dépendances JavaScript
- Le bundle admin (`sidebar-jlg/assets/js/admin-script.js`) est écrit en vanilla JS et n'importe pas `jquery`, pourtant `package.json` déclare `jquery` en `devDependencies`. Cela alourdit inutilement l'installation et le lockfile.【F:sidebar-jlg/assets/js/admin-script.js†L1-L120】【F:package.json†L1-L20】
- **Pistes :** retirer `jquery` du `package.json` et s'appuyer exclusivement sur la dépendance WordPress (`wp_enqueue_script` la fournit déjà côté admin).

## 6. Nettoyage général recommandé
- Ajouter des tests de régression autour de la gestion du cache (profil/locale) et de la revalidation pour prévenir les régressions de performance.
- Documenter les hooks publics (`sidebar_jlg_cache_enabled`, `sidebar_jlg_custom_icons_changed`) afin d'expliciter leurs effets secondaires (invalidation globale).
