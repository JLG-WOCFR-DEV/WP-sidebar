<?php

namespace JLG\Sidebar\Settings;

use JLG\Sidebar\Icons\IconLibrary;

class SettingsRepository
{
    private DefaultSettings $defaults;
    private IconLibrary $icons;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
    }

    public function getDefaultSettings(): array
    {
        return $this->defaults->all();
    }

    public function getOptions(): array
    {
        $optionsFromDb = get_option('sidebar_jlg_settings', []);
        $options = wp_parse_args($optionsFromDb, $this->getDefaultSettings());

        return $options;
    }

    public function getOptionsWithRevalidation(): array
    {
        $optionsFromDb = get_option('sidebar_jlg_settings', []);
        $defaults = $this->getDefaultSettings();
        $options = wp_parse_args($optionsFromDb, $defaults);

        $revalidated = $this->revalidateCustomIcons($options);
        if ($revalidated !== $options) {
            update_option('sidebar_jlg_settings', $revalidated);
        }

        return wp_parse_args($revalidated, $defaults);
    }

    public function saveOptions(array $options): void
    {
        update_option('sidebar_jlg_settings', $options);
    }

    public function deleteOptions(): void
    {
        delete_option('sidebar_jlg_settings');
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
        if ($revalidated !== $merged) {
            update_option('sidebar_jlg_settings', $revalidated);
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
}
