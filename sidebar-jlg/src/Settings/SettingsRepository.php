<?php

namespace JLG\Sidebar\Settings;

use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\ValueNormalizer;

class SettingsRepository
{
    private const DIMENSION_OPTION_KEYS = [
        'content_margin',
        'floating_vertical_margin',
        'border_radius',
        'hamburger_top_position',
        'header_padding_top',
    ];

    private const COLOR_OPTION_KEYS = [
        'bg_color',
        'bg_color_start',
        'bg_color_end',
        'accent_color',
        'accent_color_start',
        'accent_color_end',
        'font_color',
        'font_color_start',
        'font_color_end',
        'font_hover_color',
        'font_hover_color_start',
        'font_hover_color_end',
        'overlay_color',
        'mobile_bg_color',
    ];

    private DefaultSettings $defaults;
    private IconLibrary $icons;
    private ?array $optionsCache = null;
    private ?array $optionsCacheRaw = null;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
        $this->registerCacheInvalidationHooks();
    }

    public function getDefaultSettings(): array
    {
        return $this->defaults->all();
    }

    public function getOptions(): array
    {
        $optionsFromDb = $this->getStoredOptions();
        if ($this->optionsCache !== null && $this->optionsCacheRaw === $optionsFromDb) {
            return $this->optionsCache;
        }

        $options = wp_parse_args($optionsFromDb, $this->getDefaultSettings());

        $this->optionsCacheRaw = $optionsFromDb;
        $this->optionsCache = $options;

        return $options;
    }

    public function getOptionsWithRevalidation(): array
    {
        $optionsFromDb = $this->getStoredOptions();
        $defaults = $this->getDefaultSettings();
        $options = wp_parse_args($optionsFromDb, $defaults);

        $revalidated = $this->revalidateCustomIcons($options);
        if ($revalidated !== $options) {
            update_option('sidebar_jlg_settings', $revalidated);
        }

        $finalOptions = wp_parse_args($revalidated, $defaults);
        $this->optionsCacheRaw = $revalidated;
        $this->optionsCache = $finalOptions;

        return $finalOptions;
    }

    public function saveOptions(array $options): void
    {
        update_option('sidebar_jlg_settings', $options);
        $this->invalidateCache();
    }

    public function deleteOptions(): void
    {
        delete_option('sidebar_jlg_settings');
        $this->invalidateCache();
    }

    public function revalidateStoredOptions(): void
    {
        $stored = get_option('sidebar_jlg_settings', null);
        if (!is_array($stored)) {
            return;
        }

        $defaults = $this->getDefaultSettings();
        $merged = wp_parse_args($stored, $defaults);
        $revalidated = $this->revalidateCustomIcons($merged);

        $fallbackBorderColor = $defaults['border_color'] ?? '';
        $normalizedBorderColor = ValueNormalizer::normalizeColorWithExisting(
            $revalidated['border_color'] ?? null,
            $fallbackBorderColor
        );

        if (($revalidated['border_color'] ?? '') !== $normalizedBorderColor) {
            $revalidated['border_color'] = $normalizedBorderColor;
        }

        foreach (self::COLOR_OPTION_KEYS as $colorKey) {
            $defaultColor = $defaults[$colorKey] ?? '';
            $normalizedColor = ValueNormalizer::normalizeColorWithExisting(
                $revalidated[$colorKey] ?? null,
                $defaultColor
            );

            if (($revalidated[$colorKey] ?? '') !== $normalizedColor) {
                $revalidated[$colorKey] = $normalizedColor;
            }
        }

        foreach (self::DIMENSION_OPTION_KEYS as $dimensionKey) {
            $defaultValue = $defaults[$dimensionKey] ?? '';
            $normalizedValue = ValueNormalizer::normalizeCssDimension($revalidated[$dimensionKey] ?? null, $defaultValue);

            if (($revalidated[$dimensionKey] ?? '') !== $normalizedValue) {
                $revalidated[$dimensionKey] = $normalizedValue;
            }
        }

        if ($revalidated !== $merged) {
            update_option('sidebar_jlg_settings', $revalidated);
            $this->invalidateCache();
        }
    }

    private function revalidateCustomIcons(array $options): array
    {
        $availableIcons = $this->icons->getAllIcons();
        $menuItemsChanged = false;
        $socialIconsChanged = false;

        if (!empty($options['menu_items']) && is_array($options['menu_items'])) {
            foreach ($options['menu_items'] as $index => $item) {
                if (!is_array($item)) {
                    unset($options['menu_items'][$index]);
                    $menuItemsChanged = true;
                    continue;
                }

                $iconType = $item['icon_type'] ?? '';
                $iconValue = $item['icon'] ?? '';

                if ($iconType === 'svg_url' || $iconValue === '') {
                    continue;
                }

                if (strpos($iconValue, 'custom_') === 0) {
                    $iconKey = sanitize_key($iconValue);

                    if ($iconKey === '' || !isset($availableIcons[$iconKey])) {
                        $options['menu_items'][$index]['icon'] = '';
                        $options['menu_items'][$index]['icon_type'] = 'svg_inline';
                        $menuItemsChanged = true;
                    } elseif ($iconKey !== $iconValue) {
                        $options['menu_items'][$index]['icon'] = $iconKey;
                        $menuItemsChanged = true;
                    }
                }
            }

            if ($menuItemsChanged) {
                $options['menu_items'] = array_values(array_filter($options['menu_items'], static function ($item) {
                    return is_array($item);
                }));
            }
        }

        if (!empty($options['social_icons']) && is_array($options['social_icons'])) {
            foreach ($options['social_icons'] as $index => $icon) {
                if (!is_array($icon)) {
                    unset($options['social_icons'][$index]);
                    $socialIconsChanged = true;
                    continue;
                }

                $iconKey = $icon['icon'] ?? '';

                if (strpos($iconKey, 'custom_') !== 0) {
                    continue;
                }

                $sanitizedKey = sanitize_key($iconKey);

                if ($sanitizedKey === '' || !isset($availableIcons[$sanitizedKey])) {
                    unset($options['social_icons'][$index]);
                    $socialIconsChanged = true;
                } elseif ($sanitizedKey !== $iconKey) {
                    $options['social_icons'][$index]['icon'] = $sanitizedKey;
                    $socialIconsChanged = true;
                }
            }

            if ($socialIconsChanged) {
                $options['social_icons'] = array_values(array_filter($options['social_icons'], static function ($icon) {
                    return is_array($icon) && !empty($icon['icon']) && !empty($icon['url']);
                }));
            }
        }

        return $options;
    }

    private function getStoredOptions(): array
    {
        $optionsFromDb = get_option('sidebar_jlg_settings', []);

        if (!is_array($optionsFromDb)) {
            return [];
        }

        return $optionsFromDb;
    }

    public function invalidateCache(): void
    {
        $this->optionsCache = null;
        $this->optionsCacheRaw = null;
    }

    private function registerCacheInvalidationHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('update_option_sidebar_jlg_settings', [$this, 'invalidateCache'], 0, 0);
        add_action('delete_option_sidebar_jlg_settings', [$this, 'invalidateCache'], 0, 0);
    }

}
