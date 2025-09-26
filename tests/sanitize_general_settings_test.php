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
    'border_color'    => '#fff; background: url(javascript:alert(1))',
];

$result_invalid = $method->invoke($sanitizer, $input_invalid, $existing_options);

assertSame($expected_overlay_existing, $result_invalid['overlay_color'] ?? '', 'Overlay color falls back to existing value on invalid input');
assertSame(1.0, $result_invalid['overlay_opacity'] ?? null, 'Overlay opacity is capped at 1.0');
assertSame(
    $existing_options['border_color'],
    $result_invalid['border_color'] ?? '',
    'Border color falls back to existing value when sanitized input is invalid'
);

$input_valid = [
    'overlay_color'   => '#ABCDEF',
    'border_color'    => '#EECCDD',
];

$result_valid = $method->invoke($sanitizer, $input_valid, $existing_options);

assertSame('#abcdef', $result_valid['overlay_color'] ?? '', 'Overlay color accepts valid hex values');
assertSame(0.4, $result_valid['overlay_opacity'] ?? null, 'Overlay opacity falls back to existing value when missing');
assertSame('#eeccdd', $result_valid['border_color'] ?? '', 'Border color accepts valid hex values without modification');

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

$existing_enums = array_merge($defaults->all(), [
    'layout_style'           => 'floating',
    'desktop_behavior'       => 'push',
    'search_method'          => 'hook',
    'search_alignment'       => 'flex-end',
    'header_logo_type'       => 'image',
    'header_alignment_desktop' => 'center',
    'header_alignment_mobile'  => 'flex-start',
]);

$input_invalid_enums = [
    'layout_style'           => 'diagonal',
    'desktop_behavior'       => 'teleport',
    'search_method'          => 'quantum',
    'search_alignment'       => 'space-between',
    'header_logo_type'       => 'emoji',
    'header_alignment_desktop' => 'space-around',
    'header_alignment_mobile'  => 'stretch',
];

$result_invalid_enums = $method->invoke($sanitizer, $input_invalid_enums, $existing_enums);

assertSame('floating', $result_invalid_enums['layout_style'] ?? null, 'Invalid layout style falls back to existing value');
assertSame('push', $result_invalid_enums['desktop_behavior'] ?? null, 'Invalid desktop behavior falls back to default');
assertSame('hook', $result_invalid_enums['search_method'] ?? null, 'Invalid search method falls back to existing value');
assertSame('flex-end', $result_invalid_enums['search_alignment'] ?? null, 'Invalid search alignment falls back to existing value');
assertSame('image', $result_invalid_enums['header_logo_type'] ?? null, 'Invalid header logo type falls back to existing value');
assertSame('center', $result_invalid_enums['header_alignment_desktop'] ?? null, 'Invalid desktop header alignment falls back to existing value');
assertSame('flex-start', $result_invalid_enums['header_alignment_mobile'] ?? null, 'Invalid mobile header alignment falls back to existing value');

$existing_invalid_alignment = array_merge($defaults->all(), [
    'header_alignment_desktop' => 'diagonal',
]);

$input_invalid_alignment = [
    'header_alignment_desktop' => 'curved',
];

$result_alignment_default = $method->invoke($sanitizer, $input_invalid_alignment, $existing_invalid_alignment);

assertSame('flex-start', $result_alignment_default['header_alignment_desktop'] ?? null, 'Invalid alignment with invalid existing value falls back to default');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
