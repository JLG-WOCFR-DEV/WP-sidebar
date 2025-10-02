<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';

$GLOBALS['wp_test_function_overrides']['wp_check_filetype'] = static function ($file, $allowed = []) {
    return ['ext' => '', 'type' => ''];
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$repository = new SettingsRepository($defaults, $icons);
$sanitizer = new SettingsSanitizer($defaults, $icons, $repository);

$reflection = new ReflectionClass(SettingsSanitizer::class);
$effectsMethod = $reflection->getMethod('sanitize_effects_settings');
$effectsMethod->setAccessible(true);

$testsPassed = true;

function assertSame($expected, $actual, string $message): void {
    global $testsPassed;

    if ($expected !== $actual) {
        $testsPassed = false;
        echo "Assertion failed: {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
    }
}

$existing_effects = array_merge($defaults->all(), [
    'hover_effect_desktop' => 'neon',
    'hover_effect_mobile'  => 'tile-slide',
    'animation_type'       => 'fade',
]);

$input_invalid_effects = [
    'hover_effect_desktop' => 'super-glow',
    'hover_effect_mobile'  => 'spiral',
    'animation_type'       => 'spin',
];

$result_effects = $effectsMethod->invoke($sanitizer, $input_invalid_effects, $existing_effects);

assertSame('neon', $result_effects['hover_effect_desktop'] ?? null, 'Invalid desktop hover effect falls back to existing value');
assertSame('tile-slide', $result_effects['hover_effect_mobile'] ?? null, 'Invalid mobile hover effect falls back to existing value');
assertSame('fade', $result_effects['animation_type'] ?? null, 'Invalid animation type falls back to existing value');

$existing_effects_invalid = array_merge($defaults->all(), [
    'hover_effect_mobile' => 'spiral',
    'animation_type'      => 'warp',
]);

$result_effects_default = $effectsMethod->invoke($sanitizer, $input_invalid_effects, $existing_effects_invalid);

assertSame('none', $result_effects_default['hover_effect_mobile'] ?? null, 'Invalid mobile hover effect with invalid existing falls back to default');
assertSame('slide-left', $result_effects_default['animation_type'] ?? null, 'Invalid animation type with invalid existing falls back to default');

$socialMethod = $reflection->getMethod('sanitize_social_settings');
$socialMethod->setAccessible(true);

$existing_social = array_merge($defaults->all(), [
    'social_orientation' => 'vertical',
    'social_position'    => 'in-menu',
]);

$input_invalid_social = [
    'social_orientation' => 'diagonal',
    'social_position'    => 'header',
];

$result_social = $socialMethod->invoke($sanitizer, $input_invalid_social, $existing_social);

assertSame('vertical', $result_social['social_orientation'] ?? null, 'Invalid social orientation falls back to existing value');
assertSame('in-menu', $result_social['social_position'] ?? null, 'Invalid social position falls back to existing value');

$existing_social_invalid = array_merge($defaults->all(), [
    'social_orientation' => 'angled',
    'social_position'    => 'header',
]);

$result_social_default = $socialMethod->invoke($sanitizer, $input_invalid_social, $existing_social_invalid);

assertSame('horizontal', $result_social_default['social_orientation'] ?? null, 'Invalid social orientation with invalid existing falls back to default');
assertSame('footer', $result_social_default['social_position'] ?? null, 'Invalid social position with invalid existing falls back to default');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
