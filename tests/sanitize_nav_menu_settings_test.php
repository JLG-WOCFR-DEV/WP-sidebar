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

$GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_object'] = static function ($menu) {
    if ((int) $menu === 12) {
        return (object) ['term_id' => 12];
    }

    return false;
};

$existingOptions = $defaults->all();

$input = [
    'menu_items' => [
        [
            'type' => 'nav_menu',
            'label' => 'Primary',
            'value' => '12',
            'nav_menu_max_depth' => '3',
            'nav_menu_filter' => 'current-branch',
            'icon_type' => 'svg_inline',
            'icon' => 'custom_star',
        ],
        [
            'type' => 'nav_menu',
            'label' => 'Invalid',
            'value' => '99',
            'nav_menu_max_depth' => '-4',
            'nav_menu_filter' => 'unknown',
        ],
    ],
];

$result = $menuMethod->invoke($sanitizer, $input, $existingOptions);

$first = $result['menu_items'][0] ?? [];
$second = $result['menu_items'][1] ?? [];

if (($first['value'] ?? null) !== 12) {
    echo "Expected first nav menu value to be 12, got " . var_export($first['value'] ?? null, true) . "\n";
    exit(1);
}

if (($first['nav_menu_max_depth'] ?? null) !== 3) {
    echo "Expected nav menu depth to be 3, got " . var_export($first['nav_menu_max_depth'] ?? null, true) . "\n";
    exit(1);
}

if (($first['nav_menu_filter'] ?? null) !== 'current-branch') {
    echo "Expected nav menu filter to be 'current-branch', got " . var_export($first['nav_menu_filter'] ?? null, true) . "\n";
    exit(1);
}

if (($second['value'] ?? null) !== 0) {
    echo "Expected invalid nav menu id to fall back to 0, got " . var_export($second['value'] ?? null, true) . "\n";
    exit(1);
}

if (($second['nav_menu_max_depth'] ?? null) !== 0) {
    echo "Expected invalid depth to reset to 0, got " . var_export($second['nav_menu_max_depth'] ?? null, true) . "\n";
    exit(1);
}

if (($second['nav_menu_filter'] ?? null) !== 'all') {
    echo "Expected invalid filter to fall back to 'all', got " . var_export($second['nav_menu_filter'] ?? null, true) . "\n";
    exit(1);
}

exit(0);
