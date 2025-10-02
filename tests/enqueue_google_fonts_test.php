<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$previousEnqueueStyleOverride = $GLOBALS['wp_test_function_overrides']['wp_enqueue_style'] ?? null;

$enqueuedStyles = [];

$GLOBALS['wp_test_function_overrides']['wp_enqueue_style'] = static function (...$args) use (&$enqueuedStyles): void {
    $handle = $args[0] ?? '';
    $src = $args[1] ?? '';
    $enqueuedStyles[] = [
        'handle' => (string) $handle,
        'src'    => (string) $src,
    ];
};

$plugin = plugin();
$repository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();

$options = $repository->getDefaultSettings();
$options['enable_sidebar'] = true;
$options['font_family'] = 'google-roboto';
$options['font_weight'] = '700';
$repository->saveOptions($options);

$renderer->enqueueAssets();

$testsPassed = true;

function assertTrue($condition, string $message): void {
    if ($condition) {
        echo "[PASS] {$message}\n";
        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertSameValue($expected, $actual, string $message): void {
    if ($expected === $actual) {
        echo "[PASS] {$message}\n";
        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
}

$googleHandles = array_values(array_filter($enqueuedStyles, static function ($style) {
    return strpos($style['handle'], 'sidebar-jlg-google-font-') === 0;
}));

assertTrue(count($googleHandles) === 1, 'A Google font stylesheet is enqueued when a Google font is selected');
if ($googleHandles !== []) {
    assertTrue(strpos($googleHandles[0]['src'], 'fonts.googleapis.com') !== false, 'Google font stylesheet points to fonts.googleapis.com');
}

$enqueuedStyles = [];
$options['font_family'] = 'arial';
$repository->saveOptions($options);
$renderer->enqueueAssets();

$googleHandlesAfterSafeFont = array_values(array_filter($enqueuedStyles, static function ($style) {
    return strpos($style['handle'], 'sidebar-jlg-google-font-') === 0;
}));

assertSameValue([], $googleHandlesAfterSafeFont, 'No Google font stylesheet is enqueued for system fonts');

if ($previousEnqueueStyleOverride !== null) {
    $GLOBALS['wp_test_function_overrides']['wp_enqueue_style'] = $previousEnqueueStyleOverride;
} else {
    unset($GLOBALS['wp_test_function_overrides']['wp_enqueue_style']);
}

if ($testsPassed) {
    exit(0);
}

exit(1);
