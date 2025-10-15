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
            'type' => 'separator',
            'label' => 'Section à venir',
            'value' => 'https://example.com/should-be-ignored',
            'icon_type' => 'svg_url',
            'icon' => 'https://cdn.example.com/icon.svg',
        ],
    ],
];

$result = $menuMethod->invoke($sanitizer, $input, $existingOptions);
$items = $result['menu_items'] ?? [];
$separator = $items[0] ?? null;

if (!is_array($separator)) {
    echo "Expected separator item to be present after sanitization.\n";
    exit(1);
}

if (($separator['type'] ?? null) !== 'separator') {
    echo "Expected separator type to be preserved, got " . var_export($separator['type'] ?? null, true) . "\n";
    exit(1);
}

if (($separator['value'] ?? null) !== '') {
    echo "Separator value should be cleared, got " . var_export($separator['value'] ?? null, true) . "\n";
    exit(1);
}

if (($separator['icon'] ?? null) !== '' || ($separator['icon_type'] ?? null) !== 'svg_inline') {
    echo "Separator icon data should be cleared, got icon=" . var_export($separator['icon'] ?? null, true) . ' icon_type=' . var_export($separator['icon_type'] ?? null, true) . "\n";
    exit(1);
}

if (($separator['label'] ?? null) !== 'Section à venir') {
    echo "Separator label should be preserved, got " . var_export($separator['label'] ?? null, true) . "\n";
    exit(1);
}

if (array_key_exists('nav_menu_max_depth', $separator) || array_key_exists('cta_title', $separator)) {
    echo "Separator item should not include unrelated fields.\n";
    exit(1);
}

echo "Separator menu item sanitization passed.\n";
exit(0);
