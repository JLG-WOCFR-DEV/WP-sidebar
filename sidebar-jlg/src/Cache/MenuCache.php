<?php

namespace JLG\Sidebar\Cache;

class MenuCache
{
    private const CACHE_TTL = 86400;
    private const OBJECT_CACHE_GROUP = 'sidebar_jlg';
    private const LOCALE_INDEX_CACHE_KEY = 'menu_cache_index';
    private const DEFAULT_SUFFIX_KEY = '__default__';
    private const METRICS_KEY = 'metrics';
    private const METRICS_SUFFIX_ALIAS = 'metrics_profile';

    private string $optionName = 'sidebar_jlg_cached_locales';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $loadedLocaleIndex = null;

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

        if (isset($index[$normalizedLocale][$suffixKey]) && $index[$normalizedLocale][$suffixKey] === $transientKey) {
            return;
        }

        if (!isset($index[$normalizedLocale]) || !is_array($index[$normalizedLocale])) {
            $index[$normalizedLocale] = [];
        }

        $index[$normalizedLocale][$suffixKey] = $transientKey;
        $this->resetMetricsForEntry($index, $normalizedLocale, $suffixKey);

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

        $stored = get_option($this->optionName, []);
        $normalized = $this->normalizeStoredIndex($stored);

        $this->storeLocaleIndexInObjectCache($normalized);
        $this->loadedLocaleIndex = $normalized;

        return $normalized;
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

        if ($this->loadedLocaleIndex !== null && $this->loadedLocaleIndex === $normalized) {
            return;
        }

        $this->loadedLocaleIndex = $normalized;

        if ($normalized === []) {
            $this->storeLocaleIndexInObjectCache($normalized);
            $this->deleteLocaleIndexOption();

            return;
        }

        $this->storeLocaleIndexInObjectCache($normalized);
        update_option($this->optionName, $normalized, 'no');
    }

    private function clearLocaleIndexStorage(): void
    {
        $this->loadedLocaleIndex = [];
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
