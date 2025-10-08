<?php

namespace JLG\Sidebar\Frontend;

use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;
use JLG\Sidebar\Settings\ValueNormalizer;
use JLG\Sidebar\Settings\TypographyOptions;
use function admin_url;
use function wp_create_nonce;

class SidebarRenderer
{
    private const DYNAMIC_STYLE_DEFAULTS = [
        'width_desktop' => 280,
        'width_tablet' => 320,
        'width_mobile' => '100%',
        'bg_color_type' => 'solid',
        'bg_color' => 'rgba(26, 29, 36, 1)',
        'bg_color_start' => '#18181b',
        'bg_color_end' => '#27272a',
        'accent_color_type' => 'solid',
        'accent_color' => 'rgba(13, 110, 253, 1)',
        'accent_color_start' => '#60a5fa',
        'accent_color_end' => '#c084fc',
        'font_size' => 16,
        'font_family' => 'system-ui',
        'font_weight' => '400',
        'text_transform' => 'none',
        'letter_spacing' => '0em',
        'font_color' => 'rgba(224, 224, 224, 1)',
        'font_hover_color' => 'rgba(255, 255, 255, 1)',
        'animation_speed' => 400,
        'header_padding_top' => '2.5rem',
        'header_alignment_desktop' => 'flex-start',
        'header_alignment_mobile' => 'center',
        'header_logo_size' => 150,
        'hamburger_top_position' => '4rem',
        'hamburger_horizontal_offset' => '15px',
        'hamburger_size' => '50px',
        'hamburger_color' => 'rgba(255, 255, 255, 1)',
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
        'horizontal_bar_height' => '4rem',
        'horizontal_bar_alignment' => 'space-between',
        'horizontal_bar_position' => 'top',
        'sidebar_position' => 'left',
    ];

    private const STYLE_VARIABLE_MAP = [
        [
            'option' => 'width_desktop',
            'variable' => '--sidebar-width-desktop',
            'transform' => 'formatPixelValue',
        ],
        [
            'option' => 'width_tablet',
            'variable' => '--sidebar-width-tablet',
            'transform' => 'formatPixelValue',
        ],
        [
            'option' => 'width_mobile',
            'variable' => '--sidebar-width-mobile',
            'transform' => 'sanitizeCssString',
        ],
        [
            'handler' => 'applyBackgroundStyles',
        ],
        [
            'handler' => 'applyAccentStyles',
        ],
        [
            'option' => 'font_size',
            'variable' => '--sidebar-font-size',
            'transform' => 'formatPixelValue',
        ],
        [
            'handler' => 'applyFontFamily',
        ],
        [
            'option' => 'font_weight',
            'variable' => '--sidebar-font-weight',
            'transform' => 'transformFontWeight',
        ],
        [
            'option' => 'text_transform',
            'variable' => '--sidebar-text-transform',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'letter_spacing',
            'variable' => '--sidebar-letter-spacing',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'font_color',
            'variable' => '--sidebar-text-color',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'font_hover_color',
            'variable' => '--sidebar-text-hover-color',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'animation_speed',
            'variable' => '--transition-speed',
            'transform' => 'formatMillisecondsValue',
        ],
        [
            'option' => 'header_padding_top',
            'variable' => '--header-padding-top',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'header_alignment_desktop',
            'variable' => '--header-alignment-desktop',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'header_alignment_mobile',
            'variable' => '--header-alignment-mobile',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'header_logo_size',
            'variable' => '--header-logo-size',
            'transform' => 'formatPixelValue',
        ],
        [
            'option' => 'hamburger_top_position',
            'variable' => '--hamburger-top-position',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'hamburger_horizontal_offset',
            'variable' => '--hamburger-inline-offset',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'hamburger_size',
            'variable' => '--hamburger-size',
            'transform' => 'sanitizeCssString',
        ],
        [
            'handler' => 'applyHamburgerColor',
        ],
        [
            'handler' => 'applyContentMargin',
        ],
        [
            'option' => 'floating_vertical_margin',
            'variable' => '--floating-vertical-margin',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'border_radius',
            'variable' => '--border-radius',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'border_width',
            'variable' => '--border-width',
            'transform' => 'formatPixelValue',
        ],
        [
            'option' => 'border_color',
            'variable' => '--border-color',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'overlay_color',
            'variable' => '--overlay-color',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'overlay_opacity',
            'variable' => '--overlay-opacity',
            'transform' => 'formatOpacityValue',
        ],
        [
            'option' => 'mobile_bg_color',
            'variable' => '--mobile-bg-color',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'mobile_bg_opacity',
            'variable' => '--mobile-bg-opacity',
            'transform' => 'formatOpacityValue',
        ],
        [
            'option' => 'mobile_blur',
            'variable' => '--mobile-blur',
            'transform' => 'formatPixelValue',
        ],
        [
            'option' => 'menu_alignment_desktop',
            'variable' => '--menu-alignment-desktop',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'menu_alignment_mobile',
            'variable' => '--menu-alignment-mobile',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'search_alignment',
            'variable' => '--search-alignment',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'horizontal_bar_height',
            'variable' => '--horizontal-bar-height',
            'transform' => 'sanitizeCssString',
        ],
        [
            'option' => 'horizontal_bar_alignment',
            'variable' => '--horizontal-bar-alignment',
            'transform' => 'sanitizeCssString',
        ],
        [
            'handler' => 'applySocialIconSizeFactor',
        ],
        [
            'handler' => 'applyHoverEffectStyles',
        ],
        [
            'handler' => 'applyAccessibleLabels',
        ],
        [
            'handler' => 'applySafeAreaFallbacks',
        ],
    ];

    private SettingsRepository $settings;
    private IconLibrary $icons;
    private MenuCache $cache;
    private ProfileSelector $profileSelector;
    private RequestContextResolver $requestContextResolver;
    private string $pluginFile;
    private string $version;
    private bool $bodyDataPrinted = false;
    private static ?RequestContextResolver $sharedRequestContextResolver = null;

    public function __construct(
        SettingsRepository $settings,
        IconLibrary $icons,
        MenuCache $cache,
        ProfileSelector $profileSelector,
        RequestContextResolver $requestContextResolver,
        string $pluginFile,
        string $version
    ) {
        $this->settings = $settings;
        $this->icons = $icons;
        $this->cache = $cache;
        $this->profileSelector = $profileSelector;
        $this->requestContextResolver = $requestContextResolver;
        $this->pluginFile = $pluginFile;
        $this->version = $version;
        self::$sharedRequestContextResolver = $requestContextResolver;
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'outputSidebar']);
        add_filter('body_class', [$this, 'addBodyClasses']);
        add_action('wp_body_open', [$this, 'outputBodyDataScript']);
        add_action('wp_footer', [$this, 'outputBodyDataScriptFallback'], 5);
    }

    public function enqueueAssets(): void
    {
        $activeProfile = $this->getActiveProfileData();
        $profile = $activeProfile['profile'];
        $options = $activeProfile['settings'];
        if (empty($options['enable_sidebar'])) {
            return;
        }

        /**
         * Filters whether the public-facing assets should be enqueued. Default true.
         *
         * @param bool  $shouldEnqueue Whether the assets should be registered on the front-end.
         * @param array $options       The sidebar options currently applied.
         */
        $shouldEnqueue = apply_filters('sidebar_jlg_should_enqueue_public_assets', true, $options);

        if (!$shouldEnqueue) {
            return;
        }

        $this->maybeEnqueueGoogleFont($options);

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
            'remember_last_state' => $options['remember_last_state'] ?? false,
            'behavior_triggers' => [
                'time_delay' => max(0, min(600, (int) ($options['auto_open_time_delay'] ?? 0))),
                'scroll_depth' => max(0, min(100, (int) ($options['auto_open_scroll_depth'] ?? 0))),
            ],
            'touch_gestures' => [
                'edge_swipe_enabled' => !empty($options['touch_gestures_edge_swipe']),
                'close_swipe_enabled' => !empty($options['touch_gestures_close_swipe']),
                'edge_size' => max(0, min(200, (int) ($options['touch_gestures_edge_size'] ?? 32))),
                'min_distance' => max(30, min(600, (int) ($options['touch_gestures_min_distance'] ?? 96))),
            ],
            'debug_mode' => (string) ($options['debug_mode'] ?? '0'),
            'sidebar_position' => $this->resolveSidebarPosition($options),
            'active_profile_id' => isset($profile['id']) ? (string) $profile['id'] : 'default',
            'is_fallback_profile' => (bool) ($profile['is_fallback'] ?? false),
            'messages' => [
                'missingElements' => __('Sidebar JLG : menu introuvable.', 'sidebar-jlg'),
            ],
        ];

        $analyticsConfig = [
            'enabled' => !empty($options['enable_analytics']),
        ];

        if (!empty($options['enable_analytics'])) {
            $analyticsConfig['endpoint'] = admin_url('admin-ajax.php');
            $analyticsConfig['nonce'] = wp_create_nonce('jlg_track_event');
            $analyticsConfig['action'] = 'jlg_track_event';
            $analyticsConfig['profile_id'] = $localizedOptions['active_profile_id'];
            $analyticsConfig['profile_is_fallback'] = $localizedOptions['is_fallback_profile'];
        }

        $localizedOptions['analytics'] = $analyticsConfig;

        $localizedOptions['state_storage_key'] = sprintf(
            'sidebar-jlg-state:%s',
            sanitize_key($localizedOptions['active_profile_id'] ?? 'default')
        );

        wp_localize_script('sidebar-jlg-public-js', 'sidebarSettings', $localizedOptions);
    }

    private function buildDynamicStyles(array $options): string
    {
        $variables = $this->collectDynamicStyleVariables($options);

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

    private function collectDynamicStyleVariables(array $options): array
    {
        $variables = [];

        foreach (self::STYLE_VARIABLE_MAP as $definition) {
            if (isset($definition['handler'])) {
                $handler = $definition['handler'];
                if (method_exists($this, $handler)) {
                    $this->$handler($variables, $options, $definition);
                }

                continue;
            }

            $optionKey = $definition['option'] ?? null;
            $variableName = $definition['variable'] ?? null;

            if ($optionKey === null || $variableName === null) {
                continue;
            }

            $value = self::resolveOption($options, $optionKey);

            if (isset($definition['transform'])) {
                $value = self::applyConfiguredTransform($definition['transform'], $value);
            }

            if ($value === null && array_key_exists('fallback', $definition)) {
                $value = $definition['fallback'];
            }

            $this->assignVariable($variables, $variableName, $value);
        }

        return $variables;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function applyConfiguredTransform(string $transform, $value)
    {
        if ($transform === '' || $transform === 'identity') {
            return $value;
        }

        if (!is_callable([self::class, $transform])) {
            return $value;
        }

        return call_user_func([self::class, $transform], $value);
    }

    private function applyBackgroundStyles(array &$variables, array $options, array $definition = []): void
    {
        $bgType = self::sanitizeCssString(self::resolveOption($options, 'bg_color_type'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['bg_color_type'];

        if ($bgType === 'gradient') {
            $bgStart = self::sanitizeCssString(self::resolveOption($options, 'bg_color_start'));
            $bgEnd = self::sanitizeCssString(self::resolveOption($options, 'bg_color_end'));

            if ($bgStart !== null && $bgEnd !== null) {
                $this->assignVariable(
                    $variables,
                    '--sidebar-bg-image',
                    sprintf('linear-gradient(180deg, %s 0%%, %s 100%%)', $bgStart, $bgEnd)
                );
                $this->assignVariable($variables, '--sidebar-bg-color', $bgStart);

                return;
            }
        }

        $solidBg = self::sanitizeCssString(self::resolveOption($options, 'bg_color'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['bg_color'];
        $this->assignVariable($variables, '--sidebar-bg-image', 'none');
        $this->assignVariable($variables, '--sidebar-bg-color', $solidBg);
    }

    private function applyAccentStyles(array &$variables, array $options, array $definition = []): void
    {
        $accentType = self::sanitizeCssString(self::resolveOption($options, 'accent_color_type'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['accent_color_type'];

        if ($accentType === 'gradient') {
            $accentStart = self::sanitizeCssString(self::resolveOption($options, 'accent_color_start'));
            $accentEnd = self::sanitizeCssString(self::resolveOption($options, 'accent_color_end'));

            if ($accentStart !== null && $accentEnd !== null) {
                $this->assignVariable(
                    $variables,
                    '--primary-accent-image',
                    sprintf('linear-gradient(90deg, %s 0%%, %s 100%%)', $accentStart, $accentEnd)
                );
                $this->assignVariable($variables, '--primary-accent-color', $accentStart);

                return;
            }
        }

        $solidAccent = self::sanitizeCssString(self::resolveOption($options, 'accent_color'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['accent_color'];
        $this->assignVariable($variables, '--primary-accent-image', 'none');
        $this->assignVariable($variables, '--primary-accent-color', $solidAccent);
    }

    private function applyFontFamily(array &$variables, array $options, array $definition = []): void
    {
        $fontFamilyKey = self::resolveOption($options, 'font_family');
        $fontStack = null;

        if (is_string($fontFamilyKey) && $fontFamilyKey !== '') {
            $fontStack = TypographyOptions::getFontStack($fontFamilyKey);
        }

        if ($fontStack === null) {
            $fontStack = TypographyOptions::getFontStack(self::DYNAMIC_STYLE_DEFAULTS['font_family']);
        }

        if ($fontStack !== null) {
            $this->assignVariable($variables, '--sidebar-font-family', $fontStack);
        }
    }

    private function applyHamburgerColor(array &$variables, array $options, array $definition = []): void
    {
        $hamburgerColor = self::sanitizeCssString($options['hamburger_color'] ?? null);

        if ($hamburgerColor === null) {
            $hamburgerColor = self::sanitizeCssString(self::resolveOption($options, 'font_color'));
        }

        $this->assignVariable($variables, '--hamburger-color', $hamburgerColor);
    }

    private function applyContentMargin(array &$variables, array $options, array $definition = []): void
    {
        $contentMargin = self::resolveContentMargin($options);

        if ($contentMargin !== null) {
            $this->assignVariable($variables, '--content-margin', $contentMargin);
        }
    }

    private function applySocialIconSizeFactor(array &$variables, array $options, array $definition = []): void
    {
        $rawSocialIconSize = self::resolveOption($options, 'social_icon_size');

        if (!is_numeric($rawSocialIconSize)) {
            $rawSocialIconSize = self::DYNAMIC_STYLE_DEFAULTS['social_icon_size'];
        }

        $socialIconSizeFactor = ((float) $rawSocialIconSize) / 100;
        $normalized = self::normalizeNumericValue($socialIconSizeFactor);

        $this->assignVariable($variables, '--social-icon-size-factor', $normalized);
    }

    private function applyHoverEffectStyles(array &$variables, array $options, array $definition = []): void
    {
        $hoverEffectDesktop = self::sanitizeCssString(self::resolveOption($options, 'hover_effect_desktop'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['hover_effect_desktop'];
        $hoverEffectMobile = self::sanitizeCssString(self::resolveOption($options, 'hover_effect_mobile'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['hover_effect_mobile'];

        if ($hoverEffectDesktop !== 'neon' && $hoverEffectMobile !== 'neon') {
            return;
        }

        $this->assignVariable($variables, '--neon-blur', self::formatPixelValue(self::resolveOption($options, 'neon_blur')));
        $this->assignVariable($variables, '--neon-spread', self::formatPixelValue(self::resolveOption($options, 'neon_spread')));
    }

    private function applyAccessibleLabels(array &$variables, array $options, array $definition = []): void
    {
        $navAriaLabel = self::resolveAccessibleLabelOption($options, 'nav_aria_label', __('Navigation principale', 'sidebar-jlg'));
        $toggleExpandLabel = self::resolveAccessibleLabelOption($options, 'toggle_open_label', __('Afficher le sous-menu', 'sidebar-jlg'));
        $toggleCollapseLabel = self::resolveAccessibleLabelOption($options, 'toggle_close_label', __('Masquer le sous-menu', 'sidebar-jlg'));

        $this->assignVariable($variables, '--sidebar-nav-label', self::formatCssStringValue($navAriaLabel));
        $this->assignVariable($variables, '--sidebar-toggle-open-label', self::formatCssStringValue($toggleExpandLabel));
        $this->assignVariable($variables, '--sidebar-toggle-close-label', self::formatCssStringValue($toggleCollapseLabel));
    }

    private function applySafeAreaFallbacks(array &$variables, array $options, array $definition = []): void
    {
        $safeAreaFallbackDefaults = [
            'block_start' => '0px',
            'block_end' => '0px',
            'inline_start' => '0px',
            'inline_end' => '0px',
        ];

        $safeAreaFallbacks = apply_filters('sidebar_jlg_safe_area_fallbacks', $safeAreaFallbackDefaults, $options);

        if (!is_array($safeAreaFallbacks)) {
            $safeAreaFallbacks = $safeAreaFallbackDefaults;
        } else {
            $safeAreaFallbacks = array_merge($safeAreaFallbackDefaults, $safeAreaFallbacks);
        }

        $safeAreaVariables = [
            'block_start' => '--jlg-safe-area-inset-block-start-fallback',
            'block_end' => '--jlg-safe-area-inset-block-end-fallback',
            'inline_start' => '--jlg-safe-area-inset-inline-start-fallback',
            'inline_end' => '--jlg-safe-area-inset-inline-end-fallback',
        ];

        foreach ($safeAreaVariables as $key => $variableName) {
            $fallbackValue = $safeAreaFallbacks[$key] ?? $safeAreaFallbackDefaults[$key];
            $sanitizedValue = self::sanitizeCssString($fallbackValue) ?? $safeAreaFallbackDefaults[$key];
            $this->assignVariable($variables, $variableName, $sanitizedValue);
        }
    }

    /**
     * @param mixed $value
     */
    private static function transformFontWeight($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    private function maybeEnqueueGoogleFont(array $options): void
    {
        $fontKey = $options['font_family'] ?? '';

        if (!is_string($fontKey) || $fontKey === '') {
            return;
        }

        $query = TypographyOptions::getGoogleFontQuery($fontKey);
        if ($query === null) {
            return;
        }

        $handleKey = sanitize_key($fontKey);
        if ($handleKey === '') {
            $handleKey = md5($fontKey);
        }

        $url = $this->buildGoogleFontUrl($query);
        wp_enqueue_style(
            'sidebar-jlg-google-font-' . $handleKey,
            $url,
            [],
            null
        );
    }

    private function buildGoogleFontUrl(string $query): string
    {
        $encodedFamily = str_replace(' ', '+', $query);

        return 'https://fonts.googleapis.com/css2?family=' . $encodedFamily . '&display=swap';
    }

    private function assignVariable(array &$variables, string $name, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $variables[$name] = $value;
    }

    private static function resolveOption(array $options, string $key)
    {
        $value = $options[$key] ?? null;

        if (is_array($value) && array_key_exists('value', $value)) {
            $fallback = self::DYNAMIC_STYLE_DEFAULTS[$key] ?? '';
            $value = ValueNormalizer::dimensionToCss($value, is_string($fallback) ? $fallback : '');
        }

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

    private static function sanitizeCssString($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    private static function formatPixelValue($value): ?string
    {
        $normalized = self::normalizeNumericValue($value);

        return $normalized === null ? null : $normalized . 'px';
    }

    private static function formatMillisecondsValue($value): ?string
    {
        $normalized = self::normalizeNumericValue($value);

        return $normalized === null ? null : $normalized . 'ms';
    }

    private static function formatOpacityValue($value): ?string
    {
        return self::normalizeNumericValue($value);
    }

    private static function normalizeNumericValue($value): ?string
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

    private static function resolveAccessibleLabelOption(array $options, string $key, string $fallback): string
    {
        $rawValue = $options[$key] ?? null;

        if (!is_string($rawValue)) {
            return $fallback;
        }

        $trimmed = trim($rawValue);

        if ($trimmed === '') {
            return $fallback;
        }

        return sanitize_text_field($trimmed);
    }

    private static function formatCssStringValue(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }

    private static function resolveContentMargin(array $options): ?string
    {
        $rawValue = self::resolveOption($options, 'content_margin');
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

    public function renderSidebarToHtml(array $options): ?string
    {
        $allIcons = $this->icons->getAllIcons();
        $templatePath = $this->resolveSidebarTemplatePath($options);

        if ($templatePath === null || !is_readable($templatePath)) {
            if (function_exists('error_log')) {
                $path = is_string($templatePath) ? $templatePath : '(none)';
                error_log('[Sidebar JLG] Sidebar template not found or unreadable at ' . $path);
            }

            return null;
        }

        $bufferStarted = ob_start();
        if ($bufferStarted === false) {
            $this->logSidebarBufferFailure('ob_start');

            return null;
        }

        $bufferLevel = ob_get_level();
        $optionsForTemplate = $options;
        $allIconsForTemplate = $allIcons;
        $options = $optionsForTemplate;
        $allIcons = $allIconsForTemplate;

        try {
            /** @psalm-suppress UnresolvableInclude */
            require $templatePath;
        } catch (\Throwable $exception) {
            $this->cleanSidebarBuffer($bufferLevel);
            $this->logSidebarTemplateFailure($templatePath, $exception);

            return null;
        }

        $html = ob_get_clean();

        if (!is_string($html)) {
            $this->cleanSidebarBuffer($bufferLevel);
            $this->logSidebarBufferFailure('ob_get_clean');

            return null;
        }

        return $html;
    }

    public function render(): ?string
    {
        $activeProfile = $this->getActiveProfileData();
        $profile = $activeProfile['profile'];
        $options = $activeProfile['settings'];
        $profileId = isset($profile['id']) && is_string($profile['id']) && $profile['id'] !== ''
            ? $profile['id']
            : 'default';
        if (empty($options['enable_sidebar'])) {
            return null;
        }

        $currentLocale = $this->cache->getLocaleForCache();
        $transientKey = $this->cache->getTransientKey($currentLocale, $profileId);

        $cacheEnabled = (bool) \apply_filters(
            'sidebar_jlg_cache_enabled',
            !$this->is_sidebar_output_dynamic($options),
            $options,
            $currentLocale,
            $transientKey,
            $profileId
        );

        $html = false;

        if ($cacheEnabled) {
            $html = $this->cache->get($currentLocale, $profileId);
        } else {
            $this->cache->delete($currentLocale, $profileId);
        }

        if (!$cacheEnabled || false === $html) {
            $html = $this->renderSidebarToHtml($options);

            if (!is_string($html)) {
                return null;
            }

            if ($cacheEnabled) {
                $this->cache->set($currentLocale, $html, $profileId);
            }
        }

        return $html;
    }

    public function outputSidebar(): void
    {
        $html = $this->render();

        if (!is_string($html)) {
            return;
        }

        echo $html;
    }

    public function outputBodyDataScript(): void
    {
        $this->printBodyDataScript();
    }

    public function outputBodyDataScriptFallback(): void
    {
        $this->printBodyDataScript();
    }

    private function printBodyDataScript(): void
    {
        if ($this->bodyDataPrinted) {
            return;
        }

        $state = $this->resolveActiveSidebarState();
        if ($state === null) {
            return;
        }

        $encodedPosition = json_encode($state['position'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        if (!is_string($encodedPosition)) {
            return;
        }

        printf(
            '<script id="sidebar-jlg-body-data">document.body.dataset.sidebarPosition=%1$s;document.body.setAttribute("data-sidebar-position",%1$s);</script>',
            $encodedPosition
        );

        $this->bodyDataPrinted = true;
    }

    private function cleanSidebarBuffer(int $targetLevel): void
    {
        while ($targetLevel > 0 && \ob_get_level() >= $targetLevel) {
            \ob_end_clean();
        }
    }

    private function resolveSidebarTemplatePath(array $options): ?string
    {
        $defaultTemplatePath = plugin_dir_path($this->pluginFile) . 'includes/sidebar-template.php';
        $templatePath = $defaultTemplatePath;

        if (function_exists('locate_template')) {
            $locatedTemplate = locate_template(['sidebar-jlg/sidebar-template.php', 'sidebar-jlg.php'], false, false);
            if (is_string($locatedTemplate) && $locatedTemplate !== '') {
                $templatePath = $locatedTemplate;
            }
        }

        if (function_exists('apply_filters')) {
            $filteredTemplate = apply_filters('sidebar_jlg_template_path', $templatePath, $options, $defaultTemplatePath);
            if (is_string($filteredTemplate) && $filteredTemplate !== '') {
                if (!self::isAbsolutePath($filteredTemplate) && function_exists('locate_template')) {
                    $maybeLocated = locate_template([$filteredTemplate], false, false);
                    if (is_string($maybeLocated) && $maybeLocated !== '') {
                        $filteredTemplate = $maybeLocated;
                    }
                }

                $templatePath = $filteredTemplate;
            }
        }

        if (!is_string($templatePath) || $templatePath === '') {
            return null;
        }

        return $templatePath;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        if (strlen($path) > 1 && $path[1] === ':' && (($path[2] ?? null) === '\\' || ($path[2] ?? null) === '/')) {
            return true;
        }

        return false;
    }

    private function logSidebarBufferFailure(string $operation): void
    {
        if (!function_exists('error_log')) {
            return;
        }

        error_log(sprintf('[Sidebar JLG] Failed to capture sidebar output buffer using %s.', $operation));
    }

    private function logSidebarTemplateFailure(string $templatePath, \Throwable $exception): void
    {
        if (!function_exists('error_log')) {
            return;
        }

        $message = sprintf(
            '[Sidebar JLG] Failed to render sidebar template %s: %s',
            $templatePath,
            $exception->getMessage()
        );

        error_log($message);
    }

    public static function getCurrentRequestContext(): array
    {
        return self::getRequestContextResolver()->resolve();
    }

    public static function isMenuItemCurrent(array $item, array $context): bool
    {
        $type = isset($item['type']) ? (string) $item['type'] : '';
        $context += [
            'current_post_ids'     => [],
            'current_post_types'   => [],
            'current_category_ids' => [],
            'current_url'          => null,
        ];

        switch ($type) {
            case 'page':
                $targetId = absint($item['value'] ?? 0);
                if ($targetId === 0) {
                    return false;
                }

                if (!in_array($targetId, $context['current_post_ids'], true)) {
                    return false;
                }

                $postTypes = (array) $context['current_post_types'];
                if ($postTypes === []) {
                    return true;
                }

                return in_array('page', $postTypes, true);

            case 'post':
                $targetId = absint($item['value'] ?? 0);
                if ($targetId === 0) {
                    return false;
                }

                if (!in_array($targetId, $context['current_post_ids'], true)) {
                    return false;
                }

                $postTypes = (array) $context['current_post_types'];
                if ($postTypes === []) {
                    return true;
                }

                return in_array('post', $postTypes, true);

            case 'category':
                $targetId = absint($item['value'] ?? 0);
                if ($targetId === 0) {
                    return false;
                }

                return in_array($targetId, (array) $context['current_category_ids'], true);

            case 'custom':
                $targetUrl = isset($item['value']) ? (string) $item['value'] : '';
                if ($targetUrl === '') {
                    return false;
                }

                $currentUrl = is_string($context['current_url'] ?? null) ? (string) $context['current_url'] : '';
                if ($currentUrl === '') {
                    return false;
                }

                return self::urlsMatch($targetUrl, $currentUrl);
            case 'nav_menu_item':
                $menuItemType = isset($item['menu_item_type']) ? (string) $item['menu_item_type'] : '';
                $object = isset($item['object']) ? (string) $item['object'] : '';
                $objectId = absint($item['object_id'] ?? 0);
                $currentUrl = is_string($context['current_url'] ?? null) ? (string) $context['current_url'] : '';
                $targetUrl = isset($item['url']) ? (string) $item['url'] : '';

                switch ($menuItemType) {
                    case 'post_type':
                        if ($objectId > 0 && in_array($objectId, (array) $context['current_post_ids'], true)) {
                            $postTypes = (array) $context['current_post_types'];
                            if ($object === '' || $postTypes === [] || in_array($object, $postTypes, true)) {
                                return true;
                            }
                        }
                        break;
                    case 'post_type_archive':
                        if ($object !== '' && in_array($object, (array) $context['current_post_types'], true)) {
                            return true;
                        }
                        break;
                    case 'taxonomy':
                        if ($object === 'category' && $objectId > 0) {
                            if (in_array($objectId, (array) $context['current_category_ids'], true)) {
                                return true;
                            }
                        }
                        break;
                }

                if ($targetUrl !== '' && $currentUrl !== '' && self::urlsMatch($targetUrl, $currentUrl)) {
                    return true;
                }

                return false;
        }

        return false;
    }

    public static function buildMenuTree(array $options, array $allIcons, array $context): array
    {
        $menuItems = [];
        if (isset($options['menu_items']) && is_array($options['menu_items'])) {
            $menuItems = $options['menu_items'];
        }

        $nodes = [];

        foreach ($menuItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = isset($item['type']) ? (string) $item['type'] : '';

            if ($type === 'nav_menu') {
                $menuId = absint($item['value'] ?? 0);
                if ($menuId <= 0) {
                    continue;
                }

                $maxDepth = isset($item['nav_menu_max_depth']) ? max(0, absint($item['nav_menu_max_depth'])) : 0;
                $filter = isset($item['nav_menu_filter']) ? sanitize_key((string) $item['nav_menu_filter']) : 'all';

                $navNodes = self::buildNodesFromNavMenu($menuId, $maxDepth, $filter, $context);
                if (!empty($navNodes)) {
                    $nodes = array_merge($nodes, $navNodes);
                }

                continue;
            }

            $staticNode = self::buildStaticMenuNode($item, $allIcons, $context);
            if ($staticNode !== null) {
                $nodes[] = $staticNode;
            }
        }

        return array_values($nodes);
    }

    private static function buildStaticMenuNode(array $item, array $allIcons, array $context): ?array
    {
        $type = isset($item['type']) ? (string) $item['type'] : '';

        if ($type === 'cta') {
            $title = '';
            if (isset($item['cta_title']) && is_string($item['cta_title'])) {
                $title = sanitize_text_field($item['cta_title']);
            }

            $description = '';
            if (isset($item['cta_description']) && is_string($item['cta_description'])) {
                $description = wp_kses_post($item['cta_description']);
            }

            $buttonLabel = '';
            if (isset($item['cta_button_label']) && is_string($item['cta_button_label'])) {
                $buttonLabel = sanitize_text_field($item['cta_button_label']);
            }

            $rawButtonUrl = '';
            if (isset($item['cta_button_url']) && is_string($item['cta_button_url'])) {
                $rawButtonUrl = $item['cta_button_url'];
            } elseif (isset($item['value']) && is_string($item['value'])) {
                $rawButtonUrl = $item['value'];
            }

            $buttonUrl = esc_url($rawButtonUrl);
            $buttonHref = $buttonUrl !== '' ? $buttonUrl : '#';

            $shortcodeMarkup = '';
            if (isset($item['cta_shortcode']) && is_string($item['cta_shortcode'])) {
                $rawShortcode = $item['cta_shortcode'];
                if ($rawShortcode !== '') {
                    $processed = function_exists('do_shortcode') ? do_shortcode($rawShortcode) : $rawShortcode;
                    $shortcodeMarkup = wp_kses_post($processed);
                }
            }

            $fallbackLabel = '';
            if (isset($item['label']) && is_string($item['label'])) {
                $fallbackLabel = sanitize_text_field($item['label']);
            }

            $trackingSource = $title !== '' ? $title : ($buttonLabel !== '' ? $buttonLabel : $fallbackLabel);
            $trackingId = $trackingSource !== '' ? sanitize_title($trackingSource) : '';
            if ($trackingId === '') {
                $trackingId = 'cta-' . substr(md5($trackingSource . $buttonUrl), 0, 8);
            }

            $classes = self::normalizeMenuClasses(['menu-item', 'menu-item-static', 'menu-item-cta']);

            return [
                'type' => 'cta',
                'label' => $title !== '' ? $title : $fallbackLabel,
                'url' => '',
                'classes' => $classes,
                'is_current' => false,
                'is_current_ancestor' => false,
                'children' => [],
                'icon' => [
                    'type' => '',
                    'markup' => '',
                    'url' => '',
                    'is_custom' => false,
                ],
                'origin' => 'static',
                'cta' => [
                    'title' => $title,
                    'description' => $description,
                    'button_label' => $buttonLabel,
                    'button_url' => $buttonHref,
                    'shortcode' => $shortcodeMarkup,
                ],
                'data_attributes' => $trackingId !== ''
                    ? [
                        'data-cta-id' => $trackingId,
                        'data-cta-label' => $trackingSource,
                    ]
                    : [],
            ];
        }

        $url = '#';
        $rawUrl = '';
        $isValid = true;

        switch ($type) {
            case 'custom':
                $rawUrl = isset($item['value']) ? (string) $item['value'] : '';
                break;
            case 'post':
            case 'page':
                $targetId = absint($item['value'] ?? 0);
                if ($targetId > 0 && function_exists('get_permalink')) {
                    $rawUrl = get_permalink($targetId);
                }
                break;
            case 'category':
                $targetId = absint($item['value'] ?? 0);
                if ($targetId > 0 && function_exists('get_category_link')) {
                    $rawUrl = get_category_link($targetId);
                }
                break;
        }

        if ($rawUrl === '' || (function_exists('is_wp_error') && is_wp_error($rawUrl))) {
            $isValid = false;
        }

        if ($isValid && is_string($rawUrl) && $rawUrl !== '') {
            $url = $rawUrl;
        }

        $icon = [
            'type' => '',
            'markup' => '',
            'url' => '',
            'is_custom' => false,
        ];

        $iconType = isset($item['icon_type']) ? (string) $item['icon_type'] : 'svg_inline';
        $iconValue = isset($item['icon']) ? (string) $item['icon'] : '';

        if ($iconType === 'svg_url') {
            if ($iconValue !== '' && filter_var($iconValue, FILTER_VALIDATE_URL)) {
                $icon['type'] = 'svg_url';
                $icon['url'] = $iconValue;
            }
        } elseif ($iconValue !== '' && isset($allIcons[$iconValue])) {
            $icon['type'] = 'svg_inline';
            $icon['markup'] = (string) $allIcons[$iconValue];
            $icon['is_custom'] = strpos($iconValue, 'custom_') === 0;
        }

        $isCurrent = self::isMenuItemCurrent($item, $context);

        $classes = ['menu-item', 'menu-item-static'];
        if ($isCurrent) {
            $classes[] = 'current-menu-item';
        }

        if ($icon['type'] !== '') {
            $classes[] = 'menu-item-has-icon';
        }

        $classes = self::normalizeMenuClasses($classes);

        return [
            'label' => isset($item['label']) ? (string) $item['label'] : '',
            'url' => $url,
            'classes' => $classes,
            'is_current' => $isCurrent,
            'is_current_ancestor' => false,
            'children' => [],
            'icon' => $icon,
            'origin' => 'static',
        ];
    }

    private static function buildNodesFromNavMenu(int $menuId, int $maxDepth, string $filter, array $context): array
    {
        if (!function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $rawItems = wp_get_nav_menu_items($menuId, ['update_post_term_cache' => false]);
        if (!is_array($rawItems)) {
            return [];
        }

        $itemsById = [];
        $childrenMap = [];
        $rootIds = [];

        foreach ($rawItems as $menuItem) {
            if (!is_object($menuItem) || !isset($menuItem->ID)) {
                continue;
            }

            $mapped = self::mapNavMenuItem($menuItem);
            if ($mapped === null) {
                continue;
            }

            $menuItemId = (int) $menuItem->ID;
            $parentId = isset($menuItem->menu_item_parent) ? (int) $menuItem->menu_item_parent : 0;

            $classes = is_array($menuItem->classes) ? $menuItem->classes : [];
            $classes[] = 'menu-item';
            $classes[] = 'menu-item-nav';
            $classes[] = 'origin-nav-menu';

            $isCurrent = self::isMenuItemCurrent($mapped['item_data'], $context);

            $itemsById[$menuItemId] = [
                'id' => $menuItemId,
                'parent' => $parentId,
                'label' => $mapped['label'],
                'url' => $mapped['url'],
                'item_data' => $mapped['item_data'],
                'classes' => self::normalizeMenuClasses($classes),
                'is_current' => $isCurrent,
            ];
        }

        foreach ($itemsById as $id => $node) {
            $parentId = $node['parent'];
            if ($parentId > 0 && isset($itemsById[$parentId])) {
                if (!isset($childrenMap[$parentId])) {
                    $childrenMap[$parentId] = [];
                }
                $childrenMap[$parentId][] = $id;
            } else {
                $rootIds[] = $id;
            }
        }

        $tree = [];
        foreach ($rootIds as $rootId) {
            $node = self::buildNavMenuNodeTree($rootId, $itemsById, $childrenMap, $maxDepth, 0);
            if ($node !== null) {
                $tree[] = $node;
            }
        }

        if ($filter === 'top-level') {
            $tree = self::stripChildrenFromNavNodes($tree);
        } elseif ($filter === 'current-branch') {
            $filtered = self::pruneNavMenuToCurrentBranch($tree);
            if ($filtered !== []) {
                $tree = $filtered;
            }
        }

        return array_values($tree);
    }

    private static function buildNavMenuNodeTree(int $nodeId, array $itemsById, array $childrenMap, int $maxDepth, int $depth): ?array
    {
        if (!isset($itemsById[$nodeId])) {
            return null;
        }

        $item = $itemsById[$nodeId];

        $node = [
            'label' => $item['label'],
            'url' => $item['url'],
            'classes' => $item['classes'],
            'is_current' => $item['is_current'],
            'is_current_ancestor' => false,
            'children' => [],
            'icon' => [
                'type' => '',
                'markup' => '',
                'url' => '',
                'is_custom' => false,
            ],
            'origin' => 'nav_menu',
        ];

        if (isset($childrenMap[$nodeId]) && ($maxDepth === 0 || $depth + 1 < $maxDepth)) {
            foreach ($childrenMap[$nodeId] as $childId) {
                $childNode = self::buildNavMenuNodeTree($childId, $itemsById, $childrenMap, $maxDepth, $depth + 1);
                if ($childNode === null) {
                    continue;
                }

                if ($childNode['is_current'] || $childNode['is_current_ancestor']) {
                    $node['is_current_ancestor'] = true;
                }

                $node['children'][] = $childNode;
            }
        }

        if (!empty($node['children'])) {
            $node['classes'][] = 'menu-item-has-children';
        }

        if ($node['is_current']) {
            $node['classes'][] = 'current-menu-item';
        }

        if ($node['is_current_ancestor']) {
            $node['classes'][] = 'current-menu-ancestor';
        }

        $node['classes'] = self::normalizeMenuClasses($node['classes']);

        return $node;
    }

    private static function mapNavMenuItem(object $menuItem): ?array
    {
        if (!isset($menuItem->title)) {
            return null;
        }

        $label = sanitize_text_field((string) $menuItem->title);
        $url = isset($menuItem->url) && is_string($menuItem->url) ? (string) $menuItem->url : '';

        return [
            'label' => $label,
            'url' => $url,
            'item_data' => [
                'type' => 'nav_menu_item',
                'menu_item_type' => isset($menuItem->type) ? (string) $menuItem->type : '',
                'object' => isset($menuItem->object) ? (string) $menuItem->object : '',
                'object_id' => isset($menuItem->object_id) ? absint($menuItem->object_id) : 0,
                'url' => $url,
            ],
        ];
    }

    private static function normalizeMenuClasses($classes): array
    {
        if (!is_array($classes)) {
            return [];
        }

        $normalized = [];

        foreach ($classes as $class) {
            if (!is_string($class)) {
                continue;
            }

            $sanitized = sanitize_html_class($class);
            if ($sanitized === '') {
                continue;
            }

            $normalized[$sanitized] = true;
        }

        return array_keys($normalized);
    }

    private static function stripChildrenFromNavNodes(array $nodes): array
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $node['children'] = [];
            }

            $node['classes'] = self::normalizeMenuClasses(array_diff($node['classes'], ['menu-item-has-children']));
        }

        unset($node);

        return array_values($nodes);
    }

    private static function pruneNavMenuToCurrentBranch(array $nodes): array
    {
        $pruned = [];

        foreach ($nodes as $node) {
            $children = self::pruneNavMenuToCurrentBranch($node['children']);
            $shouldKeep = $node['is_current'] || $node['is_current_ancestor'] || !empty($children);

            if (!$shouldKeep) {
                continue;
            }

            $node['children'] = array_values($children);

            if (!empty($children)) {
                if (!in_array('menu-item-has-children', $node['classes'], true)) {
                    $node['classes'][] = 'menu-item-has-children';
                }
                if (!in_array('current-menu-ancestor', $node['classes'], true)) {
                    $node['classes'][] = 'current-menu-ancestor';
                }
                $node['is_current_ancestor'] = true;
            } else {
                $node['classes'] = array_values(array_diff($node['classes'], ['menu-item-has-children']));
            }

            $node['classes'] = self::normalizeMenuClasses($node['classes']);
            $pruned[] = $node;
        }

        return $pruned;
    }

    private static function urlsMatch(string $first, string $second): bool
    {
        $resolver = self::getRequestContextResolver();
        $normalizedFirst = $resolver->normalizeUrlForComparison($first);
        $normalizedSecond = $resolver->normalizeUrlForComparison($second);

        if ($normalizedFirst === '' || $normalizedSecond === '') {
            return false;
        }

        return $normalizedFirst === $normalizedSecond;
    }

    private static function getRequestContextResolver(): RequestContextResolver
    {
        if (self::$sharedRequestContextResolver === null) {
            self::$sharedRequestContextResolver = new RequestContextResolver();
        }

        return self::$sharedRequestContextResolver;
    }

    public function addBodyClasses(array $classes): array
    {
        $state = $this->resolveActiveSidebarState();
        if ($state === null) {
            return $classes;
        }

        $options = $state['settings'];
        $classes[] = 'jlg-sidebar-active';
        $classes[] = 'jlg-sidebar-position-' . $state['position'];
        $layoutStyle = $options['layout_style'] ?? 'full';

        if ($layoutStyle === 'horizontal-bar') {
            $classes[] = 'jlg-sidebar-horizontal-bar';
            $position = sanitize_key($options['horizontal_bar_position'] ?? 'top');
            if ($position !== 'top' && $position !== 'bottom') {
                $position = 'top';
            }
            $classes[] = 'jlg-horizontal-position-' . $position;

            if (!empty($options['horizontal_bar_sticky'])) {
                $classes[] = 'jlg-horizontal-sticky';
            }
        } else {
            if (($options['desktop_behavior'] ?? 'push') === 'push') {
                $classes[] = 'jlg-sidebar-push';
            } else {
                $classes[] = 'jlg-sidebar-overlay';
            }

            if ($layoutStyle === 'floating') {
                $classes[] = 'jlg-sidebar-floating';
            }
        }

        return $classes;
    }

    private function getActiveProfile(): array
    {
        return $this->profileSelector->selectProfile();
    }

    private function getActiveProfileData(): array
    {
        $profile = $this->getActiveProfile();
        $settings = [];

        if (isset($profile['settings']) && is_array($profile['settings'])) {
            $settings = $profile['settings'];
        }

        return [
            'profile' => $profile,
            'settings' => $settings,
        ];
    }

    private function resolveActiveSidebarState(): ?array
    {
        $activeProfile = $this->getActiveProfileData();
        $profile = $activeProfile['profile'];
        $settings = $activeProfile['settings'];

        if (empty($settings['enable_sidebar'])) {
            return null;
        }

        return [
            'profile' => $profile,
            'settings' => $settings,
            'position' => $this->resolveSidebarPosition($settings),
        ];
    }

    public function is_sidebar_output_dynamic(?array $options = null): bool
    {
        if ($options === null) {
            $options = $this->settings->getOptions();
        }

        $isDynamic = !empty($options['enable_search']);

        if (!$isDynamic && !empty($options['menu_items']) && is_array($options['menu_items'])) {
            $context = self::getCurrentRequestContext();

            foreach ($options['menu_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (self::isMenuItemCurrent($item, $context)) {
                    $isDynamic = true;
                    break;
                }
            }
        }

        return (bool) \apply_filters('sidebar_jlg_is_dynamic', $isDynamic, $options);
    }

    private function resolveSidebarPosition(array $options): string
    {
        $position = \sanitize_key($options['sidebar_position'] ?? '');

        return $position === 'right' ? 'right' : 'left';
    }
}
