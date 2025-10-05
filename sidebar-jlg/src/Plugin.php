<?php

namespace JLG\Sidebar;

use JLG\Sidebar\Admin\MenuPage;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Admin\View\ColorPickerField;
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
    private string $pluginFile;
    private string $version;
    private DefaultSettings $defaults;
    private IconLibrary $icons;
    private SettingsRepository $settings;
    private MenuCache $cache;
    private SettingsSanitizer $sanitizer;
    private MenuPage $menuPage;
    private ProfileSelector $profileSelector;
    private RequestContextResolver $contextResolver;
    private SidebarRenderer $renderer;
    private Endpoints $ajax;
    private SearchBlock $searchBlock;

    public function __construct(string $pluginFile, string $version)
    {
        $this->pluginFile = $pluginFile;
        $this->version = $version;
        $this->defaults = new DefaultSettings();
        $this->icons = new IconLibrary($pluginFile);
        $this->settings = new SettingsRepository($this->defaults, $this->icons);
        $this->cache = new MenuCache();
        $this->sanitizer = new SettingsSanitizer($this->defaults, $this->icons);
        $this->contextResolver = new RequestContextResolver();
        $this->profileSelector = new ProfileSelector($this->settings, $this->contextResolver);
        $this->menuPage = new MenuPage(
            $this->settings,
            $this->sanitizer,
            $this->icons,
            new ColorPickerField(),
            $pluginFile,
            $version
        );
        $this->renderer = new SidebarRenderer(
            $this->settings,
            $this->icons,
            $this->cache,
            $this->profileSelector,
            $this->contextResolver,
            $pluginFile,
            $version
        );
        $this->ajax = new Endpoints($this->settings, $this->cache, $this->icons, $this->sanitizer, $pluginFile);
        $this->searchBlock = new SearchBlock($this->settings, $pluginFile, $version);
    }

    public function register(): void
    {
        $this->maybeInvalidateCacheOnVersionChange();

        add_filter('sanitize_option_sidebar_jlg_profiles', [$this->sanitizer, 'sanitize_profiles'], 10, 2);
        add_filter('sanitize_option_sidebar_jlg_active_profile', [$this->sanitizer, 'sanitize_active_profile'], 10, 2);
        add_action('plugins_loaded', [$this, 'loadTextdomain']);
        add_action('admin_notices', [$this, 'renderActivationErrorNotice']);
        add_action('update_option_sidebar_jlg_settings', [$this, 'handleSettingsUpdated'], 10, 3);
        add_action('add_option_sidebar_jlg_profiles', [$this, 'handleProfilesOptionChanged'], 10, 2);
        add_action('update_option_sidebar_jlg_profiles', [$this, 'handleProfilesOptionChanged'], 10, 3);
        add_action('delete_option_sidebar_jlg_profiles', [$this, 'handleProfilesOptionChanged'], 10, 1);
        add_action('add_option_sidebar_jlg_active_profile', [$this, 'handleActiveProfileChanged'], 10, 2);
        add_action('update_option_sidebar_jlg_active_profile', [$this, 'handleActiveProfileChanged'], 10, 3);
        add_action('delete_option_sidebar_jlg_active_profile', [$this, 'handleActiveProfileChanged'], 10, 1);
        add_action('sidebar_jlg_custom_icons_changed', [$this->cache, 'clear'], 10, 0);
        add_action('wp_update_nav_menu', [$this->cache, 'clear'], 10, 0);

        $this->settings->revalidateStoredOptions();
        $this->menuPage->registerHooks();
        $this->renderer->registerHooks();
        $this->ajax->registerHooks();
        $this->searchBlock->registerHooks();

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

    /**
     * @param mixed $oldValue
     * @param mixed $value
     */
    public function handleSettingsUpdated($oldValue = null, $value = null, string $optionName = ''): void
    {
        $this->cache->clear();

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

        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));

        if (function_exists('delete_transient')) {
            delete_transient('sidebar_jlg_activation_error');
        }
    }
}
