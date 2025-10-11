<?php

namespace JLG\Sidebar\Analytics;

use function add_action;
use function array_filter;
use function array_values;
use function call_user_func;
use function current_time;
use function function_exists;
use function get_option;
use function is_array;
use function is_numeric;
use function is_string;
use function time;
use function update_option;
use function wp_clear_scheduled_hook;
use function wp_next_scheduled;
use function wp_schedule_single_event;

class AnalyticsEventQueue
{
    public const OPTION_NAME = 'sidebar_jlg_analytics_queue';
    public const CRON_HOOK = 'sidebar_jlg_flush_analytics_queue';
    private const DEFAULT_FLUSH_DELAY = 60;

    private AnalyticsRepository $repository;

    public function __construct(AnalyticsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerHooks(): void
    {
        add_action(self::CRON_HOOK, [$this, 'flushQueuedEvents']);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function enqueue(string $event, array $context): void
    {
        $queue = $this->readQueue();
        $queue[] = [
            'event' => $event,
            'context' => $context,
            'timestamp' => $this->resolveEventTimestamp($context),
        ];

        update_option(self::OPTION_NAME, $queue);
        $this->scheduleFlushIfNeeded();
    }

    public function flushQueuedEvents(): void
    {
        $queue = $this->readQueue();
        if ($queue === []) {
            $this->clearScheduledFlush();

            return;
        }

        update_option(self::OPTION_NAME, []);
        $this->clearScheduledFlush();

        $events = [];
        foreach ($queue as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $eventKey = $entry['event'] ?? '';
            if (!is_string($eventKey) || $eventKey === '') {
                continue;
            }

            $context = [];
            if (isset($entry['context']) && is_array($entry['context'])) {
                $context = $entry['context'];
            }

            $timestamp = null;
            if (isset($entry['timestamp']) && is_numeric($entry['timestamp'])) {
                $timestamp = (int) $entry['timestamp'];
            }

            $events[] = [
                'event' => $eventKey,
                'context' => $context,
                'timestamp' => $timestamp,
            ];
        }

        if ($events === []) {
            return;
        }

        $this->repository->recordEvents($events);
    }

    public function clearScheduledFlush(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readQueue(): array
    {
        $queue = get_option(self::OPTION_NAME, []);

        if (!is_array($queue)) {
            return [];
        }

        return array_values(array_filter($queue, static function ($item) {
            return is_array($item);
        }));
    }

    private function scheduleFlushIfNeeded(): void
    {
        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($next !== false) {
            return;
        }

        $timestamp = time() + self::DEFAULT_FLUSH_DELAY;
        $resolved = $this->resolveCurrentTime();
        if ($resolved !== null) {
            $timestamp = $resolved + self::DEFAULT_FLUSH_DELAY;
        }

        wp_schedule_single_event($timestamp, self::CRON_HOOK);
    }

    private function resolveEventTimestamp(array $context): int
    {
        if (isset($context['timestamp']) && is_numeric($context['timestamp'])) {
            return (int) $context['timestamp'];
        }

        $resolved = $this->resolveCurrentTime();
        if ($resolved !== null) {
            return $resolved;
        }

        return time();
    }

    private function resolveCurrentTime(): ?int
    {
        if (function_exists(__NAMESPACE__ . '\\current_time')) {
            $timestamp = (int) call_user_func(__NAMESPACE__ . '\\current_time', 'timestamp', true);

            return $timestamp > 0 ? $timestamp : null;
        }

        if (function_exists('current_time')) {
            $timestamp = current_time('timestamp', true);
            if (is_numeric($timestamp)) {
                return (int) $timestamp;
            }
        }

        return null;
    }
}
