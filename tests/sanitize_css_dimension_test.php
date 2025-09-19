<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

define('ABSPATH', true);
define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void {
        // No-op for tests.
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string {
        return rtrim(dirname($file), "/\\") . '/';
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value): string {
        return rtrim($value, "/\\") . '/';
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array {
        return [
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com/uploads',
        ];
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($file, $allowed = []): array {
        return ['ext' => '', 'type' => ''];
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html = []) {
        return $string;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t ]+/', ' ', $value);

        return trim($value);
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$reflection = new ReflectionClass(SettingsSanitizer::class);
$method = $reflection->getMethod('sanitize_css_dimension');
$method->setAccessible(true);

$tests = [
    'valid_px' => [
        'input'    => '24px',
        'fallback' => '2.5rem',
        'expected' => '24px',
    ],
    'valid_rem' => [
        'input'    => '1.5rem',
        'fallback' => '2.5rem',
        'expected' => '1.5rem',
    ],
    'valid_vh' => [
        'input'    => '50vh',
        'fallback' => '2.5rem',
        'expected' => '50vh',
    ],
    'negative_value' => [
        'input'    => '-10px',
        'fallback' => '2.5rem',
        'expected' => '-10px',
    ],
    'zero_without_unit' => [
        'input'    => '0',
        'fallback' => '2.5rem',
        'expected' => '0',
    ],
    'zero_decimal' => [
        'input'    => '0.0',
        'fallback' => '2.5rem',
        'expected' => '0',
    ],
    'valid_calc_expression' => [
        'input'    => 'calc(100% - 20px)',
        'fallback' => '2.5rem',
        'expected' => 'calc(100% - 20px)',
    ],
    'invalid_calc_disallowed_unit' => [
        'input'    => 'calc(100% - 20pt)',
        'fallback' => '2.5rem',
        'expected' => '2.5rem',
    ],
    'empty_input_uses_fallback' => [
        'input'    => '',
        'fallback' => '2.5rem',
        'expected' => '2.5rem',
    ],
    'invalid_input_uses_fallback' => [
        'input'    => 'auto',
        'fallback' => '2.5rem',
        'expected' => '2.5rem',
    ],
];

$allPassed = true;
foreach ($tests as $name => $test) {
    $result = $method->invoke($sanitizer, $test['input'], $test['fallback']);
    if ($result === $test['expected']) {
        echo sprintf("[PASS] %s\n", $name);
        continue;
    }

    $allPassed = false;
    echo sprintf(
        "[FAIL] %s - expected %s got %s\n",
        $name,
        var_export($test['expected'], true),
        var_export($result, true)
    );
}

if ($allPassed) {
    echo "All sanitize_css_dimension tests passed.\n";
    exit(0);
}

echo "sanitize_css_dimension tests failed.\n";
exit(1);
