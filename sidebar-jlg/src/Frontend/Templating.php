<?php

namespace JLG\Sidebar\Frontend;

class Templating
{
    private const ICON_COLOR_SUFFIXES = ['white', 'black'];

    public static function makeInlineIconDecorative(string $markup): string
    {
        if (stripos($markup, '<svg') === false) {
            return $markup;
        }

        $normalized = preg_replace_callback(
            '/<svg\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[1];

                $attributes = preg_replace("/\s+aria-hidden=(?:\"|')[^\"']*(?:\"|')/i", '', $attributes) ?? $attributes;
                $attributes = preg_replace("/\s+focusable=(?:\"|')[^\"']*(?:\"|')/i", '', $attributes) ?? $attributes;
                $attributes = preg_replace("/\s+role=(?:\"|')[^\"']*(?:\"|')/i", '', $attributes) ?? $attributes;

                $cleanAttributes = trim($attributes);

                $openingTag = '<svg';

                if ($cleanAttributes !== '') {
                    $openingTag .= ' ' . $cleanAttributes;
                }

                return $openingTag . ' aria-hidden="true" focusable="false" role="presentation">';
            },
            $markup
        );

        if ($normalized === null) {
            return $markup;
        }

        return $normalized;
    }

    public static function renderSocialIcons(array $socialIcons, array $allIcons, string $orientation): string
    {
        if ($socialIcons === []) {
            return '';
        }

        $iconsMarkup = [];

        foreach ($socialIcons as $social) {
            if (empty($social['url'])) {
                continue;
            }

            $customLabel = '';

            if (isset($social['label']) && is_string($social['label'])) {
                $customLabel = trim($social['label']);
            }

            $iconKey = isset($social['icon']) ? (string) $social['icon'] : '';
            $iconMarkup = $iconKey !== '' && isset($allIcons[$iconKey]) ? (string) $allIcons[$iconKey] : null;
            $isDecorativeInlineIcon = false;

            if ($iconMarkup !== null && stripos($iconMarkup, '<svg') !== false) {
                $iconMarkup = self::makeInlineIconDecorative($iconMarkup);
                $isDecorativeInlineIcon = true;
            }

            if ($iconMarkup !== null) {
                $iconMarkup = IconHelpers::makeInlineIconDecorative($iconMarkup);
            }

            $defaultLabel = self::humanizeIconKey($iconKey);
            $ariaLabel = $customLabel !== '' ? $customLabel : $defaultLabel;

            $href = esc_url($social['url']);

            if ($href === '') {
                continue;
            }

            $linkClasses = [];

            if ($iconMarkup === null) {
                $linkClasses[] = 'no-icon';
            }

            $classAttribute = $linkClasses === [] ? '' : sprintf(' class="%s"', esc_attr(implode(' ', $linkClasses)));

            $content = $iconMarkup ?? sprintf('<span class="no-icon-label">%s</span>', esc_html($ariaLabel));

            if ($isDecorativeInlineIcon && $iconMarkup !== null) {
                $content = sprintf('<span aria-hidden="true" focusable="false">%s</span>', $iconMarkup);
            }

            $newWindowAnnouncement = __('s’ouvre dans une nouvelle fenêtre', 'sidebar-jlg');
            $announcedLabel = sprintf(
                /* translators: %s: Social network label. */
                __('%s – s’ouvre dans une nouvelle fenêtre', 'sidebar-jlg'),
                $ariaLabel
            );
            $screenReaderSuffix = sprintf('<span class="screen-reader-text">%s</span>', esc_html($newWindowAnnouncement));

            $iconsMarkup[] = sprintf(
                '<a href="%1$s"%2$s target="_blank" rel="noopener noreferrer" aria-label="%3$s">%4$s%5$s</a>',
                $href,
                $classAttribute,
                esc_attr($announcedLabel),
                $content,
                $screenReaderSuffix
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

        if ($readable !== '' && self::ICON_COLOR_SUFFIXES !== []) {
            $suffixPattern = sprintf(
                '/\b(?:%s)\s*$/u',
                implode('|', array_map(static fn (string $suffix): string => preg_quote($suffix, '/'), self::ICON_COLOR_SUFFIXES))
            );

            $readable = (string) preg_replace($suffixPattern, '', $readable);
            $readable = trim($readable);
        }

        if ($readable === '') {
            return __('Lien social', 'sidebar-jlg');
        }

        return ucwords($readable);
    }
}
