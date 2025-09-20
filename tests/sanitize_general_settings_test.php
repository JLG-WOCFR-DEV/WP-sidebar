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
$expected_overlay_existing = preg_replace('/\s+/', '', $existing_options['overlay_color']);

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

assertSame($expected_overlay_existing, $result_invalid['overlay_color'] ?? '', 'Overlay color falls back to existing value on invalid input');
assertSame(1.0, $result_invalid['overlay_opacity'] ?? null, 'Overlay opacity is capped at 1.0');

$input_valid = [
    'overlay_color'   => '#ABCDEF',
];

$result_valid = $method->invoke($sanitizer, $input_valid, $existing_options);

assertSame('#abcdef', $result_valid['overlay_color'] ?? '', 'Overlay color accepts valid hex values');
assertSame(0.4, $result_valid['overlay_opacity'] ?? null, 'Overlay opacity falls back to existing value when missing');

$input_close_on_click = [
    'close_on_link_click' => '1',
];

$result_close_on = $method->invoke($sanitizer, $input_close_on_click, $existing_options);

assertSame(true, $result_close_on['close_on_link_click'] ?? null, 'Close-on-click option is enabled when checkbox is set');

$input_close_on_click_true_string = [
    'close_on_link_click' => 'true',
];

$result_close_on_true_string = $method->invoke($sanitizer, $input_close_on_click_true_string, $existing_options);

assertSame(true, $result_close_on_true_string['close_on_link_click'] ?? null, 'Close-on-click option accepts "true" as a truthy value');

$input_close_on_click_disabled = [];

$result_close_off = $method->invoke($sanitizer, $input_close_on_click_disabled, $existing_options);

assertSame(false, $result_close_off['close_on_link_click'] ?? null, 'Close-on-click option defaults to disabled when missing');

$input_close_on_click_zero = [
    'close_on_link_click' => '0',
];

$result_close_zero = $method->invoke($sanitizer, $input_close_on_click_zero, $existing_options);

assertSame(false, $result_close_zero['close_on_link_click'] ?? null, 'Close-on-click option treats "0" as disabled');

$input_min = [
    'overlay_opacity' => -0.3,
];

$result_min = $method->invoke($sanitizer, $input_min, $existing_options);

assertSame(0.0, $result_min['overlay_opacity'] ?? null, 'Overlay opacity is floored at 0.0');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
