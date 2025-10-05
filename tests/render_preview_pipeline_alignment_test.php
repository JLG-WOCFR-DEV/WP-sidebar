<?php
declare(strict_types=1);

use JLG\Sidebar\Ajax\Endpoints;
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
        return true;
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

$endpoints = new Endpoints(
    $settings,
    $cache,
    $icons,
    $sanitizer,
    $plugin->getPluginFile(),
    $renderer
);

$options = $settings->getDefaultSettings();
$options['enable_sidebar'] = true;

update_option('sidebar_jlg_settings', $options);
$cache->clear();
$cache->forgetLocaleIndex();
$GLOBALS['wp_test_transients'] = [];

$_POST = [
    'nonce' => 'preview-nonce',
    'options' => $options,
];

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

ob_start();
$renderReturn = $renderer->render();
$renderedHtml = ob_get_clean();

if (!is_string($renderedHtml) || $renderedHtml === '') {
    $renderedHtml = is_string($renderReturn) ? $renderReturn : '';
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

if ($testsPassed) {
    echo "Render preview pipeline alignment tests passed.\n";
    exit(0);
}

echo "Render preview pipeline alignment tests failed.\n";
exit(1);
