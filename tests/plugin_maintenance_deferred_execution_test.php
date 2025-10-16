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
$originalScheduleOverride = $GLOBALS['wp_test_function_overrides']['wp_schedule_single_event'] ?? null;
$originalNextScheduledOverride = $GLOBALS['wp_test_function_overrides']['wp_next_scheduled'] ?? null;

// Frontend request primes the maintenance flag and schedules the deferred task.
$GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] = '4.7.0';
unset($GLOBALS['wp_test_options']['sidebar_jlg_pending_maintenance']);
$GLOBALS['wp_test_cron_events'] = [];

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
    ($GLOBALS['wp_test_options']['sidebar_jlg_pending_maintenance'] ?? null) === 'scheduled',
    'Frontend bootstrap marks maintenance flag as scheduled when version is outdated'
);
assertTestCondition(
    ($GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] ?? null) === '4.7.0',
    'Frontend bootstrap defers plugin version update until maintenance runs'
);

assertTestCondition(
    !empty($GLOBALS['wp_test_cron_events']['sidebar_jlg_run_maintenance'] ?? []),
    'Frontend bootstrap schedules the single maintenance event'
);

// Run the scheduled maintenance task.
$simulateHook('sidebar_jlg_run_maintenance', $registeredHooks);

assertTestCondition(
    empty($GLOBALS['wp_test_options']['sidebar_jlg_pending_maintenance'] ?? null),
    'Deferred maintenance clears the pending flag after execution'
);
assertTestCondition(
    ($GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] ?? null) === SIDEBAR_JLG_VERSION,
    'Deferred maintenance updates plugin version to current release'
);

if ($originalAddActionOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['add_action']);
} else {
    $GLOBALS['wp_test_function_overrides']['add_action'] = $originalAddActionOverride;
}

if ($originalScheduleOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['wp_schedule_single_event']);
} else {
    $GLOBALS['wp_test_function_overrides']['wp_schedule_single_event'] = $originalScheduleOverride;
}

if ($originalNextScheduledOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['wp_next_scheduled']);
} else {
    $GLOBALS['wp_test_function_overrides']['wp_next_scheduled'] = $originalNextScheduledOverride;
}

if ($testsPassed) {
    echo "Plugin deferred maintenance tests passed.\n";
    exit(0);
}

echo "Plugin deferred maintenance tests failed.\n";
exit(1);
