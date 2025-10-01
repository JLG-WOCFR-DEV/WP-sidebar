<?php

namespace JLG\Sidebar\Settings;

final class OptionChoices
{
    /**
     * @var array<string, string[]>
     */
    public const ALLOWED_CHOICES = [
        'layout_style' => ['full', 'floating', 'horizontal-bar'],
        'desktop_behavior' => ['push', 'overlay'],
        'search_method' => ['default', 'shortcode', 'hook'],
        'search_alignment' => ['flex-start', 'center', 'flex-end'],
        'header_logo_type' => ['text', 'image'],
        'header_alignment_desktop' => ['flex-start', 'center', 'flex-end'],
        'header_alignment_mobile' => ['flex-start', 'center', 'flex-end'],
        'style_preset' => ['custom', 'moderne_dark'],
        'bg_color_type' => ['solid', 'gradient'],
        'accent_color_type' => ['solid', 'gradient'],
        'font_color_type' => ['solid', 'gradient'],
        'font_hover_color_type' => ['solid', 'gradient'],
        'hover_effect_desktop' => [
            'none',
            'tile-slide',
            'underline-center',
            'pill-center',
            'spotlight',
            'glossy-tilt',
            'neon',
            'glow',
            'pulse',
        ],
        'hover_effect_mobile' => [
            'none',
            'tile-slide',
            'underline-center',
            'pill-center',
            'spotlight',
            'glossy-tilt',
            'neon',
            'glow',
            'pulse',
        ],
        'animation_type' => ['slide-left', 'fade', 'scale'],
        'menu_alignment_desktop' => ['flex-start', 'center', 'flex-end'],
        'menu_alignment_mobile' => ['flex-start', 'center', 'flex-end'],
        'social_orientation' => ['horizontal', 'vertical'],
        'social_position' => ['footer', 'in-menu'],
        'horizontal_bar_position' => ['top', 'bottom'],
        'horizontal_bar_alignment' => ['flex-start', 'center', 'flex-end', 'space-between'],
    ];

    /**
     * @return array<string, string[]>
     */
    public static function getAll(): array
    {
        return self::ALLOWED_CHOICES;
    }

    /**
     * @param mixed $rawValue
     * @param mixed $existingValue
     * @param mixed $defaultValue
     *
     * @return string
     */
    public static function resolveChoice($rawValue, array $allowed, $existingValue, $defaultValue): string
    {
        $allowed = array_values(array_unique(array_map('strval', $allowed)));

        $choice = self::normalizeChoice($rawValue, $allowed);
        if ($choice !== null) {
            return $choice;
        }

        $existingChoice = self::normalizeChoice($existingValue, $allowed);
        if ($existingChoice !== null) {
            return $existingChoice;
        }

        $defaultChoice = self::normalizeChoice($defaultValue, $allowed);
        if ($defaultChoice !== null) {
            return $defaultChoice;
        }

        return $allowed[0] ?? '';
    }

    /**
     * @param mixed $value
     */
    public static function normalizeChoice($value, array $allowed): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $sanitized = sanitize_key((string) $value);
        if ($sanitized === '') {
            return null;
        }

        return in_array($sanitized, $allowed, true) ? $sanitized : null;
    }
}
