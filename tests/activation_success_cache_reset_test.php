<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$testsPassed = true;

function assertActivation(bool $condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

$activationCallback = null;
$mkdirRequests = [];

$GLOBALS['wp_test_function_overrides']['register_activation_hook'] = static function ($file, $callback) use (&$activationCallback): void {
    $activationCallback = $callback;
};

$uploadsBaseDir = sys_get_temp_dir() . '/sidebar_jlg_activation_' . uniqid();

$GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = static function () use ($uploadsBaseDir): array {
    return [
        'basedir' => $uploadsBaseDir,
        'error'   => null,
    ];
};

$GLOBALS['wp_test_function_overrides']['wp_mkdir_p'] = static function ($dir) use (&$mkdirRequests): bool {
    $mkdirRequests[] = $dir;

    return true;
};

$GLOBALS['wp_test_options']['sidebar_jlg_cached_locales'] = [
    ['locale' => 'fr_FR'],
    ['locale' => 'en_US', 'suffix' => 'profile_a'],
];

$GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR'] = '<div>cached fr</div>';
$GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_profile_a'] = '<div>cached en</div>';
$GLOBALS['wp_test_transients']['sidebar_jlg_full_html'] = '<div>legacy</div>';

update_option('sidebar_jlg_plugin_version', '0.0.1');

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

assertActivation(is_callable($activationCallback), 'Activation hook registered');

($activationCallback)();

assertActivation(get_transient('sidebar_jlg_activation_error') === false, 'No activation error transient is present after a successful run');
assertActivation(get_option('sidebar_jlg_plugin_version') === SIDEBAR_JLG_VERSION, 'Plugin version is updated during activation');
assertActivation(get_option('sidebar_jlg_cached_locales', '__missing__') === '__missing__', 'Cached locale index is removed');

assertActivation(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']), 'Locale cache transient is cleared (fr_FR)');
assertActivation(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_profile_a']), 'Locale cache transient is cleared (en_US profile)');
assertActivation(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html']), 'Legacy sidebar cache transient is cleared');

$expectedIconsDir = trailingslashit($uploadsBaseDir) . 'sidebar-jlg/icons/';
assertActivation(in_array($expectedIconsDir, $mkdirRequests, true), 'Icons directory is created when missing');

if ($testsPassed) {
    echo "Activation success cache reset test passed.\n";
    exit(0);
}

echo "Activation success cache reset test failed.\n";
exit(1);
