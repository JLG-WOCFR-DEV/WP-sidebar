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

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$cache = $plugin->getMenuCache();

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

function assertNotContains(string $needle, string $haystack, string $message): void
{
    assertTrue(strpos($haystack, $needle) === false, $message);
}

function assertSame($expected, $actual, string $message): void
{
    assertTrue($expected === $actual, $message);
}

$baseSettings = $settingsRepository->getDefaultSettings();
$baseSettings['enable_sidebar'] = true;
$baseSettings['social_icons'] = [];
$baseSettings['menu_items'] = [];
$baseSettings['nav_aria_label'] = 'Default Profile Nav';
$baseSettings['profiles'] = [
    [
        'id' => 'post-profile',
        'priority' => 10,
        'conditions' => [
            'post_types' => ['post'],
        ],
        'settings' => [
            'nav_aria_label' => 'Post Profile Nav',
        ],
    ],
    [
        'id' => 'page-profile',
        'priority' => 10,
        'conditions' => [
            'post_types' => ['page'],
        ],
        'settings' => [
            'nav_aria_label' => 'Page Profile Nav',
        ],
    ],
];

$settingsRepository->saveOptions($baseSettings);

$cache->clear();
$GLOBALS['wp_test_transients'] = [];
switch_to_locale('en_US');

$setPostContext = static function (string $postType): void {
    $GLOBALS['test_post_type'] = $postType;
    $GLOBALS['test_queried_object'] = (object) ['post_type' => $postType];
};

$setPostContext('post');
ob_start();
$renderer->render();
$postProfileHtml = (string) ob_get_clean();

assertContains('Post Profile Nav', $postProfileHtml, 'Post profile navigation label rendered');
assertNotContains('Page Profile Nav', $postProfileHtml, 'Page profile label absent from post profile cache');
assertTrue(
    isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_post-profile']),
    'Post profile cache stored with profile suffix'
);

$setPostContext('page');
ob_start();
$renderer->render();
$pageProfileHtml = (string) ob_get_clean();

assertContains('Page Profile Nav', $pageProfileHtml, 'Page profile navigation label rendered');
assertNotContains('Post Profile Nav', $pageProfileHtml, 'Page profile render does not reuse post profile cache');
assertTrue(
    isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_page-profile']),
    'Page profile cache stored under unique key'
);
assertTrue($pageProfileHtml !== $postProfileHtml, 'Page profile HTML differs from post profile HTML');

$setPostContext('post');
ob_start();
$renderer->render();
$secondPostHtml = (string) ob_get_clean();

assertSame($postProfileHtml, $secondPostHtml, 'Post profile cache reused on subsequent render');
assertTrue(
    isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_post-profile']),
    'Post profile transient persists after reuse'
);

if ($testsPassed) {
    echo "Sidebar profile cache isolation tests passed.\n";
    exit(0);
}

echo "Sidebar profile cache isolation tests failed.\n";
exit(1);
