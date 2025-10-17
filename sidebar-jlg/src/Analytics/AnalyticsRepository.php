<?php

namespace JLG\Sidebar\Analytics;

use function arsort;
use function array_slice;
use function count;
use function get_option;
use function gmdate;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function ksort;
use function max;
use function preg_match;
use function preg_replace;
use function sanitize_key;
use function sanitize_text_field;
use function strtolower;
use function substr;
use function time;
use function trim;
use function update_option;

class AnalyticsRepository
{
    private const OPTION_NAME = 'sidebar_jlg_analytics';
    private const MAX_DAILY_BUCKETS = 30;
    private const MAX_SESSION_INTERACTIONS = 50;

    /**
     * @var string[]
     */
    private const ALLOWED_EVENTS = [
        'sidebar_open',
        'menu_link_click',
        'cta_view',
        'cta_click',
        'cta_conversion',
        'sidebar_session',
        'form_view',
        'form_start',
        'form_submit',
        'slider_view',
        'slider_interaction',
        'slider_cta',
        'woocommerce_widget_view',
        'woocommerce_widget_click',
        'woocommerce_widget_purchase',
    ];

    /**
     * Returns the list of supported analytics event identifiers.
     *
     * @return string[]
     */
    public function getSupportedEvents(): array
    {
        return self::ALLOWED_EVENTS;
    }

    /**
     * Returns a normalized snapshot of the analytics option.
     *
     * @return array{
     *     totals: array<string, int>,
     *     daily: array<string, array<string, int>>,
     *     profiles: array<string, array{label: string, is_fallback: bool, totals: array<string, int>}>,
     *     targets: array<string, array<string, int>>,
     *     windows: array<string, array{days: int, totals: array<string, int>}>,
     *     sessions: array{
     *         count: int,
     *         total_duration_ms: int,
     *         max_duration_ms: int,
     *         average_duration_ms: int,
     *         close_reasons: array<string, int>,
     *         open_targets: array<string, int>,
     *         interactions: array<string, int>
     *     },
     *     last_event_at: ?string,
     *     last_event_type: ?string
     * }
     */
    public function getSummary(): array
    {
        $data = $this->readOption();

        return $this->buildSummaryFromData($data);
    }

    /**
     * Records an analytics event and returns the updated summary payload.
     *
     * @param array<string, mixed> $context
     *
     * @return array{
     *     totals: array<string, int>,
     *     daily: array<string, array<string, int>>,
     *     profiles: array<string, array{label: string, is_fallback: bool, totals: array<string, int>}>,
     *     targets: array<string, array<string, int>>,
     *     windows: array<string, array{days: int, totals: array<string, int>}>,
     *     sessions: array{
     *         count: int,
     *         total_duration_ms: int,
     *         max_duration_ms: int,
     *         average_duration_ms: int,
     *         close_reasons: array<string, int>,
     *         open_targets: array<string, int>,
     *         interactions: array<string, int>
     *     },
     *     last_event_at: ?string,
     *     last_event_type: ?string
     * }
     */
    public function recordEvent(string $event, array $context = []): array
    {
        return $this->recordEvents([
            [
                'event' => $event,
                'context' => $context,
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function recordEvents(array $events): array
    {
        $data = $this->readOption();
        $processed = false;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $eventType = $event['event'] ?? null;
            if (!is_string($eventType)) {
                continue;
            }

            $normalizedEvent = $this->normalizeEventKey($eventType);
            if ($normalizedEvent === null) {
                continue;
            }

            $context = [];
            if (isset($event['context']) && is_array($event['context'])) {
                $context = $event['context'];
            }

            $timestamp = null;
            if (isset($event['timestamp']) && is_numeric($event['timestamp'])) {
                $timestamp = (int) $event['timestamp'];
            }

            if ($timestamp === null) {
                $timestamp = $this->resolveTimestamp();
            }

            $data = $this->applyEventData($data, $normalizedEvent, $context, $timestamp);
            $processed = true;
        }

        if (!$processed) {
            return $this->buildSummaryFromData($data);
        }

        update_option(self::OPTION_NAME, $data);

        return $this->buildSummaryFromData($data);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function applyEventData(array $data, string $normalizedEvent, array $context, int $timestamp): array
    {
        $totals = $this->normalizeTotals($data['totals'] ?? []);
        $totals[$normalizedEvent] = ($totals[$normalizedEvent] ?? 0) + 1;
        $data['totals'] = $totals;

        $dateKey = gmdate('Y-m-d', $timestamp);

        $daily = $this->normalizeDaily($data['daily'] ?? []);
        if (!isset($daily[$dateKey])) {
            $daily[$dateKey] = $this->getEmptyEventCounts();
        }
        $daily[$dateKey][$normalizedEvent] = ($daily[$dateKey][$normalizedEvent] ?? 0) + 1;
        ksort($daily);
        if (count($daily) > self::MAX_DAILY_BUCKETS) {
            $daily = array_slice($daily, -self::MAX_DAILY_BUCKETS, null, true);
        }
        $data['daily'] = $daily;

        $profiles = $this->normalizeProfiles($data['profiles'] ?? []);
        $profileId = $this->sanitizeProfileId($context['profile_id'] ?? '');
        $profileLabel = isset($context['profile_label']) ? $this->sanitizeLabel($context['profile_label']) : '';
        $isFallback = !empty($context['is_fallback_profile']);

        if ($profileId !== '') {
            if (!isset($profiles[$profileId])) {
                $profiles[$profileId] = [
                    'label' => $profileLabel,
                    'is_fallback' => $isFallback,
                    'totals' => $this->getEmptyEventCounts(),
                ];
            }

            if ($profileLabel !== '') {
                $profiles[$profileId]['label'] = $profileLabel;
            }

            if ($isFallback) {
                $profiles[$profileId]['is_fallback'] = true;
            }

            $profiles[$profileId]['totals'][$normalizedEvent] = ($profiles[$profileId]['totals'][$normalizedEvent] ?? 0) + 1;
            $data['profiles'] = $profiles;
        }

        $target = $this->sanitizeTarget($context['target'] ?? null);
        if ($target !== null) {
            $targets = $this->normalizeTargets($data['targets'] ?? []);
            if (!isset($targets[$normalizedEvent])) {
                $targets[$normalizedEvent] = [];
            }

            $targets[$normalizedEvent][$target] = ($targets[$normalizedEvent][$target] ?? 0) + 1;
            arsort($targets[$normalizedEvent]);
            if (count($targets[$normalizedEvent]) > 10) {
                $targets[$normalizedEvent] = array_slice($targets[$normalizedEvent], 0, 10, true);
            }
            $data['targets'] = $targets;
        }

        if ($normalizedEvent === 'sidebar_session') {
            $sessionContext = [
                'duration' => $context['duration_ms'] ?? ($context['duration'] ?? null),
                'close_reason' => $context['close_reason'] ?? ($context['closeReason'] ?? null),
                'target' => $context['target'] ?? ($context['open_target'] ?? null),
                'interactions' => $context['interactions'] ?? [],
            ];
            $data['sessions'] = $this->updateSessionMetrics($data['sessions'] ?? [], $sessionContext);
        }

        $data['last_event_at'] = gmdate('c', $timestamp);
        $data['last_event_type'] = $normalizedEvent;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function readOption(): array
    {
        $option = get_option(self::OPTION_NAME, []);

        return is_array($option) ? $option : [];
    }

    private function resolveTimestamp(): int
    {
        $namespacedFunction = __NAMESPACE__ . '\\current_time';
        if (function_exists($namespacedFunction)) {
            $timestamp = $namespacedFunction('timestamp', true);
            if (is_numeric($timestamp)) {
                return (int) $timestamp;
            }
        }

        if (function_exists('current_time')) {
            $timestamp = current_time('timestamp', true);
            if (is_numeric($timestamp)) {
                return (int) $timestamp;
            }
        }

        return time();
    }

    /**
     * @return array<string, int>
     */
    private function getEmptyEventCounts(): array
    {
        $counts = [];
        foreach (self::ALLOWED_EVENTS as $eventKey) {
            $counts[$eventKey] = 0;
        }

        return $counts;
    }

    /**
     * @param mixed $totals
     *
     * @return array<string, int>
     */
    private function normalizeTotals($totals): array
    {
        $normalized = $this->getEmptyEventCounts();

        if (!is_array($totals)) {
            return $normalized;
        }

        foreach ($normalized as $eventKey => $defaultValue) {
            if (isset($totals[$eventKey]) && is_numeric($totals[$eventKey])) {
                $normalized[$eventKey] = max(0, (int) $totals[$eventKey]);
            } else {
                $normalized[$eventKey] = $defaultValue;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $daily
     *
     * @return array<string, array<string, int>>
     */
    private function normalizeDaily($daily): array
    {
        if (!is_array($daily)) {
            return [];
        }

        $normalized = [];

        foreach ($daily as $date => $counts) {
            $dateKey = $this->sanitizeDateKey($date);
            if ($dateKey === null) {
                continue;
            }

            $normalized[$dateKey] = $this->normalizeTotals($counts);
        }

        ksort($normalized);
        if (count($normalized) > self::MAX_DAILY_BUCKETS) {
            $normalized = array_slice($normalized, -self::MAX_DAILY_BUCKETS, null, true);
        }

        return $normalized;
    }

    /**
     * @param mixed $profiles
     *
     * @return array<string, array{label: string, is_fallback: bool, totals: array<string, int>}>
     */
    private function normalizeProfiles($profiles): array
    {
        if (!is_array($profiles)) {
            return [];
        }

        $normalized = [];

        foreach ($profiles as $profileId => $profileData) {
            $id = $this->sanitizeProfileId($profileId);
            if ($id === '') {
                continue;
            }

            $label = '';
            if (is_array($profileData) && isset($profileData['label'])) {
                $label = $this->sanitizeLabel($profileData['label']);
            }

            $isFallback = false;
            if (is_array($profileData) && !empty($profileData['is_fallback'])) {
                $isFallback = true;
            }

            $totals = [];
            if (is_array($profileData) && isset($profileData['totals'])) {
                $totals = $this->normalizeTotals($profileData['totals']);
            } else {
                $totals = $this->getEmptyEventCounts();
            }

            $normalized[$id] = [
                'label' => $label,
                'is_fallback' => $isFallback,
                'totals' => $totals,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $targets
     *
     * @return array<string, array<string, int>>
     */
    private function normalizeTargets($targets): array
    {
        if (!is_array($targets)) {
            return [];
        }

        $normalized = [];

        foreach ($targets as $eventKey => $distribution) {
            $normalizedEvent = $this->normalizeEventKey($eventKey);
            if ($normalizedEvent === null || !is_array($distribution)) {
                continue;
            }

            $normalized[$normalizedEvent] = [];
            foreach ($distribution as $targetKey => $count) {
                $target = $this->sanitizeTarget($targetKey);
                if ($target === null) {
                    continue;
                }

                $normalized[$normalizedEvent][$target] = max(0, (int) $count);
            }

            if ($normalized[$normalizedEvent] !== []) {
                arsort($normalized[$normalizedEvent]);
                if (count($normalized[$normalizedEvent]) > 10) {
                    $normalized[$normalizedEvent] = array_slice($normalized[$normalizedEvent], 0, 10, true);
                }
            }
        }

        return $normalized;
    }

    private function sanitizeDateKey($date): ?string
    {
        if (!is_string($date)) {
            return null;
        }

        $trimmed = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function sanitizeProfileId($profileId): string
    {
        if (!is_string($profileId)) {
            return '';
        }

        $normalized = sanitize_key($profileId);

        return $normalized;
    }

    private function sanitizeLabel($label): string
    {
        if (!is_string($label)) {
            return '';
        }

        return sanitize_text_field($label);
    }

    private function sanitizeTarget($target): ?string
    {
        if (!is_string($target)) {
            return null;
        }

        $normalized = sanitize_key($target);
        if ($normalized === '') {
            $normalized = preg_replace('/[^a-z0-9\-]+/i', '_', strtolower($target));
            $normalized = trim((string) $normalized, '_-');
        }

        if ($normalized === '' || $normalized === null) {
            return null;
        }

        return substr((string) $normalized, 0, 48);
    }

    private function normalizeDateTime($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeEventKey($event): ?string
    {
        if (!is_string($event)) {
            return null;
        }

        $normalized = sanitize_key($event);

        return in_array($normalized, self::ALLOWED_EVENTS, true) ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     totals: array<string, int>,
     *     daily: array<string, array<string, int>>,
     *     profiles: array<string, array{label: string, is_fallback: bool, totals: array<string, int>}>,
     *     targets: array<string, array<string, int>>,
     *     windows: array<string, array{days: int, totals: array<string, int>}>,
     *     sessions: array{
     *         count: int,
     *         total_duration_ms: int,
     *         max_duration_ms: int,
     *         average_duration_ms: int,
     *         close_reasons: array<string, int>,
     *         open_targets: array<string, int>,
     *         interactions: array<string, int>
     *     },
     *     last_event_at: ?string,
     *     last_event_type: ?string
     * }
     */
    private function buildSummaryFromData(array $data): array
    {
        $totals = $this->normalizeTotals($data['totals'] ?? []);
        $daily = $this->normalizeDaily($data['daily'] ?? []);

        $sessions = $this->normalizeSessions($data['sessions'] ?? []);
        $sessions['average_duration_ms'] = $sessions['count'] > 0
            ? (int) round($sessions['total_duration_ms'] / max(1, $sessions['count']))
            : 0;

        return [
            'totals' => $totals,
            'daily' => $daily,
            'profiles' => $this->normalizeProfiles($data['profiles'] ?? []),
            'targets' => $this->normalizeTargets($data['targets'] ?? []),
            'windows' => [
                'last7' => $this->computeRollingWindowTotals($daily, 7),
                'last30' => $this->computeRollingWindowTotals($daily, 30),
            ],
            'sessions' => $sessions,
            'last_event_at' => $this->normalizeDateTime($data['last_event_at'] ?? null),
            'last_event_type' => $this->normalizeEventKey($data['last_event_type'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $context
     *
     * @return array{
     *     count: int,
     *     total_duration_ms: int,
     *     max_duration_ms: int,
     *     close_reasons: array<string, int>,
     *     open_targets: array<string, int>,
     *     interactions: array<string, int>
     * }
     */
    private function updateSessionMetrics($current, array $context): array
    {
        $metrics = $this->normalizeSessions($current);
        $metrics['count'] += 1;

        $duration = $this->sanitizeDuration($context['duration'] ?? null);
        if ($duration !== null) {
            $metrics['total_duration_ms'] += $duration;
            if ($duration > $metrics['max_duration_ms']) {
                $metrics['max_duration_ms'] = $duration;
            }
        }

        $reasonKey = $this->sanitizeTarget($context['close_reason'] ?? null);
        if ($reasonKey !== null) {
            $metrics['close_reasons'][$reasonKey] = ($metrics['close_reasons'][$reasonKey] ?? 0) + 1;
            arsort($metrics['close_reasons']);
            if (count($metrics['close_reasons']) > 10) {
                $metrics['close_reasons'] = array_slice($metrics['close_reasons'], 0, 10, true);
            }
        }

        $targetKey = $this->sanitizeTarget($context['target'] ?? null);
        if ($targetKey !== null) {
            $metrics['open_targets'][$targetKey] = ($metrics['open_targets'][$targetKey] ?? 0) + 1;
            arsort($metrics['open_targets']);
            if (count($metrics['open_targets']) > 10) {
                $metrics['open_targets'] = array_slice($metrics['open_targets'], 0, 10, true);
            }
        }

        if (isset($context['interactions']) && is_array($context['interactions'])) {
            foreach ($context['interactions'] as $interactionKey => $rawValue) {
                $normalizedInteraction = $this->sanitizeTarget($interactionKey);
                if ($normalizedInteraction === null || !is_numeric($rawValue)) {
                    continue;
                }

                $metrics['interactions'][$normalizedInteraction] = ($metrics['interactions'][$normalizedInteraction] ?? 0)
                    + max(0, (int) $rawValue);
            }

            if ($metrics['interactions'] !== []) {
                arsort($metrics['interactions']);
                if (count($metrics['interactions']) > self::MAX_SESSION_INTERACTIONS) {
                    $metrics['interactions'] = array_slice(
                        $metrics['interactions'],
                        0,
                        self::MAX_SESSION_INTERACTIONS,
                        true
                    );
                }
            }
        }

        return $metrics;
    }

    /**
     * @param mixed $sessions
     *
     * @return array{
     *     count: int,
     *     total_duration_ms: int,
     *     max_duration_ms: int,
     *     close_reasons: array<string, int>,
     *     open_targets: array<string, int>,
     *     interactions: array<string, int>
     * }
     */
    private function normalizeSessions($sessions): array
    {
        $normalized = [
            'count' => 0,
            'total_duration_ms' => 0,
            'max_duration_ms' => 0,
            'close_reasons' => [],
            'open_targets' => [],
            'interactions' => [],
        ];

        if (!is_array($sessions)) {
            return $normalized;
        }

        if (isset($sessions['count']) && is_numeric($sessions['count'])) {
            $normalized['count'] = max(0, (int) $sessions['count']);
        }

        if (isset($sessions['total_duration_ms']) && is_numeric($sessions['total_duration_ms'])) {
            $normalized['total_duration_ms'] = max(0, (int) $sessions['total_duration_ms']);
        }

        if (isset($sessions['max_duration_ms']) && is_numeric($sessions['max_duration_ms'])) {
            $normalized['max_duration_ms'] = max(0, (int) $sessions['max_duration_ms']);
        }

        $normalized['close_reasons'] = $this->normalizeLabelMap($sessions['close_reasons'] ?? []);
        $normalized['open_targets'] = $this->normalizeLabelMap($sessions['open_targets'] ?? []);
        $normalized['interactions'] = $this->normalizeLabelMap($sessions['interactions'] ?? []);

        return $normalized;
    }

    /**
     * @param mixed $map
     *
     * @return array<string, int>
     */
    private function normalizeLabelMap($map): array
    {
        if (!is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $key => $value) {
            $normalizedKey = $this->sanitizeTarget(is_string($key) ? $key : '');
            if ($normalizedKey === null || !is_numeric($value)) {
                continue;
            }

            $normalized[$normalizedKey] = max(0, (int) $value);
        }

        arsort($normalized);

        return array_slice($normalized, 0, 20, true);
    }

    /**
     * @param mixed $value
     */
    private function sanitizeDuration($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $duration = (int) round((float) $value);

        return $duration < 0 ? 0 : $duration;
    }

    /**
     * @param array<string, array<string, int>> $daily
     *
     * @return array{days: int, totals: array<string, int>}
     */
    private function computeRollingWindowTotals(array $daily, int $windowSize): array
    {
        $normalized = [
            'days' => 0,
            'totals' => $this->getEmptyEventCounts(),
        ];

        if ($windowSize <= 0 || $daily === []) {
            return $normalized;
        }

        $window = array_slice($daily, -$windowSize, null, true);
        $normalized['days'] = count($window);

        foreach ($window as $counts) {
            if (!is_array($counts)) {
                continue;
            }

            foreach ($normalized['totals'] as $eventKey => $currentTotal) {
                if (isset($counts[$eventKey]) && is_numeric($counts[$eventKey])) {
                    $normalized['totals'][$eventKey] = $currentTotal + max(0, (int) $counts[$eventKey]);
                }
            }
        }

        return $normalized;
    }
}
