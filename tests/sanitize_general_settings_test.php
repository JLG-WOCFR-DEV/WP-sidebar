<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

define('ABSPATH', true);
define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void
    {
        // No-op for tests.
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string
    {
        return rtrim(dirname($file), "/\\") . '/';
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value): string
    {
        return rtrim($value, "/\\") . '/';
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return [
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com/uploads',
        ];
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($file, $allowed = []): array
    {
        return ['ext' => '', 'type' => ''];
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html = [])
    {
        return $string;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string
    {
        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]/', '', $value);

        return trim($value);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value): string
    {
        return (string) $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

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
