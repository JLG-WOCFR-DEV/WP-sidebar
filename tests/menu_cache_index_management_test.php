<?php
declare(strict_types=1);

use JLG\Sidebar\Cache\MenuCache;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$cache = new MenuCache();

$GLOBALS['wp_test_options'] = [];
$GLOBALS['wp_test_transients'] = [];
$recordedEvents = [];

$GLOBALS['wp_test_function_overrides']['do_action'] = static function (string $hook, ...$args) use (&$recordedEvents): void {
    if ($hook === 'sidebar_jlg_cache_event') {
        $recordedEvents[] = $args;
    }
};

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
    if ($expected === $actual) {
        assertTrue(true, $message);

        return;
    }

    $expectedJson = json_encode($expected, JSON_PRETTY_PRINT);
    $actualJson = json_encode($actual, JSON_PRETTY_PRINT);
    assertTrue(false, $message . "\nExpected: {$expectedJson}\nActual: {$actualJson}");
}

// Populate the cache with repeated writes to ensure locale index deduplicates entries.
$cache->set('fr_FR', '<div>First FR</div>', 'profile-a');
$cache->set('fr_FR', '<div>Second FR</div>', 'profile-a');
$cache->set('en_US', '<div>EN default</div>');
$cache->set('en_US', '<div>EN profile B</div>', 'profile-b');
$cache->set('en_US', '<div>EN profile B update</div>', 'profile-b');

$expectedLocales = [
    ['locale' => 'fr_FR', 'suffix' => 'profile-a'],
    ['locale' => 'en_US', 'suffix' => null],
    ['locale' => 'en_US', 'suffix' => 'profile-b'],
];

assertSame($expectedLocales, $cache->getCachedLocales(), 'Locale index deduplicates entries across repeated cache writes.');

$cachedHtml = $cache->get('en_US', 'profile-b');
assertSame('<div>EN profile B update</div>', $cachedHtml, 'Cache returns the latest stored HTML for locale/profile combination.');

// Ensure clear() removes all stored transients and the locale option.
$legacyKey = 'sidebar_jlg_full_html';
set_transient($legacyKey, '<div>legacy</div>', 3600);

$frKey = $cache->getTransientKey('fr_FR', 'profile-a');
$enDefaultKey = $cache->getTransientKey('en_US', null);
$enProfileKey = $cache->getTransientKey('en_US', 'profile-b');

assertTrue(isset($GLOBALS['wp_test_transients'][$frKey]), 'FR cached HTML stored.');
assertTrue(isset($GLOBALS['wp_test_transients'][$enDefaultKey]), 'EN default cached HTML stored.');
assertTrue(isset($GLOBALS['wp_test_transients'][$enProfileKey]), 'EN profile cached HTML stored.');
assertTrue(isset($GLOBALS['wp_test_transients'][$legacyKey]), 'Legacy cached HTML stored.');
assertTrue(get_option('sidebar_jlg_cached_locales') !== [], 'Locale index option stored before clearing.');

$cache->clear();

assertTrue(!isset($GLOBALS['wp_test_transients'][$frKey]), 'FR cached HTML cleared.');
assertTrue(!isset($GLOBALS['wp_test_transients'][$enDefaultKey]), 'EN default cached HTML cleared.');
assertTrue(!isset($GLOBALS['wp_test_transients'][$enProfileKey]), 'EN profile cached HTML cleared.');
assertTrue(!isset($GLOBALS['wp_test_transients'][$legacyKey]), 'Legacy cached HTML cleared.');
assertSame([], get_option('sidebar_jlg_cached_locales', []), 'Locale index option removed on cache clear.');

$eventsByType = [];
foreach ($recordedEvents as $eventArgs) {
    $eventName = $eventArgs[0] ?? null;
    if (!is_string($eventName)) {
        continue;
    }

    if (!isset($eventsByType[$eventName])) {
        $eventsByType[$eventName] = 0;
    }

    $eventsByType[$eventName]++;
}

assertTrue(($eventsByType['set'] ?? 0) >= 1, 'Cache instrumentation records set events.');
assertTrue(($eventsByType['hit'] ?? 0) >= 1, 'Cache instrumentation records hit events.');
assertTrue(($eventsByType['clear'] ?? 0) >= 1, 'Cache instrumentation records clear events.');

if ($testsPassed) {
    echo "Menu cache index management tests passed.\n";
    unset($GLOBALS['wp_test_function_overrides']['do_action']);
    exit(0);
}

echo "Menu cache index management tests failed.\n";
unset($GLOBALS['wp_test_function_overrides']['do_action']);
exit(1);
