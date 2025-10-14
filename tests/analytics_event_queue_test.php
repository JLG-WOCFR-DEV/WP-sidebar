<?php
declare(strict_types=1);

namespace JLG\Sidebar\Analytics {
    function current_time($type = 'timestamp', $gmt = 0)
    {
        $timestamp = $GLOBALS['analytics_queue_test_time'] ?? time();

        if ($type === 'timestamp') {
            return $timestamp;
        }

        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s', $timestamp);
        }

        return $timestamp;
    }
}

namespace {
    use JLG\Sidebar\Analytics\AnalyticsEventQueue;
    use JLG\Sidebar\Analytics\AnalyticsRepository;

    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

    $GLOBALS['wp_test_options'] = [];
    $GLOBALS['wp_test_cron_events'] = [];
    delete_option('sidebar_jlg_analytics');
    delete_option('sidebar_jlg_analytics_queue');

    $testsPassed = true;

    function assertSame($expected, $actual, string $message): void
    {
        global $testsPassed;

        if ($expected === $actual) {
            echo "[PASS] {$message}\n";

            return;
        }

        $testsPassed = false;
        echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
    }

    function assertTrue($condition, string $message): void
    {
        assertSame(true, (bool) $condition, $message);
    }

    $repository = new AnalyticsRepository();
    $queue = new AnalyticsEventQueue($repository);

    $GLOBALS['analytics_queue_test_time'] = strtotime('2024-03-01 12:00:00');
$queue->enqueue('sidebar_open', [
    'profile_id' => 'default',
    'target' => 'toggle_button',
]);
$queue->enqueue('cta_view', [
    'profile_id' => 'default',
]);

$queuedBeforeFlush = wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP);
assertSame(2, count($queuedBeforeFlush ?? []), 'Two analytics events are buffered before flush');
assertSame([], get_option(AnalyticsEventQueue::OPTION_NAME, []), 'Queue option remains empty while buffering');
assertTrue(wp_next_scheduled(AnalyticsEventQueue::CRON_HOOK) !== false, 'Cron event scheduled after enqueue');

$queue->flushQueuedEvents();
$summaryAfterFlush = $repository->getSummary();
assertSame(1, $summaryAfterFlush['totals']['sidebar_open'] ?? null, 'Sidebar open counted after flush');
assertSame(1, $summaryAfterFlush['totals']['cta_view'] ?? null, 'CTA view counted after flush');
assertSame('toggle_button', array_key_first($summaryAfterFlush['targets']['sidebar_open'] ?? []) ?? null, 'Sidebar open target persisted after flush');
assertSame([], get_option(AnalyticsEventQueue::OPTION_NAME, []), 'Queue emptied after flush');
assertSame([], wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP) ?: [], 'Cache cleared after flush');
assertTrue(wp_next_scheduled(AnalyticsEventQueue::CRON_HOOK) === false, 'Scheduled cron hook cleared after flush');

$GLOBALS['analytics_queue_test_time'] = strtotime('2024-03-02 09:30:00');
$queue->enqueue('menu_link_click', [
    'profile_id' => 'default',
]);
$queue->flushQueuedEvents();
$summaryAfterSecondFlush = $repository->getSummary();
assertSame(1, $summaryAfterSecondFlush['totals']['menu_link_click'] ?? null, 'Menu link click counted in second flush');
assertSame('2024-03-02', array_key_last($summaryAfterSecondFlush['daily'] ?? []) ?? null, 'Latest daily bucket reflects queued timestamp');
assertSame([], wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP) ?: [], 'Cache remains empty after second flush');

    unset($GLOBALS['analytics_queue_test_time']);

    if ($testsPassed) {
        echo "Analytics event queue tests passed.\n";
        exit(0);
    }

    echo "Analytics event queue tests failed.\n";
    exit(1);
}
