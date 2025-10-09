<?php
declare(strict_types=1);

namespace JLG\Sidebar\Analytics {
    function current_time($type = 'timestamp', $gmt = 0)
    {
        $timestamp = $GLOBALS['analytics_test_current_time'] ?? time();

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
    use JLG\Sidebar\Analytics\AnalyticsRepository;

    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

    $GLOBALS['wp_test_options'] = [];
    delete_option('sidebar_jlg_analytics');

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

    $initialSummary = $repository->getSummary();
    assertSame(0, $initialSummary['totals']['sidebar_open'] ?? null, 'Initial totals start at zero');
    assertSame([], $initialSummary['targets']['sidebar_open'] ?? [], 'Initial targets array is empty');
    assertSame(0, $initialSummary['windows']['last7']['days'] ?? null, 'Initial 7-day window has no days');
    assertSame(0, $initialSummary['windows']['last30']['totals']['sidebar_open'] ?? null, 'Initial 30-day window starts at zero');

    $GLOBALS['analytics_test_current_time'] = strtotime('2024-01-01 10:00:00');
    $repository->recordEvent('sidebar_open', [
        'profile_id' => 'default',
        'target' => 'toggle_button',
    ]);

    $summaryAfterOpen = $repository->getSummary();
    assertSame(1, $summaryAfterOpen['totals']['sidebar_open'] ?? null, 'Sidebar open increments total');
    assertSame('toggle_button', array_key_first($summaryAfterOpen['targets']['sidebar_open'] ?? []) ?? null, 'Sidebar open target stored');
    assertSame(1, $summaryAfterOpen['windows']['last7']['totals']['sidebar_open'] ?? null, '7-day window counts sidebar open');
    assertSame(1, $summaryAfterOpen['windows']['last30']['totals']['sidebar_open'] ?? null, '30-day window counts sidebar open');

    $GLOBALS['analytics_test_current_time'] = strtotime('2024-01-01 10:05:00');
    $repository->recordEvent('cta_view', [
        'profile_id' => 'profile-alpha',
        'profile_label' => 'Alpha',
        'target' => 'cta_hero',
    ]);
    $repository->recordEvent('cta_click', [
        'profile_id' => 'profile-alpha',
        'profile_label' => 'Alpha',
        'target' => 'cta_button',
    ]);

    $summaryAfterCta = $repository->getSummary();
    assertSame(1, $summaryAfterCta['profiles']['profile-alpha']['totals']['cta_click'] ?? null, 'Profile totals record CTA clicks');
    assertSame('Alpha', $summaryAfterCta['profiles']['profile-alpha']['label'] ?? null, 'Profile label persisted');
    assertSame('cta_button', array_key_first($summaryAfterCta['targets']['cta_click'] ?? []) ?? null, 'CTA click target tracked');
    assertSame(1, $summaryAfterCta['windows']['last7']['totals']['cta_click'] ?? null, '7-day window counts CTA click');

    $repository->recordEvent('sidebar_session', [
        'duration_ms' => 4200,
        'close_reason' => 'overlay',
        'target' => 'toggle_button',
        'interactions' => [
            'menu_link_click' => 3,
            'cta_click' => 1,
        ],
    ]);

    $sessionSummary = $repository->getSummary();
    assertSame(1, $sessionSummary['sessions']['count'] ?? null, 'Session count increments');
    assertSame(4200, $sessionSummary['sessions']['total_duration_ms'] ?? null, 'Session duration aggregated');
    assertSame(4200, $sessionSummary['sessions']['average_duration_ms'] ?? null, 'Session average computed');
    assertSame('overlay', array_key_first($sessionSummary['sessions']['close_reasons'] ?? []) ?? null, 'Session close reason stored');
    assertSame('toggle_button', array_key_first($sessionSummary['sessions']['open_targets'] ?? []) ?? null, 'Session open target stored');
    assertSame(3, $sessionSummary['sessions']['interactions']['menu_link_click'] ?? null, 'Session interactions aggregated');

    $reflection = new \ReflectionClass(AnalyticsRepository::class);
    $maxBuckets = (int) $reflection->getConstant('MAX_DAILY_BUCKETS');

    for ($day = 0; $day < $maxBuckets + 2; $day++) {
        $GLOBALS['analytics_test_current_time'] = strtotime('2024-02-01 +' . $day . ' days');
        $repository->recordEvent('menu_link_click', ['profile_id' => 'default']);
    }

    $summaryAfterRetention = $repository->getSummary();
    assertSame($maxBuckets, count($summaryAfterRetention['daily']), 'Daily retention window enforced');
    assertSame($maxBuckets, $summaryAfterRetention['windows']['last30']['days'] ?? null, '30-day window records capped day count');

    $repository->recordEvent('unknown_event', []);
    $postUnknownSummary = $repository->getSummary();
    assertTrue(!isset($postUnknownSummary['totals']['unknown_event']), 'Unknown events are ignored');

    unset($GLOBALS['analytics_test_current_time']);

    if ($testsPassed) {
        echo "Analytics repository tests passed.\n";
        exit(0);
    }

    echo "Analytics repository tests failed.\n";
    exit(1);
}
