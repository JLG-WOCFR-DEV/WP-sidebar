<?php

namespace JLG\Sidebar\Admin;

use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\ValueNormalizer;

class SettingsSanitizer
{
    private DefaultSettings $defaults;
    private IconLibrary $icons;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
    }

    public function sanitize_settings($input): array
    {
        $defaults = $this->defaults->all();
        $existingOptions = get_option('sidebar_jlg_settings', $defaults);
        if (!is_array($existingOptions)) {
            if (function_exists('error_log')) {
                error_log('Sidebar JLG settings option was not an array. Resetting to defaults.');
            }
            $existingOptions = $defaults;
        }
        $preparedInput = is_array($input) ? $input : [];

        $sanitizedInput = array_merge(
            $this->sanitize_general_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_style_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_effects_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_menu_settings($preparedInput, $existingOptions) ?: [],
            $this->sanitize_social_settings($preparedInput, $existingOptions) ?: []
        );

        return array_merge($existingOptions, $sanitizedInput);
    }

    private function sanitize_general_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $defaults = $this->defaults->all();

        $sanitized['enable_sidebar'] = !empty($input['enable_sidebar']);
        $sanitized['layout_style'] = sanitize_key($input['layout_style'] ?? $existingOptions['layout_style']);
        $sanitized['floating_vertical_margin'] = ValueNormalizer::normalizeCssDimension(
            $input['floating_vertical_margin'] ?? $existingOptions['floating_vertical_margin'],
            $existingOptions['floating_vertical_margin']
        );
        $sanitized['border_radius'] = ValueNormalizer::normalizeCssDimension(
            $input['border_radius'] ?? $existingOptions['border_radius'],
            $existingOptions['border_radius']
        );
        $sanitized['border_width'] = absint($input['border_width'] ?? $existingOptions['border_width']);
        $existingBorderColor = array_key_exists('border_color', $existingOptions)
            ? $existingOptions['border_color']
            : ($defaults['border_color'] ?? '');
        $sanitized['border_color'] = ValueNormalizer::normalizeColorWithExisting(
            $input['border_color'] ?? null,
            $existingBorderColor
        );
        $sanitized['desktop_behavior'] = sanitize_key($input['desktop_behavior'] ?? $existingOptions['desktop_behavior']);
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
        $sanitized['content_margin'] = ValueNormalizer::normalizeCssDimension(
            $input['content_margin'] ?? $existingOptions['content_margin'],
            $existingOptions['content_margin']
        );
        $sanitized['width_desktop'] = absint($input['width_desktop'] ?? $existingOptions['width_desktop']);
        $sanitized['width_tablet'] = absint($input['width_tablet'] ?? $existingOptions['width_tablet']);
        $sanitized['enable_search'] = !empty($input['enable_search']);
        $sanitized['search_method'] = sanitize_key($input['search_method'] ?? $existingOptions['search_method']);
        $sanitized['search_shortcode'] = sanitize_text_field($input['search_shortcode'] ?? $existingOptions['search_shortcode']);
        $sanitized['search_alignment'] = sanitize_key($input['search_alignment'] ?? $existingOptions['search_alignment']);
        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        $sanitized['show_close_button'] = !empty($input['show_close_button']);
        // Checkbox -> boolean conversion for the automatic closing behaviour.
        $sanitized['close_on_link_click'] = !empty($input['close_on_link_click']);
        $sanitized['hamburger_top_position'] = ValueNormalizer::normalizeCssDimension(
            $input['hamburger_top_position'] ?? $existingOptions['hamburger_top_position'],
            $existingOptions['hamburger_top_position']
        );
        $sanitized['app_name'] = sanitize_text_field($input['app_name'] ?? $existingOptions['app_name']);
        $sanitized['header_logo_type'] = sanitize_key($input['header_logo_type'] ?? $existingOptions['header_logo_type']);
        $sanitized['header_logo_image'] = esc_url_raw($input['header_logo_image'] ?? $existingOptions['header_logo_image']);
        $sanitized['header_logo_size'] = absint($input['header_logo_size'] ?? $existingOptions['header_logo_size']);
        $sanitized['header_alignment_desktop'] = sanitize_key($input['header_alignment_desktop'] ?? $existingOptions['header_alignment_desktop']);
        $sanitized['header_alignment_mobile'] = sanitize_key($input['header_alignment_mobile'] ?? $existingOptions['header_alignment_mobile']);
        $sanitized['header_padding_top'] = ValueNormalizer::normalizeCssDimension(
            $input['header_padding_top'] ?? $existingOptions['header_padding_top'],
            $existingOptions['header_padding_top']
        );

        return $sanitized;
    }

    private function sanitize_style_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $sanitized['style_preset'] = sanitize_key($input['style_preset'] ?? $existingOptions['style_preset']);

        $sanitized['bg_color_type'] = sanitize_key($input['bg_color_type'] ?? $existingOptions['bg_color_type']);
        $sanitized['bg_color'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color'] ?? null, $existingOptions['bg_color']);
        $sanitized['bg_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color_start'] ?? null, $existingOptions['bg_color_start']);
        $sanitized['bg_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['bg_color_end'] ?? null, $existingOptions['bg_color_end']);

        $sanitized['accent_color_type'] = sanitize_key($input['accent_color_type'] ?? $existingOptions['accent_color_type']);
        $sanitized['accent_color'] = ValueNormalizer::normalizeColorWithExisting($input['accent_color'] ?? null, $existingOptions['accent_color']);
        $sanitized['accent_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['accent_color_start'] ?? null, $existingOptions['accent_color_start']);
        $sanitized['accent_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['accent_color_end'] ?? null, $existingOptions['accent_color_end']);

        $sanitized['font_size'] = absint($input['font_size'] ?? $existingOptions['font_size']);
        $sanitized['font_color_type'] = sanitize_key($input['font_color_type'] ?? $existingOptions['font_color_type']);
        $sanitized['font_color'] = ValueNormalizer::normalizeColorWithExisting($input['font_color'] ?? null, $existingOptions['font_color']);
        $sanitized['font_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['font_color_start'] ?? null, $existingOptions['font_color_start']);
        $sanitized['font_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['font_color_end'] ?? null, $existingOptions['font_color_end']);

        $sanitized['font_hover_color_type'] = sanitize_key($input['font_hover_color_type'] ?? $existingOptions['font_hover_color_type']);
        $sanitized['font_hover_color'] = ValueNormalizer::normalizeColorWithExisting($input['font_hover_color'] ?? null, $existingOptions['font_hover_color']);
        $sanitized['font_hover_color_start'] = ValueNormalizer::normalizeColorWithExisting($input['font_hover_color_start'] ?? null, $existingOptions['font_hover_color_start']);
        $sanitized['font_hover_color_end'] = ValueNormalizer::normalizeColorWithExisting($input['font_hover_color_end'] ?? null, $existingOptions['font_hover_color_end']);

        $sanitized['mobile_bg_color'] = ValueNormalizer::normalizeColorWithExisting($input['mobile_bg_color'] ?? null, $existingOptions['mobile_bg_color']);
        $existingOpacity = isset($existingOptions['mobile_bg_opacity']) ? (float) $existingOptions['mobile_bg_opacity'] : 0.0;
        $sanitized['mobile_bg_opacity'] = is_numeric($input['mobile_bg_opacity'] ?? null)
            ? max(0.0, min(1.0, (float) $input['mobile_bg_opacity']))
            : max(0.0, min(1.0, $existingOpacity));
        $sanitized['mobile_blur'] = absint($input['mobile_blur'] ?? $existingOptions['mobile_blur']);

        return $sanitized;
    }

    private function sanitize_effects_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];

        $sanitized['hover_effect_desktop'] = sanitize_key($input['hover_effect_desktop'] ?? $existingOptions['hover_effect_desktop']);
        $sanitized['hover_effect_mobile'] = sanitize_key($input['hover_effect_mobile'] ?? $existingOptions['hover_effect_mobile']);
        $sanitized['animation_speed'] = absint($input['animation_speed'] ?? $existingOptions['animation_speed']);
        $sanitized['animation_type'] = sanitize_key($input['animation_type'] ?? $existingOptions['animation_type']);
        $sanitized['neon_blur'] = absint($input['neon_blur'] ?? $existingOptions['neon_blur']);
        $sanitized['neon_spread'] = absint($input['neon_spread'] ?? $existingOptions['neon_spread']);

        return $sanitized;
    }

    private function sanitize_menu_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $availableIcons = $this->icons->getAllIcons();

        $sanitized['menu_alignment_desktop'] = sanitize_key($input['menu_alignment_desktop'] ?? $existingOptions['menu_alignment_desktop']);
        $sanitized['menu_alignment_mobile'] = sanitize_key($input['menu_alignment_mobile'] ?? $existingOptions['menu_alignment_mobile']);

        $sanitizedMenuItems = [];
        if (isset($input['menu_items']) && is_array($input['menu_items'])) {
            foreach ($input['menu_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemType = sanitize_key($item['type'] ?? '');
                $iconType = sanitize_key($item['icon_type'] ?? '');
                $iconType = ($iconType === 'svg_url') ? 'svg_url' : 'svg_inline';

                $sanitizedItem = [
                    'label' => sanitize_text_field($item['label'] ?? ''),
                    'type' => $itemType,
                    'icon_type' => $iconType,
                ];

                if ($iconType === 'svg_url') {
                    $sanitizedItem['icon'] = esc_url_raw($item['icon'] ?? '');
                } else {
                    $iconKey = sanitize_key($item['icon'] ?? '');
                    if ($iconKey !== '' && isset($availableIcons[$iconKey])) {
                        $sanitizedItem['icon'] = $iconKey;
                    } else {
                        $sanitizedItem['icon'] = '';
                        $sanitizedItem['icon_type'] = 'svg_inline';
                    }
                }

                switch ($itemType) {
                    case 'custom':
                        $sanitizedItem['value'] = esc_url_raw($item['value'] ?? '');
                        break;
                    case 'post':
                    case 'page':
                    case 'category':
                    default:
                        $sanitizedItem['value'] = absint($item['value'] ?? 0);
                        break;
                }

                $sanitizedMenuItems[] = $sanitizedItem;
            }
        }

        $sanitized['menu_items'] = $sanitizedMenuItems;

        return $sanitized;
    }

    private function sanitize_social_settings(array $input, array $existingOptions): array
    {
        $sanitized = [];
        $availableIcons = $this->icons->getAllIcons();

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
        $sanitized['social_orientation'] = sanitize_key($input['social_orientation'] ?? $existingOptions['social_orientation']);
        $sanitized['social_position'] = sanitize_key($input['social_position'] ?? $existingOptions['social_position']);
        $sanitized['social_icon_size'] = absint($input['social_icon_size'] ?? $existingOptions['social_icon_size']);

        return $sanitized;
    }

}
