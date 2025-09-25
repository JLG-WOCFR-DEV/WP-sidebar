<?php

namespace JLG\Sidebar\Frontend;

class Templating
{
    public static function renderSocialIcons(array $socialIcons, array $allIcons, string $orientation): string
    {
        if ($socialIcons === []) {
            return '';
        }

        $iconsMarkup = [];

        foreach ($socialIcons as $social) {
            if (empty($social['icon']) || empty($social['url']) || !isset($allIcons[$social['icon']])) {
                continue;
            }

            $customLabel = '';

            if (isset($social['label']) && is_string($social['label'])) {
                $customLabel = trim($social['label']);
            }

            $defaultLabel = self::humanizeIconKey((string) $social['icon']);
            $ariaLabel = $customLabel !== '' ? $customLabel : $defaultLabel;

            $iconsMarkup[] = sprintf(
                '<a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">%3$s</a>',
                esc_url($social['url']),
                esc_attr($ariaLabel),
                wp_kses_post($allIcons[$social['icon']])
            );
        }

        if ($iconsMarkup === []) {
            return '';
        }

        $classes = 'social-icons';
        $orientationClass = trim($orientation);
        if ($orientationClass !== '') {
            $classes .= ' ' . $orientationClass;
        }

        return sprintf(
            '<div class="%1$s">%2$s</div>',
            esc_attr($classes),
            implode('', $iconsMarkup)
        );
    }

    private static function humanizeIconKey(string $iconKey): string
    {
        $readable = str_replace(['_', '-'], ' ', strtolower($iconKey));

        if (strpos($readable, 'custom ') === 0) {
            $readable = trim(substr($readable, strlen('custom ')));
        }

        $readable = trim($readable);

        if ($readable === '') {
            return __('Lien social', 'sidebar-jlg');
        }

        return ucwords($readable);
    }
}
