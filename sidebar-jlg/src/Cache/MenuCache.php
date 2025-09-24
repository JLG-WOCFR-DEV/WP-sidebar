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

    public function getTransientKey(string $locale): string
    {
        $normalized = $this->normalizeLocale($locale);

        return 'sidebar_jlg_full_html_' . $normalized;
    }

    public function get(string $locale)
    {
        return get_transient($this->getTransientKey($locale));
    }

    public function set(string $locale, string $html): void
    {
        set_transient($this->getTransientKey($locale), $html, self::CACHE_TTL);
        $this->rememberLocale($locale);
    }

    public function delete(string $locale): void
    {
        delete_transient($this->getTransientKey($locale));
    }

    public function clear(): void
    {
        $cachedLocales = $this->getCachedLocales();

        foreach ($cachedLocales as $locale) {
            $this->delete($locale);
        }

        delete_transient('sidebar_jlg_full_html');

        if (!empty($cachedLocales)) {
            delete_option($this->optionName);
        }
    }

    public function forgetLocaleIndex(): void
    {
        delete_option($this->optionName);
    }

    public function rememberLocale(string $locale): void
    {
        $normalized = $this->normalizeLocale($locale);

        if ($normalized === '') {
            return;
        }

        $existing = get_option($this->optionName, null);

        if ($existing === null) {
            add_option($this->optionName, [$normalized], '', 'no');
            return;
        }

        if (!is_array($existing)) {
            $existing = [];
        }

        if (in_array($normalized, $existing, true)) {
            return;
        }

        $existing[] = $normalized;
        update_option($this->optionName, $existing, 'no');
    }

    public function getCachedLocales(): array
    {
        $stored = get_option($this->optionName, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $normalized = [];

        foreach ($stored as $locale) {
            $normalizedLocale = $this->normalizeLocale($locale);
            if ($normalizedLocale !== '') {
                $normalized[] = $normalizedLocale;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_\-]/', '', $locale);

        if ($normalized === null || $normalized === '') {
            return 'default';
        }

        return $normalized;
    }
}
