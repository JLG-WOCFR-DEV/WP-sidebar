<?php
declare(strict_types=1);

use JLG\Sidebar\Icons\IconLibrary;

require __DIR__ . '/bootstrap.php';

$testsPassed = true;

function assertTrue($condition, string $message): void
{
    global $testsPassed;
    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertNull($value, string $message): void
{
    assertTrue($value === null, $message);
}

$activationCallback = null;

$GLOBALS['wp_test_function_overrides']['register_activation_hook'] = static function ($file, $callback) use (&$activationCallback): void {
    $activationCallback = $callback;
};

$GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = static function (): array {
    return [
        'basedir' => '',
        'baseurl' => '',
        'error'   => 'Uploads directory is unavailable',
    ];
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

assertTrue(is_callable($activationCallback), 'Activation hook is registered');

$activationException = null;

try {
    ($activationCallback)();
} catch (Throwable $exception) {
    $activationException = $exception;
}

assertTrue($activationException === null, 'Activation callback completes without throwing');

$transientMessage = get_transient('sidebar_jlg_activation_error');
assertTrue(is_array($transientMessage), 'Activation failure details are stored in a transient array');
$transientDetails = is_array($transientMessage) ? ($transientMessage['details'] ?? '') : '';
assertTrue(($transientMessage['code'] ?? '') === 'uploads_access_error', 'Transient payload includes the uploads error code');
assertTrue(is_string($transientDetails) && strpos($transientDetails, 'Uploads directory is unavailable') !== false, 'Transient message includes upload error details');

$iconLibrary = new IconLibrary(__FILE__);
$allIcons = $iconLibrary->getAllIcons();

assertTrue($allIcons === [], 'Icon library returns an empty array when uploads directory is unavailable');
assertTrue($iconLibrary->consumeRejectedCustomIcons() === [], 'No rejected custom icons are recorded when uploads directory is unavailable');
assertNull($iconLibrary->getCustomIconSource('custom_missing'), 'Custom icon source lookup returns null when no icons are loaded');

if ($testsPassed) {
    echo "Upload directory failure regression test passed.\n";
    exit(0);
}

echo "Upload directory failure regression test failed.\n";
exit(1);
