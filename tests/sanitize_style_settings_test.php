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

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color): string {
        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
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
        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]/', '', $value);

        return trim($value);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value): string {
        return (string) $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int {
        return abs((int) $value);
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$reflection = new ReflectionClass(SettingsSanitizer::class);
$method = $reflection->getMethod('sanitize_style_settings');
$method->setAccessible(true);

$existing_options = array_merge((new DefaultSettings())->all(), [
    'style_preset'             => 'default',
    'bg_color_type'            => 'solid',
    'bg_color'                 => 'rgba(10,20,30,0.4)',
    'accent_color_type'        => 'solid',
    'accent_color'             => '#112233',
    'font_color_type'          => 'gradient',
    'font_color_start'         => '#000000',
    'font_color_end'           => '#ffffff',
    'font_hover_color_type'    => 'solid',
    'font_hover_color'         => '#ff0000',
    'header_logo_type'         => 'text',
    'app_name'                 => 'Existing App',
    'header_logo_image'        => 'http://example.com/logo.png',
    'header_logo_size'         => 80,
    'header_alignment_desktop' => 'left',
    'header_alignment_mobile'  => 'center',
    'header_padding_top'       => '10px',
    'font_size'                => 16,
    'mobile_bg_color'          => 'rgba(0,0,0,0.6)',
    'mobile_bg_opacity'        => 0.6,
    'mobile_blur'              => 4,
]);

$input = [
    'style_preset'             => 'custom',
    'bg_color_type'            => 'solid',
    'bg_color'                 => 'invalid-color',
    'accent_color_type'        => 'solid',
    'accent_color'             => '#445566',
    'font_color_type'          => 'gradient',
    'font_color_start'         => 'rgba(512,0,0,0.5)',
    'font_color_end'           => 'not-a-color',
    'font_hover_color_type'    => 'solid',
    'font_hover_color'         => 'rgba(1,2,3,0.5)',
    'header_logo_type'         => 'image',
    'app_name'                 => 'New App',
    'header_logo_image'        => 'http://example.com/new-logo.png',
    'header_logo_size'         => 100,
    'header_alignment_desktop' => 'right',
    'header_alignment_mobile'  => 'right',
    'header_padding_top'       => '20px',
    'font_size'                => 18,
    'mobile_bg_color'          => 'rgba(999,0,0,1)',
    'mobile_bg_opacity'        => 0.8,
    'mobile_blur'              => 6,
];

$result = $method->invoke($sanitizer, $input, $existing_options);

$testsPassed = true;

function assertSame($expected, $actual, string $message): void {
    global $testsPassed;

    if ($expected === $actual) {
        echo "[PASS] {$message}\n";
        return;
    }

    $testsPassed = false;
    echo sprintf(
        "[FAIL] %s - expected %s got %s\n",
        $message,
        var_export($expected, true),
        var_export($actual, true)
    );
}

assertSame('rgba(10,20,30,0.4)', $result['bg_color'], 'Fallback preserves existing solid color');
assertSame('#000000', $result['font_color_start'], 'Fallback preserves existing gradient start color');
assertSame('#ffffff', $result['font_color_end'], 'Fallback preserves existing gradient end color');
assertSame('#445566', $result['accent_color'], 'Valid solid color is sanitized normally');
assertSame('rgba(0,0,0,0.6)', $result['mobile_bg_color'], 'Fallback preserves existing mobile background color');
assertSame(0.8, $result['mobile_bg_opacity'], 'Valid opacity within range is preserved');

$inputBelowMin = $input;
$inputBelowMin['mobile_bg_opacity'] = -0.5;
$resultBelowMin = $method->invoke($sanitizer, $inputBelowMin, $existing_options);
assertSame(0.0, $resultBelowMin['mobile_bg_opacity'], 'Opacity below 0 clamps to 0.0');

$inputAboveMax = $input;
$inputAboveMax['mobile_bg_opacity'] = 3.14;
$resultAboveMax = $method->invoke($sanitizer, $inputAboveMax, $existing_options);
assertSame(1.0, $resultAboveMax['mobile_bg_opacity'], 'Opacity above 1 clamps to 1.0');

$existingOpacityOutOfRange = $existing_options;
$existingOpacityOutOfRange['mobile_bg_opacity'] = 2.5;
$inputWithoutOpacity = $input;
unset($inputWithoutOpacity['mobile_bg_opacity']);
$resultExistingClamp = $method->invoke($sanitizer, $inputWithoutOpacity, $existingOpacityOutOfRange);
assertSame(1.0, $resultExistingClamp['mobile_bg_opacity'], 'Fallback opacity clamps existing value to 1.0');

if ($testsPassed) {
    echo "All sanitize_style_settings tests passed.\n";
    exit(0);
}

echo "sanitize_style_settings tests failed.\n";
exit(1);
