<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        return $GLOBALS['test_post_type'] ?? null;
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return $GLOBALS['test_queried_object'] ?? null;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        return $GLOBALS['test_queried_object_id'] ?? 0;
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();

$baseSettings = $settingsRepository->getDefaultSettings();
$baseSettings['enable_sidebar'] = false;
$baseSettings['social_icons'] = [];
$baseSettings['menu_items'] = [];
$baseSettings['profiles'] = [
    [
        'id' => 'conditional-page',
        'priority' => 5,
        'conditions' => [
            'post_types' => ['page'],
        ],
        'settings' => [
            'enable_sidebar' => true,
            'layout_style' => 'floating',
            'desktop_behavior' => 'overlay',
            'sidebar_position' => 'right',
        ],
    ],
];

$settingsRepository->saveOptions($baseSettings);

$previousAddFilter = $GLOBALS['wp_test_function_overrides']['add_filter'] ?? null;
$previousAddAction = $GLOBALS['wp_test_function_overrides']['add_action'] ?? null;

$registeredFilters = [];
$registeredActions = [];

$GLOBALS['wp_test_function_overrides']['add_filter'] = static function ($hook, $callback) use (&$registeredFilters): void {
    $registeredFilters[$hook][] = $callback;
};

$GLOBALS['wp_test_function_overrides']['add_action'] = static function ($hook, $callback) use (&$registeredActions): void {
    $registeredActions[$hook][] = $callback;
};

$renderer->registerHooks();

$GLOBALS['wp_test_function_overrides']['add_filter'] = $previousAddFilter;
$GLOBALS['wp_test_function_overrides']['add_action'] = $previousAddAction;

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

$bodyClassCallback = $registeredFilters['body_class'][0] ?? null;
assertTrue(is_callable($bodyClassCallback), 'Body class hook registered');

$bodyOpenCallbacks = $registeredActions['wp_body_open'] ?? [];
assertTrue($bodyOpenCallbacks !== [], 'wp_body_open hook registered');

$GLOBALS['test_post_type'] = 'page';
$GLOBALS['test_queried_object'] = (object) [
    'post_type' => 'page',
    'ID' => 123,
];
$GLOBALS['test_queried_object_id'] = 123;

$initialClasses = ['existing-class'];
if (is_callable($bodyClassCallback)) {
    $finalClasses = $bodyClassCallback($initialClasses);
} else {
    $finalClasses = $initialClasses;
}

assertTrue(in_array('jlg-sidebar-active', $finalClasses, true), 'Active sidebar class added');
assertTrue(in_array('jlg-sidebar-position-right', $finalClasses, true), 'Sidebar position class reflects conditional profile');
assertTrue(in_array('jlg-sidebar-overlay', $finalClasses, true), 'Overlay behavior class applied');
assertTrue(in_array('jlg-sidebar-floating', $finalClasses, true), 'Floating layout class applied');

ob_start();
foreach ($bodyOpenCallbacks as $callback) {
    if (is_callable($callback)) {
        $callback();
    }
}
$bodyOpenOutput = (string) ob_get_clean();

assertContains('document.body.dataset.sidebarPosition', $bodyOpenOutput, 'Body data script sets sidebar position dataset');
assertContains('data-sidebar-position', $bodyOpenOutput, 'Body data script sets data-sidebar-position attribute');

if ($testsPassed) {
    echo "Sidebar profile hooks tests passed.\n";
    exit(0);
}

echo "Sidebar profile hooks tests failed.\n";
exit(1);
