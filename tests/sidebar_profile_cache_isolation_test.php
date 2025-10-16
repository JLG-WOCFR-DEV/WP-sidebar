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

function renderSidebarHtml(): string
{
    global $renderer;

    $resetResolver = \Closure::bind(
        static function (): void {
            if (self::$sharedRequestContextResolver !== null) {
                self::$sharedRequestContextResolver->resetCachedContext();
            }
        },
        null,
        \JLG\Sidebar\Frontend\SidebarRenderer::class
    );

    if (is_callable($resetResolver)) {
        $resetResolver();
    }

    $html = $renderer->render();
    assertTrue(is_string($html), 'Sidebar renderer returned HTML during profile cache isolation test');

    return (string) $html;
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

update_option('sidebar_jlg_profiles', $baseSettings['profiles'], 'no');
$settingsRepository->saveOptions($baseSettings);
$storedSettings = get_option('sidebar_jlg_settings', []);
if (is_array($storedSettings)) {
    $storedSettings['profiles'] = $baseSettings['profiles'];
    update_option('sidebar_jlg_settings', $storedSettings, 'no');
}

$cache->clear();
$GLOBALS['wp_test_transients'] = [];
switch_to_locale('en_US');

$setPostContext = static function (string $postType): void {
    $GLOBALS['test_post_type'] = $postType;
    $GLOBALS['test_queried_object'] = (object) ['post_type' => $postType];
};

$setPostContext('post');
$postProfileHtml = renderSidebarHtml();

assertContains('Post Profile Nav', $postProfileHtml, 'Post profile navigation label rendered');
assertNotContains('Page Profile Nav', $postProfileHtml, 'Page profile label absent from post profile cache');
assertTrue(
    isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_post-profile']),
    'Post profile cache stored with profile suffix'
);

$setPostContext('page');
$pageProfileHtml = renderSidebarHtml();

assertContains('Page Profile Nav', $pageProfileHtml, 'Page profile navigation label rendered');
assertNotContains('Post Profile Nav', $pageProfileHtml, 'Page profile render does not reuse post profile cache');
assertTrue(
    isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_page-profile']),
    'Page profile cache stored under unique key'
);
assertTrue($pageProfileHtml !== $postProfileHtml, 'Page profile HTML differs from post profile HTML');

$setPostContext('post');
$secondPostHtml = renderSidebarHtml();

assertSame($postProfileHtml, $secondPostHtml, 'Post profile cache reused on subsequent render');
assertTrue(
    isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_post-profile']),
    'Post profile transient persists after reuse'
);

$cache->clear();
$GLOBALS['wp_test_transients'] = [];
unset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']);

$dynamicSettings = $settingsRepository->getDefaultSettings();
$dynamicSettings['enable_sidebar'] = true;
$dynamicSettings['social_icons'] = [];
$dynamicSettings['menu_items'] = [];
$dynamicSettings['profiles'] = [
    [
        'id' => 'post-profile',
        'priority' => 10,
        'conditions' => [
            'post_types' => ['post'],
        ],
        'settings' => [
            'nav_aria_label' => 'Post Profile Nav',
            'enable_search' => false,
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
            'enable_search' => true,
        ],
    ],
];

update_option('sidebar_jlg_profiles', $dynamicSettings['profiles'], 'no');
$settingsRepository->saveOptions($dynamicSettings);
$storedDynamicSettings = get_option('sidebar_jlg_settings', []);
if (is_array($storedDynamicSettings)) {
    $storedDynamicSettings['profiles'] = $dynamicSettings['profiles'];
    update_option('sidebar_jlg_settings', $storedDynamicSettings, 'no');
}

switch_to_locale('en_US');

$setPostContext('post');
$dynamicPostHtml = renderSidebarHtml();
$dynamicPostKey = $cache->getTransientKey('en_US', 'post-profile');
assertTrue(isset($GLOBALS['wp_test_transients'][$dynamicPostKey]), 'Post profile cache stored before dynamic render scenario');

$setPostContext('page');
$dynamicPageHtml = renderSidebarHtml();
$dynamicPageKey = $cache->getTransientKey('en_US', 'page-profile');
assertTrue(!isset($GLOBALS['wp_test_transients'][$dynamicPageKey]), 'Dynamic page profile render skips cache storage');

$cachedLocales = get_option('sidebar_jlg_cached_locales', []);
$postEntryRetained = false;
$pageEntryStored = false;

if (is_array($cachedLocales) && isset($cachedLocales['en_US']) && is_array($cachedLocales['en_US'])) {
    foreach ($cachedLocales['en_US'] as $suffixKey => $_value) {
        if (!is_string($suffixKey) || $suffixKey === '__default__') {
            continue;
        }

        if ($suffixKey === 'post-profile') {
            $postEntryRetained = true;
        }

        if ($suffixKey === 'page-profile') {
            $pageEntryStored = true;
        }
    }
} elseif (is_array($cachedLocales)) {
    foreach ($cachedLocales as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $entryLocale = isset($entry['locale']) ? (string) $entry['locale'] : '';

        if ($entryLocale !== 'en_US') {
            continue;
        }

        $entrySuffix = $entry['suffix'] ?? null;

        if ($entrySuffix === 'post-profile') {
            $postEntryRetained = true;
        }

        if ($entrySuffix === 'page-profile') {
            $pageEntryStored = true;
        }
    }
}

assertTrue($postEntryRetained, 'Locale index retains cached post profile entry after dynamic render');
assertTrue(!$pageEntryStored, 'Locale index does not persist dynamic page profile entry');

if ($testsPassed) {
    echo "Sidebar profile cache isolation tests passed.\n";
    exit(0);
}

echo "Sidebar profile cache isolation tests failed.\n";
exit(1);
