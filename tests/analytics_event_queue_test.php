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

    function resetTestState(): void
    {
        $GLOBALS['wp_test_options'] = [];
        $GLOBALS['wp_test_object_cache'] = [];
        $GLOBALS['wp_test_cron_events'] = [];
        unset($GLOBALS['wp_test_using_ext_object_cache'], $GLOBALS['analytics_queue_test_time']);

        delete_option('sidebar_jlg_analytics');
        delete_option(AnalyticsEventQueue::OPTION_NAME);
        delete_option(AnalyticsEventQueue::BUFFER_OPTION_NAME);
        wp_cache_delete(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP);
    }

    // Scenario 1: persistent object cache available.
    resetTestState();
    $GLOBALS['wp_test_using_ext_object_cache'] = true;

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
    assertSame(2, count($queuedBeforeFlush ?? []), 'Two analytics events are buffered before flush with persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::OPTION_NAME, []), 'Queue option remains empty while buffering with persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::BUFFER_OPTION_NAME, []), 'Persistent buffer unused while object cache is available');
    assertTrue(wp_next_scheduled(AnalyticsEventQueue::CRON_HOOK) !== false, 'Cron event scheduled after enqueue with persistent cache');

    $queue->flushQueuedEvents();
    $summaryAfterFlush = $repository->getSummary();
    assertSame(1, $summaryAfterFlush['totals']['sidebar_open'] ?? null, 'Sidebar open counted after flush with persistent cache');
    assertSame(1, $summaryAfterFlush['totals']['cta_view'] ?? null, 'CTA view counted after flush with persistent cache');
    assertSame('toggle_button', array_key_first($summaryAfterFlush['targets']['sidebar_open'] ?? []) ?? null, 'Sidebar open target persisted after flush with persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::OPTION_NAME, []), 'Queue emptied after flush with persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::BUFFER_OPTION_NAME, []), 'Persistent buffer cleared after flush with persistent cache');
    assertSame([], wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP) ?: [], 'Cache cleared after flush with persistent cache');
    assertTrue(wp_next_scheduled(AnalyticsEventQueue::CRON_HOOK) === false, 'Scheduled cron hook cleared after flush with persistent cache');

    $GLOBALS['analytics_queue_test_time'] = strtotime('2024-03-02 09:30:00');
    $queue->enqueue('menu_link_click', [
        'profile_id' => 'default',
    ]);
    $queue->flushQueuedEvents();
    $summaryAfterSecondFlush = $repository->getSummary();
    assertSame(1, $summaryAfterSecondFlush['totals']['menu_link_click'] ?? null, 'Menu link click counted in second flush with persistent cache');
    assertSame('2024-03-02', array_key_last($summaryAfterSecondFlush['daily'] ?? []) ?? null, 'Latest daily bucket reflects queued timestamp with persistent cache');
    assertSame([], wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP) ?: [], 'Cache remains empty after second flush with persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::BUFFER_OPTION_NAME, []), 'Persistent buffer remains empty after second flush with persistent cache');

    // Scenario 2: no persistent object cache, events must survive between requests.
    resetTestState();
    $GLOBALS['wp_test_using_ext_object_cache'] = false;

    $firstRequestRepository = new AnalyticsRepository();
    $firstRequestQueue = new AnalyticsEventQueue($firstRequestRepository);

    $GLOBALS['analytics_queue_test_time'] = strtotime('2024-03-03 10:15:00');
    $firstRequestQueue->enqueue('sidebar_open', [
        'profile_id' => 'default',
        'target' => 'header_button',
    ]);

    $bufferOptionBeforeFlush = get_option(AnalyticsEventQueue::BUFFER_OPTION_NAME, []);
    assertSame(1, count($bufferOptionBeforeFlush ?? []), 'One analytics event persisted to option buffer without persistent cache');
    assertTrue(wp_next_scheduled(AnalyticsEventQueue::CRON_HOOK) !== false, 'Cron event scheduled after enqueue without persistent cache');

    unset($firstRequestQueue, $firstRequestRepository);

    $secondRequestRepository = new AnalyticsRepository();
    $secondRequestQueue = new AnalyticsEventQueue($secondRequestRepository);
    $secondRequestQueue->flushQueuedEvents();

    $summaryAfterPersistentFlush = $secondRequestRepository->getSummary();
    assertSame(1, $summaryAfterPersistentFlush['totals']['sidebar_open'] ?? null, 'Sidebar open counted after flushing persistent buffer without persistent cache');
    assertSame('header_button', array_key_first($summaryAfterPersistentFlush['targets']['sidebar_open'] ?? []) ?? null, 'Sidebar open target persisted from persistent buffer without persistent cache');
    assertSame('2024-03-03', array_key_last($summaryAfterPersistentFlush['daily'] ?? []) ?? null, 'Daily bucket reflects timestamp from persistent buffer without persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::BUFFER_OPTION_NAME, []), 'Persistent buffer cleared after flush without persistent cache');
    assertSame([], wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP) ?: [], 'Object cache empty after flushing persistent buffer without persistent cache');
    assertSame([], get_option(AnalyticsEventQueue::OPTION_NAME, []), 'Queue option cleared after flushing persistent buffer without persistent cache');
    assertTrue(wp_next_scheduled(AnalyticsEventQueue::CRON_HOOK) === false, 'Scheduled cron hook cleared after flushing persistent buffer without persistent cache');

    unset($GLOBALS['analytics_queue_test_time']);

    if ($testsPassed) {
        echo "Analytics event queue tests passed.\n";
        exit(0);
    }

    echo "Analytics event queue tests failed.\n";
    exit(1);
}
