<?php

namespace JLG\Sidebar\Frontend;

use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;

class SidebarRenderer
{
    private SettingsRepository $settings;
    private IconLibrary $icons;
    private MenuCache $cache;
    private string $pluginFile;
    private string $version;

    public function __construct(
        SettingsRepository $settings,
        IconLibrary $icons,
        MenuCache $cache,
        string $pluginFile,
        string $version
    ) {
        $this->settings = $settings;
        $this->icons = $icons;
        $this->cache = $cache;
        $this->pluginFile = $pluginFile;
        $this->version = $version;
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'render']);
        add_filter('body_class', [$this, 'addBodyClasses']);
    }

    public function enqueueAssets(): void
    {
        $options = $this->settings->getOptions();
        if (empty($options['enable_sidebar'])) {
            return;
        }

        wp_enqueue_style(
            'sidebar-jlg-public-css',
            plugin_dir_url($this->pluginFile) . 'assets/css/public-style.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'sidebar-jlg-public-js',
            plugin_dir_url($this->pluginFile) . 'assets/js/public-script.js',
            [],
            $this->version,
            true
        );

        wp_localize_script('sidebar-jlg-public-js', 'sidebarSettings', $options);
    }

    public function render(): void
    {
        $options = $this->settings->getOptions();
        if (empty($options['enable_sidebar'])) {
            return;
        }

        $currentLocale = $this->cache->getLocaleForCache();
        $transientKey = $this->cache->getTransientKey($currentLocale);

        $cacheEnabled = \apply_filters(
            'sidebar_jlg_cache_enabled',
            !$this->is_sidebar_output_dynamic($options),
            $options,
            $currentLocale,
            $transientKey
        );

        $html = false;

        if ($cacheEnabled) {
            $html = $this->cache->get($currentLocale);
        } else {
            $this->cache->delete($currentLocale);
        }

        if (!$cacheEnabled || false === $html) {
            $allIcons = $this->icons->getAllIcons();
            $templatePath = plugin_dir_path($this->pluginFile) . 'includes/sidebar-template.php';

            ob_start();
            $optionsForTemplate = $options;
            $allIconsForTemplate = $allIcons;
            $options = $optionsForTemplate;
            $allIcons = $allIconsForTemplate;
            require $templatePath;
            $html = ob_get_clean();

            if ($cacheEnabled) {
                $this->cache->set($currentLocale, $html);
            }
        }

        echo $html;
    }

    public function addBodyClasses(array $classes): array
    {
        $options = $this->settings->getOptions();
        if (empty($options['enable_sidebar'])) {
            return $classes;
        }

        $classes[] = 'jlg-sidebar-active';
        if (($options['desktop_behavior'] ?? 'push') === 'push') {
            $classes[] = 'jlg-sidebar-push';
        } else {
            $classes[] = 'jlg-sidebar-overlay';
        }

        if (($options['layout_style'] ?? 'full') === 'floating') {
            $classes[] = 'jlg-sidebar-floating';
        }

        return $classes;
    }

    public function is_sidebar_output_dynamic(?array $options = null): bool
    {
        if ($options === null) {
            $options = $this->settings->getOptions();
        }

        $searchMethod = $options['search_method'] ?? 'default';
        $isDynamic = in_array($searchMethod, ['shortcode', 'hook'], true);

        return (bool) \apply_filters('sidebar_jlg_is_dynamic', $isDynamic, $options);
    }
}
