<?php
declare(strict_types=1);

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

function assertSame($expected, $actual, string $message): void
{
    assertTrue($expected === $actual, $message);
}

update_option('sidebar_jlg_plugin_version', '4.1.0');
assertSame('4.1.0', get_option('sidebar_jlg_plugin_version'), 'Plugin version option is seeded before uninstall');

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}

require __DIR__ . '/../sidebar-jlg/uninstall.php';

$deletedValue = get_option('sidebar_jlg_plugin_version', null);
assertSame(null, $deletedValue, 'Plugin version option is removed during uninstall');

if ($testsPassed) {
    echo "Uninstall version cleanup test passed.\n";
    exit(0);
}

echo "Uninstall version cleanup test failed.\n";
exit(1);
