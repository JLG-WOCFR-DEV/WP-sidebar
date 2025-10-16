<?php

namespace JLG\Sidebar;

use JLG\Sidebar\Accessibility\AuditRunner;
use JLG\Sidebar\Admin\MenuPage;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Admin\View\ColorPickerField;
use JLG\Sidebar\Analytics\AnalyticsEventQueue;
use JLG\Sidebar\Analytics\AnalyticsRepository;
use JLG\Sidebar\Analytics\EventRateLimiter;
use JLG\Sidebar\Ajax\Endpoints;
use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Frontend\Blocks\SearchBlock;
use JLG\Sidebar\Frontend\ProfileSelector;
use JLG\Sidebar\Frontend\RequestContextResolver;
use JLG\Sidebar\Frontend\SidebarRenderer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsMaintenanceRunner;
use JLG\Sidebar\Settings\SettingsRepository;

class Plugin
{
    private const MAINTENANCE_FLAG_OPTION = 'sidebar_jlg_pending_maintenance';
    private const MAINTENANCE_CRON_HOOK = 'sidebar_jlg_run_maintenance';
    private string $pluginFile;
    private string $version;
    private DefaultSettings $defaults;
    private IconLibrary $icons;
    private SettingsRepository $settings;
    private SettingsMaintenanceRunner $settingsMaintenance;
    private MenuCache $cache;
    private SettingsSanitizer $sanitizer;
    private AnalyticsRepository $analytics;
    private AnalyticsEventQueue $analyticsQueue;
    private EventRateLimiter $eventRateLimiter;
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
        $this->sanitizer = new SettingsSanitizer($this->defaults, $this->icons);
        $this->settings = new SettingsRepository($this->defaults, $this->icons, $this->sanitizer);
        $this->settingsMaintenance = new SettingsMaintenanceRunner($this->settings);
        $this->cache = new MenuCache();
        $this->analytics = new AnalyticsRepository();
        $this->analyticsQueue = new AnalyticsEventQueue($this->analytics);
        $this->eventRateLimiter = new EventRateLimiter();
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
            $this->analyticsQueue,
            $this->eventRateLimiter,
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
        add_action(self::MAINTENANCE_CRON_HOOK, [$this, 'handleScheduledMaintenance'], 10, 0);
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
        add_action('wp_update_nav_menu', [SettingsRepository::class, 'invalidateCachedNavMenu'], 10, 1);
        add_action('wp_delete_nav_menu', [SettingsRepository::class, 'invalidateCachedNavMenu'], 10, 1);

        $this->analyticsQueue->registerHooks();
        $this->menuPage->registerHooks();
        $this->renderer->registerHooks();
        $this->ajax->registerHooks();
        $this->searchBlock->registerHooks();
        $this->settingsMaintenance->registerHooks();
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
        $this->dispatchPendingMaintenance('bootstrap');

        if (!$this->isAdminContext()) {
            return;
        }

        $this->registerActivationErrorNoticeIfNeeded();
    }

    /**
     * @param mixed $oldValue
     * @param mixed $value
     */
    public function handleSettingsUpdated($oldValue = null, $value = null, string $optionName = ''): void
    {
        $this->clearCachedEntries();
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
        $this->clearCachedEntries();
        $this->cache->forgetLocaleIndex();
        $this->renderer->bumpDynamicStylesCacheSalt();
    }

    private function clearCachedEntries(): void
    {
        foreach ($this->cache->getCachedLocales() as $entry) {
            $locale = isset($entry['locale']) && is_string($entry['locale']) ? $entry['locale'] : '';
            $suffix = $entry['suffix'] ?? null;
            $suffixValue = is_string($suffix) ? $suffix : null;

            $this->cache->clearEntry($locale, $suffixValue);
        }
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

    public function getAnalyticsQueue(): AnalyticsEventQueue
    {
        return $this->analyticsQueue;
    }

    public function getEventRateLimiter(): EventRateLimiter
    {
        return $this->eventRateLimiter;
    }

    public function getSidebarRenderer(): SidebarRenderer
    {
        return $this->renderer;
    }

    public function getRequestContextResolver(): RequestContextResolver
    {
        return $this->requestContextResolver;
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
            $this->markMaintenancePending();
            $this->dispatchPendingMaintenance('upgrade');
        }
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

    private function hasMaintenanceWorkPending(): bool
    {
        if ($this->isMaintenanceFlagSet()) {
            return true;
        }

        return $this->isPluginVersionOutdated();
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

        return is_string($value) && $value !== '';
    }

    private function markMaintenancePending(): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        $state = $this->getMaintenanceState();

        if ($state === 'pending' || $state === 'scheduled' || $state === 'running') {
            return;
        }

        $updated = update_option(self::MAINTENANCE_FLAG_OPTION, 'pending', 'no');

        if ($updated) {
            $this->logMaintenanceState('pending');
        }
    }

    private function clearPendingMaintenanceFlag(): void
    {
        if (!function_exists('delete_option')) {
            return;
        }

        $this->logMaintenanceState('completed');
        delete_option(self::MAINTENANCE_FLAG_OPTION);
    }

    private function getMaintenanceState(): string
    {
        if (!function_exists('get_option')) {
            return '';
        }

        $value = get_option(self::MAINTENANCE_FLAG_OPTION, '');

        return is_string($value) ? $value : '';
    }

    private function dispatchPendingMaintenance(string $origin): void
    {
        if (!$this->hasMaintenanceWorkPending()) {
            return;
        }

        $state = $this->getMaintenanceState();

        if ($state === 'scheduled' && $this->isMaintenanceEventScheduled()) {
            return;
        }

        if (!function_exists('wp_schedule_single_event')) {
            $this->logMaintenanceState('inline:' . $origin);
            $this->runMaintenanceTasks();

            return;
        }

        if ($state === 'pending') {
            if (!$this->transitionMaintenanceState('pending', 'scheduled')) {
                return;
            }

            $this->logMaintenanceState('scheduled:' . $origin);
        } elseif ($state === '' && $this->isPluginVersionOutdated()) {
            $this->markMaintenancePending();
            $state = $this->getMaintenanceState();

            if ($state !== 'pending') {
                return;
            }

            if (!$this->transitionMaintenanceState('pending', 'scheduled')) {
                return;
            }

            $this->logMaintenanceState('scheduled:' . $origin);
        } elseif ($state === 'scheduled') {
            $this->logMaintenanceState('rescheduled:' . $origin);
        } else {
            return;
        }

        $timestamp = time();

        if ($timestamp <= 0) {
            $timestamp = time();
        }

        wp_schedule_single_event($timestamp + 5, self::MAINTENANCE_CRON_HOOK);
    }

    public function handleScheduledMaintenance(): void
    {
        $state = $this->getMaintenanceState();

        if ($state === '') {
            if (!$this->isPluginVersionOutdated()) {
                return;
            }

            $this->markMaintenancePending();
            $state = $this->getMaintenanceState();
        }

        if ($state !== 'scheduled' && $state !== 'pending') {
            return;
        }

        if ($state === 'pending' && !$this->transitionMaintenanceState('pending', 'running')) {
            return;
        }

        if ($state === 'scheduled' && !$this->transitionMaintenanceState('scheduled', 'running')) {
            return;
        }

        $this->runMaintenanceTasks();
    }

    private function transitionMaintenanceState(string $expected, string $next): bool
    {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return false;
        }

        $current = get_option(self::MAINTENANCE_FLAG_OPTION, '');

        if (!is_string($current) || $current !== $expected) {
            return false;
        }

        $updated = update_option(self::MAINTENANCE_FLAG_OPTION, $next, 'no');

        if ($updated) {
            $this->logMaintenanceState($next);
        }

        return $updated;
    }

    private function isMaintenanceEventScheduled(): bool
    {
        if (!function_exists('wp_next_scheduled')) {
            return false;
        }

        $timestamp = wp_next_scheduled(self::MAINTENANCE_CRON_HOOK);

        return $timestamp !== false;
    }

    private function logMaintenanceState(string $state): void
    {
        if (!function_exists('error_log')) {
            return;
        }

        error_log(sprintf('[Sidebar JLG] Maintenance state: %s', $state));
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
