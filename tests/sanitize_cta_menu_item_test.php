<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$reflection = new ReflectionClass(SettingsSanitizer::class);
$menuMethod = $reflection->getMethod('sanitize_menu_settings');
$menuMethod->setAccessible(true);

$existingOptions = $defaults->all();

$input = [
    'menu_items' => [
        [
            'type' => 'cta',
            'label' => 'My CTA',
            'icon_type' => 'svg_inline',
            'cta_title' => 'Boostez vos ventes',
            'cta_description' => '<p>Profitez de notre offre spéciale</p>',
            'cta_button_label' => 'En savoir plus',
            'cta_button_url' => 'https://example.com/offre',
            'cta_shortcode' => '[special_offer]',
        ],
    ],
];

$result = $menuMethod->invoke($sanitizer, $input, $existingOptions);
$ctaItem = $result['menu_items'][0] ?? [];

$expectedFields = [
    'type' => 'cta',
    'cta_title' => 'Boostez vos ventes',
    'cta_description' => '<p>Profitez de notre offre spéciale</p>',
    'cta_button_label' => 'En savoir plus',
    'cta_button_url' => 'https://example.com/offre',
    'cta_shortcode' => '[special_offer]',
];

foreach ($expectedFields as $field => $expectedValue) {
    if (($ctaItem[$field] ?? null) !== $expectedValue) {
        echo "Expected CTA field '{$field}' to be " . var_export($expectedValue, true) . ", got " . var_export($ctaItem[$field] ?? null, true) . "\n";
        exit(1);
    }
}

exit(0);
