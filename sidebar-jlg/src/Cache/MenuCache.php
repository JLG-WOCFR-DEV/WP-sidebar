<?php

namespace JLG\Sidebar\Cache;

class MenuCache
{
    private const CACHE_TTL = 86400;
    private string $optionName = 'sidebar_jlg_cached_locales';

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
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffix($suffix);

        return 'sidebar_jlg_full_html_' . $normalizedLocale . $normalizedSuffix;
    }

    public function get(string $locale, ?string $suffix = null)
    {
        $value = get_transient($this->getTransientKey($locale, $suffix));

        if ($value === false) {
            return false;
        }

        if (!is_string($value)) {
            $this->delete($locale, $suffix);

            if (function_exists('error_log')) {
                $suffixNotice = '';
                $normalizedSuffix = $this->normalizeSuffixValue($suffix);
                if ($normalizedSuffix !== null) {
                    $suffixNotice = ' and profile ' . $normalizedSuffix;
                }

                error_log(
                    '[Sidebar JLG] Invalid sidebar cache payload cleared for locale '
                    . $this->normalizeLocale($locale)
                    . $suffixNotice
                );
            }

            return false;
        }

        return $value;
    }

    public function set(string $locale, string $html, ?string $suffix = null): void
    {
        set_transient($this->getTransientKey($locale, $suffix), $html, self::CACHE_TTL);
        $this->rememberLocale($locale, $suffix);
    }

    public function delete(string $locale, ?string $suffix = null): void
    {
        delete_transient($this->getTransientKey($locale, $suffix));

        if ($suffix !== null) {
            // Clear legacy cache entries that were stored without profile context.
            delete_transient($this->getTransientKey($locale, null));
        }

        $this->removeLocaleFromIndex($locale, $suffix);
    }

    public function clear(): void
    {
        $cachedEntries = $this->getCachedLocales();

        foreach ($cachedEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $locale = $entry['locale'] ?? null;
            if (!is_string($locale) || $locale === '') {
                continue;
            }

            $suffix = $entry['suffix'] ?? null;
            $suffix = is_string($suffix) ? $suffix : null;

            $this->delete($locale, $suffix);
        }

        delete_transient('sidebar_jlg_full_html');

        if (!empty($cachedEntries)) {
            delete_option($this->optionName);
        }
    }

    public function forgetLocaleIndex(): void
    {
        delete_option($this->optionName);
    }

    private function removeLocaleFromIndex(string $locale, ?string $suffix): void
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);

        if ($normalizedLocale === '') {
            return;
        }

        $existing = get_option($this->optionName, null);

        if ($existing === null) {
            return;
        }

        if (!is_array($existing) || $existing === []) {
            delete_option($this->optionName);

            return;
        }

        $remaining = [];

        foreach ($existing as $entry) {
            $parsed = $this->parseLocaleEntry($entry);

            if ($parsed === null) {
                continue;
            }

            if ($parsed['locale'] === $normalizedLocale && $parsed['suffix'] === $normalizedSuffix) {
                continue;
            }

            $remaining[] = $this->createLocaleEntry($parsed['locale'], $parsed['suffix']);
        }

        if ($remaining === []) {
            delete_option($this->optionName);

            return;
        }

        update_option($this->optionName, $remaining, 'no');
    }

    public function rememberLocale(string $locale, ?string $suffix = null): void
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedSuffix = $this->normalizeSuffixValue($suffix);

        if ($normalizedLocale === '') {
            return;
        }

        $existing = get_option($this->optionName, null);
        $entry = $this->createLocaleEntry($normalizedLocale, $normalizedSuffix);

        if ($existing === null) {
            add_option($this->optionName, [$entry], '', 'no');
            return;
        }

        if (!is_array($existing)) {
            $existing = [];
        }

        foreach ($existing as $stored) {
            $parsed = $this->parseLocaleEntry($stored);
            if ($parsed === null) {
                continue;
            }

            if ($parsed['locale'] === $normalizedLocale && $parsed['suffix'] === $normalizedSuffix) {
                return;
            }
        }

        $existing[] = $entry;
        update_option($this->optionName, $existing, 'no');
    }

    public function getCachedLocales(): array
    {
        $stored = get_option($this->optionName, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $normalized = [];

        foreach ($stored as $entry) {
            $parsed = $this->parseLocaleEntry($entry);

            if ($parsed === null) {
                continue;
            }

            $key = $parsed['locale'] . '|' . ($parsed['suffix'] ?? '');
            $normalized[$key] = $parsed;
        }

        return array_values($normalized);
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_\-]/', '', $locale);

        if ($normalized === null || $normalized === '') {
            return 'default';
        }

        return $normalized;
    }

    private function normalizeSuffix(?string $suffix): string
    {
        $normalized = $this->normalizeSuffixValue($suffix);

        if ($normalized === null || $normalized === '') {
            return '';
        }

        return '_' . $normalized;
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

    private function createLocaleEntry(string $locale, ?string $suffix): array
    {
        $entry = ['locale' => $locale];

        if ($suffix !== null && $suffix !== '') {
            $entry['suffix'] = $suffix;
        }

        return $entry;
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
