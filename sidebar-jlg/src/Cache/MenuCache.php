<?php

namespace JLG\Sidebar\Cache;

class MenuCache
{
    private const CACHE_TTL = 86400;
    private const OBJECT_CACHE_GROUP = 'sidebar_jlg';
    private const LOCALE_INDEX_CACHE_KEY = 'menu_cache_index';
    private const LOCALE_ENTRY_CACHE_PREFIX = 'menu_cache_entry_';
    private const LOCALE_ENTRY_OPTION_PREFIX = 'sidebar_jlg_cached_locale_entry_';
    private const DEFAULT_SUFFIX_KEY = '__default__';
    private const METRICS_KEY = 'metrics';
    private const METRICS_SUFFIX_ALIAS = 'metrics_profile';
    private const MAINTENANCE_CRON_HOOK = 'sidebar_jlg_menu_cache_maintenance';

    private string $optionName = 'sidebar_jlg_cached_locales';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $loadedLocaleIndex = null;

    /**
     * @var array<string, array<string, int>>
     */
    private array $pendingEntryExpirations = [];

    public function __construct()
    {
        if (function_exists('add_action')) {
            add_action(self::MAINTENANCE_CRON_HOOK, [$this, 'purgeExpiredEntries']);
            add_action('init', [$this, 'scheduleMaintenanceEvent']);
        }
    }

    public function getLocaleForCache(): string
    {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } elseif (function_exists('get_locale')) {
            $locale = get_locale();
        } else {
            $locale = '';
        }

        if (!is_string($locale)) {
            $locale = '';
        }

        return '' === $locale ? 'default' : $locale;
    }

    public function getTransientKey(string $locale, ?string $suffix = null): string
    {
        return $this->buildTransientKey(
            $this->normalizeLocale($locale),
            $this->normalizeSuffixValue($suffix)
        );
    }

    public function get(string $locale, ?string $suffix = null)
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);
        $transientKey = $this->buildTransientKey($normalizedLocale, $normalizedSuffix);
        $value = get_transient($transientKey);

        if ($value === false) {
            $this->recordCacheMetric($normalizedLocale, $normalizedSuffix, 'miss');
            $this->emitCacheEvent('miss', $normalizedLocale, $normalizedSuffix, [
                'key' => $transientKey,
            ]);

            return false;
        }

        if (!is_string($value)) {
            $this->recordCacheMetric($normalizedLocale, $normalizedSuffix, 'miss');

            $this->delete($normalizedLocale, $normalizedSuffix);

            $this->emitCacheEvent('invalid', $normalizedLocale, $normalizedSuffix, [
                'key' => $transientKey,
            ]);

            if (function_exists('error_log')) {
                $suffixNotice = '';
                if ($normalizedSuffix !== null) {
                    $suffixNotice = ' and profile ' . $normalizedSuffix;
                }

                error_log(
                    '[Sidebar JLG] Invalid sidebar cache payload cleared for locale '
                    . $normalizedLocale
                    . $suffixNotice
                );
            }

            return false;
        }

        $this->recordCacheMetric($normalizedLocale, $normalizedSuffix, 'hit');
        $this->emitCacheEvent('hit', $normalizedLocale, $normalizedSuffix, [
            'key' => $transientKey,
            'bytes' => strlen($value),
        ]);

        return $value;
    }

    public function set(string $locale, string $html, ?string $suffix = null): void
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);
        $transientKey = $this->buildTransientKey($normalizedLocale, $normalizedSuffix);

        set_transient($transientKey, $html, self::CACHE_TTL);
        $this->rememberLocale($normalizedLocale, $normalizedSuffix);

        $this->emitCacheEvent('set', $normalizedLocale, $normalizedSuffix, [
            'key' => $transientKey,
            'bytes' => strlen($html),
        ]);
    }

    public function delete(string $locale, ?string $suffix = null): void
    {
        $this->clearEntry($locale, $suffix);
    }

    public function clearEntry(string $locale, ?string $suffix = null): void
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);
        $suffixKey = $this->normalizeSuffixKey($normalizedSuffix);
        $previousMetrics = $this->getMetricsSnapshot($normalizedLocale, $normalizedSuffix);
        $index = $this->getLocaleIndex();
        $transientKeys = [];

        if (isset($index[$normalizedLocale][$suffixKey]) && is_string($index[$normalizedLocale][$suffixKey])) {
            $transientKeys[] = $index[$normalizedLocale][$suffixKey];
        } else {
            $transientKeys[] = $this->buildTransientKey($normalizedLocale, $normalizedSuffix);
        }

        foreach (array_unique($transientKeys) as $transientKey) {
            delete_transient($transientKey);
        }

        if ($suffixKey !== self::DEFAULT_SUFFIX_KEY) {
            // Clear legacy cache entries that were stored without profile context.
            $legacyKey = $this->buildTransientKey($normalizedLocale, null);
            delete_transient($legacyKey);
            $transientKeys[] = $legacyKey;
        }

        if (isset($index[$normalizedLocale])) {
            unset($index[$normalizedLocale][$suffixKey]);

            $this->removeMetricsForEntry($index, $normalizedLocale, $suffixKey);

            if ($this->isLocaleIndexEmpty($index, $normalizedLocale)) {
                unset($index[$normalizedLocale]);
            }
        }

        $this->persistLocaleIndex($index);

        $context = [
            'keys' => array_values(array_unique($transientKeys)),
        ];

        if ($previousMetrics !== null) {
            $context['metrics'] = $previousMetrics;
        }

        $this->emitCacheEvent('delete', $normalizedLocale, $normalizedSuffix, $context);
    }

    public function clear(): void
    {
        $index = $this->getLocaleIndex();
        $transientKeys = [];

        foreach ($index as $locale => $profiles) {
            foreach ($profiles as $suffixKey => $storedKey) {
                if ($suffixKey === self::METRICS_KEY) {
                    continue;
                }

                if (is_string($storedKey) && $storedKey !== '') {
                    $transientKeys[$storedKey] = true;
                } else {
                    $transientKeys[$this->buildTransientKey($locale, $this->suffixKeyToSuffix($suffixKey))] = true;
                }

                if ($suffixKey !== self::DEFAULT_SUFFIX_KEY) {
                    $legacyKey = $this->buildTransientKey($locale, null);
                    $transientKeys[$legacyKey] = true;
                }
            }
        }

        foreach (array_keys($transientKeys) as $transientKey) {
            delete_transient($transientKey);
        }

        delete_transient('sidebar_jlg_full_html');

        $this->clearLocaleIndexStorage();

        $this->emitCacheEvent('clear', null, null, [
            'cleared_keys' => count($transientKeys),
        ]);
    }

    public function scheduleMaintenanceEvent(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::MAINTENANCE_CRON_HOOK) !== false) {
            return;
        }

        $delay = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
        wp_schedule_event(time() + $delay, 'twicedaily', self::MAINTENANCE_CRON_HOOK);
    }

    public function purgeExpiredEntries(): void
    {
        $index = $this->getLocaleIndex();
        $registry = $this->getLocaleRegistry();
        $updated = false;

        foreach ($registry as $locale => $suffixes) {
            foreach ($suffixes as $suffixKey => $_present) {
                $entry = $this->loadLocaleEntry($locale, $suffixKey);

                if ($entry === null) {
                    if (isset($index[$locale][$suffixKey])) {
                        unset($index[$locale][$suffixKey]);
                        $this->removeMetricsForEntry($index, $locale, $suffixKey);

                        if ($this->isLocaleIndexEmpty($index, $locale)) {
                            unset($index[$locale]);
                        }

                        $updated = true;
                    }

                    continue;
                }

                $transientKey = isset($entry['transient_key']) && is_string($entry['transient_key'])
                    ? $entry['transient_key']
                    : '';
                $expiresAt = isset($entry['expires_at']) ? (int) $entry['expires_at'] : 0;

                $isExpired = $expiresAt > 0 && $expiresAt <= time();

                if (!$isExpired && $transientKey !== '' && function_exists('get_transient')) {
                    $value = get_transient($transientKey);
                    if ($value === false) {
                        $isExpired = true;
                    }
                }

                if (!$isExpired) {
                    continue;
                }

                if ($transientKey !== '' && function_exists('delete_transient')) {
                    delete_transient($transientKey);
                }

                if (isset($index[$locale][$suffixKey])) {
                    unset($index[$locale][$suffixKey]);
                    $this->removeMetricsForEntry($index, $locale, $suffixKey);

                    if ($this->isLocaleIndexEmpty($index, $locale)) {
                        unset($index[$locale]);
                    }

                    $updated = true;
                }
            }
        }

        if ($updated) {
            $this->persistLocaleIndex($index);
        }
    }

    public function forgetLocaleIndex(): void
    {
        $this->clearLocaleIndexStorage();

        $this->emitCacheEvent('index_forget', null, null);
    }

    public function rememberLocale(string $locale, ?string $suffix = null): void
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);

        $index = $this->getLocaleIndex();
        $suffixKey = $this->normalizeSuffixKey($normalizedSuffix);
        $transientKey = $this->buildTransientKey($normalizedLocale, $normalizedSuffix);

        if (!isset($this->pendingEntryExpirations[$normalizedLocale])) {
            $this->pendingEntryExpirations[$normalizedLocale] = [];
        }

        $this->pendingEntryExpirations[$normalizedLocale][$suffixKey] = time() + self::CACHE_TTL;

        $isNewKey = !isset($index[$normalizedLocale][$suffixKey])
            || $index[$normalizedLocale][$suffixKey] !== $transientKey;

        if (!isset($index[$normalizedLocale]) || !is_array($index[$normalizedLocale])) {
            $index[$normalizedLocale] = [];
        }

        $index[$normalizedLocale][$suffixKey] = $transientKey;
        if ($isNewKey) {
            $this->resetMetricsForEntry($index, $normalizedLocale, $suffixKey);
        }

        $this->persistLocaleIndex($index);
    }

    public function getCachedLocales(): array
    {
        $index = $this->getLocaleIndex();
        $entries = [];

        foreach ($index as $locale => $profiles) {
            foreach ($profiles as $suffixKey => $_key) {
                if ($suffixKey === self::METRICS_KEY) {
                    continue;
                }

                $entries[] = [
                    'locale' => $locale,
                    'suffix' => $this->suffixKeyToSuffix($suffixKey),
                ];
            }
        }

        return $entries;
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_\-]/', '', $locale);

        if ($normalized === null || $normalized === '') {
            return 'default';
        }

        return $normalized;
    }

    private function normalizeSuffixValue(?string $suffix): ?string
    {
        if ($suffix === null) {
            return null;
        }

        $trimmed = trim((string) $suffix);

        if ($trimmed === '') {
            return null;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '', $trimmed);

        if ($sanitized === null || $sanitized === '') {
            $sanitized = substr(md5($trimmed), 0, 12);
        }

        return strtolower($sanitized);
    }

    private function buildTransientKey(string $locale, ?string $suffix): string
    {
        return 'sidebar_jlg_full_html_' . $locale . ($suffix === null || $suffix === '' ? '' : '_' . $suffix);
    }

    private function normalizeSuffixKey(?string $normalizedSuffix): string
    {
        if ($normalizedSuffix === null || $normalizedSuffix === '') {
            return self::DEFAULT_SUFFIX_KEY;
        }

        if ($normalizedSuffix === self::METRICS_KEY) {
            return self::METRICS_SUFFIX_ALIAS;
        }

        return $normalizedSuffix;
    }

    private function suffixKeyToSuffix(string $suffixKey): ?string
    {
        if ($suffixKey === self::DEFAULT_SUFFIX_KEY) {
            return null;
        }

        if ($suffixKey === self::METRICS_SUFFIX_ALIAS) {
            return self::METRICS_KEY;
        }

        return $suffixKey;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getLocaleIndex(): array
    {
        if ($this->loadedLocaleIndex !== null) {
            return $this->loadedLocaleIndex;
        }

        $cached = $this->getLocaleIndexFromObjectCache();

        if ($cached !== null) {
            $this->loadedLocaleIndex = $this->normalizeStoredIndex($cached);

            return $this->loadedLocaleIndex;
        }

        $registry = $this->getLocaleRegistry();
        $index = [];

        foreach ($registry as $locale => $suffixes) {
            foreach ($suffixes as $suffixKey => $_flag) {
                $entry = $this->loadLocaleEntry($locale, $suffixKey);

                if ($entry === null) {
                    continue;
                }

                $transientKey = isset($entry['transient_key']) && is_string($entry['transient_key'])
                    ? $entry['transient_key']
                    : $this->buildTransientKey($locale, $this->suffixKeyToSuffix($suffixKey));

                if (!isset($index[$locale])) {
                    $index[$locale] = [];
                }

                $index[$locale][$suffixKey] = $transientKey;

                if (!isset($index[$locale][self::METRICS_KEY])) {
                    $index[$locale][self::METRICS_KEY] = [];
                }

                $index[$locale][self::METRICS_KEY][$suffixKey] = [
                    'hits' => isset($entry['hits']) ? max(0, (int) $entry['hits']) : 0,
                    'misses' => isset($entry['misses']) ? max(0, (int) $entry['misses']) : 0,
                ];
            }
        }

        if ($index === []) {
            $stored = $this->getLocaleRegistryRaw();

            if ($this->isLegacyRegistry($stored)) {
                $legacyIndex = $this->normalizeStoredIndex($stored);

                if ($legacyIndex !== []) {
                    $this->persistLocaleIndex($legacyIndex);
                    $index = $legacyIndex;
                }
            }
        }

        $this->storeLocaleIndexInObjectCache($index);
        $this->loadedLocaleIndex = $index;

        return $index;
    }

    /**
     * @param mixed $stored
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizeStoredIndex($stored): array
    {
        if (!is_array($stored)) {
            return [];
        }

        $normalized = [];

        foreach ($stored as $localeKey => $profiles) {
            if (is_int($localeKey)) {
                $parsed = $this->parseLocaleEntry($profiles);

                if ($parsed === null) {
                    continue;
                }

                $locale = $parsed['locale'];
                $suffixKey = $this->normalizeSuffixKey($parsed['suffix']);

                if (!isset($normalized[$locale]) || !is_array($normalized[$locale])) {
                    $normalized[$locale] = [];
                }

                $normalized[$locale][$suffixKey] = $this->buildTransientKey($locale, $parsed['suffix']);

                continue;
            }

            $locale = $this->normalizeLocale((string) $localeKey);

            if ($locale === '') {
                continue;
            }

            if (!is_array($profiles)) {
                continue;
            }

            if (!isset($normalized[$locale]) || !is_array($normalized[$locale])) {
                $normalized[$locale] = [];
            }

            $rawMetrics = [];
            if (isset($profiles[self::METRICS_KEY]) && is_array($profiles[self::METRICS_KEY])) {
                $rawMetrics = $profiles[self::METRICS_KEY];
            }

            foreach ($profiles as $suffixKey => $transientKey) {
                if ($suffixKey === self::METRICS_KEY) {
                    continue;
                }

                $normalizedSuffixKey = $this->normalizeStoredSuffixKey($suffixKey);
                $suffix = $this->suffixKeyToSuffix($normalizedSuffixKey);
                $finalTransientKey = is_string($transientKey) && $transientKey !== ''
                    ? $transientKey
                    : $this->buildTransientKey($locale, $suffix);

                $normalized[$locale][$normalizedSuffixKey] = $finalTransientKey;
            }

            if ($rawMetrics !== []) {
                if (!isset($normalized[$locale][self::METRICS_KEY]) || !is_array($normalized[$locale][self::METRICS_KEY])) {
                    $normalized[$locale][self::METRICS_KEY] = [];
                }

                foreach ($rawMetrics as $metricsSuffixKey => $metricsData) {
                    $normalizedSuffixKey = $this->normalizeStoredSuffixKey($metricsSuffixKey);

                    $hits = 0;
                    $misses = 0;

                    if (is_array($metricsData)) {
                        $hits = isset($metricsData['hits']) ? max(0, (int) $metricsData['hits']) : 0;
                        $misses = isset($metricsData['misses']) ? max(0, (int) $metricsData['misses']) : 0;
                    } elseif (is_numeric($metricsData)) {
                        $hits = max(0, (int) $metricsData);
                    }

                    $normalized[$locale][self::METRICS_KEY][$normalizedSuffixKey] = [
                        'hits' => $hits,
                        'misses' => $misses,
                    ];
                }
            }
        }

        foreach ($normalized as $locale => &$profiles) {
            if (!is_array($profiles)) {
                unset($normalized[$locale]);
                continue;
            }

            $suffixKeys = [];
            foreach ($profiles as $suffixKey => $value) {
                if ($suffixKey === self::METRICS_KEY) {
                    continue;
                }

                if (!is_string($suffixKey) || $suffixKey === '') {
                    unset($profiles[$suffixKey]);
                    continue;
                }

                $suffixKeys[] = $suffixKey;
            }

            if ($suffixKeys === []) {
                unset($normalized[$locale]);
                continue;
            }

            $metrics = isset($profiles[self::METRICS_KEY]) && is_array($profiles[self::METRICS_KEY])
                ? $profiles[self::METRICS_KEY]
                : [];

            $finalMetrics = [];
            foreach ($suffixKeys as $suffixKey) {
                $bucket = $metrics[$suffixKey] ?? ['hits' => 0, 'misses' => 0];
                $hits = isset($bucket['hits']) ? max(0, (int) $bucket['hits']) : 0;
                $misses = isset($bucket['misses']) ? max(0, (int) $bucket['misses']) : 0;

                $finalMetrics[$suffixKey] = [
                    'hits' => $hits,
                    'misses' => $misses,
                ];
            }

            if ($finalMetrics !== []) {
                $profiles[self::METRICS_KEY] = $finalMetrics;
            } else {
                unset($profiles[self::METRICS_KEY]);
            }
        }
        unset($profiles);

        return $normalized;
    }

    private function normalizeStoredSuffixKey($suffixKey): string
    {
        if (!is_string($suffixKey) || $suffixKey === '' || $suffixKey === self::DEFAULT_SUFFIX_KEY) {
            return self::DEFAULT_SUFFIX_KEY;
        }

        if ($suffixKey === self::METRICS_KEY || $suffixKey === self::METRICS_SUFFIX_ALIAS) {
            return self::METRICS_SUFFIX_ALIAS;
        }

        $normalized = $this->normalizeSuffixValue($suffixKey);

        return $normalized === null ? self::DEFAULT_SUFFIX_KEY : $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function persistLocaleIndex(array $index): void
    {
        $normalized = $this->normalizeStoredIndex($index);
        $this->loadedLocaleIndex = $normalized;

        $existingRegistry = $this->getLocaleRegistry();
        $seenEntries = [];

        foreach ($normalized as $locale => $profiles) {
            if (!is_array($profiles)) {
                continue;
            }

            if (!isset($seenEntries[$locale])) {
                $seenEntries[$locale] = [];
            }

            $metricsBuckets = isset($profiles[self::METRICS_KEY]) && is_array($profiles[self::METRICS_KEY])
                ? $profiles[self::METRICS_KEY]
                : [];

            foreach ($profiles as $suffixKey => $storedKey) {
                if ($suffixKey === self::METRICS_KEY) {
                    continue;
                }

                $suffix = $this->suffixKeyToSuffix($suffixKey);
                $transientKey = is_string($storedKey) && $storedKey !== ''
                    ? $storedKey
                    : $this->buildTransientKey($locale, $suffix);

                $metrics = isset($metricsBuckets[$suffixKey]) && is_array($metricsBuckets[$suffixKey])
                    ? $metricsBuckets[$suffixKey]
                    : ['hits' => 0, 'misses' => 0];

                $currentEntry = $this->loadLocaleEntry($locale, $suffixKey);
                $expiresAt = $currentEntry['expires_at'] ?? null;

                if (isset($this->pendingEntryExpirations[$locale][$suffixKey])) {
                    $expiresAt = $this->pendingEntryExpirations[$locale][$suffixKey];
                }

                if (!is_int($expiresAt) || $expiresAt <= 0) {
                    $expiresAt = time() + self::CACHE_TTL;
                }

                $entryData = [
                    'transient_key' => $transientKey,
                    'hits' => isset($metrics['hits']) ? max(0, (int) $metrics['hits']) : 0,
                    'misses' => isset($metrics['misses']) ? max(0, (int) $metrics['misses']) : 0,
                    'expires_at' => $expiresAt,
                ];

                $this->storeLocaleEntry($locale, $suffixKey, $entryData);
                $seenEntries[$locale][$suffixKey] = true;
            }
        }

        $this->pendingEntryExpirations = [];

        foreach ($existingRegistry as $locale => $suffixes) {
            foreach ($suffixes as $suffixKey => $_flag) {
                if (!isset($seenEntries[$locale][$suffixKey])) {
                    $this->deleteLocaleEntry($locale, $suffixKey);
                }
            }
        }

        $registryToStore = [];
        foreach ($seenEntries as $locale => $suffixes) {
            foreach ($suffixes as $suffixKey => $_flag) {
                if (!isset($registryToStore[$locale])) {
                    $registryToStore[$locale] = [];
                }

                $registryToStore[$locale][$suffixKey] = true;
            }
        }

        if ($registryToStore === []) {
            $this->deleteLocaleIndexOption();
        } else {
            $this->saveLocaleRegistry($registryToStore);
        }

        $this->storeLocaleIndexInObjectCache($normalized);
    }

    private function clearLocaleIndexStorage(): void
    {
        $registry = $this->getLocaleRegistry();

        foreach ($registry as $locale => $suffixes) {
            foreach ($suffixes as $suffixKey => $_flag) {
                $this->deleteLocaleEntry($locale, $suffixKey);
            }
        }

        $this->loadedLocaleIndex = [];
        $this->pendingEntryExpirations = [];
        $this->deleteLocaleIndexOption();
        $this->deleteLocaleIndexFromObjectCache();
    }

    private function deleteLocaleIndexOption(): void
    {
        delete_option($this->optionName);
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function getLocaleIndexFromObjectCache(): ?array
    {
        if (!function_exists('wp_cache_get')) {
            return null;
        }

        $value = wp_cache_get(self::LOCALE_INDEX_CACHE_KEY, self::OBJECT_CACHE_GROUP);

        if ($value === false) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function storeLocaleIndexInObjectCache(array $index): void
    {
        if (!function_exists('wp_cache_set')) {
            return;
        }

        wp_cache_set(self::LOCALE_INDEX_CACHE_KEY, $index, self::OBJECT_CACHE_GROUP, self::CACHE_TTL);
    }

    private function deleteLocaleIndexFromObjectCache(): void
    {
        if (!function_exists('wp_cache_delete')) {
            return;
        }

        wp_cache_delete(self::LOCALE_INDEX_CACHE_KEY, self::OBJECT_CACHE_GROUP);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function getLocaleRegistry(): array
    {
        $raw = $this->getLocaleRegistryRaw();

        if ($this->isLegacyRegistry($raw)) {
            return [];
        }

        $registry = [];

        foreach ($raw as $locale => $suffixes) {
            if (!is_array($suffixes)) {
                continue;
            }

            $normalizedLocale = $this->normalizeLocale((string) $locale);

            if ($normalizedLocale === '') {
                continue;
            }

            foreach ($suffixes as $suffixKey => $flag) {
                if ($suffixKey === self::METRICS_KEY) {
                    continue;
                }

                if ($flag === false) {
                    continue;
                }

                $normalizedSuffixKey = $this->normalizeStoredSuffixKey($suffixKey);

                if (!isset($registry[$normalizedLocale])) {
                    $registry[$normalizedLocale] = [];
                }

                $registry[$normalizedLocale][$normalizedSuffixKey] = true;
            }
        }

        return $registry;
    }

    /**
     * @return array<string, mixed>
     */
    private function getLocaleRegistryRaw(): array
    {
        $stored = get_option($this->optionName, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param array<string, array<string, bool>> $registry
     */
    private function saveLocaleRegistry(array $registry): void
    {
        $normalized = [];

        foreach ($registry as $locale => $suffixes) {
            if (!is_array($suffixes)) {
                continue;
            }

            $normalizedLocale = $this->normalizeLocale((string) $locale);

            if ($normalizedLocale === '') {
                continue;
            }

            foreach ($suffixes as $suffixKey => $flag) {
                if ($flag === false) {
                    continue;
                }

                $normalizedSuffixKey = $this->normalizeStoredSuffixKey($suffixKey);

                if (!isset($normalized[$normalizedLocale])) {
                    $normalized[$normalizedLocale] = [];
                }

                $normalized[$normalizedLocale][$normalizedSuffixKey] = true;
            }
        }

        update_option($this->optionName, $normalized, 'no');
    }

    private function getLocaleEntryCacheKey(string $locale, string $suffixKey): string
    {
        return self::LOCALE_ENTRY_CACHE_PREFIX . $locale . '|' . $suffixKey;
    }

    private function getLocaleEntryOptionName(string $locale, string $suffixKey): string
    {
        return self::LOCALE_ENTRY_OPTION_PREFIX . strtolower($locale . '_' . $suffixKey);
    }

    private function loadLocaleEntry(string $locale, string $suffixKey): ?array
    {
        $cacheKey = $this->getLocaleEntryCacheKey($locale, $suffixKey);

        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($cacheKey, self::OBJECT_CACHE_GROUP);

            if ($cached !== false) {
                return is_array($cached) ? $cached : null;
            }
        }

        $optionName = $this->getLocaleEntryOptionName($locale, $suffixKey);
        $stored = get_option($optionName, null);

        if (!is_array($stored)) {
            return null;
        }

        if (function_exists('wp_cache_set')) {
            wp_cache_set($cacheKey, $stored, self::OBJECT_CACHE_GROUP, self::CACHE_TTL);
        }

        return $stored;
    }

    private function storeLocaleEntry(string $locale, string $suffixKey, array $entry): void
    {
        $optionName = $this->getLocaleEntryOptionName($locale, $suffixKey);

        $payload = [
            'transient_key' => isset($entry['transient_key']) && is_string($entry['transient_key'])
                ? $entry['transient_key']
                : '',
            'hits' => isset($entry['hits']) ? max(0, (int) $entry['hits']) : 0,
            'misses' => isset($entry['misses']) ? max(0, (int) $entry['misses']) : 0,
            'expires_at' => isset($entry['expires_at']) ? (int) $entry['expires_at'] : 0,
        ];

        update_option($optionName, $payload, 'no');

        if (function_exists('wp_cache_set')) {
            wp_cache_set($this->getLocaleEntryCacheKey($locale, $suffixKey), $payload, self::OBJECT_CACHE_GROUP, self::CACHE_TTL);
        }
    }

    private function deleteLocaleEntry(string $locale, string $suffixKey): void
    {
        $optionName = $this->getLocaleEntryOptionName($locale, $suffixKey);
        delete_option($optionName);

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($this->getLocaleEntryCacheKey($locale, $suffixKey), self::OBJECT_CACHE_GROUP);
        }
    }

    private function isLegacyRegistry($stored): bool
    {
        if (!is_array($stored)) {
            return false;
        }

        foreach ($stored as $locale => $profiles) {
            if (is_int($locale)) {
                return true;
            }

            if (!is_array($profiles)) {
                return true;
            }

            foreach ($profiles as $suffixKey => $value) {
                if ($suffixKey === self::METRICS_KEY) {
                    return true;
                }

                if (!is_bool($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function removeMetricsForEntry(array &$index, string $locale, string $suffixKey): void
    {
        if (!isset($index[$locale][self::METRICS_KEY]) || !is_array($index[$locale][self::METRICS_KEY])) {
            return;
        }

        if (isset($index[$locale][self::METRICS_KEY][$suffixKey])) {
            unset($index[$locale][self::METRICS_KEY][$suffixKey]);
        }

        if ($index[$locale][self::METRICS_KEY] === []) {
            unset($index[$locale][self::METRICS_KEY]);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function isLocaleIndexEmpty(array $index, string $locale): bool
    {
        if (!isset($index[$locale])) {
            return true;
        }

        $entries = $index[$locale];
        if (isset($entries[self::METRICS_KEY])) {
            unset($entries[self::METRICS_KEY]);
        }

        return $entries === [];
    }

    private function emitCacheEvent(string $event, ?string $locale, ?string $suffix, array $context = []): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        if (isset($context['bytes']) && !isset($context['size'])) {
            $context['size'] = $context['bytes'];
        }

        $contextMetrics = null;
        if (isset($context['metrics']) && is_array($context['metrics'])) {
            $contextMetrics = [
                'hits' => isset($context['metrics']['hits']) ? (int) $context['metrics']['hits'] : 0,
                'misses' => isset($context['metrics']['misses']) ? (int) $context['metrics']['misses'] : 0,
            ];

            unset($context['metrics']);
        }

        $payload = [
            'locale' => $locale,
            'suffix' => $suffix,
            'context' => $context,
        ];

        $metrics = $this->getMetricsSnapshot($locale, $suffix);
        if ($metrics === null && $contextMetrics !== null) {
            $metrics = $contextMetrics;
        }

        if ($metrics !== null) {
            $payload['metrics'] = $metrics;
        }

        do_action('sidebar_jlg_cache_event', $event, $payload);
    }

    private function getMetricsSnapshot(?string $locale, ?string $suffix): ?array
    {
        if ($locale === null) {
            return null;
        }

        $index = $this->getLocaleIndex();
        if (!isset($index[$locale])) {
            return null;
        }

        $suffixKey = $this->normalizeSuffixKey($this->normalizeSuffixValue($suffix));
        $metrics = $index[$locale][self::METRICS_KEY][$suffixKey] ?? null;

        if (!is_array($metrics)) {
            return null;
        }

        $hits = isset($metrics['hits']) ? (int) $metrics['hits'] : 0;
        $misses = isset($metrics['misses']) ? (int) $metrics['misses'] : 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function ensureMetricsBucket(array &$index, string $locale, string $suffixKey): void
    {
        if (!isset($index[$locale]) || !is_array($index[$locale])) {
            $index[$locale] = [];
        }

        if (!isset($index[$locale][self::METRICS_KEY]) || !is_array($index[$locale][self::METRICS_KEY])) {
            $index[$locale][self::METRICS_KEY] = [];
        }

        if (!isset($index[$locale][self::METRICS_KEY][$suffixKey]) || !is_array($index[$locale][self::METRICS_KEY][$suffixKey])) {
            $index[$locale][self::METRICS_KEY][$suffixKey] = [
                'hits' => 0,
                'misses' => 0,
            ];
        }

        $index[$locale][self::METRICS_KEY][$suffixKey]['hits'] = isset($index[$locale][self::METRICS_KEY][$suffixKey]['hits'])
            ? (int) $index[$locale][self::METRICS_KEY][$suffixKey]['hits']
            : 0;
        $index[$locale][self::METRICS_KEY][$suffixKey]['misses'] = isset($index[$locale][self::METRICS_KEY][$suffixKey]['misses'])
            ? (int) $index[$locale][self::METRICS_KEY][$suffixKey]['misses']
            : 0;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function resetMetricsForEntry(array &$index, string $locale, string $suffixKey): void
    {
        $this->ensureMetricsBucket($index, $locale, $suffixKey);
        $index[$locale][self::METRICS_KEY][$suffixKey]['hits'] = 0;
        $index[$locale][self::METRICS_KEY][$suffixKey]['misses'] = 0;
    }

    private function recordCacheMetric(string $locale, ?string $suffix, string $metric): void
    {
        $index = $this->getLocaleIndex();
        $suffixKey = $this->normalizeSuffixKey($this->normalizeSuffixValue($suffix));

        $this->ensureMetricsBucket($index, $locale, $suffixKey);

        if ($metric === 'hit') {
            $index[$locale][self::METRICS_KEY][$suffixKey]['hits']++;
        } elseif ($metric === 'miss') {
            $index[$locale][self::METRICS_KEY][$suffixKey]['misses']++;
        }

        $this->persistLocaleIndex($index);
    }

    private function parseLocaleEntry($entry): ?array
    {
        if (is_array($entry)) {
            $locale = isset($entry['locale']) ? (string) $entry['locale'] : '';
            $suffix = $entry['suffix'] ?? null;
            $suffix = is_string($suffix) ? $suffix : null;
        } elseif (is_string($entry)) {
            $locale = $entry;
            $suffix = null;
        } else {
            return null;
        }

        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);

        if ($normalizedLocale === '') {
            return null;
        }

        return [
            'locale' => $normalizedLocale,
            'suffix' => $normalizedSuffix,
        ];
    }
}
