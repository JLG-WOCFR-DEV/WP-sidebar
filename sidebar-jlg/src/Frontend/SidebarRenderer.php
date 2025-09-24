<?php

namespace JLG\Sidebar\Frontend;

use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;

class SidebarRenderer
{
    private const DYNAMIC_STYLE_DEFAULTS = [
        'width_desktop' => 280,
        'width_tablet' => 320,
        'bg_color_type' => 'solid',
        'bg_color' => 'rgba(26, 29, 36, 1)',
        'bg_color_start' => '#18181b',
        'bg_color_end' => '#27272a',
        'accent_color_type' => 'solid',
        'accent_color' => 'rgba(13, 110, 253, 1)',
        'accent_color_start' => '#60a5fa',
        'accent_color_end' => '#c084fc',
        'font_size' => 16,
        'font_color' => 'rgba(224, 224, 224, 1)',
        'font_hover_color' => 'rgba(255, 255, 255, 1)',
        'animation_speed' => 400,
        'header_padding_top' => '2.5rem',
        'header_alignment_desktop' => 'flex-start',
        'header_alignment_mobile' => 'center',
        'header_logo_size' => 150,
        'hamburger_top_position' => '4rem',
        'content_margin' => '2rem',
        'floating_vertical_margin' => '4rem',
        'border_radius' => '12px',
        'border_width' => 1,
        'border_color' => 'rgba(255,255,255,0.2)',
        'overlay_color' => 'rgba(0, 0, 0, 1)',
        'overlay_opacity' => 0.5,
        'mobile_bg_color' => 'rgba(26, 29, 36, 0.8)',
        'mobile_bg_opacity' => 0.8,
        'mobile_blur' => 10,
        'menu_alignment_desktop' => 'flex-start',
        'menu_alignment_mobile' => 'flex-start',
        'search_alignment' => 'flex-start',
        'social_icon_size' => 100,
        'neon_blur' => 15,
        'neon_spread' => 5,
        'hover_effect_desktop' => 'none',
        'hover_effect_mobile' => 'none',
    ];

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
        $variables = [];

        $this->assignVariable($variables, '--sidebar-width-desktop', $this->formatPixelValue($this->resolveOption($options, 'width_desktop')));
        $this->assignVariable($variables, '--sidebar-width-tablet', $this->formatPixelValue($this->resolveOption($options, 'width_tablet')));

        $bgType = $this->sanitizeCssString($this->resolveOption($options, 'bg_color_type')) ?? self::DYNAMIC_STYLE_DEFAULTS['bg_color_type'];
        if ($bgType === 'gradient') {
            $bgStart = $this->sanitizeCssString($this->resolveOption($options, 'bg_color_start'));
            $bgEnd = $this->sanitizeCssString($this->resolveOption($options, 'bg_color_end'));

            if ($bgStart !== null && $bgEnd !== null) {
                $this->assignVariable($variables, '--sidebar-bg-image', sprintf('linear-gradient(180deg, %s 0%%, %s 100%%)', $bgStart, $bgEnd));
                $this->assignVariable($variables, '--sidebar-bg-color', $bgStart);
            } else {
                $solidBg = $this->sanitizeCssString($this->resolveOption($options, 'bg_color')) ?? self::DYNAMIC_STYLE_DEFAULTS['bg_color'];
                $this->assignVariable($variables, '--sidebar-bg-image', 'none');
                $this->assignVariable($variables, '--sidebar-bg-color', $solidBg);
            }
        } else {
            $solidBg = $this->sanitizeCssString($this->resolveOption($options, 'bg_color')) ?? self::DYNAMIC_STYLE_DEFAULTS['bg_color'];
            $this->assignVariable($variables, '--sidebar-bg-image', 'none');
            $this->assignVariable($variables, '--sidebar-bg-color', $solidBg);
        }

        $accentType = $this->sanitizeCssString($this->resolveOption($options, 'accent_color_type')) ?? self::DYNAMIC_STYLE_DEFAULTS['accent_color_type'];
        if ($accentType === 'gradient') {
            $accentStart = $this->sanitizeCssString($this->resolveOption($options, 'accent_color_start'));
            $accentEnd = $this->sanitizeCssString($this->resolveOption($options, 'accent_color_end'));

            if ($accentStart !== null && $accentEnd !== null) {
                $this->assignVariable($variables, '--primary-accent-image', sprintf('linear-gradient(90deg, %s 0%%, %s 100%%)', $accentStart, $accentEnd));
                $this->assignVariable($variables, '--primary-accent-color', $accentStart);
            } else {
                $solidAccent = $this->sanitizeCssString($this->resolveOption($options, 'accent_color')) ?? self::DYNAMIC_STYLE_DEFAULTS['accent_color'];
                $this->assignVariable($variables, '--primary-accent-image', 'none');
                $this->assignVariable($variables, '--primary-accent-color', $solidAccent);
            }
        } else {
            $solidAccent = $this->sanitizeCssString($this->resolveOption($options, 'accent_color')) ?? self::DYNAMIC_STYLE_DEFAULTS['accent_color'];
            $this->assignVariable($variables, '--primary-accent-image', 'none');
            $this->assignVariable($variables, '--primary-accent-color', $solidAccent);
        }

        $this->assignVariable($variables, '--sidebar-font-size', $this->formatPixelValue($this->resolveOption($options, 'font_size')));
        $this->assignVariable($variables, '--sidebar-text-color', $this->sanitizeCssString($this->resolveOption($options, 'font_color')));
        $this->assignVariable($variables, '--sidebar-text-hover-color', $this->sanitizeCssString($this->resolveOption($options, 'font_hover_color')));
        $this->assignVariable($variables, '--transition-speed', $this->formatMillisecondsValue($this->resolveOption($options, 'animation_speed')));
        $this->assignVariable($variables, '--header-padding-top', $this->sanitizeCssString($this->resolveOption($options, 'header_padding_top')));
        $this->assignVariable($variables, '--header-alignment-desktop', $this->sanitizeCssString($this->resolveOption($options, 'header_alignment_desktop')));
        $this->assignVariable($variables, '--header-alignment-mobile', $this->sanitizeCssString($this->resolveOption($options, 'header_alignment_mobile')));
        $this->assignVariable($variables, '--header-logo-size', $this->formatPixelValue($this->resolveOption($options, 'header_logo_size')));
        $this->assignVariable($variables, '--hamburger-top-position', $this->sanitizeCssString($this->resolveOption($options, 'hamburger_top_position')));

        $contentMargin = $this->resolveContentMargin($options);
        if ($contentMargin !== null) {
            $this->assignVariable($variables, '--content-margin', $contentMargin);
        }

        $this->assignVariable($variables, '--floating-vertical-margin', $this->sanitizeCssString($this->resolveOption($options, 'floating_vertical_margin')));
        $this->assignVariable($variables, '--border-radius', $this->sanitizeCssString($this->resolveOption($options, 'border_radius')));
        $this->assignVariable($variables, '--border-width', $this->formatPixelValue($this->resolveOption($options, 'border_width')));
        $this->assignVariable($variables, '--border-color', $this->sanitizeCssString($this->resolveOption($options, 'border_color')));
        $this->assignVariable($variables, '--overlay-color', $this->sanitizeCssString($this->resolveOption($options, 'overlay_color')));
        $this->assignVariable($variables, '--overlay-opacity', $this->formatOpacityValue($this->resolveOption($options, 'overlay_opacity')));
        $this->assignVariable($variables, '--mobile-bg-color', $this->sanitizeCssString($this->resolveOption($options, 'mobile_bg_color')));
        $this->assignVariable($variables, '--mobile-bg-opacity', $this->formatOpacityValue($this->resolveOption($options, 'mobile_bg_opacity')));
        $this->assignVariable($variables, '--mobile-blur', $this->formatPixelValue($this->resolveOption($options, 'mobile_blur')));
        $this->assignVariable($variables, '--menu-alignment-desktop', $this->sanitizeCssString($this->resolveOption($options, 'menu_alignment_desktop')));
        $this->assignVariable($variables, '--menu-alignment-mobile', $this->sanitizeCssString($this->resolveOption($options, 'menu_alignment_mobile')));
        $this->assignVariable($variables, '--search-alignment', $this->sanitizeCssString($this->resolveOption($options, 'search_alignment')));

        $rawSocialIconSize = $this->resolveOption($options, 'social_icon_size');
        if (!is_numeric($rawSocialIconSize)) {
            $rawSocialIconSize = self::DYNAMIC_STYLE_DEFAULTS['social_icon_size'];
        }
        $socialIconSizeFactor = ((float) $rawSocialIconSize) / 100;
        $this->assignVariable($variables, '--social-icon-size-factor', $this->normalizeNumericValue($socialIconSizeFactor));

        $hoverEffectDesktop = $this->sanitizeCssString($this->resolveOption($options, 'hover_effect_desktop')) ?? self::DYNAMIC_STYLE_DEFAULTS['hover_effect_desktop'];
        $hoverEffectMobile = $this->sanitizeCssString($this->resolveOption($options, 'hover_effect_mobile')) ?? self::DYNAMIC_STYLE_DEFAULTS['hover_effect_mobile'];
        if ($hoverEffectDesktop === 'neon' || $hoverEffectMobile === 'neon') {
            $this->assignVariable($variables, '--neon-blur', $this->formatPixelValue($this->resolveOption($options, 'neon_blur')));
            $this->assignVariable($variables, '--neon-spread', $this->formatPixelValue($this->resolveOption($options, 'neon_spread')));
        }

        if ($variables === []) {
            return '';
        }

        $styles = ':root {';
        foreach ($variables as $name => $value) {
            $styles .= $name . ': ' . esc_attr($value) . ';';
        }
        $styles .= '}';

        return $styles;
    }

    private function assignVariable(array &$variables, string $name, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $variables[$name] = $value;
    }

    private function resolveOption(array $options, string $key)
    {
        $value = $options[$key] ?? null;

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                $value = null;
            }
        }

        if ($value === null && array_key_exists($key, self::DYNAMIC_STYLE_DEFAULTS)) {
            $value = self::DYNAMIC_STYLE_DEFAULTS[$key];
        }

        return $value;
    }

    private function sanitizeCssString($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    private function formatPixelValue($value): ?string
    {
        $normalized = $this->normalizeNumericValue($value);

        return $normalized === null ? null : $normalized . 'px';
    }

    private function formatMillisecondsValue($value): ?string
    {
        $normalized = $this->normalizeNumericValue($value);

        return $normalized === null ? null : $normalized . 'ms';
    }

    private function formatOpacityValue($value): ?string
    {
        return $this->normalizeNumericValue($value);
    }

    private function normalizeNumericValue($value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $floatValue = (float) $value;
        if (abs($floatValue - (int) $floatValue) < 0.0001) {
            return (string) (int) round($floatValue);
        }

        return rtrim(rtrim(sprintf('%.4f', $floatValue), '0'), '.');
    }

    private function resolveContentMargin(array $options): ?string
    {
        $rawValue = $this->resolveOption($options, 'content_margin');
        if ($rawValue === null) {
            return null;
        }

        $stringValue = is_string($rawValue) ? $rawValue : (string) $rawValue;
        $stringValue = trim($stringValue);

        if ($stringValue === '') {
            return null;
        }

        if (preg_match('/^calc\((.*)\)$/i', $stringValue, $matches)) {
            $stringValue = trim($matches[1]);
        }

        if ($stringValue === '') {
            return null;
        }

        return 'calc(var(--sidebar-width-desktop) + ' . $stringValue . ')';
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
