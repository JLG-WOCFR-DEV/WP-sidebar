<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsMaintenanceRunner;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$GLOBALS['wp_test_options'] = [];
$GLOBALS['wp_test_object_cache'] = [];
SettingsRepository::invalidateCachedNavMenu();

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
$defaults = new DefaultSettings();
$icons = new IconLibrary($pluginFile);
$sanitizer = new SettingsSanitizer($defaults, $icons);
$repository = new SettingsRepository($defaults, $icons, $sanitizer);

$GLOBALS['wp_test_options']['sidebar_jlg_settings'] = [
    'menu_items' => [
        [
            'type' => 'nav_menu',
            'value' => 42,
            'nav_menu_filter' => 'all',
            'nav_menu_max_depth' => '2',
        ],
    ],
];

$callCount = 0;
$GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_object'] = static function ($menu) use (&$callCount) {
    $callCount++;

    if ((int) $menu === 42) {
        return (object) ['term_id' => 42];
    }

    return false;
};

$repository->getOptionsWithRevalidation();
if ($callCount !== 1) {
    echo 'Expected revalidation to perform exactly one lookup, got ' . $callCount . "\n";
    exit(1);
}

$queued = $GLOBALS['wp_test_options'][SettingsRepository::REVALIDATION_QUEUE_OPTION] ?? null;
if (!is_array($queued)) {
    echo "Expected revalidation payload to be queued.\n";
    exit(1);
}

$repository->getOptionsWithRevalidation();
if ($callCount !== 1) {
    echo 'Expected cached nav menu lookup to be reused, got ' . $callCount . " calls\n";
    exit(1);
}

unset($GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_object']);
SettingsRepository::invalidateCachedNavMenu(42);

$maintenance = new SettingsMaintenanceRunner($repository);
$maintenance->applyQueuedRevalidations();

$queuedAfter = $GLOBALS['wp_test_options'][SettingsRepository::REVALIDATION_QUEUE_OPTION] ?? null;
if ($queuedAfter !== null) {
    echo "Expected queued payload to be cleared after maintenance run.\n";
    exit(1);
}

$persisted = $GLOBALS['wp_test_options']['sidebar_jlg_settings']['menu_items'][0]['nav_menu_max_depth'] ?? null;
if (!is_int($persisted) || $persisted !== 2) {
    echo "Expected persisted nav_menu_max_depth to be normalized to integer 2.\n";
    exit(1);
}

exit(0);
