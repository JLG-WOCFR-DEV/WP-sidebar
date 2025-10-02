<?php

namespace JLG\Sidebar\Admin;

use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\OptionChoices;
use JLG\Sidebar\Settings\ValueNormalizer;

class SettingsSanitizer
{
    private const NAV_MENU_ALLOWED_FILTERS = ['all', 'top-level', 'current-branch'];
    private const DIMENSION_UNITS = [
        'floating_vertical_margin' => ['px', 'rem', 'em', '%', 'vh', 'vw'],
        'horizontal_bar_height' => ['px', 'rem', 'em', 'vh', 'vw'],
        'border_radius' => ['px', 'rem', 'em', '%'],
        'content_margin' => ['px', 'rem', 'em', '%'],
        'hamburger_top_position' => ['px', 'rem', 'em', 'vh', 'vw'],
        'header_padding_top' => ['px', 'rem', 'em', '%'],
        'letter_spacing' => ['px', 'rem', 'em'],
    ];

    private DefaultSettings $defaults;
    private IconLibrary $icons;

    /**
     * @var array<string, string[]>
     */
    private array $allowedChoices;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
        $this->allowedChoices = OptionChoices::getAll();
    }

    public function sanitize_settings($input): array
    {
        $defaults = $this->defaults->all();
        $existingOptions = get_option('sidebar_jlg_settings', $defaults);
        if (!is_array($existingOptions)) {
            if (function_exists('error_log')) {
                error_log('Sidebar JLG settings option was not an array. Resetting to defaults.');
            }
            $existingOptions = [];
        }
        $allowedKeys = array_fill_keys(array_keys($defaults), true);
        $existingOptions = array_intersect_key($existingOptions, $allowedKeys);
        $existingOptions = array_merge($defaults, $existingOptions);
        $preparedInput = is_array($input) ? $input : [];

        $sanitizedInput = array_merge(
            $this->sanitize_general_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_style_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_effects_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_menu_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_social_settings($preparedInput, $existingOptions) ?: []
        );

        $sanitizedInput = array_intersect_key($sanitizedInput, $allowedKeys);

        return array_merge($defaults, $sanitizedInput);
    }

    /**
     * @return array{host: string|null, allowed_path: string}
     */
    public function getSvgUrlRestrictions(): array
    {
        return $this->getSvgUrlValidationContext();
    }

    private function sanitize_general_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $defaults = $this->defaults->all();

        $sanitized['enable_sidebar'] = !empty($input['enable_sidebar']);
        $sanitized['layout_style'] = $this->sanitizeChoice(
            $input['layout_style'] ?? null,
            $this->allowedChoices['layout_style'],
            $existingOptions['layout_style'] ?? ($defaults['layout_style'] ?? ''),
            $defaults['layout_style'] ?? ''
        );
        $sanitized['sidebar_position'] = $this->sanitizeChoice(
            $input['sidebar_position'] ?? null,
            $this->allowedChoices['sidebar_position'],
            $existingOptions['sidebar_position'] ?? ($defaults['sidebar_position'] ?? ''),
            $defaults['sidebar_position'] ?? ''
        );
        $sanitized['horizontal_bar_height'] = $this->sanitizeDimension(
            $input,
            'horizontal_bar_height',
            $existingOptions,
            $defaults
        );
        $sanitized['horizontal_bar_alignment'] = $this->sanitizeChoice(
            $input['horizontal_bar_alignment'] ?? null,
            $this->allowedChoices['horizontal_bar_alignment'],
            $existingOptions['horizontal_bar_alignment'] ?? ($defaults['horizontal_bar_alignment'] ?? ''),
            $defaults['horizontal_bar_alignment'] ?? ''
        );
        $sanitized['horizontal_bar_position'] = $this->sanitizeChoice(
            $input['horizontal_bar_position'] ?? null,
            $this->allowedChoices['horizontal_bar_position'],
            $existingOptions['horizontal_bar_position'] ?? ($defaults['horizontal_bar_position'] ?? ''),
            $defaults['horizontal_bar_position'] ?? ''
        );
        $sanitized['horizontal_bar_sticky'] = !empty($input['horizontal_bar_sticky']);
        $sanitized['floating_vertical_margin'] = $this->sanitizeDimension(
            $input,
            'floating_vertical_margin',
            $existingOptions,
            $defaults
        );
        $sanitized['border_radius'] = $this->sanitizeDimension(
            $input,
            'border_radius',
            $existingOptions,
            $defaults
        );
        $sanitized['border_width'] = $this->sanitizeIntegerOption(
            $input,
            'border_width',
            $existingOptions,
            $defaults
        );
        $existingBorderColor = array_key_exists('border_color', $existingOptions)
            ? $existingOptions['border_color']
            : ($defaults['border_color'] ?? '');
        $sanitized['border_color'] = ValueNormalizer::normalizeColorWithExisting(
            $input['border_color'] ?? null,
            $existingBorderColor
        );
        $sanitized['desktop_behavior'] = $this->sanitizeChoice(
            $input['desktop_behavior'] ?? null,
            $this->allowedChoices['desktop_behavior'],
            $existingOptions['desktop_behavior'] ?? ($defaults['desktop_behavior'] ?? ''),
            $defaults['desktop_behavior'] ?? ''
        );
        $sanitized['overlay_color'] = ValueNormalizer::normalizeColorWithExisting(
            $input['overlay_color'] ?? null,
            $existingOptions['overlay_color'] ?? ''
        );
        $existingOverlayOpacity = isset($existingOptions['overlay_opacity'])
            ? (float) $existingOptions['overlay_opacity']
            : 0.0;
        $sanitized['overlay_opacity'] = is_numeric($input['overlay_opacity'] ?? null)
            ? max(0.0, min(1.0, (float) $input['overlay_opacity']))
            : max(0.0, min(1.0, $existingOverlayOpacity));
        $sanitized['content_margin'] = $this->sanitizeDimension(
            $input,
            'content_margin',
            $existingOptions,
            $defaults
        );
        $sanitized['width_desktop'] = $this->sanitizeIntegerOption(
            $input,
            'width_desktop',
            $existingOptions,
            $defaults
        );
        $sanitized['width_tablet'] = $this->sanitizeIntegerOption(
            $input,
            'width_tablet',
            $existingOptions,
            $defaults
        );
        $sanitized['enable_search'] = !empty($input['enable_search']);
        $sanitized['search_method'] = $this->sanitizeChoice(
            $input['search_method'] ?? null,
            $this->allowedChoices['search_method'],
            $existingOptions['search_method'] ?? ($defaults['search_method'] ?? ''),
            $defaults['search_method'] ?? ''
        );
        $sanitized['search_shortcode'] = sanitize_text_field($input['search_shortcode'] ?? $existingOptions['search_shortcode']);
        $sanitized['search_alignment'] = $this->sanitizeChoice(
            $input['search_alignment'] ?? null,
            $this->allowedChoices['search_alignment'],
            $existingOptions['search_alignment'] ?? ($defaults['search_alignment'] ?? ''),
            $defaults['search_alignment'] ?? ''
        );
        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        $sanitized['show_close_button'] = !empty($input['show_close_button']);
        // Checkbox -> boolean conversion for the automatic closing behaviour.
        $sanitized['close_on_link_click'] = !empty($input['close_on_link_click']);
        $sanitized['hamburger_top_position'] = $this->sanitizeDimension(
            $input,
            'hamburger_top_position',
            $existingOptions,
            $defaults
        );
        $sanitized['hamburger_color'] = ValueNormalizer::normalizeColorWithExisting(
            $input['hamburger_color'] ?? null,
            $existingOptions['hamburger_color'] ?? ''
        );
        $sanitized['app_name'] = sanitize_text_field($input['app_name'] ?? $existingOptions['app_name']);
        $sanitized['header_logo_type'] = $this->sanitizeChoice(
            $input['header_logo_type'] ?? null,
            $this->allowedChoices['header_logo_type'],
            $existingOptions['header_logo_type'] ?? ($defaults['header_logo_type'] ?? ''),
            $defaults['header_logo_type'] ?? ''
        );
        $sanitized['header_logo_image'] = esc_url_raw($input['header_logo_image'] ?? $existingOptions['header_logo_image']);
        $sanitized['header_logo_size'] = $this->sanitizeIntegerOption(
            $input,
            'header_logo_size',
            $existingOptions,
            $defaults
        );
        $sanitized['header_alignment_desktop'] = $this->sanitizeChoice(
            $input['header_alignment_desktop'] ?? null,
            $this->allowedChoices['header_alignment_desktop'],
            $existingOptions['header_alignment_desktop'] ?? ($defaults['header_alignment_desktop'] ?? ''),
            $defaults['header_alignment_desktop'] ?? ''
        );
        $sanitized['header_alignment_mobile'] = $this->sanitizeChoice(
            $input['header_alignment_mobile'] ?? null,
            $this->allowedChoices['header_alignment_mobile'],
            $existingOptions['header_alignment_mobile'] ?? ($defaults['header_alignment_mobile'] ?? ''),
            $defaults['header_alignment_mobile'] ?? ''
        );
        $sanitized['header_padding_top'] = $this->sanitizeDimension(
            $input,
            'header_padding_top',
            $existingOptions,
            $defaults
        );

        return $sanitized;
    }

    private function sanitize_style_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $defaults = $this->defaults->all();

        $sanitized['style_preset'] = $this->sanitizeChoice(
            $input['style_preset'] ?? null,
            $this->allowedChoices['style_preset'],
            $existingOptions['style_preset'] ?? ($defaults['style_preset'] ?? ''),
            $defaults['style_preset'] ?? ''
        );

        $sanitized['bg_color_type'] = $this->sanitizeChoice(
            $input['bg_color_type'] ?? null,
            $this->allowedChoices['bg_color_type'],
            $existingOptions['bg_color_type'] ?? ($defaults['bg_color_type'] ?? ''),
            $defaults['bg_color_type'] ?? ''
        );
        $sanitized['bg_color'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color'] ?? null, $existingOptions['bg_color']);
        $sanitized['bg_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color_start'] ?? null, $existingOptions['bg_color_start']);
        $sanitized['bg_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color_end'] ?? null, $existingOptions['bg_color_end']);

        $sanitized['accent_color_type'] = $this->sanitizeChoice(
            $input['accent_color_type'] ?? null,
            $this->allowedChoices['accent_color_type'],
            $existingOptions['accent_color_type'] ?? ($defaults['accent_color_type'] ?? ''),
            $defaults['accent_color_type'] ?? ''
        );
        $sanitized['accent_color'] = ValueNormalizer::normalizeColorWithExisting($input['accent_color'] ?? null, $existingOptions['accent_color']);
        $sanitized['accent_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['accent_color_start'] ?? null, $existingOptions['accent_color_start']);
        $sanitized['accent_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['accent_color_end'] ?? null, $existingOptions['accent_color_end']);

        $sanitized['font_size'] = $this->sanitizeIntegerOption(
            $input,
            'font_size',
            $existingOptions,
            $defaults
        );
        $sanitized['font_family'] = $this->sanitizeChoice(
            $input['font_family'] ?? null,
            $this->allowedChoices['font_family'],
            $existingOptions['font_family'] ?? ($defaults['font_family'] ?? ''),
            $defaults['font_family'] ?? ''
        );
        $sanitized['font_weight'] = $this->sanitizeChoice(
            $input['font_weight'] ?? null,
            $this->allowedChoices['font_weight'],
            $existingOptions['font_weight'] ?? ($defaults['font_weight'] ?? ''),
            $defaults['font_weight'] ?? ''
        );
        $sanitized['text_transform'] = $this->sanitizeChoice(
            $input['text_transform'] ?? null,
            $this->allowedChoices['text_transform'],
            $existingOptions['text_transform'] ?? ($defaults['text_transform'] ?? ''),
            $defaults['text_transform'] ?? ''
        );
        $sanitized['letter_spacing'] = $this->sanitizeDimension(
            $input,
            'letter_spacing',
            $existingOptions,
            $defaults
        );
        $sanitized['font_color_type'] = $this->sanitizeChoice(
            $input['font_color_type'] ?? null,
            $this->allowedChoices['font_color_type'],
            $existingOptions['font_color_type'] ?? ($defaults['font_color_type'] ?? ''),
            $defaults['font_color_type'] ?? ''
        );
        $sanitized['font_color'] = ValueNormalizer::normalizeColorWithExisting($input['font_color'] ?? null, $existingOptions['font_color']);
        $sanitized['font_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['font_color_start'] ?? null, $existingOptions['font_color_start']);
        $sanitized['font_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['font_color_end'] ?? null, $existingOptions['font_color_end']);

        $sanitized['font_hover_color_type'] = $this->sanitizeChoice(
            $input['font_hover_color_type'] ?? null,
            $this->allowedChoices['font_hover_color_type'],
            $existingOptions['font_hover_color_type'] ?? ($defaults['font_hover_color_type'] ?? ''),
            $defaults['font_hover_color_type'] ?? ''
        );
        $sanitized['font_hover_color'] = ValueNormalizer::normalizeColorWithExisting($input['font_hover_color'] ?? null, $existingOptions['font_hover_color']);
        $sanitized['font_hover_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['font_hover_color_start'] ?? null, $existingOptions['font_hover_color_start']);
        $sanitized['font_hover_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['font_hover_color_end'] ?? null, $existingOptions['font_hover_color_end']);

        $sanitized['mobile_bg_color'] = ValueNormalizer::normalizeColorWithExisting($input['mobile_bg_color'] ?? null, $existingOptions['mobile_bg_color']);
        $existingOpacity = isset($existingOptions['mobile_bg_opacity']) ? (float) $existingOptions['mobile_bg_opacity'] : 0.0;
        $sanitized['mobile_bg_opacity'] = is_numeric($input['mobile_bg_opacity'] ?? null)
            ? max(0.0, min(1.0, (float) $input['mobile_bg_opacity']))
            : max(0.0, min(1.0, $existingOpacity));
        $sanitized['mobile_blur'] = $this->sanitizeIntegerOption(
            $input,
            'mobile_blur',
            $existingOptions,
            $defaults
        );

        return $sanitized;
    }

    private function sanitize_effects_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $defaults = $this->defaults->all();

        $sanitized['hover_effect_desktop'] = $this->sanitizeChoice(
            $input['hover_effect_desktop'] ?? null,
            $this->allowedChoices['hover_effect_desktop'],
            $existingOptions['hover_effect_desktop'] ?? ($defaults['hover_effect_desktop'] ?? ''),
            $defaults['hover_effect_desktop'] ?? ''
        );
        $sanitized['hover_effect_mobile'] = $this->sanitizeChoice(
            $input['hover_effect_mobile'] ?? null,
            $this->allowedChoices['hover_effect_mobile'],
            $existingOptions['hover_effect_mobile'] ?? ($defaults['hover_effect_mobile'] ?? ''),
            $defaults['hover_effect_mobile'] ?? ''
        );
        $sanitized['animation_speed'] = $this->sanitizeIntegerOption(
            $input,
            'animation_speed',
            $existingOptions,
            $defaults
        );
        $sanitized['animation_type'] = $this->sanitizeChoice(
            $input['animation_type'] ?? null,
            $this->allowedChoices['animation_type'],
            $existingOptions['animation_type'] ?? ($defaults['animation_type'] ?? ''),
            $defaults['animation_type'] ?? ''
        );
        $sanitized['neon_blur'] = $this->sanitizeIntegerOption(
            $input,
            'neon_blur',
            $existingOptions,
            $defaults
        );
        $sanitized['neon_spread'] = $this->sanitizeIntegerOption(
            $input,
            'neon_spread',
            $existingOptions,
            $defaults
        );

        return $sanitized;
    }

    private function sanitize_menu_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $availableIcons = $this->icons->getAllIcons();
        $defaults = $this->defaults->all();

        $sanitized['menu_alignment_desktop'] = $this->sanitizeChoice(
            $input['menu_alignment_desktop'] ?? null,
            $this->allowedChoices['menu_alignment_desktop'],
            $existingOptions['menu_alignment_desktop'] ?? ($defaults['menu_alignment_desktop'] ?? ''),
            $defaults['menu_alignment_desktop'] ?? ''
        );
        $sanitized['menu_alignment_mobile'] = $this->sanitizeChoice(
            $input['menu_alignment_mobile'] ?? null,
            $this->allowedChoices['menu_alignment_mobile'],
            $existingOptions['menu_alignment_mobile'] ?? ($defaults['menu_alignment_mobile'] ?? ''),
            $defaults['menu_alignment_mobile'] ?? ''
        );

        $sanitizedMenuItems = [];
        $svgUrlContext = $this->getSvgUrlValidationContext();
        $existingMenuItems = [];
        if (isset($existingOptions['menu_items']) && is_array($existingOptions['menu_items'])) {
            $existingMenuItems = $existingOptions['menu_items'];
        }

        if (isset($input['menu_items']) && is_array($input['menu_items'])) {
            foreach ($input['menu_items'] as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $allowedItemTypes = ['custom', 'post', 'page', 'category', 'nav_menu'];
                $itemType = sanitize_key($item['type'] ?? '');
                if (!in_array($itemType, $allowedItemTypes, true)) {
                    $itemType = 'custom';
                }
                $iconType = sanitize_key($item['icon_type'] ?? '');
                $iconType = ($iconType === 'svg_url') ? 'svg_url' : 'svg_inline';

                $sanitizedItem = [
                    'label' => sanitize_text_field($item['label'] ?? ''),
                    'type' => $itemType,
                    'icon_type' => $iconType,
                ];

                if ($iconType === 'svg_url') {
                    $rawIconUrl = isset($item['icon']) ? (string) $item['icon'] : '';
                    $sanitizedUrl = esc_url_raw($rawIconUrl);

                    if ($sanitizedUrl !== '' && $this->isSvgUrlAllowed($sanitizedUrl, $svgUrlContext)) {
                        $sanitizedItem['icon'] = $sanitizedUrl;
                    } else {
                        if ($sanitizedUrl !== '') {
                            $this->icons->recordRejectedCustomIcon(
                                $sanitizedUrl,
                                'external_svg_url',
                                ['source' => 'menu_item']
                            );
                        }

                        $sanitizedItem['icon'] = '';
                        $sanitizedItem['icon_type'] = 'svg_inline';
                    }
                } else {
                    $iconKey = sanitize_key($item['icon'] ?? '');
                    if ($iconKey !== '' && isset($availableIcons[$iconKey])) {
                        $sanitizedItem['icon'] = $iconKey;
                    } else {
                        $sanitizedItem['icon'] = '';
                        $sanitizedItem['icon_type'] = 'svg_inline';
                    }
                }

                $existingItem = [];
                if (isset($existingMenuItems[$index]) && is_array($existingMenuItems[$index])) {
                    $existingItem = $existingMenuItems[$index];
                }

                switch ($itemType) {
                    case 'custom':
                        $rawValue = array_key_exists('value', $item) ? $item['value'] : ($existingItem['value'] ?? '');
                        $sanitizedItem['value'] = esc_url_raw($rawValue);
                        break;
                    case 'nav_menu':
                        $rawMenuValue = array_key_exists('value', $item) ? $item['value'] : ($existingItem['value'] ?? 0);
                        $menuId = absint($rawMenuValue);
                        if ($menuId > 0 && function_exists('wp_get_nav_menu_object')) {
                            $menuObject = wp_get_nav_menu_object($menuId);
                            if (!$menuObject) {
                                $menuId = 0;
                            }
                        }

                        $sanitizedItem['value'] = $menuId;
                        $depthSource = array_key_exists('nav_menu_max_depth', $item)
                            ? $item['nav_menu_max_depth']
                            : ($existingItem['nav_menu_max_depth'] ?? null);
                        $filterSource = array_key_exists('nav_menu_filter', $item)
                            ? $item['nav_menu_filter']
                            : ($existingItem['nav_menu_filter'] ?? null);

                        $sanitizedItem['nav_menu_max_depth'] = $this->sanitizeNavMenuDepth($depthSource);
                        $sanitizedItem['nav_menu_filter'] = $this->sanitizeNavMenuFilter($filterSource);
                        break;
                    case 'post':
                    case 'page':
                    case 'category':
                    default:
                        $rawTarget = array_key_exists('value', $item) ? $item['value'] : ($existingItem['value'] ?? 0);
                        $sanitizedItem['value'] = absint($rawTarget);
                        break;
                }

                $sanitizedMenuItems[] = $sanitizedItem;
            }
        }

        $sanitized['menu_items'] = $sanitizedMenuItems;

        return $sanitized;
    }

    public static function getAllowedNavMenuFilters(): array
    {
        return self::NAV_MENU_ALLOWED_FILTERS;
    }

    /**
     * @param mixed $value
     */
    private function sanitizeNavMenuDepth($value): int
    {
        if (!is_scalar($value)) {
            return 0;
        }

        $depth = absint($value);

        return $depth > 0 ? $depth : 0;
    }

    /**
     * @param mixed $value
     */
    private function sanitizeNavMenuFilter($value): string
    {
        if (is_string($value)) {
            $normalized = sanitize_key($value);
            if (in_array($normalized, self::NAV_MENU_ALLOWED_FILTERS, true)) {
                return $normalized;
            }
        }

        return self::NAV_MENU_ALLOWED_FILTERS[0];
    }

    /**
     * @return array{host: string|null, allowed_path: string}
     */
    private function getSvgUrlValidationContext(): array
    {
        $uploads = wp_upload_dir();
        $baseUrl = '';
        $allowedHost = null;
        $allowedPath = '/wp-content/uploads/sidebar-jlg/';

        if (is_array($uploads)) {
            if (!empty($uploads['baseurl']) && is_string($uploads['baseurl'])) {
                $baseUrl = trailingslashit($uploads['baseurl']);
            }
        }

        if ($baseUrl !== '') {
            $baseParts = wp_parse_url($baseUrl);
            if (is_array($baseParts)) {
                if (!empty($baseParts['host'])) {
                    $allowedHost = strtolower((string) $baseParts['host']);
                }

                if (!empty($baseParts['path'])) {
                    $normalizedBasePath = wp_normalize_path((string) $baseParts['path']);
                    if ($normalizedBasePath !== '') {
                        $allowedPath = wp_normalize_path(trailingslashit($normalizedBasePath) . 'sidebar-jlg/');
                    }
                }
            }
        }

        if ($allowedHost === null) {
            $siteUrl = get_option('siteurl');
            if (is_string($siteUrl) && $siteUrl !== '') {
                $siteParts = wp_parse_url($siteUrl);
                if (is_array($siteParts) && !empty($siteParts['host'])) {
                    $allowedHost = strtolower((string) $siteParts['host']);
                }
            }
        }

        $allowedPath = wp_normalize_path($allowedPath);
        if ($allowedPath === '') {
            $allowedPath = '/wp-content/uploads/sidebar-jlg/';
        }

        if ($allowedPath[0] !== '/') {
            $allowedPath = '/' . ltrim($allowedPath, '/');
        }

        $allowedPath = rtrim($allowedPath, '/') . '/';

        return [
            'host' => $allowedHost,
            'allowed_path' => $allowedPath,
        ];
    }

    /**
     * @param array{host: string|null, allowed_path: string} $context
     */
    private function isSvgUrlAllowed(string $url, array $context): bool
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['path'])) {
            return false;
        }

        $rawPath = (string) $parts['path'];
        $decodedPath = rawurldecode($rawPath);
        if ($decodedPath === '') {
            return false;
        }

        if (preg_match('#(^|[\\/])\.\.(?=[\\/]|$)#', $decodedPath)) {
            return false;
        }

        $path = wp_normalize_path($decodedPath);
        if ($path === '') {
            return false;
        }

        if ($path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        $allowedPath = wp_normalize_path($context['allowed_path']);
        if ($allowedPath === '') {
            return false;
        }

        $allowedPath = rtrim($allowedPath, '/') . '/';

        if (strpos($path, $allowedPath) !== 0) {
            return false;
        }

        if (!empty($parts['host'])) {
            if ($context['host'] === null) {
                return false;
            }

            $host = strtolower((string) $parts['host']);
            if (strcasecmp($host, (string) $context['host']) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function sanitize_social_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $availableIcons = $this->icons->getAllIcons();
        $defaults = $this->defaults->all();

        $sanitizedSocialIcons = [];
        if (isset($input['social_icons']) && is_array($input['social_icons'])) {
            foreach ($input['social_icons'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $url = esc_url_raw($item['url'] ?? '');
                $icon = sanitize_key($item['icon'] ?? '');

                if ($url === '' || $icon === '' || !isset($availableIcons[$icon])) {
                    continue;
                }

                $label = '';
                if (isset($item['label'])) {
                    $rawLabel = $item['label'];
                    if (is_scalar($rawLabel)) {
                        $label = sanitize_text_field((string) $rawLabel);
                    }
                }

                $sanitizedSocialIcons[] = [
                    'url' => $url,
                    'icon' => $icon,
                    'label' => $label,
                ];
            }
        }

        $sanitized['social_icons'] = $sanitizedSocialIcons;
        $sanitized['social_orientation'] = $this->sanitizeChoice(
            $input['social_orientation'] ?? null,
            $this->allowedChoices['social_orientation'],
            $existingOptions['social_orientation'] ?? ($defaults['social_orientation'] ?? ''),
            $defaults['social_orientation'] ?? ''
        );
        $sanitized['social_position'] = $this->sanitizeChoice(
            $input['social_position'] ?? null,
            $this->allowedChoices['social_position'],
            $existingOptions['social_position'] ?? ($defaults['social_position'] ?? ''),
            $defaults['social_position'] ?? ''
        );
        $sanitized['social_icon_size'] = $this->sanitizeIntegerOption(
            $input,
            'social_icon_size',
            $existingOptions,
            $defaults
        );

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existingOptions
     * @param array<string, mixed> $defaults
     */
    private function sanitizeDimension(array $input, string $key, array $existingOptions, array $defaults): array
    {
        $allowedUnits = self::DIMENSION_UNITS[$key] ?? null;
        $existing = $existingOptions[$key] ?? ($defaults[$key] ?? []);
        $default = $defaults[$key] ?? [];

        return ValueNormalizer::normalizeDimensionStructure(
            $input[$key] ?? null,
            $existing,
            $default,
            $allowedUnits
        );
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existingOptions
     * @param array<string, mixed> $defaults
     */
    private function sanitizeIntegerOption(array $input, string $key, array $existingOptions, array $defaults): int
    {
        $existing = $existingOptions[$key] ?? ($defaults[$key] ?? 0);

        if (array_key_exists($key, $input)) {
            $raw = $input[$key];

            if (!is_scalar($raw)) {
                return absint($existing);
            }

            if (is_string($raw)) {
                $raw = trim($raw);
            }

            if ($raw === '' || !is_numeric($raw)) {
                return absint($existing);
            }

            return absint($raw);
        }

        return absint($existing);
    }

    private function sanitizeChoice($rawValue, array $allowed, $existingValue, $defaultValue): string
    {
        return OptionChoices::resolveChoice($rawValue, $allowed, $existingValue, $defaultValue);
    }

}
