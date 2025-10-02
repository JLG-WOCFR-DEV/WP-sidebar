<?php

namespace JLG\Sidebar\Frontend;

use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;
use JLG\Sidebar\Settings\TypographyOptions;

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

    private SettingsRepository $settings;
    private IconLibrary $icons;
    private MenuCache $cache;
    private string $pluginFile;
    private string $version;
    private bool $bodyDataPrinted = false;

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
        add_action('wp_body_open', [$this, 'outputBodyDataScript']);
        add_action('wp_footer', [$this, 'outputBodyDataScriptFallback'], 5);
    }

    public function enqueueAssets(): void
    {
        $options = $this->settings->getOptions();
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
            'debug_mode' => (string) ($options['debug_mode'] ?? '0'),
            'sidebar_position' => $this->resolveSidebarPosition($options),
            'messages' => [
                'missingElements' => __('Sidebar JLG : menu introuvable.', 'sidebar-jlg'),
            ],
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
        $fontFamilyKey = $this->resolveOption($options, 'font_family');
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
        $fontWeight = $this->resolveOption($options, 'font_weight');
        if (is_string($fontWeight) || is_numeric($fontWeight)) {
            $this->assignVariable($variables, '--sidebar-font-weight', (string) $fontWeight);
        }
        $textTransform = $this->sanitizeCssString($this->resolveOption($options, 'text_transform'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['text_transform'];
        $this->assignVariable($variables, '--sidebar-text-transform', $textTransform);
        $letterSpacing = $this->sanitizeCssString($this->resolveOption($options, 'letter_spacing'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['letter_spacing'];
        $this->assignVariable($variables, '--sidebar-letter-spacing', $letterSpacing);
        $this->assignVariable($variables, '--sidebar-text-color', $this->sanitizeCssString($this->resolveOption($options, 'font_color')));
        $this->assignVariable($variables, '--sidebar-text-hover-color', $this->sanitizeCssString($this->resolveOption($options, 'font_hover_color')));
        $this->assignVariable($variables, '--transition-speed', $this->formatMillisecondsValue($this->resolveOption($options, 'animation_speed')));
        $this->assignVariable($variables, '--header-padding-top', $this->sanitizeCssString($this->resolveOption($options, 'header_padding_top')));
        $this->assignVariable($variables, '--header-alignment-desktop', $this->sanitizeCssString($this->resolveOption($options, 'header_alignment_desktop')));
        $this->assignVariable($variables, '--header-alignment-mobile', $this->sanitizeCssString($this->resolveOption($options, 'header_alignment_mobile')));
        $this->assignVariable($variables, '--header-logo-size', $this->formatPixelValue($this->resolveOption($options, 'header_logo_size')));
        $this->assignVariable($variables, '--hamburger-top-position', $this->sanitizeCssString($this->resolveOption($options, 'hamburger_top_position')));

        $hamburgerColor = $this->sanitizeCssString($options['hamburger_color'] ?? null);
        if ($hamburgerColor === null) {
            $hamburgerColor = $this->sanitizeCssString($this->resolveOption($options, 'font_color'));
        }

        $this->assignVariable($variables, '--hamburger-color', $hamburgerColor);

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

        $horizontalHeight = $this->sanitizeCssString($this->resolveOption($options, 'horizontal_bar_height'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['horizontal_bar_height'];
        $this->assignVariable($variables, '--horizontal-bar-height', $horizontalHeight);

        $horizontalAlignment = $this->sanitizeCssString($this->resolveOption($options, 'horizontal_bar_alignment'))
            ?? self::DYNAMIC_STYLE_DEFAULTS['horizontal_bar_alignment'];
        $this->assignVariable($variables, '--horizontal-bar-alignment', $horizontalAlignment);

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
            $sanitizedValue = $this->sanitizeCssString($fallbackValue) ?? $safeAreaFallbackDefaults[$key];
            $this->assignVariable($variables, $variableName, $sanitizedValue);
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

            $bufferStarted = ob_start();
            if ($bufferStarted === false) {
                $this->logSidebarBufferFailure('ob_start');

                return;
            }

            $bufferLevel = ob_get_level();
            $optionsForTemplate = $options;
            $allIconsForTemplate = $allIcons;
            $options = $optionsForTemplate;
            $allIcons = $allIconsForTemplate;
            require $templatePath;
            $html = ob_get_clean();

            if (!is_string($html)) {
                $this->cleanSidebarBuffer($bufferLevel);
                $this->logSidebarBufferFailure('ob_get_clean');

                return;
            }

            if ($cacheEnabled) {
                $this->cache->set($currentLocale, $html);
            }
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

        $options = $this->settings->getOptions();
        if (empty($options['enable_sidebar'])) {
            return;
        }

        $position = $this->resolveSidebarPosition($options);
        $encodedPosition = json_encode($position, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        if (!is_string($encodedPosition)) {
            return;
        }

        printf(
            '<script id="sidebar-jlg-body-data">document.body.dataset.sidebarPosition=%s;</script>',
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

    private function logSidebarBufferFailure(string $operation): void
    {
        if (!function_exists('error_log')) {
            return;
        }

        error_log(sprintf('[Sidebar JLG] Failed to capture sidebar output buffer using %s.', $operation));
    }

    public static function getCurrentRequestContext(): array
    {
        $context = [
            'current_post_ids'      => [],
            'current_post_types'    => [],
            'current_category_ids'  => [],
            'current_url'           => null,
        ];

        $queriedObject = null;
        if (function_exists('get_queried_object')) {
            $queriedObject = get_queried_object();
        }

        if (function_exists('get_queried_object_id')) {
            $queriedId = absint(get_queried_object_id());
            if ($queriedId > 0) {
                $context['current_post_ids'][] = $queriedId;
            }
        }

        if (is_object($queriedObject)) {
            if (isset($queriedObject->ID)) {
                $objectId = absint($queriedObject->ID);
                if ($objectId > 0 && !in_array($objectId, $context['current_post_ids'], true)) {
                    $context['current_post_ids'][] = $objectId;
                }
            }

            if (isset($queriedObject->post_type)) {
                $postType = (string) $queriedObject->post_type;
                if ($postType !== '' && !in_array($postType, $context['current_post_types'], true)) {
                    $context['current_post_types'][] = $postType;
                }
            }

            if (isset($queriedObject->taxonomy) && isset($queriedObject->term_id)) {
                $taxonomy = (string) $queriedObject->taxonomy;
                $termId = absint($queriedObject->term_id);

                if ($taxonomy === 'category' && $termId > 0) {
                    $context['current_category_ids'][] = $termId;
                }
            }
        }

        $currentUrl = self::buildCurrentUrl();
        if ($currentUrl !== null) {
            $context['current_url'] = self::normalizeUrlForComparison($currentUrl);
        }

        return $context;
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

    private static function buildCurrentUrl(): ?string
    {
        $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
        if ($host === '') {
            return null;
        }

        $scheme = 'http';
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            $scheme = 'https';
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($requestUri === '') {
            $requestUri = '/';
        }

        return $scheme . '://' . $host . $requestUri;
    }

    private static function urlsMatch(string $first, string $second): bool
    {
        $normalizedFirst = self::normalizeUrlForComparison($first);
        $normalizedSecond = self::normalizeUrlForComparison($second);

        if ($normalizedFirst === '' || $normalizedSecond === '') {
            return false;
        }

        return $normalizedFirst === $normalizedSecond;
    }

    private static function normalizeUrlForComparison(?string $url): string
    {
        if (!is_string($url)) {
            return '';
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = @parse_url($url);
        if ($parts === false) {
            return self::trimPath($url);
        }

        if (!isset($parts['scheme']) || !isset($parts['host'])) {
            $absoluteUrl = self::convertRelativeUrlToAbsolute($url);
            if ($absoluteUrl !== null) {
                $parts = @parse_url($absoluteUrl);
                if ($parts === false) {
                    return self::trimPath($absoluteUrl);
                }

                $url = $absoluteUrl;
            }
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        if ($path === '') {
            $path = '/';
        }
        $path = '/' . ltrim($path, '/');
        $path = self::trimPath($path);

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        if (isset($parts['scheme']) && isset($parts['host'])) {
            $scheme = strtolower((string) $parts['scheme']);
            $host = strtolower((string) $parts['host']);
            $normalized = $scheme . '://' . $host;

            if (isset($parts['port']) && $parts['port'] !== null) {
                $port = (int) $parts['port'];
                if (!self::isDefaultPortForScheme($port, $scheme)) {
                    $normalized .= ':' . $port;
                }
            }

            return $normalized . $path . $query;
        }

        return $path . $query;
    }

    private static function convertRelativeUrlToAbsolute(string $url): ?string
    {
        $homeUrl = self::getHomeUrlForNormalization();
        if ($homeUrl === null) {
            return null;
        }

        $homeUrl = rtrim($homeUrl, '/');
        if ($homeUrl === '') {
            return null;
        }

        if ($url === '' || $url === '/') {
            return $homeUrl . '/';
        }

        $firstChar = $url[0];
        if ($firstChar === '/') {
            return $homeUrl . $url;
        }

        if ($firstChar === '?' || $firstChar === '#') {
            return $homeUrl . '/' . $url;
        }

        return $homeUrl . '/' . $url;
    }

    private static function getHomeUrlForNormalization(): ?string
    {
        if (!function_exists('home_url')) {
            return null;
        }

        $homeUrl = home_url('/');
        if (!is_string($homeUrl)) {
            return null;
        }

        $homeUrl = trim($homeUrl);
        if ($homeUrl === '') {
            return null;
        }

        return $homeUrl;
    }

    private static function trimPath(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private static function isDefaultPortForScheme(int $port, string $scheme): bool
    {
        $scheme = strtolower($scheme);

        if ($scheme === 'http') {
            return $port === 80;
        }

        if ($scheme === 'https') {
            return $port === 443;
        }

        return false;
    }

    public function addBodyClasses(array $classes): array
    {
        $options = $this->settings->getOptions();
        if (empty($options['enable_sidebar'])) {
            return $classes;
        }

        $classes[] = 'jlg-sidebar-active';
        $classes[] = 'jlg-sidebar-position-' . $this->resolveSidebarPosition($options);
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
