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
$styleMethod = $reflection->getMethod('sanitize_style_settings');
$styleMethod->setAccessible(true);
$effectsMethod = $reflection->getMethod('sanitize_effects_settings');
$effectsMethod->setAccessible(true);
$menuMethod = $reflection->getMethod('sanitize_menu_settings');
$menuMethod->setAccessible(true);
$socialMethod = $reflection->getMethod('sanitize_social_settings');
$socialMethod->setAccessible(true);

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

$existing_numeric_general = array_merge($defaults->all(), [
    'border_width'   => 4,
    'width_desktop'  => 360,
    'width_tablet'   => 280,
    'header_logo_size' => 96,
]);

$input_empty_numeric_general = [
    'border_width'   => '',
    'width_desktop'  => '   ',
    'width_tablet'   => 'abc',
    'header_logo_size' => false,
];

$result_numeric_general = $method->invoke($sanitizer, $input_empty_numeric_general, $existing_numeric_general);

assertSame(4, $result_numeric_general['border_width'] ?? null, 'Empty border width keeps existing value');
assertSame(360, $result_numeric_general['width_desktop'] ?? null, 'Empty desktop width keeps existing value');
assertSame(280, $result_numeric_general['width_tablet'] ?? null, 'Non-numeric tablet width keeps existing value');
assertSame(96, $result_numeric_general['header_logo_size'] ?? null, 'Non-numeric header logo size keeps existing value');

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
    'horizontal_bar_position' => 'bottom',
    'horizontal_bar_alignment' => 'flex-start',
]);

$input_invalid_enums = [
    'layout_style'           => 'diagonal',
    'desktop_behavior'       => 'teleport',
    'search_method'          => 'quantum',
    'search_alignment'       => 'space-between',
    'header_logo_type'       => 'emoji',
    'header_alignment_desktop' => 'space-around',
    'header_alignment_mobile'  => 'stretch',
    'horizontal_bar_position' => 'diagonal',
    'horizontal_bar_alignment' => 'middle',
];

$result_invalid_enums = $method->invoke($sanitizer, $input_invalid_enums, $existing_enums);

assertSame('floating', $result_invalid_enums['layout_style'] ?? null, 'Invalid layout style falls back to existing value');
assertSame('push', $result_invalid_enums['desktop_behavior'] ?? null, 'Invalid desktop behavior falls back to default');
assertSame('hook', $result_invalid_enums['search_method'] ?? null, 'Invalid search method falls back to existing value');
assertSame('flex-end', $result_invalid_enums['search_alignment'] ?? null, 'Invalid search alignment falls back to existing value');
assertSame('image', $result_invalid_enums['header_logo_type'] ?? null, 'Invalid header logo type falls back to existing value');
assertSame('center', $result_invalid_enums['header_alignment_desktop'] ?? null, 'Invalid desktop header alignment falls back to existing value');
assertSame('flex-start', $result_invalid_enums['header_alignment_mobile'] ?? null, 'Invalid mobile header alignment falls back to existing value');
assertSame('bottom', $result_invalid_enums['horizontal_bar_position'] ?? null, 'Invalid horizontal bar position falls back to existing value');
assertSame('flex-start', $result_invalid_enums['horizontal_bar_alignment'] ?? null, 'Invalid horizontal bar alignment falls back to existing value');

$horizontal_existing = array_merge($defaults->all(), [
    'layout_style' => 'floating',
    'horizontal_bar_height' => '4rem',
    'horizontal_bar_position' => 'top',
    'horizontal_bar_alignment' => 'space-between',
    'horizontal_bar_sticky' => false,
]);

$horizontal_input = [
    'layout_style' => 'horizontal-bar',
    'horizontal_bar_height' => '72px',
    'horizontal_bar_position' => 'bottom',
    'horizontal_bar_alignment' => 'center',
    'horizontal_bar_sticky' => '1',
];

$horizontal_result = $method->invoke($sanitizer, $horizontal_input, $horizontal_existing);

assertSame('horizontal-bar', $horizontal_result['layout_style'] ?? null, 'Horizontal layout is accepted');
assertSame('72px', $horizontal_result['horizontal_bar_height'] ?? null, 'Horizontal bar height is sanitized');
assertSame('bottom', $horizontal_result['horizontal_bar_position'] ?? null, 'Horizontal bar position accepts valid input');
assertSame('center', $horizontal_result['horizontal_bar_alignment'] ?? null, 'Horizontal bar alignment accepts valid input');
assertSame(true, $horizontal_result['horizontal_bar_sticky'] ?? null, 'Horizontal bar sticky flag is converted to boolean');

$horizontal_invalid_height = [
    'horizontal_bar_height' => 'javascript:alert(1);',
];

$horizontal_height_result = $method->invoke($sanitizer, $horizontal_invalid_height, $horizontal_existing);

assertSame('4rem', $horizontal_height_result['horizontal_bar_height'] ?? null, 'Invalid horizontal bar height falls back to existing value');

$existing_invalid_alignment = array_merge($defaults->all(), [
    'header_alignment_desktop' => 'diagonal',
]);

$input_invalid_alignment = [
    'header_alignment_desktop' => 'curved',
];

$result_alignment_default = $method->invoke($sanitizer, $input_invalid_alignment, $existing_invalid_alignment);

assertSame('flex-start', $result_alignment_default['header_alignment_desktop'] ?? null, 'Invalid alignment with invalid existing value falls back to default');

$existing_style_enums = array_merge($defaults->all(), [
    'style_preset' => 'moderne_dark',
    'bg_color_type' => 'gradient',
    'accent_color_type' => 'gradient',
]);

$input_invalid_style_enums = [
    'style_preset' => 'retro',
    'bg_color_type' => 'pattern',
    'accent_color_type' => 'sparkle',
];

$result_style_invalid = $styleMethod->invoke($sanitizer, $input_invalid_style_enums, $existing_style_enums);

assertSame('moderne_dark', $result_style_invalid['style_preset'] ?? null, 'Invalid style preset falls back to existing value');
assertSame('gradient', $result_style_invalid['bg_color_type'] ?? null, 'Invalid background color type falls back to existing value');
assertSame('gradient', $result_style_invalid['accent_color_type'] ?? null, 'Invalid accent color type falls back to existing value');

$existing_style_defaults = array_merge($defaults->all(), [
    'font_color_type' => 'pattern',
    'font_hover_color_type' => 'sparkle',
]);

$input_invalid_font_types = [
    'font_color_type' => 'dots',
    'font_hover_color_type' => 'stripes',
];

$result_style_default = $styleMethod->invoke($sanitizer, $input_invalid_font_types, $existing_style_defaults);

assertSame('solid', $result_style_default['font_color_type'] ?? null, 'Invalid font color type with invalid existing value falls back to default');
assertSame('solid', $result_style_default['font_hover_color_type'] ?? null, 'Invalid font hover color type with invalid existing value falls back to default');

$existing_style_numeric = array_merge($defaults->all(), [
    'font_size' => 22,
    'mobile_blur' => 7,
]);

$input_style_numeric = [
    'font_size' => '',
    'mobile_blur' => 'blur',
];

$result_style_numeric = $styleMethod->invoke($sanitizer, $input_style_numeric, $existing_style_numeric);

assertSame(22, $result_style_numeric['font_size'] ?? null, 'Empty font size keeps existing value');
assertSame(7, $result_style_numeric['mobile_blur'] ?? null, 'Non-numeric mobile blur keeps existing value');

$existing_effects_enums = array_merge($defaults->all(), [
    'hover_effect_desktop' => 'glow',
    'hover_effect_mobile' => 'neon',
    'animation_type' => 'fade',
]);

$input_invalid_effects = [
    'hover_effect_desktop' => 'spin',
    'hover_effect_mobile' => 'bounce',
    'animation_type' => 'teleport',
];

$result_effects_invalid = $effectsMethod->invoke($sanitizer, $input_invalid_effects, $existing_effects_enums);

assertSame('glow', $result_effects_invalid['hover_effect_desktop'] ?? null, 'Invalid desktop hover effect falls back to existing value');
assertSame('neon', $result_effects_invalid['hover_effect_mobile'] ?? null, 'Invalid mobile hover effect falls back to existing value');
assertSame('fade', $result_effects_invalid['animation_type'] ?? null, 'Invalid animation type falls back to existing value');

$existing_effects_defaults = array_merge($defaults->all(), [
    'hover_effect_mobile' => 'orbit',
]);

$input_invalid_mobile_effect = [
    'hover_effect_mobile' => 'ripple',
];

$result_effects_default = $effectsMethod->invoke($sanitizer, $input_invalid_mobile_effect, $existing_effects_defaults);

assertSame('none', $result_effects_default['hover_effect_mobile'] ?? null, 'Invalid mobile hover effect with invalid existing value falls back to default');

$existing_effects_numeric = array_merge($defaults->all(), [
    'animation_speed' => 450,
    'neon_blur' => 14,
    'neon_spread' => 6,
]);

$input_effects_numeric = [
    'animation_speed' => '',
    'neon_blur' => 'blurrier',
    'neon_spread' => [],
];

$result_effects_numeric = $effectsMethod->invoke($sanitizer, $input_effects_numeric, $existing_effects_numeric);

assertSame(450, $result_effects_numeric['animation_speed'] ?? null, 'Empty animation speed keeps existing value');
assertSame(14, $result_effects_numeric['neon_blur'] ?? null, 'Non-numeric neon blur keeps existing value');
assertSame(6, $result_effects_numeric['neon_spread'] ?? null, 'Invalid neon spread keeps existing value');

$existing_menu_enums = array_merge($defaults->all(), [
    'menu_alignment_desktop' => 'center',
    'menu_alignment_mobile' => 'flex-end',
]);

$GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_object'] = static function ($menuId) {
    if ((int) $menuId === 42) {
        return (object) ['term_id' => 42];
    }

    return false;
};

$input_invalid_menu_alignments = [
    'menu_alignment_desktop' => 'space-between',
    'menu_alignment_mobile' => 'space-around',
];

$result_menu_invalid = $menuMethod->invoke($sanitizer, $input_invalid_menu_alignments, $existing_menu_enums);

assertSame('center', $result_menu_invalid['menu_alignment_desktop'] ?? null, 'Invalid desktop menu alignment falls back to existing value');
assertSame('flex-end', $result_menu_invalid['menu_alignment_mobile'] ?? null, 'Invalid mobile menu alignment falls back to existing value');

$existing_menu_defaults = array_merge($defaults->all(), [
    'menu_alignment_mobile' => 'stretch',
]);

$input_invalid_menu_mobile = [
    'menu_alignment_mobile' => 'baseline',
];

$result_menu_default = $menuMethod->invoke($sanitizer, $input_invalid_menu_mobile, $existing_menu_defaults);

assertSame('flex-start', $result_menu_default['menu_alignment_mobile'] ?? null, 'Invalid mobile menu alignment with invalid existing value falls back to default');

$input_valid_wp_menu = [
    'wp_menu_id' => '42',
];

$result_valid_wp_menu = $menuMethod->invoke($sanitizer, $input_valid_wp_menu, $existing_menu_enums);

assertSame(42, $result_valid_wp_menu['wp_menu_id'] ?? null, 'Valid WordPress menu id is preserved');

$existing_menu_defaults_with_selection = array_merge($defaults->all(), [
    'wp_menu_id' => 84,
]);

$input_invalid_wp_menu = [
    'wp_menu_id' => '999',
];

$result_invalid_wp_menu = $menuMethod->invoke($sanitizer, $input_invalid_wp_menu, $existing_menu_defaults_with_selection);

assertSame(0, $result_invalid_wp_menu['wp_menu_id'] ?? null, 'Unknown WordPress menu id resets to zero');

$input_non_scalar_wp_menu = [
    'wp_menu_id' => ['nested' => true],
];

$result_non_scalar_wp_menu = $menuMethod->invoke($sanitizer, $input_non_scalar_wp_menu, $existing_menu_defaults_with_selection);

assertSame(0, $result_non_scalar_wp_menu['wp_menu_id'] ?? null, 'Non scalar WordPress menu id is rejected');

$existing_social_enums = array_merge($defaults->all(), [
    'social_orientation' => 'vertical',
    'social_position' => 'in-menu',
]);

$input_invalid_social = [
    'social_orientation' => 'diagonal',
    'social_position' => 'header',
];

$result_social_invalid = $socialMethod->invoke($sanitizer, $input_invalid_social, $existing_social_enums);

assertSame('vertical', $result_social_invalid['social_orientation'] ?? null, 'Invalid social orientation falls back to existing value');
assertSame('in-menu', $result_social_invalid['social_position'] ?? null, 'Invalid social position falls back to existing value');

$existing_social_defaults = array_merge($defaults->all(), [
    'social_position' => 'sidebar',
]);

$input_invalid_social_default = [
    'social_position' => 'toolbar',
];

$result_social_default = $socialMethod->invoke($sanitizer, $input_invalid_social_default, $existing_social_defaults);

assertSame('footer', $result_social_default['social_position'] ?? null, 'Invalid social position with invalid existing value falls back to default');

$existing_social_numeric = array_merge($defaults->all(), [
    'social_icon_size' => 42,
]);

$input_social_numeric = [
    'social_icon_size' => '',
];

$result_social_numeric = $socialMethod->invoke($sanitizer, $input_social_numeric, $existing_social_numeric);

assertSame(42, $result_social_numeric['social_icon_size'] ?? null, 'Empty social icon size keeps existing value');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
