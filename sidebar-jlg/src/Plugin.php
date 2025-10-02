<?php

namespace JLG\Sidebar;

use JLG\Sidebar\Admin\MenuPage;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Admin\View\ColorPickerField;
use JLG\Sidebar\Ajax\Endpoints;
use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Frontend\Blocks\SearchBlock;
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
            $pluginFile,
            $version
        );
        $this->ajax = new Endpoints($this->settings, $this->cache, $this->icons);
        $this->searchBlock = new SearchBlock($this->settings, $pluginFile, $version);
    }

    public function register(): void
    {
        $this->maybeInvalidateCacheOnVersionChange();

        add_action('plugins_loaded', [$this, 'loadTextdomain']);
        add_action('admin_notices', [$this, 'renderActivationErrorNotice']);
        add_action('update_option_sidebar_jlg_settings', [$this, 'handleSettingsUpdated'], 10, 3);
        add_action('sidebar_jlg_custom_icons_changed', [$this->cache, 'clear'], 10, 0);

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
    public function handleSettingsUpdated($oldValue, $value, string $optionName = ''): void
    {
        $this->cache->clear();

        if ($this->hasSidebarPositionChanged($oldValue, $value)) {
            $this->cache->forgetLocaleIndex();
        }
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
