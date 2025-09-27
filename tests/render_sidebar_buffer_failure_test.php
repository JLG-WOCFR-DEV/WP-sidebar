<?php
declare(strict_types=1);

namespace JLG\Sidebar\Frontend {
    function ob_get_clean()
    {
        if (!empty($GLOBALS['jlg_sidebar_test_fail_ob_get_clean'])) {
            $GLOBALS['jlg_sidebar_test_fail_ob_get_clean']--;

            return false;
        }

        return \ob_get_clean();
    }
}

namespace {

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

$GLOBALS['jlg_sidebar_test_fail_ob_get_clean'] = 0;

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$renderer = $plugin->getSidebarRenderer();
$settingsRepository = $plugin->getSettingsRepository();
$menuCache = $plugin->getMenuCache();

$defaultSettings = $settingsRepository->getDefaultSettings();
$defaultSettings['menu_items'] = [
    [
        'label'     => 'Buffer Failure Item',
        'type'      => 'page',
        'icon_type' => 'svg_inline',
        'icon'      => '',
        'value'     => 321,
    ],
];
$defaultSettings['social_icons'] = [];

update_option('sidebar_jlg_settings', $defaultSettings);
$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];

function assertTrue(bool $condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

$testsPassed = true;

$GLOBALS['jlg_sidebar_test_fail_ob_get_clean'] = 1;

\ob_start();
$renderer->render();
$output = \ob_get_clean();

$transientKey = $menuCache->getTransientKey($menuCache->getLocaleForCache());

assertTrue($output === '', 'Renderer outputs nothing when buffer capture fails.');
assertFalse(array_key_exists($transientKey, $GLOBALS['wp_test_transients'] ?? []), 'Cache is skipped when buffer capture fails.');

unset($GLOBALS['jlg_sidebar_test_fail_ob_get_clean']);

if ($testsPassed) {
    echo "Render sidebar buffer failure tests passed.\n";
    exit(0);
}

echo "Render sidebar buffer failure tests failed.\n";
exit(1);

}
