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

// Touch another locale to ensure metrics are tracked across profiles.
$cache->get('fr_FR', 'profile-a');

// Ensure targeted clear removes only the requested entry.
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

$cache->clearEntry('fr_FR', 'profile-a');

assertTrue(!isset($GLOBALS['wp_test_transients'][$frKey]), 'FR cached HTML cleared without touching other locales.');
assertTrue(isset($GLOBALS['wp_test_transients'][$enDefaultKey]), 'EN default cached HTML preserved after targeted clear.');
assertTrue(isset($GLOBALS['wp_test_transients'][$enProfileKey]), 'EN profile cached HTML preserved after targeted clear.');
assertTrue(isset($GLOBALS['wp_test_transients'][$legacyKey]), 'Legacy cached HTML untouched by targeted clear.');

$expectedAfterTargetedClear = [
    ['locale' => 'en_US', 'suffix' => null],
    ['locale' => 'en_US', 'suffix' => 'profile-b'],
];

assertSame($expectedAfterTargetedClear, $cache->getCachedLocales(), 'Targeted clear keeps other locales cached.');

delete_transient($enProfileKey);
$missResult = $cache->get('en_US', 'profile-b');
assertSame(false, $missResult, 'Cache miss recorded after transient removal.');

$cache->set('fr_FR', '<div>FR after purge</div>', 'profile-a');
$cache->set('en_US', '<div>EN profile B refreshed</div>', 'profile-b');

assertTrue(isset($GLOBALS['wp_test_transients'][$frKey]), 'FR cached HTML stored after repopulating.');
assertTrue(isset($GLOBALS['wp_test_transients'][$enProfileKey]), 'EN profile cached HTML stored after repopulating.');

// Record a fresh hit for the FR profile to track metrics stability across maintenance.
$cache->get('fr_FR', 'profile-a');

$frEntryOptionName = 'sidebar_jlg_cached_locale_entry_fr_fr_profile-a';
$enEntryOptionName = 'sidebar_jlg_cached_locale_entry_en_us_profile-b';

$frEntryBeforeMaintenance = get_option($frEntryOptionName, []);
$frHitsBeforeMaintenance = is_array($frEntryBeforeMaintenance) ? ($frEntryBeforeMaintenance['hits'] ?? 0) : 0;

$enProfileEntry = get_option($enEntryOptionName, []);
if (is_array($enProfileEntry)) {
    $enProfileEntry['expires_at'] = time() - 10;
    update_option($enEntryOptionName, $enProfileEntry, 'no');
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete('menu_cache_entry_en_US|profile-b', 'sidebar_jlg');
    }
}

$cache->purgeExpiredEntries();

assertTrue(!isset($GLOBALS['wp_test_transients'][$enProfileKey]), 'Expired EN profile entry purged by maintenance job.');
assertTrue(isset($GLOBALS['wp_test_transients'][$frKey]), 'FR cache persists after maintenance purge.');
assertTrue(!isset($GLOBALS['wp_test_options'][$enEntryOptionName]), 'Expired profile index entry removed from registry option store.');

$frEntryAfterMaintenance = get_option($frEntryOptionName, []);
$frHitsAfterMaintenance = is_array($frEntryAfterMaintenance) ? ($frEntryAfterMaintenance['hits'] ?? null) : null;
assertSame($frHitsBeforeMaintenance, $frHitsAfterMaintenance, 'Maintenance purge keeps hit counter for unaffected entries.');

$expectedAfterMaintenance = [
    ['locale' => 'en_US', 'suffix' => null],
    ['locale' => 'fr_FR', 'suffix' => 'profile-a'],
];

assertSame($expectedAfterMaintenance, $cache->getCachedLocales(), 'Maintenance purge removes only expired locale/profile entries.');

$cache->clear();

assertTrue(!isset($GLOBALS['wp_test_transients'][$frKey]), 'FR cached HTML cleared.');
assertTrue(!isset($GLOBALS['wp_test_transients'][$enDefaultKey]), 'EN default cached HTML cleared.');
assertTrue(!isset($GLOBALS['wp_test_transients'][$enProfileKey]), 'EN profile cached HTML cleared.');
assertTrue(!isset($GLOBALS['wp_test_transients'][$legacyKey]), 'Legacy cached HTML cleared.');
assertSame([], get_option('sidebar_jlg_cached_locales', []), 'Locale index option removed on cache clear.');

$eventsByType = [];
$hitMetrics = null;
$missMetrics = null;

foreach ($recordedEvents as $eventArgs) {
    $eventName = $eventArgs[0] ?? null;
    $payload = $eventArgs[1] ?? null;

    if (!is_string($eventName)) {
        continue;
    }

    if (!isset($eventsByType[$eventName])) {
        $eventsByType[$eventName] = 0;
    }

    $eventsByType[$eventName]++;

    if (!is_array($payload) || !isset($payload['metrics']) || !is_array($payload['metrics'])) {
        continue;
    }

    if ($eventName === 'hit') {
        $hitMetrics = $payload['metrics'];
    }

    if ($eventName === 'miss') {
        $missMetrics = $payload['metrics'];
    }
}

assertTrue(($eventsByType['set'] ?? 0) >= 1, 'Cache instrumentation records set events.');
assertTrue(($eventsByType['hit'] ?? 0) >= 1, 'Cache instrumentation records hit events.');
assertTrue(($eventsByType['miss'] ?? 0) >= 1, 'Cache instrumentation records miss events.');
assertTrue(($eventsByType['clear'] ?? 0) >= 1, 'Cache instrumentation records clear events.');
assertTrue(is_array($hitMetrics) && ($hitMetrics['hits'] ?? 0) >= 1, 'Hit events expose cumulative metrics.');
assertTrue(is_array($missMetrics) && ($missMetrics['misses'] ?? 0) >= 1, 'Miss events expose cumulative metrics.');

if ($testsPassed) {
    echo "Menu cache index management tests passed.\n";
    unset($GLOBALS['wp_test_function_overrides']['do_action']);
    exit(0);
}

echo "Menu cache index management tests failed.\n";
unset($GLOBALS['wp_test_function_overrides']['do_action']);
exit(1);
