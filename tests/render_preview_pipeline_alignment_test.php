<?php
declare(strict_types=1);

use JLG\Sidebar\Accessibility\AuditRunner;
use JLG\Sidebar\Ajax\Endpoints;
use JLG\Sidebar\Settings\SettingsMaintenanceRunner;
use JLG\Sidebar\Settings\SettingsRepository;
use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

if (!function_exists('wp_send_json_success')) {
    class SidebarPreviewResponse extends \Exception
    {
        /** @var array|null */
        public $payload;
        /** @var string */
        public $status;

        public function __construct(string $status, ?array $payload = null)
        {
            parent::__construct($status);
            $this->status = $status;
            $this->payload = $payload;
        }
    }

    function wp_send_json_success($data = null): void
    {
        throw new SidebarPreviewResponse('success', is_array($data) ? $data : null);
    }

    function wp_send_json_error($data = null): void
    {
        throw new SidebarPreviewResponse('error', is_array($data) ? $data : null);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        if ($capability === 'manage_options' && isset($GLOBALS['wp_test_current_user_can_manage_options'])) {
            return (bool) $GLOBALS['wp_test_current_user_can_manage_options'];
        }

        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return isset($GLOBALS['wp_test_is_admin']) ? (bool) $GLOBALS['wp_test_is_admin'] : true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return isset($GLOBALS['wp_test_is_user_logged_in']) ? (bool) $GLOBALS['wp_test_is_user_logged_in'] : true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false)
    {
        $GLOBALS['checked_nonces'][] = [$action, $query_arg];

        return true;
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settings = $plugin->getSettingsRepository();
$cache = $plugin->getMenuCache();
$icons = $plugin->getIconLibrary();
$sanitizer = $plugin->getSanitizer();
$renderer = $plugin->getSidebarRenderer();
$analytics = $plugin->getAnalyticsRepository();
$queue = $plugin->getAnalyticsQueue();
$auditRunner = new AuditRunner($plugin->getPluginFile());

$endpoints = new Endpoints(
    $settings,
    $cache,
    $icons,
    $sanitizer,
    $analytics,
    $queue,
    $plugin->getEventRateLimiter(),
    $plugin->getPluginFile(),
    $renderer,
    $auditRunner
);

$options = $settings->getDefaultSettings();
$options['enable_sidebar'] = true;
$options['menu_items'] = [
    [
        'type' => 'nav_menu',
        'value' => 99,
        'nav_menu_filter' => 'all',
        'nav_menu_max_depth' => '3',
        'icon_type' => 'svg_inline',
        'icon' => 'custom_missing_icon',
    ],
];

$GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_object'] = static function () {
    return false;
};

update_option('sidebar_jlg_settings', $options);
$cache->clear();
$cache->forgetLocaleIndex();
$GLOBALS['wp_test_transients'] = [];

$_POST = [
    'nonce' => 'preview-nonce',
    'options' => $options,
];

$GLOBALS['wp_test_is_admin'] = false;
$GLOBALS['wp_test_is_user_logged_in'] = false;
$GLOBALS['wp_test_current_user_can_manage_options'] = false;

$settings->getOptionsWithRevalidation();

$queuedPayload = $GLOBALS['wp_test_options'][SettingsRepository::REVALIDATION_QUEUE_OPTION] ?? null;
if (!is_array($queuedPayload)) {
    echo "[FAIL] Expected revalidated options to be queued for maintenance.\n";
    exit(1);
}

$GLOBALS['wp_test_is_admin'] = true;
$GLOBALS['wp_test_is_user_logged_in'] = true;
$GLOBALS['wp_test_current_user_can_manage_options'] = true;

try {
    $endpoints->ajax_render_preview();
    $previewPayload = null;
} catch (SidebarPreviewResponse $response) {
    if ($response->status !== 'success') {
        echo "[FAIL] Preview endpoint returned error status.\n";
        exit(1);
    }
    $previewPayload = $response->payload;
}

if (!is_array($previewPayload) || !isset($previewPayload['html'])) {
    echo "[FAIL] Preview endpoint did not provide HTML payload.\n";
    exit(1);
}

$previewHtml = (string) $previewPayload['html'];

$renderedHtml = $renderer->render();

if (!is_string($renderedHtml)) {
    echo "[FAIL] Frontend renderer did not return HTML string.\n";
    exit(1);
}

$testsPassed = true;

function assertSame($expected, $actual, string $message): void
{
    global $testsPassed;
    if ($expected === $actual) {
        echo "[PASS] {$message}\n";
        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message} (expected length " . strlen($expected) . ", got " . strlen($actual) . ")\n";
}

assertSame($previewHtml, $renderedHtml, 'Preview HTML matches frontend render output');

$maintenance = new SettingsMaintenanceRunner($settings);
$maintenance->applyQueuedRevalidations();

$queuedAfter = $GLOBALS['wp_test_options'][SettingsRepository::REVALIDATION_QUEUE_OPTION] ?? null;
if ($queuedAfter !== null) {
    echo "[FAIL] Expected queued maintenance payload to be cleared after runner execution.\n";
    exit(1);
}

$persistedMenuItem = $GLOBALS['wp_test_options']['sidebar_jlg_settings']['menu_items'][0] ?? null;
if (!is_array($persistedMenuItem)) {
    echo "[FAIL] Expected persisted menu item after maintenance.\n";
    exit(1);
}

if (($persistedMenuItem['value'] ?? null) !== 0) {
    echo "[FAIL] Expected invalid menu ID to be reset during maintenance.\n";
    exit(1);
}

if (!isset($persistedMenuItem['nav_menu_max_depth']) || !is_int($persistedMenuItem['nav_menu_max_depth']) || $persistedMenuItem['nav_menu_max_depth'] !== 3) {
    echo "[FAIL] Expected nav menu depth to be normalized to integer during maintenance.\n";
    exit(1);
}

if (isset($persistedMenuItem['icon']) && $persistedMenuItem['icon'] !== '') {
    echo "[FAIL] Expected missing custom icon to be cleared during maintenance.\n";
    exit(1);
}

unset($GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_object']);

if ($testsPassed) {
    echo "Render preview pipeline alignment tests passed.\n";
    exit(0);
}

echo "Render preview pipeline alignment tests failed.\n";
exit(1);
