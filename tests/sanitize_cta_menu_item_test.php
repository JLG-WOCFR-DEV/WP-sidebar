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
            'label' => 'CTA Label',
            'cta_title' => 'Rejoignez-nous',
            'cta_description' => 'Une description <strong>percutante</strong>.',
            'cta_shortcode' => 'Shortcode CTA',
            'cta_button_label' => 'Cliquez ici',
            'cta_button_url' => 'https://example.com/inscription',
            'icon_type' => 'svg_inline',
            'icon' => '',
        ],
    ],
];

$result = $menuMethod->invoke($sanitizer, $input, $existingOptions);

$ctaItem = $result['menu_items'][0] ?? null;

if (!is_array($ctaItem)) {
    echo "Expected CTA item to be present in sanitized menu items.\n";
    exit(1);
}

if (($ctaItem['type'] ?? null) !== 'cta') {
    echo "Expected CTA item type to remain 'cta', got " . var_export($ctaItem['type'] ?? null, true) . "\n";
    exit(1);
}

if (($ctaItem['cta_title'] ?? null) !== 'Rejoignez-nous') {
    echo "Expected CTA title to be preserved, got " . var_export($ctaItem['cta_title'] ?? null, true) . "\n";
    exit(1);
}

if (($ctaItem['cta_description'] ?? null) !== 'Une description <strong>percutante</strong>.') {
    echo "Expected CTA description to retain formatting, got " . var_export($ctaItem['cta_description'] ?? null, true) . "\n";
    exit(1);
}

if (($ctaItem['cta_shortcode'] ?? null) !== 'Shortcode CTA') {
    echo "Expected CTA shortcode to be preserved, got " . var_export($ctaItem['cta_shortcode'] ?? null, true) . "\n";
    exit(1);
}

if (($ctaItem['cta_button_label'] ?? null) !== 'Cliquez ici') {
    echo "Expected CTA button label to be preserved, got " . var_export($ctaItem['cta_button_label'] ?? null, true) . "\n";
    exit(1);
}

if (($ctaItem['cta_button_url'] ?? null) !== 'https://example.com/inscription') {
    echo "Expected CTA button URL to be preserved, got " . var_export($ctaItem['cta_button_url'] ?? null, true) . "\n";
    exit(1);
}

exit(0);
