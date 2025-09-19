<?php
declare(strict_types=1);

use JLG\Sidebar\Ajax\Endpoints;

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

if (!defined('SIDEBAR_JLG_SKIP_BOOTSTRAP')) {
    define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);
}

$GLOBALS['registered_actions'] = [];
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
{
    $GLOBALS['registered_actions'][] = [
        'hook' => $hook,
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function register_activation_hook($file, $callback): void {}
function wp_upload_dir(): array
{
    return [
        'basedir' => sys_get_temp_dir(),
        'baseurl' => 'http://example.com/uploads',
    ];
}
function wp_mkdir_p(string $dir): bool { return true; }
function trailingslashit($value): string
{
    return rtrim($value, "/\\") . '/';
}
function plugin_dir_path($file): string
{
    return trailingslashit(dirname($file));
}

$GLOBALS['test_current_user_can'] = true;
function current_user_can($capability): bool
{
    return $GLOBALS['test_current_user_can'];
}

$GLOBALS['checked_nonces'] = [];
function check_ajax_referer($action, $query_arg = false)
{
    $value = $query_arg !== false && isset($_POST[$query_arg]) ? $_POST[$query_arg] : null;
    $GLOBALS['checked_nonces'][] = [$action, $query_arg, $value];
}

class WP_Die_Exception extends Exception {}

$GLOBALS['json_success_payloads'] = [];
function wp_send_json_success($data = null)
{
    $GLOBALS['json_success_payloads'][] = $data;
    throw new WP_Die_Exception('success');
}

$GLOBALS['json_error_payloads'] = [];
function wp_send_json_error($data = null)
{
    $GLOBALS['json_error_payloads'][] = $data;
    throw new WP_Die_Exception('error');
}

function apply_filters($hook, $value, ...$args)
{
    return $value;
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

class SpySettingsRepository extends JLG\Sidebar\Settings\SettingsRepository
{
    public bool $deleteCalled = false;

    public function __construct()
    {
        // Intentionally bypass parent constructor; properties are unused in this spy.
    }

    public function deleteOptions(): void
    {
        $this->deleteCalled = true;
    }

    public function getDefaultSettings(): array
    {
        return [];
    }
}

class SpyMenuCache extends JLG\Sidebar\Cache\MenuCache
{
    public bool $clearCalled = false;

    public function clear(): void
    {
        $this->clearCalled = true;
    }
}

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
    assertTrue($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

$settingsSpy = new SpySettingsRepository();
$cacheSpy = new SpyMenuCache();
$endpoints = new Endpoints($settingsSpy, $cacheSpy);
$endpoints->registerHooks();

assertSame(3, count($GLOBALS['registered_actions']), 'All AJAX actions registered');
$registeredHooks = array_column($GLOBALS['registered_actions'], 'hook');
assertTrue(in_array('wp_ajax_jlg_get_posts', $registeredHooks, true), 'Posts AJAX action registered');
assertTrue(in_array('wp_ajax_jlg_get_categories', $registeredHooks, true), 'Categories AJAX action registered');
assertTrue(in_array('wp_ajax_jlg_reset_settings', $registeredHooks, true), 'Reset AJAX action registered');

$_POST['nonce'] = 'example-nonce';
$GLOBALS['checked_nonces'] = [];
$GLOBALS['json_success_payloads'] = [];

try {
    $endpoints->ajax_reset_settings();
} catch (WP_Die_Exception $e) {
    // Expected because wp_send_json_success() aborts execution in WordPress.
}

assertTrue($settingsSpy->deleteCalled, 'Settings repository deleteOptions invoked');
assertTrue($cacheSpy->clearCalled, 'Menu cache cleared');
assertSame('Réglages réinitialisés.', $GLOBALS['json_success_payloads'][0] ?? null, 'Success message returned');
assertSame(['jlg_reset_nonce', 'nonce', 'example-nonce'], $GLOBALS['checked_nonces'][0] ?? null, 'Nonce validated before clearing settings');

$settingsSpyUnauthorized = new SpySettingsRepository();
$cacheSpyUnauthorized = new SpyMenuCache();
$unauthorizedEndpoints = new Endpoints($settingsSpyUnauthorized, $cacheSpyUnauthorized);
$GLOBALS['test_current_user_can'] = false;
$GLOBALS['json_error_payloads'] = [];

try {
    $unauthorizedEndpoints->ajax_reset_settings();
} catch (WP_Die_Exception $e) {
    // Expected abort.
}

assertSame('Permission refusée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Unauthorized request rejected');
assertTrue(!$settingsSpyUnauthorized->deleteCalled, 'Settings not deleted when unauthorized');
assertTrue(!$cacheSpyUnauthorized->clearCalled, 'Cache not cleared when unauthorized');

if ($testsPassed) {
    echo "AJAX Endpoints tests passed.\n";
    exit(0);
}

echo "AJAX Endpoints tests failed.\n";
exit(1);
