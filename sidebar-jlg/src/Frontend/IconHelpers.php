<?php

namespace JLG\Sidebar\Frontend;

class IconHelpers
{
    public static function makeInlineIconDecorative(string $markup): string
    {
        if (stripos($markup, '<svg') === false) {
            return $markup;
        }

        if (preg_match('/<svg\b[^>]*(?:aria-label|aria-labelledby|aria-describedby|role\s*=\s*(?:"|\')img(?:"|\'))/i', $markup)) {
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
}
