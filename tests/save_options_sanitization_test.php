<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$testsPassed = true;

function assertSameValue($expected, $actual, string $message): void
{
    global $testsPassed;

    if ($expected === $actual) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
}

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
$defaults = new DefaultSettings();
$icons = new IconLibrary($pluginFile);
$sanitizer = new SettingsSanitizer($defaults, $icons);
$repository = new SettingsRepository($defaults, $icons, $sanitizer);

$GLOBALS['wp_test_options']['sidebar_jlg_settings'] = [
    'social_orientation' => 'horizontal',
];

$invalidOptions = [
    'social_orientation' => 'diagonal',
];

$repository->saveOptions($invalidOptions);

$storedOptions = get_option('sidebar_jlg_settings', []);
$defaultOrientation = $defaults->all()['social_orientation'] ?? 'horizontal';

assertSameValue(
    $defaultOrientation,
    $storedOptions['social_orientation'] ?? null,
    'Save options sanitizes disallowed social orientation values'
);

assertSameValue(
    false,
    ($storedOptions['social_orientation'] ?? null) === 'diagonal',
    'Invalid social orientation value is not persisted'
);

if ($testsPassed) {
    echo "Save options sanitization tests passed.\n";
    exit(0);
}

echo "Save options sanitization tests failed.\n";
exit(1);
