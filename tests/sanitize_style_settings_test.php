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

$existingPayloadOptions = $existing_options;
$existingPayloadOptions['bg_color'] = 'rgba(10,20,30,0.4);background-image:url(javascript:alert(1))';
$inputWithoutBgColor = $input;
unset($inputWithoutBgColor['bg_color']);
$resultSanitizedPayload = $method->invoke($sanitizer, $inputWithoutBgColor, $existingPayloadOptions);
assertSame('', $resultSanitizedPayload['bg_color'], 'Existing bg color payload is sanitized to empty string');

if ($testsPassed) {
    echo "All sanitize_style_settings tests passed.\n";
    exit(0);
}

echo "sanitize_style_settings tests failed.\n";
exit(1);
