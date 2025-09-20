<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

require __DIR__ . '/bootstrap.php';

$GLOBALS['wp_test_function_overrides']['wp_check_filetype'] = static function ($file, $allowed = []) {
    return ['ext' => '', 'type' => ''];
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$reflection = new ReflectionClass(SettingsSanitizer::class);
$method = $reflection->getMethod('sanitize_general_settings');
$method->setAccessible(true);

$existing_options = array_merge($defaults->all(), [
    'overlay_color'   => 'rgba(10, 20, 30, 0.8)',
    'overlay_opacity' => 0.4,
]);

$testsPassed = true;

function assertSame($expected, $actual, string $message): void
{
    global $testsPassed;
    if ($expected !== $actual) {
        $testsPassed = false;
        echo "Assertion failed: {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
    }
}

$input_invalid = [
    'overlay_color'   => 'not-a-color',
    'overlay_opacity' => 1.7,
];

$result_invalid = $method->invoke($sanitizer, $input_invalid, $existing_options);

assertSame('rgba(10, 20, 30, 0.8)', $result_invalid['overlay_color'] ?? '', 'Overlay color falls back to existing value on invalid input');
assertSame(1.0, $result_invalid['overlay_opacity'] ?? null, 'Overlay opacity is capped at 1.0');

$input_valid = [
    'overlay_color'   => '#ABCDEF',
];

$result_valid = $method->invoke($sanitizer, $input_valid, $existing_options);

assertSame('#abcdef', $result_valid['overlay_color'] ?? '', 'Overlay color accepts valid hex values');
assertSame(0.4, $result_valid['overlay_opacity'] ?? null, 'Overlay opacity falls back to existing value when missing');

$input_min = [
    'overlay_opacity' => -0.3,
];

$result_min = $method->invoke($sanitizer, $input_min, $existing_options);

assertSame(0.0, $result_min['overlay_opacity'] ?? null, 'Overlay opacity is floored at 0.0');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
