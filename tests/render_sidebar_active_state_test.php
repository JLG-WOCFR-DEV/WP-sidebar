<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

if (!function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return $GLOBALS['test_queried_object'] ?? null;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        $object = get_queried_object();
        if (is_object($object)) {
            if (isset($object->ID)) {
                return (int) $object->ID;
            }

            if (isset($object->term_id)) {
                return (int) $object->term_id;
            }
        }

        return 0;
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$baseSettings = $settingsRepository->getDefaultSettings();
$baseSettings['social_icons'] = [];
$baseSettings['menu_items'] = [];

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

function assertContains(string $needle, string $haystack, string $message): void
{
    assertTrue(strpos($haystack, $needle) !== false, $message);
}

function runSidebarScenario(array $menuItem, callable $configureContext): array
{
    global $renderer, $menuCache, $baseSettings;

    $settings = $baseSettings;
    $settings['menu_items'] = [$menuItem];

    update_option('sidebar_jlg_settings', $settings);

    $GLOBALS['test_queried_object'] = null;
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/';
    unset($_SERVER['HTTPS']);

    $configureContext();

    $menuCache->clear();
    $GLOBALS['wp_test_transients'] = [];

    $html = $renderer->render();
    assertTrue(is_string($html), 'Sidebar renderer returned HTML for active state scenario');
    $html = (string) $html;

    return ['html' => $html, 'settings' => $settings];
}

$pageScenario = runSidebarScenario([
    'label' => 'Sample Page',
    'type'  => 'page',
    'value' => 42,
], function (): void {
    $GLOBALS['test_queried_object'] = (object) [
        'ID'        => 42,
        'post_type' => 'page',
    ];
});

assertContains('current-menu-item', $pageScenario['html'], 'Page item marked as current on matching page');
assertContains('aria-current="page"', $pageScenario['html'], 'Page item includes aria-current attribute');
assertTrue($renderer->is_sidebar_output_dynamic($pageScenario['settings']), 'Page scenario disables cached sidebar output');

$postScenario = runSidebarScenario([
    'label' => 'Sample Post',
    'type'  => 'post',
    'value' => 75,
], function (): void {
    $GLOBALS['test_queried_object'] = (object) [
        'ID'        => 75,
        'post_type' => 'post',
    ];
});

assertContains('current-menu-item', $postScenario['html'], 'Post item marked as current on matching post');
assertContains('aria-current="page"', $postScenario['html'], 'Post item includes aria-current attribute');
assertTrue($renderer->is_sidebar_output_dynamic($postScenario['settings']), 'Post scenario disables cached sidebar output');

$categoryScenario = runSidebarScenario([
    'label' => 'Sample Category',
    'type'  => 'category',
    'value' => 9,
], function (): void {
    $GLOBALS['test_queried_object'] = (object) [
        'term_id'  => 9,
        'taxonomy' => 'category',
    ];
});

assertContains('current-menu-item', $categoryScenario['html'], 'Category item marked as current on matching archive');
assertContains('aria-current="page"', $categoryScenario['html'], 'Category item includes aria-current attribute');
assertTrue($renderer->is_sidebar_output_dynamic($categoryScenario['settings']), 'Category scenario disables cached sidebar output');

$customScenario = runSidebarScenario([
    'label' => 'Custom Link',
    'type'  => 'custom',
    'value' => 'http://example.com/custom-link',
], function (): void {
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/custom-link/';
    $GLOBALS['test_queried_object'] = null;
});

assertContains('current-menu-item', $customScenario['html'], 'Custom URL item marked as current when URLs match');
assertContains('aria-current="page"', $customScenario['html'], 'Custom URL item includes aria-current attribute');
assertTrue($renderer->is_sidebar_output_dynamic($customScenario['settings']), 'Custom URL scenario disables cached sidebar output');

$relativeCustomScenario = runSidebarScenario([
    'label' => 'Relative Custom Link',
    'type'  => 'custom',
    'value' => '/relative-path/',
], function (): void {
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/relative-path/';
    $GLOBALS['test_queried_object'] = null;
});

assertContains('current-menu-item', $relativeCustomScenario['html'], 'Relative custom URL item marked as current when URLs match');
assertContains('aria-current="page"', $relativeCustomScenario['html'], 'Relative custom URL item includes aria-current attribute');
assertTrue($renderer->is_sidebar_output_dynamic($relativeCustomScenario['settings']), 'Relative custom URL scenario disables cached sidebar output');

if ($testsPassed) {
    echo "Render sidebar active state tests passed.\n";
    exit(0);
}

echo "Render sidebar active state tests failed.\n";
exit(1);
