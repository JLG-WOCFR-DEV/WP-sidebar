<?php

namespace JLG\Sidebar;

use JLG\Sidebar\Accessibility\AuditRunner;
use JLG\Sidebar\Admin\MenuPage;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Admin\View\ColorPickerField;
use JLG\Sidebar\Analytics\AnalyticsRepository;
use JLG\Sidebar\Ajax\Endpoints;
use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Frontend\Blocks\SearchBlock;
use JLG\Sidebar\Frontend\ProfileSelector;
use JLG\Sidebar\Frontend\RequestContextResolver;
use JLG\Sidebar\Frontend\SidebarRenderer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

class Plugin
{
    private const MAINTENANCE_FLAG_OPTION = 'sidebar_jlg_pending_maintenance';
    private string $pluginFile;
    private string $version;
    private DefaultSettings $defaults;
    private IconLibrary $icons;
    private SettingsRepository $settings;
    private MenuCache $cache;
    private SettingsSanitizer $sanitizer;
    private AnalyticsRepository $analytics;
    private bool $maintenanceCompleted = false;
    private MenuPage $menuPage;
    private ProfileSelector $profileSelector;
    private RequestContextResolver $requestContextResolver;
    private SidebarRenderer $renderer;
    private Endpoints $ajax;
    private SearchBlock $searchBlock;
    private AuditRunner $auditRunner;

    public function __construct(string $pluginFile, string $version)
    {
        $this->pluginFile = $pluginFile;
        $this->version = $version;
        $this->defaults = new DefaultSettings();
        $this->icons = new IconLibrary($pluginFile);
        $this->settings = new SettingsRepository($this->defaults, $this->icons);
        $this->cache = new MenuCache();
        $this->sanitizer = new SettingsSanitizer($this->defaults, $this->icons);
        $this->analytics = new AnalyticsRepository();
        $this->requestContextResolver = new RequestContextResolver();
        $this->profileSelector = new ProfileSelector($this->settings, $this->requestContextResolver);
        $this->auditRunner = new AuditRunner($pluginFile);

        $this->menuPage = new MenuPage(
            $this->settings,
            $this->sanitizer,
            $this->icons,
            new ColorPickerField(),
            $this->analytics,
            $pluginFile,
            $version,
            $this->auditRunner
        );
        $this->renderer = new SidebarRenderer(
            $this->settings,
            $this->icons,
            $this->cache,
            $this->profileSelector,
            $this->requestContextResolver,
            $pluginFile,
            $version
        );
        $this->ajax = new Endpoints(
            $this->settings,
            $this->cache,
            $this->icons,
            $this->sanitizer,
            $this->analytics,
            $pluginFile,
            $this->renderer,
            $this->auditRunner
        );
        $this->searchBlock = new SearchBlock($this->settings, $pluginFile, $version);
    }

    public function register(): void
    {
        add_filter('sanitize_option_sidebar_jlg_profiles', [$this->sanitizer, 'sanitize_profiles'], 10, 2);
        add_filter('sanitize_option_sidebar_jlg_active_profile', [$this->sanitizer, 'sanitize_active_profile'], 10, 2);
        add_action('init', [$this, 'loadTextdomain']);
        add_action('plugins_loaded', [$this, 'onPluginsLoaded'], 1);
        add_action('upgrader_process_complete', [$this, 'handleUpgrade'], 10, 2);
        add_action('update_option_sidebar_jlg_settings', [$this, 'handleSettingsUpdated'], 10, 3);
        add_action('add_option_sidebar_jlg_profiles', [$this, 'handleProfilesOptionChanged'], 10, 2);
        add_action('update_option_sidebar_jlg_profiles', [$this, 'handleProfilesOptionChanged'], 10, 3);
        add_action('delete_option_sidebar_jlg_profiles', [$this, 'handleProfilesOptionChanged'], 10, 1);
        add_action('add_option_sidebar_jlg_active_profile', [$this, 'handleActiveProfileChanged'], 10, 2);
        add_action('update_option_sidebar_jlg_active_profile', [$this, 'handleActiveProfileChanged'], 10, 3);
        add_action('delete_option_sidebar_jlg_active_profile', [$this, 'handleActiveProfileChanged'], 10, 1);
        add_action('sidebar_jlg_custom_icons_changed', [$this->cache, 'clear'], 10, 0);
        add_action('wp_update_nav_menu', [$this->cache, 'clear'], 10, 0);

        $this->menuPage->registerHooks();
        $this->renderer->registerHooks();
        $this->ajax->registerHooks();
        $this->searchBlock->registerHooks();
        $this->registerContextInvalidationHooks();

        $contentChangeHooks = [
            'save_post',
            'deleted_post',
            'trashed_post',
            'untrashed_post',
            'edited_term',
            'delete_term',
            'created_term',
        ];

        foreach ($contentChangeHooks as $hook) {
            add_action($hook, [$this->cache, 'clear'], 10, 0);
        }
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('sidebar-jlg', false, dirname(plugin_basename($this->pluginFile)) . '/languages');
    }

    public function onPluginsLoaded(): void
    {
        $this->primeMaintenanceFlag();

        if (!$this->isAdminContext()) {
            return;
        }

        $this->registerActivationErrorNoticeIfNeeded();
        $this->maybeRunMaintenance();
    }

    /**
     * @param mixed $oldValue
     * @param mixed $value
     */
    public function handleSettingsUpdated($oldValue = null, $value = null, string $optionName = ''): void
    {
        $this->cache->clear();
        $this->renderer->bumpDynamicStylesCacheSalt();

        if ($this->hasSidebarPositionChanged($oldValue, $value)) {
            $this->cache->forgetLocaleIndex();
        }
    }

    /**
     * @param mixed $arg1
     * @param mixed $arg2
     * @param mixed $arg3
     */
    public function handleProfilesOptionChanged($arg1 = null, $arg2 = null, $arg3 = null): void
    {
        $this->resetProfileCaches();
    }

    /**
     * @param mixed $arg1
     * @param mixed $arg2
     * @param mixed $arg3
     */
    public function handleActiveProfileChanged($arg1 = null, $arg2 = null, $arg3 = null): void
    {
        $this->resetProfileCaches();
    }

    private function maybeInvalidateCacheOnVersionChange(): void
    {
        $storedVersion = get_option('sidebar_jlg_plugin_version');

        if ($storedVersion !== $this->version) {
            $this->cache->clear();
            update_option('sidebar_jlg_plugin_version', $this->version);
        }
    }

    /**
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    private function hasSidebarPositionChanged($oldValue, $newValue): bool
    {
        $normalize = static function ($value): string {
            if (!is_array($value)) {
                return 'left';
            }

            $position = \sanitize_key($value['sidebar_position'] ?? '');

            return $position === 'right' ? 'right' : 'left';
        };

        return $normalize($oldValue) !== $normalize($newValue);
    }

    private function resetProfileCaches(): void
    {
        $this->cache->clear();
        $this->cache->forgetLocaleIndex();
        $this->renderer->bumpDynamicStylesCacheSalt();
    }

    private function registerContextInvalidationHooks(): void
    {
        $hooks = [
            'set_current_user',
            'wp_set_current_user',
            'switch_blog',
            'switch_locale',
            'restore_previous_locale',
            'clean_post_cache',
            'clean_object_term_cache',
            'clean_term_cache',
        ];

        foreach ($hooks as $hook) {
            add_action($hook, [$this->requestContextResolver, 'resetCachedContext'], 10, 0);
        }
    }

    public function getDefaultSettings(): array
    {
        return $this->settings->getDefaultSettings();
    }

    public function getIconLibrary(): IconLibrary
    {
        return $this->icons;
    }

    public function getSettingsRepository(): SettingsRepository
    {
        return $this->settings;
    }

    public function getSanitizer(): SettingsSanitizer
    {
        return $this->sanitizer;
    }

    public function getAnalyticsRepository(): AnalyticsRepository
    {
        return $this->analytics;
    }

    public function getSidebarRenderer(): SidebarRenderer
    {
        return $this->renderer;
    }

    public function getMenuCache(): MenuCache
    {
        return $this->cache;
    }

    public function getPluginFile(): string
    {
        return $this->pluginFile;
    }

    public function renderActivationErrorNotice(): void
    {
        if (!function_exists('get_transient')) {
            return;
        }

        $message = get_transient('sidebar_jlg_activation_error');

        if ($message === false || $message === '') {
            return;
        }

        $messageText = is_array($message)
            ? $this->getActivationErrorMessage($message)
            : (string) $message;

        if ($messageText === '') {
            return;
        }

        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($messageText));

        if (function_exists('delete_transient')) {
            delete_transient('sidebar_jlg_activation_error');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getActivationErrorMessage(array $data): string
    {
        $code = isset($data['code']) ? (string) $data['code'] : '';
        $details = isset($data['details']) && is_string($data['details']) ? $data['details'] : '';

        switch ($code) {
            case 'uploads_access_error':
                $message = __('Sidebar JLG n\'a pas pu accéder au dossier uploads. Vérifiez les permissions du dossier uploads puis réactivez le plugin.', 'sidebar-jlg');

                break;
            case 'icons_directory_creation_failed':
                $message = __('Sidebar JLG n\'a pas pu créer le dossier d\'icônes. Vérifiez les permissions du dossier uploads puis réactivez le plugin.', 'sidebar-jlg');

                break;
            default:
                $message = __('Sidebar JLG a rencontré une erreur lors de l\'activation.', 'sidebar-jlg');
        }

        if ($details !== '') {
            $message .= ' ' . sprintf(__('Détails : %s', 'sidebar-jlg'), $details);
        }

        return $message;
    }

    /**
     * @param mixed $upgrader
     * @param mixed $hookExtra
     */
    public function handleUpgrade($upgrader = null, $hookExtra = null): void
    {
        if (!is_array($hookExtra)) {
            return;
        }

        $type = isset($hookExtra['type']) ? (string) $hookExtra['type'] : '';

        if ($type !== 'plugin') {
            return;
        }

        $updatedPlugins = [];

        if (!empty($hookExtra['plugins']) && is_array($hookExtra['plugins'])) {
            $updatedPlugins = array_map('strval', $hookExtra['plugins']);
        } elseif (!empty($hookExtra['plugin']) && is_string($hookExtra['plugin'])) {
            $updatedPlugins = [(string) $hookExtra['plugin']];
        }

        if ($updatedPlugins === []) {
            return;
        }

        $pluginBasename = function_exists('plugin_basename')
            ? plugin_basename($this->pluginFile)
            : basename($this->pluginFile);

        if (in_array($pluginBasename, $updatedPlugins, true)) {
            $this->runMaintenanceTasks();
        }
    }

    public function maybeRunMaintenance(): void
    {
        if (!$this->shouldRunMaintenanceOnCurrentRequest()) {
            return;
        }

        $this->runMaintenanceTasks();
    }

    private function runMaintenanceTasks(): void
    {
        if ($this->maintenanceCompleted) {
            return;
        }

        $this->maintenanceCompleted = true;
        $this->clearPendingMaintenanceFlag();
        $this->maybeInvalidateCacheOnVersionChange();
        $this->settings->revalidateStoredOptions();
    }

    private function shouldRunMaintenanceOnCurrentRequest(): bool
    {
        if ($this->maintenanceCompleted) {
            return false;
        }

        if (!$this->hasMaintenanceWorkPending()) {
            return false;
        }

        if ($this->isRestrictedRequestContext()) {
            return false;
        }

        return true;
    }

    private function hasMaintenanceWorkPending(): bool
    {
        if ($this->isMaintenanceFlagSet()) {
            return true;
        }

        return $this->isPluginVersionOutdated();
    }

    private function isRestrictedRequestContext(): bool
    {
        if (function_exists('wp_installing') && wp_installing()) {
            return true;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        if (function_exists('current_user_can') && !current_user_can('manage_options')) {
            return true;
        }

        return false;
    }

    private function primeMaintenanceFlag(): void
    {
        if (!$this->isPluginVersionOutdated()) {
            return;
        }

        if ($this->isMaintenanceFlagSet()) {
            return;
        }

        $this->markMaintenancePending();
    }

    private function isMaintenanceFlagSet(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $value = get_option(self::MAINTENANCE_FLAG_OPTION, '');

        return $value === 'yes';
    }

    private function markMaintenancePending(): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        update_option(self::MAINTENANCE_FLAG_OPTION, 'yes', 'no');
    }

    private function clearPendingMaintenanceFlag(): void
    {
        if (!function_exists('delete_option')) {
            return;
        }

        delete_option(self::MAINTENANCE_FLAG_OPTION);
    }

    private function isPluginVersionOutdated(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $storedVersion = get_option('sidebar_jlg_plugin_version');

        return $storedVersion !== $this->version;
    }

    private function isAdminContext(): bool
    {
        return !function_exists('is_admin') || is_admin();
    }

    private function hasActivationErrorNotice(): bool
    {
        if (!function_exists('get_transient')) {
            return false;
        }

        $message = get_transient('sidebar_jlg_activation_error');

        if ($message === false) {
            return false;
        }

        if (is_string($message)) {
            return $message !== '';
        }

        if (is_array($message)) {
            return $message !== [];
        }

        return true;
    }

    private function registerActivationErrorNoticeIfNeeded(): void
    {
        if (!$this->hasActivationErrorNotice()) {
            return;
        }

        add_action('admin_notices', [$this, 'renderActivationErrorNotice']);
    }
}
