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

        $dynamicStyles = $this->buildDynamicStyles($options);
        if ($dynamicStyles !== '') {
            wp_add_inline_style('sidebar-jlg-public-css', $dynamicStyles);
        }

        wp_enqueue_script(
            'sidebar-jlg-public-js',
            plugin_dir_url($this->pluginFile) . 'assets/js/public-script.js',
            [],
            $this->version,
            true
        );

        $localizedOptions = [
            'animation_type' => $options['animation_type'] ?? 'slide-left',
            'close_on_link_click' => $options['close_on_link_click'] ?? '',
            'debug_mode' => (string) ($options['debug_mode'] ?? '0'),
        ];

        wp_localize_script('sidebar-jlg-public-js', 'sidebarSettings', $localizedOptions);
    }

    private function buildDynamicStyles(array $options): string
    {
        $styles = ':root {';
        $styles .= '--sidebar-width-desktop: ' . esc_attr($options['width_desktop'] ?? '') . 'px;';
        $styles .= '--sidebar-width-tablet: ' . esc_attr($options['width_tablet'] ?? '') . 'px;';

        if (($options['bg_color_type'] ?? 'solid') === 'gradient') {
            $styles .= '--sidebar-bg-image: linear-gradient(180deg, ' . esc_attr($options['bg_color_start'] ?? '') . ' 0%, ' . esc_attr($options['bg_color_end'] ?? '') . ' 100%);';
            $styles .= '--sidebar-bg-color: ' . esc_attr($options['bg_color_start'] ?? '') . ';';
        } else {
            $styles .= '--sidebar-bg-image: none;';
            $styles .= '--sidebar-bg-color: ' . esc_attr($options['bg_color'] ?? '') . ';';
        }

        if (($options['accent_color_type'] ?? 'solid') === 'gradient') {
            $styles .= '--primary-accent-image: linear-gradient(90deg, ' . esc_attr($options['accent_color_start'] ?? '') . ' 0%, ' . esc_attr($options['accent_color_end'] ?? '') . ' 100%);';
            $styles .= '--primary-accent-color: ' . esc_attr($options['accent_color_start'] ?? '') . ';';
        } else {
            $styles .= '--primary-accent-image: none;';
            $styles .= '--primary-accent-color: ' . esc_attr($options['accent_color'] ?? '') . ';';
        }

        $styles .= '--sidebar-font-size: ' . esc_attr($options['font_size'] ?? '') . 'px;';
        $styles .= '--sidebar-text-color: ' . esc_attr($options['font_color'] ?? '') . ';';
        $styles .= '--sidebar-text-hover-color: ' . esc_attr($options['font_hover_color'] ?? '') . ';';
        $styles .= '--transition-speed: ' . esc_attr($options['animation_speed'] ?? '') . 'ms;';
        $styles .= '--header-padding-top: ' . esc_attr($options['header_padding_top'] ?? '') . ';';
        $styles .= '--header-alignment-desktop: ' . esc_attr($options['header_alignment_desktop'] ?? '') . ';';
        $styles .= '--header-alignment-mobile: ' . esc_attr($options['header_alignment_mobile'] ?? '') . ';';
        $styles .= '--header-logo-size: ' . esc_attr($options['header_logo_size'] ?? '') . 'px;';
        $styles .= '--hamburger-top-position: ' . esc_attr($options['hamburger_top_position'] ?? '') . ';';

        $contentMarginValue = $options['content_margin'] ?? '';
        if (is_string($contentMarginValue) || is_numeric($contentMarginValue)) {
            $contentMarginValue = (string) $contentMarginValue;
            $contentMarginTrimmed = trim($contentMarginValue);

            if (preg_match('/^calc\((.*)\)$/i', $contentMarginTrimmed, $matches)) {
                $contentMarginValue = $matches[1];
            } else {
                $contentMarginValue = $contentMarginTrimmed;
            }
        } else {
            $contentMarginValue = '';
        }

        $styles .= '--content-margin: calc(var(--sidebar-width-desktop) + ' . esc_attr($contentMarginValue) . ');';
        $styles .= '--floating-vertical-margin: ' . esc_attr($options['floating_vertical_margin'] ?? '') . ';';
        $styles .= '--border-radius: ' . esc_attr($options['border_radius'] ?? '') . ';';
        $styles .= '--border-width: ' . esc_attr($options['border_width'] ?? '') . 'px;';
        $styles .= '--border-color: ' . esc_attr($options['border_color'] ?? '') . ';';
        $styles .= '--overlay-color: ' . esc_attr($options['overlay_color'] ?? '') . ';';
        $styles .= '--overlay-opacity: ' . esc_attr($options['overlay_opacity'] ?? '') . ';';
        $styles .= '--mobile-bg-color: ' . esc_attr($options['mobile_bg_color'] ?? '') . ';';
        $styles .= '--mobile-bg-opacity: ' . esc_attr($options['mobile_bg_opacity'] ?? '') . ';';
        $styles .= '--mobile-blur: ' . esc_attr($options['mobile_blur'] ?? '') . 'px;';
        $styles .= '--menu-alignment-desktop: ' . esc_attr($options['menu_alignment_desktop'] ?? '') . ';';
        $styles .= '--menu-alignment-mobile: ' . esc_attr($options['menu_alignment_mobile'] ?? '') . ';';
        $styles .= '--search-alignment: ' . esc_attr($options['search_alignment'] ?? '') . ';';

        $rawSocialIconSize = $options['social_icon_size'] ?? 100;
        if (!is_numeric($rawSocialIconSize)) {
            $rawSocialIconSize = 100;
        }
        $socialIconSizeFactor = ((float) $rawSocialIconSize) / 100;
        $styles .= '--social-icon-size-factor: ' . esc_attr($socialIconSizeFactor) . ';';

        if (($options['hover_effect_desktop'] ?? '') === 'neon' || ($options['hover_effect_mobile'] ?? '') === 'neon') {
            $styles .= '--neon-blur: ' . esc_attr($options['neon_blur'] ?? '') . 'px;';
            $styles .= '--neon-spread: ' . esc_attr($options['neon_spread'] ?? '') . 'px;';
        }

        $styles .= '}';

        return $styles;
    }

    public function render(): void
    {
        $options = $this->settings->getOptions();
        if (empty($options['enable_sidebar'])) {
            return;
        }

        $currentLocale = $this->cache->getLocaleForCache();
        $transientKey = $this->cache->getTransientKey($currentLocale);

        $cacheEnabled = (bool) \apply_filters(
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
            $this->cache->forgetLocaleIndex();
        }

        if (!$cacheEnabled || false === $html) {
            $allIcons = $this->icons->getAllIcons();
            $templatePath = plugin_dir_path($this->pluginFile) . 'includes/sidebar-template.php';

            if (!is_readable($templatePath)) {
                if (function_exists('error_log')) {
                    error_log('[Sidebar JLG] Sidebar template not found or unreadable at ' . $templatePath);
                }

                return;
            }

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

        $isDynamic = !empty($options['enable_search']);

        return (bool) \apply_filters('sidebar_jlg_is_dynamic', $isDynamic, $options);
    }
}
