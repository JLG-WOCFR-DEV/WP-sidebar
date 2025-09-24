<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$repository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$defaults = $repository->getDefaultSettings();

function normalize_expected_color($color): string
{
    if (empty($color) || is_array($color)) {
        return '';
    }

    $color = trim((string) $color);

    if (0 !== stripos($color, 'rgba')) {
        $sanitizedHex = sanitize_hex_color($color);

        return $sanitizedHex ? $sanitizedHex : '';
    }

    $pattern = '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d+|1\.0+)\s*\)$/i';

    if (!preg_match($pattern, $color, $matches)) {
        return '';
    }

    $components = [
        (int) $matches[1],
        (int) $matches[2],
        (int) $matches[3],
    ];

    foreach ($components as $component) {
        if ($component < 0 || $component > 255) {
            return '';
        }
    }

    $alphaValue = (float) $matches[4];
    if ($alphaValue < 0 || $alphaValue > 1) {
        return '';
    }

    $alpha = $matches[4];
    if ('.' === substr($alpha, 0, 1)) {
        $alpha = '0' . $alpha;
    }

    $alpha = rtrim($alpha, '0');
    $alpha = rtrim($alpha, '.');
    if ($alpha === '') {
        $alpha = '0';
    }

    return sprintf('rgba(%d,%d,%d,%s)', $components[0], $components[1], $components[2], $alpha);
}

$expectedBgColor = normalize_expected_color($defaults['bg_color'] ?? '');
$expectedBgColorStart = normalize_expected_color($defaults['bg_color_start'] ?? '');
$expectedBgColorEnd = normalize_expected_color($defaults['bg_color_end'] ?? '');
$expectedAccentColor = normalize_expected_color($defaults['accent_color'] ?? '');
$expectedAccentColorStart = normalize_expected_color($defaults['accent_color_start'] ?? '');
$expectedAccentColorEnd = normalize_expected_color($defaults['accent_color_end'] ?? '');
$expectedFontColor = normalize_expected_color($defaults['font_color'] ?? '');
$expectedFontHoverColor = normalize_expected_color($defaults['font_hover_color'] ?? '');
$expectedMobileBgColor = normalize_expected_color($defaults['mobile_bg_color'] ?? '');
$expectedOverlayColor = normalize_expected_color($defaults['overlay_color'] ?? '');

$GLOBALS['wp_test_options']['sidebar_jlg_settings'] = [
    'enable_sidebar' => true,
    'bg_color_type' => 'gradient',
    'accent_color_type' => 'gradient',
    'bg_color' => '',
    'bg_color_start' => '',
    'bg_color_end' => '',
    'accent_color' => '',
    'accent_color_start' => '',
    'accent_color_end' => '',
    'font_color' => '',
    'font_hover_color' => '',
    'overlay_color' => '',
    'mobile_bg_color' => '',
];

$menuCache->clear();
$repository->revalidateStoredOptions();

$storedAfterRevalidation = $GLOBALS['wp_test_options']['sidebar_jlg_settings'] ?? [];

$testsPassed = true;

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

function assertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Needle `{$needle}` not found.\n";
}

assertSame($expectedBgColor, $storedAfterRevalidation['bg_color'] ?? null, 'Background color falls back to default when stored value is empty');
assertSame($expectedBgColorStart, $storedAfterRevalidation['bg_color_start'] ?? null, 'Background gradient start falls back to default when stored value is empty');
assertSame($expectedBgColorEnd, $storedAfterRevalidation['bg_color_end'] ?? null, 'Background gradient end falls back to default when stored value is empty');
assertSame($expectedAccentColor, $storedAfterRevalidation['accent_color'] ?? null, 'Accent color falls back to default when stored value is empty');
assertSame($expectedAccentColorStart, $storedAfterRevalidation['accent_color_start'] ?? null, 'Accent gradient start falls back to default when stored value is empty');
assertSame($expectedAccentColorEnd, $storedAfterRevalidation['accent_color_end'] ?? null, 'Accent gradient end falls back to default when stored value is empty');
assertSame($expectedFontColor, $storedAfterRevalidation['font_color'] ?? null, 'Font color falls back to default when stored value is empty');
assertSame($expectedFontHoverColor, $storedAfterRevalidation['font_hover_color'] ?? null, 'Font hover color falls back to default when stored value is empty');
assertSame($expectedOverlayColor, $storedAfterRevalidation['overlay_color'] ?? null, 'Overlay color falls back to default when stored value is empty');
assertSame($expectedMobileBgColor, $storedAfterRevalidation['mobile_bg_color'] ?? null, 'Mobile background color falls back to default when stored value is empty');

$GLOBALS['wp_test_inline_styles'] = [];
$renderer->enqueueAssets();
$inlineStyles = wp_test_get_inline_styles('sidebar-jlg-public-css');

ob_start();
$renderer->render();
ob_end_clean();

assertContains('--sidebar-bg-color: ' . $expectedBgColorStart . ';', $inlineStyles, 'Rendered CSS uses default background gradient start color after revalidation');
assertContains('linear-gradient(180deg, ' . $expectedBgColorStart . ' 0%, ' . $expectedBgColorEnd . ' 100%)', $inlineStyles, 'Rendered CSS uses default background gradient colors after revalidation');
assertContains('--primary-accent-color: ' . $expectedAccentColorStart . ';', $inlineStyles, 'Rendered CSS uses default accent gradient start color after revalidation');
assertContains('linear-gradient(90deg, ' . $expectedAccentColorStart . ' 0%, ' . $expectedAccentColorEnd . ' 100%)', $inlineStyles, 'Rendered CSS uses default accent gradient colors after revalidation');
assertContains('--sidebar-text-color: ' . $expectedFontColor . ';', $inlineStyles, 'Rendered CSS uses default font color after revalidation');
assertContains('--sidebar-text-hover-color: ' . $expectedFontHoverColor . ';', $inlineStyles, 'Rendered CSS uses default font hover color after revalidation');
assertContains('--overlay-color: ' . $expectedOverlayColor . ';', $inlineStyles, 'Rendered CSS uses default overlay color after revalidation');
assertContains('--mobile-bg-color: ' . $expectedMobileBgColor . ';', $inlineStyles, 'Rendered CSS uses default mobile background color after revalidation');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
