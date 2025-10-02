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

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        $user = $GLOBALS['test_current_user'] ?? null;

        if ($user === null) {
            return (object) ['roles' => []];
        }

        return $user;
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$cache = $plugin->getMenuCache();

$baseSettings = $settingsRepository->getDefaultSettings();
$baseSettings['enable_sidebar'] = true;
$baseSettings['social_icons'] = [];
$baseSettings['menu_items'] = [];
$baseSettings['profiles'] = [
    [
        'id' => 'subscriber-disabled',
        'priority' => 20,
        'conditions' => [
            'roles' => ['subscriber'],
        ],
        'settings' => [
            'enable_sidebar' => false,
        ],
    ],
    [
        'id' => 'page-special',
        'priority' => 5,
        'conditions' => [
            'post_types' => ['page'],
        ],
        'settings' => [
            'animation_type' => 'slide-right',
        ],
    ],
    [
        'id' => 'french-profile',
        'priority' => 3,
        'conditions' => [
            'languages' => ['fr_FR'],
        ],
        'settings' => [
            'animation_type' => 'fade',
        ],
    ],
];

$settingsRepository->saveOptions($baseSettings);

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
    assertTrue($expected === $actual, $message);
}

$previousLocalizeOverride = $GLOBALS['wp_test_function_overrides']['wp_localize_script'] ?? null;

$resetContext = static function (): void {
    global $cache;

    $cache->clear();
    $GLOBALS['wp_test_transients'] = [];
    $GLOBALS['test_post_type'] = null;
    $GLOBALS['test_queried_object'] = null;
};

// Scenario 1: Subscriber role disables the sidebar entirely.
$resetContext();
$GLOBALS['test_current_user'] = (object) ['roles' => ['subscriber']];
$GLOBALS['test_post_type'] = 'page';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'page'];

$localizedData = 'not-called';
$GLOBALS['wp_test_function_overrides']['wp_localize_script'] = static function (...$args) use (&$localizedData): void {
    $localizedData = $args[2] ?? null;
};

$renderer->enqueueAssets();
assertSame('not-called', $localizedData, 'Assets skipped when subscriber profile disables sidebar');

ob_start();
$renderer->render();
$subscriberHtml = (string) ob_get_clean();
assertSame('', $subscriberHtml, 'Render output empty when subscriber profile disables sidebar');

// Scenario 2: Editor on a page receives the page-specific profile.
$resetContext();
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];
$GLOBALS['test_post_type'] = 'page';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'page'];

$localizedData = null;
$GLOBALS['wp_test_function_overrides']['wp_localize_script'] = static function (...$args) use (&$localizedData): void {
    $localizedData = $args[2] ?? null;
};

$renderer->enqueueAssets();
assertTrue(is_array($localizedData), 'Localized data generated for matching page profile');
assertSame('page-special', $localizedData['active_profile_id'] ?? null, 'Page profile identifier exposed to scripts');
assertSame(false, $localizedData['is_fallback_profile'] ?? null, 'Page profile flagged as non-fallback');
assertSame('slide-right', $localizedData['animation_type'] ?? null, 'Page profile overrides animation type');

// Scenario 3: Locale-specific profile applies on posts when language matches.
$resetContext();
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];
$GLOBALS['test_post_type'] = 'post';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'post'];
switch_to_locale('fr_FR');

$localizedData = null;
$GLOBALS['wp_test_function_overrides']['wp_localize_script'] = static function (...$args) use (&$localizedData): void {
    $localizedData = $args[2] ?? null;
};

$renderer->enqueueAssets();
assertTrue(is_array($localizedData), 'Localized data generated for language profile');
assertSame('french-profile', $localizedData['active_profile_id'] ?? null, 'Language profile identifier exposed');
assertSame(false, $localizedData['is_fallback_profile'] ?? null, 'Language profile flagged as non-fallback');
assertSame('fade', $localizedData['animation_type'] ?? null, 'Language profile overrides animation type');

// Scenario 4: Fallback profile used when no conditions match.
$resetContext();
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];
$GLOBALS['test_post_type'] = 'post';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'post'];
switch_to_locale('en_US');

$localizedData = null;
$GLOBALS['wp_test_function_overrides']['wp_localize_script'] = static function (...$args) use (&$localizedData): void {
    $localizedData = $args[2] ?? null;
};

$renderer->enqueueAssets();
assertTrue(is_array($localizedData), 'Localized data generated for fallback profile');
assertSame('default', $localizedData['active_profile_id'] ?? null, 'Fallback profile identifier exposed');
assertSame(true, $localizedData['is_fallback_profile'] ?? null, 'Fallback indicator flagged for default profile');
assertSame('slide-left', $localizedData['animation_type'] ?? null, 'Fallback profile keeps default animation type');

$resetContext();
$GLOBALS['test_current_user'] = null;
switch_to_locale('fr_FR');

if ($previousLocalizeOverride !== null) {
    $GLOBALS['wp_test_function_overrides']['wp_localize_script'] = $previousLocalizeOverride;
} else {
    unset($GLOBALS['wp_test_function_overrides']['wp_localize_script']);
}

if ($testsPassed) {
    echo "Profile selector integration tests passed.\n";
    exit(0);
}

echo "Profile selector integration tests failed.\n";
exit(1);
