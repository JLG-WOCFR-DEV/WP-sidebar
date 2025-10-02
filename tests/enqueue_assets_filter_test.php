<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$previousApplyFiltersOverride = $GLOBALS['wp_test_function_overrides']['apply_filters'] ?? null;
$previousEnqueueStyleOverride = $GLOBALS['wp_test_function_overrides']['wp_enqueue_style'] ?? null;
$previousEnqueueScriptOverride = $GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] ?? null;

$enqueuedStyles = [];
$enqueuedScripts = [];

$GLOBALS['wp_test_function_overrides']['apply_filters'] = static function ($hook, $value, ...$args) {
    if ($hook === 'sidebar_jlg_should_enqueue_public_assets') {
        return false;
    }

    return $value;
};

$GLOBALS['wp_test_function_overrides']['wp_enqueue_style'] = static function (...$args) use (&$enqueuedStyles): void {
    $handle = $args[0] ?? null;

    if ($handle !== null) {
        $enqueuedStyles[] = $handle;
    }
};

$GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] = static function (...$args) use (&$enqueuedScripts): void {
    $handle = $args[0] ?? null;

    if ($handle !== null) {
        $enqueuedScripts[] = $handle;
    }
};

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();

$options = $settingsRepository->getDefaultSettings();
$options['enable_sidebar'] = true;
$settingsRepository->saveOptions($options);

$renderer->enqueueAssets();

$testsPassed = true;

if ($enqueuedStyles === [] && $enqueuedScripts === []) {
    echo "[PASS] Public assets are skipped when filter returns false.\n";
} else {
    $testsPassed = false;
    echo "[FAIL] Public assets are skipped when filter returns false.\n";
    echo 'Enqueued styles: ' . json_encode($enqueuedStyles) . "\n";
    echo 'Enqueued scripts: ' . json_encode($enqueuedScripts) . "\n";
}

if ($previousApplyFiltersOverride !== null) {
    $GLOBALS['wp_test_function_overrides']['apply_filters'] = $previousApplyFiltersOverride;
} else {
    unset($GLOBALS['wp_test_function_overrides']['apply_filters']);
}

if ($previousEnqueueStyleOverride !== null) {
    $GLOBALS['wp_test_function_overrides']['wp_enqueue_style'] = $previousEnqueueStyleOverride;
} else {
    unset($GLOBALS['wp_test_function_overrides']['wp_enqueue_style']);
}

if ($previousEnqueueScriptOverride !== null) {
    $GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] = $previousEnqueueScriptOverride;
} else {
    unset($GLOBALS['wp_test_function_overrides']['wp_enqueue_script']);
}

if ($testsPassed) {
    exit(0);
}

exit(1);
