<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$repository = new SettingsRepository($defaults, $icons);
$sanitizer = new SettingsSanitizer($defaults, $icons, $repository);

$storedOptions = $defaults->all();
$storedOptions['unexpected'] = 'should disappear';
$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    'active' => 'default',
    'profiles' => [
        'default' => $storedOptions,
    ],
];

$input = [
    'enable_sidebar' => '1',
    'unexpected_input' => 'also removed',
];

$testsPassed = true;

function assertTrue($condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertSame($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
}

$sanitized = $sanitizer->sanitize_settings($input);
$defaultSettings = $defaults->all();
$normalizedOverlayDefault = preg_replace('/\s+/', '', $defaultSettings['overlay_color']);

assertTrue(is_array($sanitized), 'Sanitized settings returns an array for corrupted stored options');
assertSame(true, $sanitized['enable_sidebar'] ?? null, 'Enable sidebar flag keeps submitted truthy value');
assertSame($defaultSettings['layout_style'], $sanitized['layout_style'] ?? null, 'Layout style falls back to default when missing from input');
assertSame($normalizedOverlayDefault, $sanitized['overlay_color'] ?? null, 'Overlay color falls back to default when missing from input');
assertSame($defaultSettings['social_position'], $sanitized['social_position'] ?? null, 'Social position falls back to default when missing from input');
assertTrue(!array_key_exists('unexpected', $sanitized), 'Unexpected stored keys are stripped after sanitization');
assertTrue(!array_key_exists('unexpected_input', $sanitized), 'Unexpected input keys are stripped after sanitization');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
