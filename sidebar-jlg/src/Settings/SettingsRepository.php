<?php

namespace JLG\Sidebar\Settings;

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\OptionChoices;
use JLG\Sidebar\Settings\ValueNormalizer;

class SettingsRepository
{
    private const STRUCTURED_DIMENSION_KEYS = [
        'content_margin',
        'floating_vertical_margin',
        'border_radius',
        'hamburger_top_position',
        'hamburger_horizontal_offset',
        'hamburger_size',
        'header_padding_top',
        'horizontal_bar_height',
        'letter_spacing',
    ];

    private const SIMPLE_DIMENSION_OPTION_KEYS = [
        'width_mobile',
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

    private const NAV_MENU_CACHE_GROUP = 'sidebar_jlg';
    private const NAV_MENU_CACHE_TTL = 300;

    private const PROFILES_OPTION = 'sidebar_jlg_profiles';

    public const REVALIDATION_QUEUE_OPTION = 'sidebar_jlg_settings_revalidation_queue';
    public const REVALIDATION_QUEUE_FLAG_OPTION = 'sidebar_jlg_settings_revalidation_pending';
    public const REVALIDATION_QUEUED_ACTION = 'sidebar_jlg_settings_revalidation_queued';
    private const PLUGIN_MAINTENANCE_FLAG_OPTION = 'sidebar_jlg_pending_maintenance';

    /**
     * @var array<int, bool>
     */
    private static array $navMenuExistenceCache = [];

    private DefaultSettings $defaults;
    private IconLibrary $icons;
    private SettingsSanitizer $sanitizer;
    private ?array $optionsCache = null;
    private ?array $optionsCacheRaw = null;

    public function __construct(DefaultSettings $defaults, IconLibrary $icons, SettingsSanitizer $sanitizer)
    {
        $this->defaults = $defaults;
        $this->icons = $icons;
        $this->sanitizer = $sanitizer;
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
        $options = $this->normalizeRuntimeChoices($options);

        $this->optionsCacheRaw = $optionsFromDb;
        $this->optionsCache = $options;

        return $options;
    }

    public function getProfiles(): array
    {
        $storedProfiles = get_option(self::PROFILES_OPTION, null);

        if (!is_array($storedProfiles)) {
            $storedOptions = $this->getStoredOptions();

            if (!isset($storedOptions['profiles']) || !is_array($storedOptions['profiles'])) {
                return [];
            }

            $storedProfiles = $storedOptions['profiles'];
        }

        $profiles = [];

        foreach ($storedProfiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $profiles[] = $profile;
        }

        return array_values($profiles);
    }

    public function getOptionsWithRevalidation(): array
    {
        $optionsFromDb = $this->getStoredOptions();
        $defaults = $this->getDefaultSettings();
        $options = wp_parse_args($optionsFromDb, $defaults);

        $revalidated = $this->revalidateCustomIcons($options);

        if ($revalidated !== $options) {
            if ($this->shouldApplyRevalidationImmediately()) {
                update_option('sidebar_jlg_settings', $revalidated);
                $this->clearQueuedRevalidation();
            } else {
                $this->queueRevalidatedOptions($revalidated);
            }
        } else {
            $this->clearQueueIfRedundant();
        }

        $finalOptions = wp_parse_args($revalidated, $defaults);
        $finalOptions = $this->normalizeRuntimeChoices($finalOptions);
        $this->optionsCacheRaw = $optionsFromDb;
        $this->optionsCache = $finalOptions;

        return $finalOptions;
    }

    public function saveOptions(array $options): void
    {
        $existingOptions = $this->getStoredOptions();
        $sanitized = $this->sanitizer->sanitize_settings($options, $existingOptions);

        if (array_key_exists('profiles', $options)) {
            $profilesPayload = [];

            if (is_array($options['profiles'])) {
                foreach ($options['profiles'] as $profile) {
                    if (!is_array($profile)) {
                        continue;
                    }

                    if (isset($profile['settings']) && is_array($profile['settings'])) {
                        if (array_key_exists('enable_sidebar', $profile['settings'])) {
                            $profile['settings']['enable_sidebar'] = $this->normalizeProfileBoolean($profile['settings']['enable_sidebar']);
                        } else {
                            unset($profile['settings']['enable_sidebar']);
                        }
                    }

                    $profilesPayload[] = $profile;
                }
            }

            if ($profilesPayload === []) {
                delete_option(self::PROFILES_OPTION);
            } else {
                update_option(self::PROFILES_OPTION, array_values($profilesPayload));
            }

            unset($sanitized['profiles']);
        }

        update_option('sidebar_jlg_settings', $sanitized);
        $this->clearQueuedRevalidation();
        $this->invalidateCache();
    }

    public function deleteOptions(): void
    {
        delete_option('sidebar_jlg_settings');
        delete_option(self::PROFILES_OPTION);
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

        $textOptionKeys = ['nav_aria_label', 'toggle_open_label', 'toggle_close_label'];
        foreach ($textOptionKeys as $textKey) {
            if (!array_key_exists($textKey, $revalidated)) {
                continue;
            }

            $rawValue = $revalidated[$textKey];
            if (!is_string($rawValue)) {
                $revalidated[$textKey] = '';
                continue;
            }

            $sanitizedValue = sanitize_text_field($rawValue);
            if ($sanitizedValue !== $rawValue) {
                $revalidated[$textKey] = $sanitizedValue;
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
            update_option('sidebar_jlg_settings', $revalidated);
            $this->invalidateCache();
        }
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

                    if ($menuId > 0) {
                        $menuExists = $this->navMenuExists($menuId);
                        if ($menuExists === false) {
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

    private function getStoredOptions(): array
    {
        $optionsFromDb = get_option('sidebar_jlg_settings', []);

        if (!is_array($optionsFromDb)) {
            $optionsFromDb = [];
        }

        $queued = $this->getQueuedRevalidationPayload();
        if ($queued !== null) {
            $queuedOptions = isset($queued['options']) && is_array($queued['options'])
                ? $queued['options']
                : null;

            if ($queuedOptions !== null) {
                $optionsFromDb = array_merge($optionsFromDb, $queuedOptions);
            }
        }

        return $optionsFromDb;
    }

    public function hasQueuedRevalidation(): bool
    {
        $payload = $this->getQueuedRevalidationPayload();

        if ($payload === null) {
            return false;
        }

        $options = $payload['options'] ?? null;

        return is_array($options) && $options !== [];
    }

    public function applyQueuedRevalidation(): bool
    {
        $payload = $this->getQueuedRevalidationPayload();
        if ($payload === null) {
            return false;
        }

        $queuedOptions = isset($payload['options']) && is_array($payload['options'])
            ? $payload['options']
            : null;

        if ($queuedOptions === null) {
            $this->clearQueuedRevalidation();

            return false;
        }

        update_option('sidebar_jlg_settings', $queuedOptions);
        $this->clearQueuedRevalidation();
        $this->invalidateCache();

        return true;
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

    private function shouldApplyRevalidationImmediately(): bool
    {
        if ($this->isPrivilegedAdminRequest()) {
            return true;
        }

        if ($this->isMaintenanceScheduled()) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    private function normalizeProfileBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '' || in_array($normalized, ['0', 'false', 'no', 'off', 'disabled'], true)) {
                return false;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
                return true;
            }
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return !empty($value);
    }

    private function isPrivilegedAdminRequest(): bool
    {
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }

        if (function_exists('current_user_can') && !current_user_can('manage_options')) {
            return false;
        }

        if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
            return false;
        }

        if (function_exists('is_admin')) {
            return is_admin();
        }

        return false;
    }

    private function isMaintenanceScheduled(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $value = get_option(self::PLUGIN_MAINTENANCE_FLAG_OPTION, '');

        return $value === 'yes';
    }

    private function queueRevalidatedOptions(array $options): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        $payload = [
            'options' => $options,
            'queued_at' => time(),
        ];

        update_option(self::REVALIDATION_QUEUE_OPTION, $payload, 'no');
        update_option(self::REVALIDATION_QUEUE_FLAG_OPTION, 'yes', 'no');

        if (function_exists('do_action')) {
            do_action(self::REVALIDATION_QUEUED_ACTION, $payload);
        }
    }

    private function clearQueuedRevalidation(): void
    {
        if (function_exists('delete_option')) {
            delete_option(self::REVALIDATION_QUEUE_OPTION);
            delete_option(self::REVALIDATION_QUEUE_FLAG_OPTION);
        }
    }

    private function clearQueueIfRedundant(): void
    {
        if (!$this->hasQueuedRevalidation()) {
            return;
        }

        $payload = $this->getQueuedRevalidationPayload();
        if ($payload === null) {
            $this->clearQueuedRevalidation();

            return;
        }

        $queuedOptions = isset($payload['options']) && is_array($payload['options'])
            ? $payload['options']
            : null;

        if ($queuedOptions === null) {
            $this->clearQueuedRevalidation();
        }
    }

    private function getQueuedRevalidationPayload(): ?array
    {
        if (!function_exists('get_option')) {
            return null;
        }

        $payload = get_option(self::REVALIDATION_QUEUE_OPTION, null);

        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function navMenuExists(int $menuId): ?bool
    {
        if ($menuId <= 0 || !function_exists('wp_get_nav_menu_object')) {
            return null;
        }

        if (array_key_exists($menuId, self::$navMenuExistenceCache)) {
            return self::$navMenuExistenceCache[$menuId];
        }

        $cacheKey = self::getNavMenuCacheKey($menuId);

        if (function_exists('wp_cache_get')) {
            $found = false;
            $cached = wp_cache_get($cacheKey, self::NAV_MENU_CACHE_GROUP, false, $found);
            if ($found) {
                $value = is_bool($cached) ? $cached : (bool) $cached;
                self::$navMenuExistenceCache[$menuId] = $value;

                return $value;
            }
        }

        $menuObject = wp_get_nav_menu_object($menuId);
        $exists = (bool) $menuObject;
        self::$navMenuExistenceCache[$menuId] = $exists;

        if (function_exists('wp_cache_set')) {
            wp_cache_set($cacheKey, $exists, self::NAV_MENU_CACHE_GROUP, self::NAV_MENU_CACHE_TTL);
        }

        return $exists;
    }

    public static function invalidateCachedNavMenu($menuId = null, ...$unused): void
    {
        if ($menuId !== null) {
            $menuId = absint($menuId);
            if ($menuId <= 0) {
                return;
            }

            unset(self::$navMenuExistenceCache[$menuId]);

            if (function_exists('wp_cache_delete')) {
                wp_cache_delete(self::getNavMenuCacheKey($menuId), self::NAV_MENU_CACHE_GROUP);
            }

            return;
        }

        self::$navMenuExistenceCache = [];
    }

    private static function getNavMenuCacheKey(int $menuId): string
    {
        return 'nav_menu_' . $menuId;
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
}
