<?php

namespace JLG\Sidebar\Admin;

use JLG\Sidebar\Accessibility\Checklist;
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
        'hamburger_horizontal_offset' => ['px', 'rem', 'em', '%', 'vh', 'vw'],
        'hamburger_size' => ['px', 'rem', 'em', '%', 'vh', 'vw'],
        'header_padding_top' => ['px', 'rem', 'em', '%'],
        'letter_spacing' => ['px', 'rem', 'em'],
    ];

    private const PROFILE_BOOLEAN_KEYS = [
        'enabled',
        'is_enabled',
        'active',
        'is_active',
        'default',
        'is_default',
    ];

    private const PROFILE_INTEGER_KEYS = [
        'order',
        'priority',
        'position',
        'menu_id',
        'weight',
        'sequence',
    ];

    private const PROFILE_SLUG_KEYS = [
        'slug',
        'key',
    ];

    private const PROFILE_TEXTAREA_KEYS = [
        'description',
        'notes',
        'summary',
    ];

    private DefaultSettings $defaults;
    private IconLibrary $icons;

    /**
     * @var array<string, string[]>|null
     */
    private ?array $allowedChoices = null;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
    }

    /**
     * @return array<string, string[]>
     */
    private function getAllowedChoices(): array
    {
        if ($this->allowedChoices === null) {
            $this->allowedChoices = OptionChoices::getAll();
        }

        return $this->allowedChoices;
    }

    /**
     * @param mixed $value
     */
    public function sanitize_accessibility_checklist($value): array
    {
        $input = is_array($value) ? $value : [];
        $sanitized = [];

        foreach (Checklist::getItems() as $item) {
            $id = $item['id'] ?? '';
            if (!is_string($id) || $id === '') {
                continue;
            }

            $sanitized[$id] = !empty($input[$id]);
        }

        return $sanitized;
    }

    public function sanitize_settings($input, ?array $existingOptionsOverride = null): array
    {
        $defaults = $this->defaults->all();
        $existingOptions = $existingOptionsOverride ?? get_option('sidebar_jlg_settings', $defaults);
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

    /**
     * @param mixed $profiles
     */
    public function sanitize_profiles($profiles, string $optionName = ''): array
    {
        return $this->sanitize_profiles_collection($profiles);
    }

    /**
     * @param mixed $profiles
     */
    public function sanitize_profiles_collection($profiles, ?array $existingProfiles = null): array
    {
        if ($existingProfiles === null) {
            $storedProfiles = get_option('sidebar_jlg_profiles', []);
            $existingProfiles = is_array($storedProfiles) ? $storedProfiles : [];
        }

        if (!is_array($profiles)) {
            return [];
        }

        return $this->sanitizeProfileCollection($profiles, $existingProfiles);
    }

    /**
     * @param mixed $value
     */
    public function sanitize_active_profile($value, string $optionName = '', ?array $profiles = null): string
    {
        $profileId = $this->extractProfileId($value);

        if ($profileId === '') {
            return '';
        }

        $availableProfiles = $profiles;
        if ($availableProfiles === null) {
            $storedProfiles = get_option('sidebar_jlg_profiles', []);
            $availableProfiles = is_array($storedProfiles)
                ? $this->sanitizeProfileCollection($storedProfiles, [])
                : [];
        }

        foreach ($availableProfiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            if (($profile['id'] ?? '') === $profileId) {
                return $profileId;
            }
        }

        return '';
    }

    private function sanitize_general_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $defaults = $this->defaults->all();

        $sanitized['enable_sidebar'] = !empty($input['enable_sidebar']);
        $sanitized['enable_analytics'] = !empty($input['enable_analytics']);
        $sanitized['layout_style'] = $this->sanitizeChoice(
            $input['layout_style'] ?? null,
            $this->getAllowedChoices()['layout_style'],
            $existingOptions['layout_style'] ?? ($defaults['layout_style'] ?? ''),
            $defaults['layout_style'] ?? ''
        );
        $sanitized['sidebar_position'] = $this->sanitizeChoice(
            $input['sidebar_position'] ?? null,
            $this->getAllowedChoices()['sidebar_position'],
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
            $this->getAllowedChoices()['horizontal_bar_alignment'],
            $existingOptions['horizontal_bar_alignment'] ?? ($defaults['horizontal_bar_alignment'] ?? ''),
            $defaults['horizontal_bar_alignment'] ?? ''
        );
        $sanitized['horizontal_bar_position'] = $this->sanitizeChoice(
            $input['horizontal_bar_position'] ?? null,
            $this->getAllowedChoices()['horizontal_bar_position'],
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
            $this->getAllowedChoices()['desktop_behavior'],
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
        $sanitized['width_mobile'] = $this->sanitizeCssDimensionOption(
            $input,
            'width_mobile',
            $existingOptions,
            $defaults
        );
        $sanitized['enable_search'] = !empty($input['enable_search']);
        $sanitized['search_method'] = $this->sanitizeChoice(
            $input['search_method'] ?? null,
            $this->getAllowedChoices()['search_method'],
            $existingOptions['search_method'] ?? ($defaults['search_method'] ?? ''),
            $defaults['search_method'] ?? ''
        );
        $sanitized['search_shortcode'] = sanitize_text_field($input['search_shortcode'] ?? $existingOptions['search_shortcode']);
        $sanitized['search_alignment'] = $this->sanitizeChoice(
            $input['search_alignment'] ?? null,
            $this->getAllowedChoices()['search_alignment'],
            $existingOptions['search_alignment'] ?? ($defaults['search_alignment'] ?? ''),
            $defaults['search_alignment'] ?? ''
        );
        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        $sanitized['show_close_button'] = !empty($input['show_close_button']);
        // Checkbox -> boolean conversion for the automatic closing behaviour.
        $sanitized['close_on_link_click'] = !empty($input['close_on_link_click']);
        // Checkbox for remembering sidebar state across navigations.
        $sanitized['remember_last_state'] = !empty($input['remember_last_state']);
        // Touch gestures inspired by premium off-canvas editors.
        $sanitized['touch_gestures_edge_swipe'] = !empty($input['touch_gestures_edge_swipe']);
        $sanitized['touch_gestures_close_swipe'] = !empty($input['touch_gestures_close_swipe']);
        $edgeSize = $this->sanitizeIntegerOption($input, 'touch_gestures_edge_size', $existingOptions, $defaults);
        $sanitized['touch_gestures_edge_size'] = max(0, min(200, $edgeSize));
        $minDistance = $this->sanitizeIntegerOption($input, 'touch_gestures_min_distance', $existingOptions, $defaults);
        $sanitized['touch_gestures_min_distance'] = max(30, min(600, $minDistance));
        $timeDelay = $this->sanitizeIntegerOption($input, 'auto_open_time_delay', $existingOptions, $defaults);
        $sanitized['auto_open_time_delay'] = max(0, min(600, $timeDelay));
        $scrollDepth = $this->sanitizeIntegerOption($input, 'auto_open_scroll_depth', $existingOptions, $defaults);
        $sanitized['auto_open_scroll_depth'] = max(0, min(100, $scrollDepth));
        $sanitized['nav_aria_label'] = sanitize_text_field($input['nav_aria_label'] ?? $existingOptions['nav_aria_label']);
        $sanitized['toggle_open_label'] = sanitize_text_field($input['toggle_open_label'] ?? $existingOptions['toggle_open_label']);
        $sanitized['toggle_close_label'] = sanitize_text_field($input['toggle_close_label'] ?? $existingOptions['toggle_close_label']);
        $sanitized['hamburger_top_position'] = $this->sanitizeDimension(
            $input,
            'hamburger_top_position',
            $existingOptions,
            $defaults
        );
        $sanitized['hamburger_horizontal_offset'] = $this->sanitizeDimension(
            $input,
            'hamburger_horizontal_offset',
            $existingOptions,
            $defaults
        );
        $sanitized['hamburger_size'] = $this->sanitizeDimension(
            $input,
            'hamburger_size',
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
            $this->getAllowedChoices()['header_logo_type'],
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
            $this->getAllowedChoices()['header_alignment_desktop'],
            $existingOptions['header_alignment_desktop'] ?? ($defaults['header_alignment_desktop'] ?? ''),
            $defaults['header_alignment_desktop'] ?? ''
        );
        $sanitized['header_alignment_mobile'] = $this->sanitizeChoice(
            $input['header_alignment_mobile'] ?? null,
            $this->getAllowedChoices()['header_alignment_mobile'],
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
            $this->getAllowedChoices()['style_preset'],
            $existingOptions['style_preset'] ?? ($defaults['style_preset'] ?? ''),
            $defaults['style_preset'] ?? ''
        );

        $sanitized['bg_color_type'] = $this->sanitizeChoice(
            $input['bg_color_type'] ?? null,
            $this->getAllowedChoices()['bg_color_type'],
            $existingOptions['bg_color_type'] ?? ($defaults['bg_color_type'] ?? ''),
            $defaults['bg_color_type'] ?? ''
        );
        $sanitized['bg_color'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color'] ?? null, $existingOptions['bg_color']);
        $sanitized['bg_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color_start'] ?? null, $existingOptions['bg_color_start']);
        $sanitized['bg_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color_end'] ?? null, $existingOptions['bg_color_end']);

        $sanitized['accent_color_type'] = $this->sanitizeChoice(
            $input['accent_color_type'] ?? null,
            $this->getAllowedChoices()['accent_color_type'],
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
            $this->getAllowedChoices()['font_family'],
            $existingOptions['font_family'] ?? ($defaults['font_family'] ?? ''),
            $defaults['font_family'] ?? ''
        );
        $sanitized['font_weight'] = $this->sanitizeChoice(
            $input['font_weight'] ?? null,
            $this->getAllowedChoices()['font_weight'],
            $existingOptions['font_weight'] ?? ($defaults['font_weight'] ?? ''),
            $defaults['font_weight'] ?? ''
        );
        $sanitized['text_transform'] = $this->sanitizeChoice(
            $input['text_transform'] ?? null,
            $this->getAllowedChoices()['text_transform'],
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
            $this->getAllowedChoices()['font_color_type'],
            $existingOptions['font_color_type'] ?? ($defaults['font_color_type'] ?? ''),
            $defaults['font_color_type'] ?? ''
        );
        $sanitized['font_color'] = ValueNormalizer::normalizeColorWithExisting($input['font_color'] ?? null, $existingOptions['font_color']);
        $sanitized['font_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['font_color_start'] ?? null, $existingOptions['font_color_start']);
        $sanitized['font_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['font_color_end'] ?? null, $existingOptions['font_color_end']);

        $sanitized['font_hover_color_type'] = $this->sanitizeChoice(
            $input['font_hover_color_type'] ?? null,
            $this->getAllowedChoices()['font_hover_color_type'],
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
            $this->getAllowedChoices()['hover_effect_desktop'],
            $existingOptions['hover_effect_desktop'] ?? ($defaults['hover_effect_desktop'] ?? ''),
            $defaults['hover_effect_desktop'] ?? ''
        );
        $sanitized['hover_effect_mobile'] = $this->sanitizeChoice(
            $input['hover_effect_mobile'] ?? null,
            $this->getAllowedChoices()['hover_effect_mobile'],
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
            $this->getAllowedChoices()['animation_type'],
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
            $this->getAllowedChoices()['menu_alignment_desktop'],
            $existingOptions['menu_alignment_desktop'] ?? ($defaults['menu_alignment_desktop'] ?? ''),
            $defaults['menu_alignment_desktop'] ?? ''
        );
        $sanitized['menu_alignment_mobile'] = $this->sanitizeChoice(
            $input['menu_alignment_mobile'] ?? null,
            $this->getAllowedChoices()['menu_alignment_mobile'],
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

                $existingItem = [];
                if (isset($existingMenuItems[$index]) && is_array($existingMenuItems[$index])) {
                    $existingItem = $existingMenuItems[$index];
                }

                $allowedItemTypes = ['custom', 'post', 'page', 'category', 'nav_menu', 'cta'];
                $rawItemType = $item['type'] ?? ($existingItem['type'] ?? '');
                $itemType = sanitize_key($rawItemType);
                if ($itemType === '' && isset($existingItem['type'])) {
                    $itemType = sanitize_key($existingItem['type']);
                }
                $effectiveItemType = in_array($itemType, $allowedItemTypes, true)
                    ? $itemType
                    : 'custom';
                $iconType = sanitize_key($item['icon_type'] ?? '');
                $iconType = ($iconType === 'svg_url') ? 'svg_url' : 'svg_inline';

                $sanitizedItem = [
                    'label' => sanitize_text_field($item['label'] ?? ''),
                    'type' => $itemType !== '' ? $itemType : $effectiveItemType,
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

                switch ($effectiveItemType) {
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
                    case 'cta':
                        $ctaTitle = array_key_exists('cta_title', $item)
                            ? $item['cta_title']
                            : ($existingItem['cta_title'] ?? '');
                        $ctaDescription = array_key_exists('cta_description', $item)
                            ? $item['cta_description']
                            : ($existingItem['cta_description'] ?? '');
                        $ctaShortcode = array_key_exists('cta_shortcode', $item)
                            ? $item['cta_shortcode']
                            : ($existingItem['cta_shortcode'] ?? '');
                        $ctaButtonLabel = array_key_exists('cta_button_label', $item)
                            ? $item['cta_button_label']
                            : ($existingItem['cta_button_label'] ?? '');
                        $ctaButtonUrl = array_key_exists('cta_button_url', $item)
                            ? $item['cta_button_url']
                            : ($existingItem['cta_button_url'] ?? '');

                        $sanitizedItem['cta_title'] = sanitize_text_field($ctaTitle);
                        $sanitizedItem['cta_description'] = wp_kses_post($ctaDescription);
                        $sanitizedItem['cta_shortcode'] = wp_kses_post($ctaShortcode);
                        $sanitizedItem['cta_button_label'] = sanitize_text_field($ctaButtonLabel);
                        $sanitizedItem['cta_button_url'] = esc_url_raw($ctaButtonUrl);
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

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || !is_numeric($value)) {
            return 0;
        }

        $numeric = (int) $value;

        if ($numeric <= 0) {
            return 0;
        }

        return absint($numeric);
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
            $this->getAllowedChoices()['social_orientation'],
            $existingOptions['social_orientation'] ?? ($defaults['social_orientation'] ?? ''),
            $defaults['social_orientation'] ?? ''
        );
        $sanitized['social_position'] = $this->sanitizeChoice(
            $input['social_position'] ?? null,
            $this->getAllowedChoices()['social_position'],
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

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existingOptions
     * @param array<string, mixed> $defaults
     */
    private function sanitizeCssDimensionOption(array $input, string $key, array $existingOptions, array $defaults): string
    {
        $existing = $existingOptions[$key] ?? ($defaults[$key] ?? '');
        $fallback = $existing !== '' ? $existing : ($defaults[$key] ?? '');

        return ValueNormalizer::normalizeCssDimension($input[$key] ?? null, $fallback);
    }

    /**
     * @param array<int, mixed> $profiles
     * @param array<int, mixed> $existingProfiles
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeProfileCollection(array $profiles, array $existingProfiles): array
    {
        $indexedExisting = $this->indexProfilesById($existingProfiles);
        $sanitized = [];
        $positions = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $sanitizedProfile = $this->sanitizeSingleProfile($profile, $indexedExisting);

            if ($sanitizedProfile === null) {
                continue;
            }

            $id = $sanitizedProfile['id'];

            if (isset($positions[$id])) {
                $sanitized[$positions[$id]] = $sanitizedProfile;
                continue;
            }

            $sanitized[] = $sanitizedProfile;
            $positions[$id] = array_key_last($sanitized);
        }

        return array_values($sanitized);
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, array<string, mixed>> $existingProfiles
     */
    private function sanitizeSingleProfile(array $profile, array $existingProfiles): ?array
    {
        $profileId = $this->extractProfileId($profile);

        if ($profileId === '') {
            return null;
        }

        $existing = $existingProfiles[$profileId] ?? [];

        $sanitized = ['id' => $profileId];

        $ignoredKeys = ['id', 'settings', 'conditions'];

        foreach ($profile as $key => $value) {
            if (in_array($key, $ignoredKeys, true)) {
                continue;
            }

            if (in_array($key, self::PROFILE_BOOLEAN_KEYS, true)) {
                $sanitized[$key] = !empty($value);
                continue;
            }

            if (in_array($key, self::PROFILE_INTEGER_KEYS, true)) {
                $existingValue = isset($existing[$key]) && is_scalar($existing[$key])
                    ? (int) $existing[$key]
                    : 0;
                $sanitized[$key] = $this->sanitizeProfileInteger($value, $existingValue);
                continue;
            }

            if (in_array($key, self::PROFILE_SLUG_KEYS, true)) {
                $slug = $this->sanitizeProfileId($value);
                if ($slug === '' && isset($existing[$key])) {
                    $slug = $this->sanitizeProfileId($existing[$key]);
                }
                if ($slug !== '') {
                    $sanitized[$key] = $slug;
                }
                continue;
            }

            if (in_array($key, self::PROFILE_TEXTAREA_KEYS, true)) {
                $sanitized[$key] = sanitize_text_field((string) $value);
                continue;
            }

            if (is_scalar($value)) {
                $sanitized[$key] = sanitize_text_field((string) $value);
            }
        }

        foreach ($existing as $key => $existingValue) {
            if (isset($sanitized[$key]) || in_array($key, ['id', 'settings', 'conditions'], true)) {
                continue;
            }

            if (in_array($key, self::PROFILE_BOOLEAN_KEYS, true)) {
                $sanitized[$key] = !empty($existingValue);
            } elseif (in_array($key, self::PROFILE_INTEGER_KEYS, true)) {
                $sanitized[$key] = $this->sanitizeProfileInteger($existingValue, 0);
            } elseif (in_array($key, self::PROFILE_SLUG_KEYS, true)) {
                $slug = $this->sanitizeProfileId($existingValue);
                if ($slug !== '') {
                    $sanitized[$key] = $slug;
                }
            } elseif (in_array($key, self::PROFILE_TEXTAREA_KEYS, true)) {
                $sanitized[$key] = sanitize_text_field((string) $existingValue);
            } elseif (is_scalar($existingValue)) {
                $sanitized[$key] = sanitize_text_field((string) $existingValue);
            }
        }

        $existingConditions = isset($existing['conditions']) && is_array($existing['conditions'])
            ? $existing['conditions']
            : [];
        $rawConditions = isset($profile['conditions']) && is_array($profile['conditions'])
            ? $profile['conditions']
            : $existingConditions;

        $sanitized['conditions'] = $this->sanitizeProfileConditions(
            is_array($rawConditions) ? $rawConditions : [],
            $existingConditions
        );

        $existingSettings = isset($existing['settings']) && is_array($existing['settings'])
            ? $existing['settings']
            : null;

        if (isset($profile['settings']) && is_array($profile['settings'])) {
            $settingsSource = $profile['settings'];
        } elseif ($existingSettings !== null) {
            $settingsSource = $existingSettings;
        } else {
            $settingsSource = [];
        }

        $sanitized['settings'] = $this->sanitize_settings($settingsSource, $existingSettings);

        return $sanitized;
    }

    /**
     * @param array<int, mixed> $profiles
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexProfilesById(array $profiles): array
    {
        $indexed = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $id = $this->extractProfileId($profile);

            if ($id === '') {
                continue;
            }

            $indexed[$id] = $profile;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $existing
     *
     * @return array{
     *     content_types: array<int, string>,
     *     taxonomies: array<int, string>,
     *     roles: array<int, string>,
     *     languages: array<int, string>,
     *     devices: array<int, string>,
     *     logged_in: string,
     *     schedule: array{start: string, end: string, days: array<int, string>}
     * }
     */
    private function sanitizeProfileConditions(array $conditions, array $existing = []): array
    {
        $existing = is_array($existing) ? $existing : [];

        $contentTypes = $this->sanitizeConditionValues(
            $conditions['content_types'] ?? [],
            $this->getAllowedContentTypes(),
            isset($existing['content_types']) && is_array($existing['content_types']) ? $existing['content_types'] : []
        );

        $taxonomies = $this->sanitizeConditionValues(
            $conditions['taxonomies'] ?? [],
            $this->getAllowedTaxonomies(),
            isset($existing['taxonomies']) && is_array($existing['taxonomies']) ? $existing['taxonomies'] : []
        );

        $roles = $this->sanitizeConditionValues(
            $conditions['roles'] ?? [],
            $this->getAllowedRoles(),
            isset($existing['roles']) && is_array($existing['roles']) ? $existing['roles'] : []
        );

        $languages = $this->sanitizeConditionValues(
            $conditions['languages'] ?? [],
            $this->getAllowedLanguages(),
            isset($existing['languages']) && is_array($existing['languages']) ? $existing['languages'] : [],
            true
        );

        $devices = $this->sanitizeConditionValues(
            $conditions['devices'] ?? [],
            $this->getAllowedDevices(),
            isset($existing['devices']) && is_array($existing['devices']) ? $existing['devices'] : []
        );

        $loggedIn = $this->sanitizeLoggedInCondition(
            $conditions['logged_in'] ?? null,
            $existing['logged_in'] ?? null
        );

        $schedule = $this->sanitizeScheduleCondition(
            isset($conditions['schedule']) && is_array($conditions['schedule']) ? $conditions['schedule'] : [],
            isset($existing['schedule']) && is_array($existing['schedule']) ? $existing['schedule'] : []
        );

        return [
            'content_types' => $contentTypes,
            'taxonomies' => $taxonomies,
            'roles' => $roles,
            'languages' => $languages,
            'devices' => $devices,
            'logged_in' => $loggedIn,
            'schedule' => $schedule,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedDevices(): array
    {
        return ['desktop', 'mobile'];
    }

    /**
     * @param mixed $value
     * @param mixed $existing
     */
    private function sanitizeLoggedInCondition($value, $existing): string
    {
        $normalized = $this->normalizeLoggedInValue($value);

        if ($normalized !== '') {
            return $normalized;
        }

        $fallback = $this->normalizeLoggedInValue($existing);
        if ($fallback !== '') {
            return $fallback;
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function normalizeLoggedInValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'logged-in' : 'logged-out';
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0 ? 'logged-in' : 'logged-out';
        }

        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '' || $normalized === 'any') {
            return '';
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'logged-in', 'logged_in'], true)) {
            return 'logged-in';
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off', 'logged-out', 'logged_out'], true)) {
            return 'logged-out';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $existing
     *
     * @return array{start: string, end: string, days: array<int, string>}
     */
    private function sanitizeScheduleCondition(array $raw, array $existing): array
    {
        $sanitized = [
            'start' => $this->sanitizeScheduleTime($raw['start'] ?? ($raw['from'] ?? null)),
            'end' => $this->sanitizeScheduleTime($raw['end'] ?? ($raw['to'] ?? null)),
            'days' => $this->sanitizeScheduleDays($raw['days'] ?? []),
        ];

        if ($sanitized['start'] === '' && isset($existing['start'])) {
            $sanitized['start'] = $this->sanitizeScheduleTime($existing['start']);
        }

        if ($sanitized['end'] === '' && isset($existing['end'])) {
            $sanitized['end'] = $this->sanitizeScheduleTime($existing['end']);
        }

        if ($sanitized['days'] === [] && isset($existing['days'])) {
            $sanitized['days'] = $this->sanitizeScheduleDays($existing['days']);
        }

        return $sanitized;
    }

    /**
     * @param mixed $value
     */
    private function sanitizeScheduleTime($value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $stringValue, $matches) !== 1) {
            return '';
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return '';
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * @param mixed $value
     *
     * @return array<int, string>
     */
    private function sanitizeScheduleDays($value): array
    {
        $allowed = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $values = [];

        if (is_array($value)) {
            $values = $value;
        } elseif (is_string($value) || is_numeric($value)) {
            $values = preg_split('/[\s,]+/', (string) $value) ?: [];
        }

        $normalized = [];

        foreach ($values as $day) {
            if (!is_string($day) && !is_numeric($day)) {
                continue;
            }

            $candidate = strtolower(trim((string) $day));
            if ($candidate === '') {
                continue;
            }

            switch ($candidate) {
                case '1':
                case '01':
                case 'monday':
                case 'mon':
                    $candidate = 'mon';
                    break;
                case '2':
                case '02':
                case 'tuesday':
                case 'tue':
                    $candidate = 'tue';
                    break;
                case '3':
                case '03':
                case 'wednesday':
                case 'wed':
                    $candidate = 'wed';
                    break;
                case '4':
                case '04':
                case 'thursday':
                case 'thu':
                    $candidate = 'thu';
                    break;
                case '5':
                case '05':
                case 'friday':
                case 'fri':
                    $candidate = 'fri';
                    break;
                case '6':
                case '06':
                case 'saturday':
                case 'sat':
                    $candidate = 'sat';
                    break;
                case '0':
                case '00':
                case '7':
                case '07':
                case 'sunday':
                case 'sun':
                    $candidate = 'sun';
                    break;
            }

            if (!in_array($candidate, $allowed, true)) {
                continue;
            }

            $normalized[$candidate] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param mixed $raw
     * @param array<int, string> $allowed
     * @param array<int, mixed> $existing
     *
     * @return array<int, string>
     */
    private function sanitizeConditionValues($raw, array $allowed, array $existing = [], bool $preserveCase = false): array
    {
        $rawValues = is_array($raw) ? $raw : [];
        $sanitized = $this->filterConditionValues($rawValues, $allowed, $preserveCase);

        if ($sanitized !== []) {
            return $sanitized;
        }

        if (!empty($existing)) {
            return $this->filterConditionValues(is_array($existing) ? $existing : [], $allowed, $preserveCase);
        }

        return [];
    }

    /**
     * @param array<int, mixed> $values
     * @param array<int, string> $allowed
     *
     * @return array<int, string>
     */
    private function filterConditionValues(array $values, array $allowed, bool $preserveCase): array
    {
        $normalizedAllowed = $preserveCase
            ? array_values(array_unique(array_map('strval', $allowed)))
            : array_values(array_unique(array_map('sanitize_key', $allowed)));

        $result = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $stringValue = (string) $value;
            $normalizedValue = $preserveCase ? $stringValue : sanitize_key($stringValue);

            if ($normalizedValue === '') {
                continue;
            }

            $comparison = $preserveCase ? $stringValue : $normalizedValue;

            if (!in_array($comparison, $normalizedAllowed, true)) {
                continue;
            }

            $result[] = $preserveCase ? $comparison : $normalizedValue;
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedContentTypes(): array
    {
        if (!function_exists('get_post_types')) {
            return ['post', 'page'];
        }

        $types = get_post_types(['public' => true], 'names');

        if (!is_array($types)) {
            $types = [];
        }

        $types[] = 'post';
        $types[] = 'page';

        $sanitized = [];

        foreach ($types as $type) {
            $normalized = sanitize_key((string) $type);

            if ($normalized !== '') {
                $sanitized[] = $normalized;
            }
        }

        return array_values(array_unique($sanitized));
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedTaxonomies(): array
    {
        if (!function_exists('get_taxonomies')) {
            return [];
        }

        $taxonomies = get_taxonomies(['public' => true], 'names');

        if (!is_array($taxonomies)) {
            $taxonomies = [];
        }

        $sanitized = [];

        foreach ($taxonomies as $taxonomy) {
            $normalized = sanitize_key((string) $taxonomy);

            if ($normalized !== '') {
                $sanitized[] = $normalized;
            }
        }

        return array_values(array_unique($sanitized));
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedRoles(): array
    {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $roles = wp_roles();

        if (!is_object($roles)) {
            return [];
        }

        $roleNames = [];

        if (isset($roles->roles) && is_array($roles->roles)) {
            foreach (array_keys($roles->roles) as $roleKey) {
                $normalized = sanitize_key((string) $roleKey);

                if ($normalized !== '') {
                    $roleNames[] = $normalized;
                }
            }
        }

        return array_values(array_unique($roleNames));
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedLanguages(): array
    {
        $languages = ['default'];

        if (function_exists('get_available_languages')) {
            $available = get_available_languages();

            if (is_array($available)) {
                foreach ($available as $language) {
                    if (!is_string($language) || $language === '') {
                        continue;
                    }

                    $languages[] = $language;
                }
            }
        }

        if (function_exists('determine_locale')) {
            $current = determine_locale();

            if (is_string($current) && $current !== '') {
                $languages[] = $current;
            }
        }

        return array_values(array_unique($languages));
    }

    /**
     * @param mixed $value
     */
    private function sanitizeProfileInteger($value, int $existing = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $existing;
    }

    /**
     * @param mixed $value
     */
    private function sanitizeProfileId($value): string
    {
        if (is_string($value) || is_int($value)) {
            $sanitized = sanitize_key((string) $value);

            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function extractProfileId($value): string
    {
        if (is_string($value) || is_int($value)) {
            return $this->sanitizeProfileId($value);
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['id', 'slug', 'key'] as $key) {
            if (!isset($value[$key])) {
                continue;
            }

            $candidate = $this->sanitizeProfileId($value[$key]);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (isset($value['profile'])) {
            $candidate = $this->extractProfileId($value['profile']);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (isset($value['value'])) {
            $candidate = $this->sanitizeProfileId($value['value']);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function sanitizeChoice($rawValue, array $allowed, $existingValue, $defaultValue): string
    {
        return OptionChoices::resolveChoice($rawValue, $allowed, $existingValue, $defaultValue);
    }

}
