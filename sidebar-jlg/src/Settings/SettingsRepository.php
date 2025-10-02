<?php

namespace JLG\Sidebar\Settings;

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\OptionChoices;
use JLG\Sidebar\Settings\ValueNormalizer;

class SettingsRepository
{
    private const PROFILES_OPTION = 'sidebar_jlg_profiles';
    public const DEFAULT_PROFILE_KEY = 'default';

    private const STRUCTURED_DIMENSION_KEYS = [
        'content_margin',
        'floating_vertical_margin',
        'border_radius',
        'hamburger_top_position',
        'header_padding_top',
        'horizontal_bar_height',
    ];

    private const SIMPLE_DIMENSION_OPTION_KEYS = [
        'letter_spacing',
    ];

    private const OPACITY_OPTION_KEYS = [
        'overlay_opacity',
        'mobile_bg_opacity',
    ];

    private const ABSINT_OPTION_KEYS = [
        'border_width',
        'width_desktop',
        'width_tablet',
        'header_logo_size',
        'font_size',
        'mobile_blur',
        'animation_speed',
        'neon_blur',
        'neon_spread',
        'social_icon_size',
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
        'hamburger_color',
    ];

    private DefaultSettings $defaults;
    private IconLibrary $icons;
    private ?array $optionsCache = null;
    private ?array $optionsCacheRaw = null;
    private ?array $profilesCache = null;
    private ?string $optionsCacheProfileKey = null;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
        $this->registerCacheInvalidationHooks();
    }

    public function migrateLegacyOptions(): void
    {
        $legacy = get_option('sidebar_jlg_settings', null);
        if (!is_array($legacy)) {
            return;
        }

        $profiles = $this->getProfilesStructure();
        $hasExistingProfiles = array_filter(
            $profiles['profiles'],
            static fn ($options) => is_array($options) && $options !== []
        );

        if ($hasExistingProfiles !== []) {
            delete_option('sidebar_jlg_settings');
            return;
        }

        $profiles['profiles'][self::DEFAULT_PROFILE_KEY] = $legacy;
        $profiles['active'] = self::DEFAULT_PROFILE_KEY;

        update_option(self::PROFILES_OPTION, $profiles);
        delete_option('sidebar_jlg_settings');
        $this->invalidateCache();
    }

    public function getDefaultSettings(): array
    {
        return $this->defaults->all();
    }

    public function getOptions(?string $profileKey = null): array
    {
        $resolvedProfileKey = $this->resolveProfileKey($profileKey);
        $optionsFromDb = $this->getStoredOptions($resolvedProfileKey);
        if (
            $this->optionsCache !== null
            && $this->optionsCacheRaw === $optionsFromDb
            && $this->optionsCacheProfileKey === $resolvedProfileKey
        ) {
            return $this->optionsCache;
        }

        $options = wp_parse_args($optionsFromDb, $this->getDefaultSettings());
        $options = $this->normalizeRuntimeChoices($options);

        $this->optionsCacheRaw = $optionsFromDb;
        $this->optionsCache = $options;
        $this->optionsCacheProfileKey = $resolvedProfileKey;

        return $options;
    }

    public function getOptionsWithRevalidation(?string $profileKey = null): array
    {
        $resolvedProfileKey = $this->resolveProfileKey($profileKey);
        $optionsFromDb = $this->getStoredOptions($resolvedProfileKey);
        $defaults = $this->getDefaultSettings();
        $options = wp_parse_args($optionsFromDb, $defaults);

        $revalidated = $this->revalidateCustomIcons($options);
        if ($revalidated !== $options) {
            $this->saveOptions($revalidated, $resolvedProfileKey);
        }

        $finalOptions = wp_parse_args($revalidated, $defaults);
        $finalOptions = $this->normalizeRuntimeChoices($finalOptions);
        $this->optionsCacheRaw = $revalidated;
        $this->optionsCache = $finalOptions;
        $this->optionsCacheProfileKey = $resolvedProfileKey;

        return $finalOptions;
    }

    public function saveOptions(array $options, ?string $profileKey = null): void
    {
        $profile = $this->resolveProfileKey($profileKey, true);
        $structure = $this->getProfilesStructure();
        $structure['profiles'][$profile] = $options;
        if (!isset($structure['active']) || $structure['active'] === '') {
            $structure['active'] = $profile;
        }

        $this->persistProfiles($structure);
        $this->invalidateCache();
    }

    public function deleteOptions(?string $profileKey = null): void
    {
        $profile = $this->resolveProfileKey($profileKey);
        $structure = $this->getProfilesStructure();

        unset($structure['profiles'][$profile]);

        if ($structure['profiles'] === []) {
            $structure['profiles'][self::DEFAULT_PROFILE_KEY] = [];
            $structure['active'] = self::DEFAULT_PROFILE_KEY;
        } elseif (($structure['active'] ?? '') === $profile) {
            $structure['active'] = array_key_first($structure['profiles']);
        }

        $this->persistProfiles($structure);
        $this->invalidateCache();
    }

    public function revalidateStoredOptions(?string $profileKey = null): void
    {
        $profile = $this->resolveProfileKey($profileKey);
        $stored = $this->getStoredOptions($profile);
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

        foreach (self::STRUCTURED_DIMENSION_KEYS as $dimensionKey) {
            $defaultValue = $defaults[$dimensionKey] ?? [];
            $existing = $revalidated[$dimensionKey] ?? $defaultValue;
            $normalizedValue = ValueNormalizer::normalizeDimensionStructure(
                $revalidated[$dimensionKey] ?? null,
                $existing,
                $defaultValue
            );

            $revalidated[$dimensionKey] = $normalizedValue;
        }

        foreach (self::SIMPLE_DIMENSION_OPTION_KEYS as $dimensionKey) {
            $defaultValue = $defaults[$dimensionKey] ?? '';
            $normalizedValue = ValueNormalizer::normalizeCssDimension($revalidated[$dimensionKey] ?? null, $defaultValue);

            if (($revalidated[$dimensionKey] ?? '') !== $normalizedValue) {
                $revalidated[$dimensionKey] = $normalizedValue;
            }
        }

        foreach (self::OPACITY_OPTION_KEYS as $opacityKey) {
            $defaultOpacity = isset($defaults[$opacityKey]) && is_numeric($defaults[$opacityKey])
                ? max(0.0, min(1.0, (float) $defaults[$opacityKey]))
                : 0.0;
            $currentOpacity = $revalidated[$opacityKey] ?? $defaultOpacity;
            $shouldUpdate = false;

            if (!is_numeric($currentOpacity)) {
                $normalizedOpacity = $defaultOpacity;
                $shouldUpdate = true;
            } else {
                $normalizedOpacity = (float) $currentOpacity;

                if ($normalizedOpacity < 0.0) {
                    $normalizedOpacity = 0.0;
                    $shouldUpdate = true;
                } elseif ($normalizedOpacity > 1.0) {
                    $normalizedOpacity = 1.0;
                    $shouldUpdate = true;
                } elseif (!is_float($currentOpacity)) {
                    $shouldUpdate = true;
                }
            }

            if ($shouldUpdate) {
                $revalidated[$opacityKey] = $normalizedOpacity;
            }
        }

        foreach (self::ABSINT_OPTION_KEYS as $intKey) {
            $defaultValue = isset($defaults[$intKey]) ? absint($defaults[$intKey]) : 0;
            $currentValue = $revalidated[$intKey] ?? $defaultValue;
            $shouldUpdate = false;

            if (!is_scalar($currentValue)) {
                $normalizedValue = $defaultValue;
                $shouldUpdate = true;
            } else {
                $normalizedValue = absint($currentValue);

                if ($normalizedValue !== (int) $currentValue || !is_int($currentValue)) {
                    $shouldUpdate = true;
                }
            }

            if ($shouldUpdate) {
                $revalidated[$intKey] = $normalizedValue;
            }
        }

        foreach (OptionChoices::getAll() as $choiceKey => $allowedValues) {
            $currentValue = $revalidated[$choiceKey] ?? null;
            $defaultValue = $defaults[$choiceKey] ?? null;

            $normalizedChoice = OptionChoices::resolveChoice(
                $currentValue,
                $allowedValues,
                $defaultValue,
                $defaultValue
            );

            if (($revalidated[$choiceKey] ?? null) !== $normalizedChoice) {
                $revalidated[$choiceKey] = $normalizedChoice;
            }
        }

        if ($revalidated !== $merged) {
            $this->saveOptions($revalidated, $profile);
            $this->invalidateCache();
        }
    }

    public function getActiveProfileKey(): string
    {
        $structure = $this->getProfilesStructure();

        return $structure['active'];
    }

    public function setActiveProfileKey(string $profileKey): void
    {
        $profileKey = sanitize_key($profileKey);
        if ($profileKey === '') {
            return;
        }

        $structure = $this->getProfilesStructure();
        if (!isset($structure['profiles'][$profileKey])) {
            return;
        }

        if ($structure['active'] === $profileKey) {
            return;
        }

        $structure['active'] = $profileKey;
        $this->persistProfiles($structure);
        $this->invalidateCache();
    }

    public function getAvailableProfileKeys(): array
    {
        $structure = $this->getProfilesStructure();

        return array_keys($structure['profiles']);
    }

    public function getRawProfileOptions(?string $profileKey = null): array
    {
        $profile = $this->resolveProfileKey($profileKey);
        $structure = $this->getProfilesStructure();

        $options = $structure['profiles'][$profile] ?? [];

        return is_array($options) ? $options : [];
    }

    private function revalidateCustomIcons(array $options): array
    {
        $availableIcons = $this->icons->getAllIcons();
        $menuItemsChanged = false;
        $socialIconsChanged = false;

        $navFilters = SettingsSanitizer::getAllowedNavMenuFilters();

        if (!empty($options['menu_items']) && is_array($options['menu_items'])) {
            foreach ($options['menu_items'] as $index => $item) {
                if (!is_array($item)) {
                    unset($options['menu_items'][$index]);
                    $menuItemsChanged = true;
                    continue;
                }

                $iconType = $item['icon_type'] ?? '';
                $iconValue = $item['icon'] ?? '';

                if ($iconType !== 'svg_url' && $iconValue !== '') {
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

                if (($item['type'] ?? '') === 'nav_menu') {
                    $rawMenuId = isset($item['value']) ? $item['value'] : 0;
                    $menuId = absint($rawMenuId);

                    if ($menuId > 0 && function_exists('wp_get_nav_menu_object')) {
                        $menuObject = wp_get_nav_menu_object($menuId);
                        if (!$menuObject) {
                            $menuId = 0;
                        }
                    }

                    if (($options['menu_items'][$index]['value'] ?? null) !== $menuId) {
                        $options['menu_items'][$index]['value'] = $menuId;
                        $menuItemsChanged = true;
                    }

                    $depth = absint($item['nav_menu_max_depth'] ?? 0);
                    if (($options['menu_items'][$index]['nav_menu_max_depth'] ?? null) !== $depth) {
                        $options['menu_items'][$index]['nav_menu_max_depth'] = $depth;
                        $menuItemsChanged = true;
                    }

                    $rawFilter = isset($item['nav_menu_filter']) ? sanitize_key($item['nav_menu_filter']) : '';
                    if (!in_array($rawFilter, $navFilters, true)) {
                        $rawFilter = $navFilters[0];
                    }

                    if (($options['menu_items'][$index]['nav_menu_filter'] ?? null) !== $rawFilter) {
                        $options['menu_items'][$index]['nav_menu_filter'] = $rawFilter;
                        $menuItemsChanged = true;
                    }

                    if (!array_key_exists('nav_menu_max_depth', $options['menu_items'][$index])) {
                        $options['menu_items'][$index]['nav_menu_max_depth'] = 0;
                    }
                    if (!array_key_exists('nav_menu_filter', $options['menu_items'][$index])) {
                        $options['menu_items'][$index]['nav_menu_filter'] = $navFilters[0];
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

    private function getStoredOptions(?string $profileKey = null): array
    {
        return $this->getRawProfileOptions($profileKey);
    }

    public function invalidateCache(): void
    {
        $this->optionsCache = null;
        $this->optionsCacheRaw = null;
        $this->profilesCache = null;
        $this->optionsCacheProfileKey = null;
    }

    private function registerCacheInvalidationHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('update_option_' . self::PROFILES_OPTION, [$this, 'invalidateCache'], 0, 0);
        add_action('delete_option_' . self::PROFILES_OPTION, [$this, 'invalidateCache'], 0, 0);
    }

    private function normalizeRuntimeChoices(array $options): array
    {
        $defaults = $this->getDefaultSettings();
        $choices = OptionChoices::getAll();

        if (!isset($choices['sidebar_position'])) {
            return $options;
        }

        $options['sidebar_position'] = OptionChoices::resolveChoice(
            $options['sidebar_position'] ?? null,
            $choices['sidebar_position'],
            $defaults['sidebar_position'] ?? null,
            $defaults['sidebar_position'] ?? 'left'
        );

        return $options;
    }

    private function getProfilesStructure(): array
    {
        if ($this->profilesCache !== null) {
            return $this->profilesCache;
        }

        $stored = get_option(self::PROFILES_OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $profiles = [];
        if (isset($stored['profiles']) && is_array($stored['profiles'])) {
            foreach ($stored['profiles'] as $key => $profileOptions) {
                if (!is_string($key)) {
                    continue;
                }

                $sanitizedKey = sanitize_key($key);
                if ($sanitizedKey === '') {
                    continue;
                }

                $profiles[$sanitizedKey] = is_array($profileOptions) ? $profileOptions : [];
            }
        }

        if ($profiles === []) {
            $profiles[self::DEFAULT_PROFILE_KEY] = [];
        }

        $active = isset($stored['active']) && is_string($stored['active'])
            ? sanitize_key($stored['active'])
            : '';

        if ($active === '' || !isset($profiles[$active])) {
            $active = array_key_first($profiles) ?? self::DEFAULT_PROFILE_KEY;
        }

        $structure = [
            'active' => $active,
            'profiles' => $profiles,
        ];

        $this->profilesCache = $structure;

        return $structure;
    }

    private function persistProfiles(array $structure): void
    {
        update_option(self::PROFILES_OPTION, [
            'active' => $structure['active'],
            'profiles' => $structure['profiles'],
        ]);
        $this->profilesCache = $structure;
    }

    private function resolveProfileKey(?string $profileKey, bool $allowCreate = false): string
    {
        if ($profileKey === null || $profileKey === '') {
            return $this->getActiveProfileKey();
        }

        $sanitized = sanitize_key($profileKey);
        if ($sanitized === '') {
            return $this->getActiveProfileKey();
        }

        $structure = $this->getProfilesStructure();

        if ($allowCreate) {
            return $sanitized;
        }

        if (!isset($structure['profiles'][$sanitized])) {
            return $structure['active'];
        }

        return $sanitized;
    }
}
