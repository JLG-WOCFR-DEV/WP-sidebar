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
            $shouldWrapDecorativeIcon = false;
            $hasExplicitAccessibleLabel = false;
            $svgTextLabel = null;

            if ($iconMarkup !== null && stripos($iconMarkup, '<svg') !== false) {
                $svgTextLabel = self::extractSvgTextLabel($iconMarkup);
                $hasExplicitAccessibleLabel = self::hasExplicitAccessibleLabel($iconMarkup);

                if (!$hasExplicitAccessibleLabel) {
                    $iconMarkup = self::makeInlineIconDecorative($iconMarkup);
                    $iconMarkup = IconHelpers::makeInlineIconDecorative($iconMarkup);
                    $shouldWrapDecorativeIcon = self::shouldWrapDecorativeIcon($iconMarkup);
                }
            } elseif ($iconMarkup !== null) {
                $iconMarkup = IconHelpers::makeInlineIconDecorative($iconMarkup);
            }

            $defaultLabel = $svgTextLabel ?? self::humanizeIconKey($iconKey);
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

            if ($shouldWrapDecorativeIcon && $iconMarkup !== null) {
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

    private static function hasExplicitAccessibleLabel(string $markup): bool
    {
        if (stripos($markup, 'aria-label=') !== false) {
            return true;
        }

        if (stripos($markup, 'aria-labelledby=') !== false) {
            return true;
        }

        if (stripos($markup, 'aria-describedby=') !== false) {
            return true;
        }

        if (stripos($markup, 'role="img"') !== false) {
            return true;
        }

        if (stripos($markup, "role='img'") !== false) {
            return true;
        }

        return self::extractSvgTextLabel($markup) !== null;
    }

    private static function shouldWrapDecorativeIcon(string $markup): bool
    {
        if (stripos($markup, '<title') !== false || stripos($markup, '<desc') !== false) {
            $label = self::extractSvgTextLabel($markup);

            if ($label !== null) {
                return false;
            }
        }

        return stripos($markup, '<title') !== false
            || stripos($markup, '<desc') !== false
            || stripos($markup, 'aria-labelledby=') !== false
            || stripos($markup, 'aria-describedby=') !== false;
    }

    private static function extractSvgTextLabel(string $markup): ?string
    {
        if (stripos($markup, '<svg') === false) {
            return null;
        }

        if (stripos($markup, '<title') === false && stripos($markup, '<desc') === false) {
            return null;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $wrappedMarkup = '<svg-wrapper>' . $markup . '</svg-wrapper>';

            if (@$document->loadXML($wrappedMarkup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) === false) {
                return null;
            }

            foreach (['title', 'desc'] as $tagName) {
                $nodes = $document->getElementsByTagName($tagName);

                foreach ($nodes as $node) {
                    $text = trim($node->textContent);

                    if ($text !== '') {
                        return $text;
                    }
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
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
