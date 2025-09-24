<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$testsPassed = true;

function assertTestTrue($condition, string $message): void
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

$GLOBALS['wp_test_function_overrides']['register_activation_hook'] = static function ($file, $callback) use (&$activationCallback): void {
    $activationCallback = $callback;
};

$uploadsBaseDir = sys_get_temp_dir() . '/sidebar_jlg_missing_' . uniqid();

$GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = static function () use ($uploadsBaseDir): array {
    return [
        'basedir' => $uploadsBaseDir,
        'error'   => null,
    ];
};

$GLOBALS['wp_test_function_overrides']['wp_mkdir_p'] = static function ($dir): bool {
    echo "[INFO] wp_mkdir_p called for {$dir}\n";

    return false;
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

assertTestTrue(is_callable($activationCallback), 'Activation hook registered');

($activationCallback)();

$transient = get_transient('sidebar_jlg_activation_error');
assertTestTrue(is_string($transient) && $transient !== '', 'Activation error notice is stored in a transient');

$storedVersion = get_option('sidebar_jlg_plugin_version', null);
assertTestTrue($storedVersion === null, 'Plugin version option is not updated when the icons directory cannot be created');

if ($testsPassed) {
    echo "Icons directory creation failure test passed.\n";
    exit(0);
}

echo "Icons directory creation failure test failed.\n";
exit(1);
