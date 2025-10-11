<?php

namespace JLG\Sidebar\Cache;

class MenuCache
{
    private const CACHE_TTL = 86400;
    private const OBJECT_CACHE_GROUP = 'sidebar_jlg';
    private const LOCALE_INDEX_CACHE_KEY = 'menu_cache_index';
    private const DEFAULT_SUFFIX_KEY = '__default__';

    private string $optionName = 'sidebar_jlg_cached_locales';

    /**
     * @var array<string, array<string, string>>|null
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
            $this->emitCacheEvent('miss', $normalizedLocale, $normalizedSuffix, [
                'key' => $transientKey,
            ]);

            return false;
        }

        if (!is_string($value)) {
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
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);
        $transientKey = $this->buildTransientKey($normalizedLocale, $normalizedSuffix);

        delete_transient($transientKey);

        if ($normalizedSuffix !== null) {
            // Clear legacy cache entries that were stored without profile context.
            delete_transient($this->buildTransientKey($normalizedLocale, null));
        }

        $this->removeLocaleFromIndex($normalizedLocale, $normalizedSuffix);

        $this->emitCacheEvent('delete', $normalizedLocale, $normalizedSuffix, [
            'key' => $transientKey,
        ]);
    }

    public function clear(): void
    {
        $index = $this->getLocaleIndex();
        $transientKeys = [];

        foreach ($index as $locale => $profiles) {
            foreach ($profiles as $suffixKey => $storedKey) {
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

    private function removeLocaleFromIndex(string $locale, ?string $suffix): void
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);

        $index = $this->getLocaleIndex();
        if (!isset($index[$normalizedLocale])) {
            return;
        }

        $suffixKey = $this->normalizeSuffixKey($normalizedSuffix);

        if (!isset($index[$normalizedLocale][$suffixKey])) {
            return;
        }

        unset($index[$normalizedLocale][$suffixKey]);

        if ($index[$normalizedLocale] === []) {
            unset($index[$normalizedLocale]);
        }

        $this->persistLocaleIndex($index);
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

        if (!isset($index[$normalizedLocale])) {
            $index[$normalizedLocale] = [];
        }

        $index[$normalizedLocale][$suffixKey] = $transientKey;

        $this->persistLocaleIndex($index);
    }

    public function getCachedLocales(): array
    {
        $index = $this->getLocaleIndex();
        $entries = [];

        foreach ($index as $locale => $profiles) {
            foreach ($profiles as $suffixKey => $_key) {
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

        return $normalizedSuffix;
    }

    private function suffixKeyToSuffix(string $suffixKey): ?string
    {
        if ($suffixKey === self::DEFAULT_SUFFIX_KEY) {
            return null;
        }

        return $suffixKey;
    }

    /**
     * @return array<string, array<string, string>>
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
     * @return array<string, array<string, string>>
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

            foreach ($profiles as $suffixKey => $transientKey) {
                $normalizedSuffixKey = $this->normalizeStoredSuffixKey($suffixKey);
                $suffix = $this->suffixKeyToSuffix($normalizedSuffixKey);
                $finalTransientKey = is_string($transientKey) && $transientKey !== ''
                    ? $transientKey
                    : $this->buildTransientKey($locale, $suffix);

                $normalized[$locale][$normalizedSuffixKey] = $finalTransientKey;
            }
        }

        foreach ($normalized as $locale => $profiles) {
            if (!is_array($profiles) || $profiles === []) {
                unset($normalized[$locale]);
            }
        }

        return $normalized;
    }

    private function normalizeStoredSuffixKey($suffixKey): string
    {
        if (!is_string($suffixKey) || $suffixKey === '' || $suffixKey === self::DEFAULT_SUFFIX_KEY) {
            return self::DEFAULT_SUFFIX_KEY;
        }

        $normalized = $this->normalizeSuffixValue($suffixKey);

        return $normalized === null ? self::DEFAULT_SUFFIX_KEY : $normalized;
    }

    /**
     * @param array<string, array<string, string>> $index
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
     * @return array<string, array<string, string>>|null
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
     * @param array<string, array<string, string>> $index
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

    private function emitCacheEvent(string $event, ?string $locale, ?string $suffix, array $context = []): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        do_action('sidebar_jlg_cache_event', $event, [
            'locale' => $locale,
            'suffix' => $suffix,
            'context' => $context,
        ]);
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
