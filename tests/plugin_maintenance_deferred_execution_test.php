<?php
declare(strict_types=1);

use JLG\Sidebar\Plugin as SidebarPlugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$GLOBALS['test_is_admin'] = false;
$GLOBALS['test_current_user_can'] = true;

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return !empty($GLOBALS['test_is_admin']);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        return !empty($GLOBALS['test_current_user_can']);
    }
}

$testsPassed = true;

function assertTestCondition($condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

$simulateHook = static function (string $hook, array $registeredHooks): void {
    if (!isset($registeredHooks[$hook])) {
        return;
    }

    foreach ($registeredHooks[$hook] as $listener) {
        $callback = $listener['callback'];
        $acceptedArgs = $listener['accepted_args'];

        if ($acceptedArgs > 0) {
            call_user_func_array($callback, array_fill(0, $acceptedArgs, null));
        } else {
            call_user_func($callback);
        }
    }
};

$originalAddActionOverride = $GLOBALS['wp_test_function_overrides']['add_action'] ?? null;

// Frontend request primes the maintenance flag without executing heavy work.
$GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] = '4.7.0';
unset($GLOBALS['wp_test_options']['sidebar_jlg_pending_maintenance']);

$registeredHooks = [];
$GLOBALS['wp_test_function_overrides']['add_action'] = static function ($hook, $callback, $priority = 10, $accepted_args = 1) use (&$registeredHooks): void {
    $registeredHooks[$hook][] = [
        'callback' => $callback,
        'accepted_args' => (int) $accepted_args,
    ];
};

$frontendPlugin = new SidebarPlugin(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php', SIDEBAR_JLG_VERSION);
$frontendPlugin->register();

$GLOBALS['test_is_admin'] = false;
$simulateHook('plugins_loaded', $registeredHooks);

assertTestCondition(
    ($GLOBALS['wp_test_options']['sidebar_jlg_pending_maintenance'] ?? null) === 'yes',
    'Frontend bootstrap marks maintenance flag when version is outdated'
);
assertTestCondition(
    ($GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] ?? null) === '4.7.0',
    'Frontend bootstrap does not update plugin version option'
);

// Admin request runs the maintenance tasks and clears the flag.
$registeredHooks = [];
$GLOBALS['wp_test_function_overrides']['add_action'] = static function ($hook, $callback, $priority = 10, $accepted_args = 1) use (&$registeredHooks): void {
    $registeredHooks[$hook][] = [
        'callback' => $callback,
        'accepted_args' => (int) $accepted_args,
    ];
};

$GLOBALS['test_is_admin'] = true;
$GLOBALS['test_current_user_can'] = true;

$adminPlugin = new SidebarPlugin(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php', SIDEBAR_JLG_VERSION);
$adminPlugin->register();

$simulateHook('plugins_loaded', $registeredHooks);

assertTestCondition(
    empty($GLOBALS['wp_test_options']['sidebar_jlg_pending_maintenance'] ?? null),
    'Admin bootstrap clears maintenance flag after running tasks'
);
assertTestCondition(
    ($GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] ?? null) === SIDEBAR_JLG_VERSION,
    'Admin bootstrap updates plugin version to current release'
);

if ($originalAddActionOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['add_action']);
} else {
    $GLOBALS['wp_test_function_overrides']['add_action'] = $originalAddActionOverride;
}

if ($testsPassed) {
    echo "Plugin deferred maintenance tests passed.\n";
    exit(0);
}

echo "Plugin deferred maintenance tests failed.\n";
exit(1);
