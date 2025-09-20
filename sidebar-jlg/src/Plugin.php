<?php

namespace JLG\Sidebar;

use JLG\Sidebar\Admin\MenuPage;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Admin\View\ColorPickerField;
use JLG\Sidebar\Ajax\Endpoints;
use JLG\Sidebar\Cache\MenuCache;
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
        $this->ajax = new Endpoints($this->settings, $this->cache);
    }

    public function register(): void
    {
        add_action('update_option_sidebar_jlg_settings', [$this->cache, 'clear'], 10, 0);

        $this->settings->revalidateStoredOptions();
        $this->menuPage->registerHooks();
        $this->renderer->registerHooks();
        $this->ajax->registerHooks();

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
}
