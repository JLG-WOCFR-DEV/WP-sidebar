<?php

namespace JLG\Sidebar\Settings;

final class TypographyOptions
{
    /**
     * @return array<string, array{label: string, stack: string, type: string, google?: string}>
     */
    public static function getFontFamilies(): array
    {
        static $fonts = null;

        if ($fonts !== null) {
            return $fonts;
        }

        $fonts = [
            'system-ui' => [
                'label' => __('Système (sans empattement)', 'sidebar-jlg'),
                'stack' => 'system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif',
                'type' => 'system',
            ],
            'arial' => [
                'label' => __('Arial', 'sidebar-jlg'),
                'stack' => 'Arial, Helvetica Neue, Helvetica, sans-serif',
                'type' => 'system',
            ],
            'georgia' => [
                'label' => __('Georgia', 'sidebar-jlg'),
                'stack' => 'Georgia, Times New Roman, Times, serif',
                'type' => 'system',
            ],
            'courier-new' => [
                'label' => __('Courier New', 'sidebar-jlg'),
                'stack' => 'Courier New, Courier, monospace',
                'type' => 'system',
            ],
            'google-roboto' => [
                'label' => __('Roboto (Google)', 'sidebar-jlg'),
                'stack' => 'Roboto, sans-serif',
                'type' => 'google',
                'google' => 'Roboto:wght@300;400;500;700',
            ],
            'google-open-sans' => [
                'label' => __('Open Sans (Google)', 'sidebar-jlg'),
                'stack' => 'Open Sans, sans-serif',
                'type' => 'google',
                'google' => 'Open Sans:wght@300;400;600;700',
            ],
            'google-montserrat' => [
                'label' => __('Montserrat (Google)', 'sidebar-jlg'),
                'stack' => 'Montserrat, sans-serif',
                'type' => 'google',
                'google' => 'Montserrat:wght@400;500;600;700',
            ],
            'google-source-serif' => [
                'label' => __('Source Serif (Google)', 'sidebar-jlg'),
                'stack' => 'Source Serif 4, serif',
                'type' => 'google',
                'google' => 'Source Serif 4:wght@400;500;600;700',
            ],
        ];

        return $fonts;
    }

    /**
     * @return string[]
     */
    public static function getFontFamilyChoices(): array
    {
        return array_keys(self::getFontFamilies());
    }

    /**
     * @return string[]
     */
    public static function getFontWeights(): array
    {
        return ['300', '400', '500', '600', '700', '800'];
    }

    /**
     * @return string[]
     */
    public static function getTextTransformChoices(): array
    {
        return ['none', 'uppercase', 'lowercase', 'capitalize'];
    }

    public static function getFontStack(string $key): ?string
    {
        $fonts = self::getFontFamilies();

        return $fonts[$key]['stack'] ?? null;
    }

    public static function getGoogleFontQuery(string $key): ?string
    {
        $fonts = self::getFontFamilies();

        if (!isset($fonts[$key])) {
            return null;
        }

        if (($fonts[$key]['type'] ?? '') !== 'google') {
            return null;
        }

        $query = $fonts[$key]['google'] ?? '';

        return $query !== '' ? $query : null;
    }
}
