<?php

namespace JLG\Sidebar\Frontend;

use JLG\Sidebar\Settings\SettingsRepository;

class ProfileSelector
{
    private const DISABLED_FLAG_KEYS = [
        'enabled',
        'is_enabled',
        'active',
        'is_active',
        'disabled',
        'is_disabled',
    ];

    private SettingsRepository $settings;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    public function selectProfile(): array
    {
        $defaultOptions = $this->settings->getOptions();
        $profiles = $this->settings->getProfiles();

        $selection = [
            'id' => 'default',
            'settings' => $defaultOptions,
            'is_fallback' => true,
        ];

        if ($profiles === []) {
            return $selection;
        }

        $context = $this->buildRequestContext();
        $bestPriority = -1;
        $bestScore = -1;

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            if ($this->isProfileDisabled($profile)) {
                continue;
            }

            $normalized = $this->normalizeProfile($profile, $defaultOptions);
            if ($normalized === null) {
                continue;
            }

            if (!$this->matchesConditions($normalized['conditions'], $context)) {
                continue;
            }

            $priority = $normalized['priority'];
            $score = $this->calculateSpecificity($normalized['conditions']);

            if ($priority > $bestPriority || ($priority === $bestPriority && $score > $bestScore)) {
                $bestPriority = $priority;
                $bestScore = $score;
                $selection = [
                    'id' => $normalized['id'],
                    'settings' => $normalized['settings'],
                    'is_fallback' => false,
                ];
            }
        }

        return $selection;
    }

    private function normalizeProfile(array $profile, array $defaults): ?array
    {
        if ($this->isProfileDisabled($profile)) {
            return null;
        }

        $profileId = isset($profile['id']) ? (string) $profile['id'] : '';
        $profileId = $this->sanitizeIdentifier($profileId);

        if ($profileId === '') {
            $hashSource = json_encode($profile);
            if (!is_string($hashSource) || $hashSource === '') {
                $hashSource = serialize($profile);
            }

            $profileId = 'profile_' . substr(md5((string) $hashSource), 0, 12);
        }

        $settings = isset($profile['settings']) && is_array($profile['settings']) ? $profile['settings'] : [];

        if (function_exists('wp_parse_args')) {
            $mergedSettings = wp_parse_args($settings, $defaults);
        } else {
            $mergedSettings = array_merge($defaults, $settings);
        }

        unset($mergedSettings['profiles']);

        $conditions = isset($profile['conditions']) && is_array($profile['conditions'])
            ? $profile['conditions']
            : [];

        $normalizedConditions = $this->normalizeConditions($conditions);

        $priority = 0;
        if (isset($profile['priority']) && is_numeric($profile['priority'])) {
            $priority = (int) $profile['priority'];
        }

        return [
            'id' => $profileId,
            'settings' => $mergedSettings,
            'conditions' => $normalizedConditions,
            'priority' => $priority,
        ];
    }

    private function isProfileDisabled(array $profile): bool
    {
        foreach (self::DISABLED_FLAG_KEYS as $flag) {
            if (!array_key_exists($flag, $profile)) {
                continue;
            }

            $value = $profile[$flag];

            if ($flag === 'disabled' || $flag === 'is_disabled') {
                if ($this->isTruthy($value)) {
                    return true;
                }

                continue;
            }

            if ($this->isFalsy($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    private function isFalsy($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return $value === false;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['0', '', 'false', 'no', 'off', 'inactive', 'disabled'], true);
        }

        if (is_array($value)) {
            return $value === [];
        }

        return empty($value);
    }

    /**
     * @param mixed $value
     */
    private function isTruthy($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value === true;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return false;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', 'inactive', 'disabled'], true)) {
                return false;
            }

            return true;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }

    private function normalizeConditions(array $conditions): array
    {
        $normalized = [
            'post_types' => [],
            'taxonomies' => [],
            'roles' => [],
            'languages' => [],
            'devices' => [],
            'logged_in' => null,
            'schedule' => [
                'start' => null,
                'end' => null,
                'days' => [],
            ],
        ];

        $postTypeSources = [];

        if (isset($conditions['post_types']) && is_array($conditions['post_types'])) {
            $postTypeSources[] = $conditions['post_types'];
        }

        if (isset($conditions['content_types']) && is_array($conditions['content_types'])) {
            $postTypeSources[] = $conditions['content_types'];
        }

        foreach ($postTypeSources as $postTypes) {
            foreach ($postTypes as $postType) {
                if (!is_string($postType) && !is_numeric($postType)) {
                    continue;
                }

                $sanitized = $this->sanitizeIdentifier((string) $postType);
                if ($sanitized === '') {
                    continue;
                }

                $normalized['post_types'][$sanitized] = true;
            }
        }

        if (isset($conditions['taxonomies']) && is_array($conditions['taxonomies'])) {
            foreach ($conditions['taxonomies'] as $taxonomyCondition) {
                if (!is_array($taxonomyCondition)) {
                    if (is_string($taxonomyCondition) || is_numeric($taxonomyCondition)) {
                        $taxonomyName = $this->sanitizeIdentifier((string) $taxonomyCondition);
                        if ($taxonomyName !== '') {
                            $normalized['taxonomies'][] = [
                                'taxonomy' => $taxonomyName,
                                'terms' => [],
                            ];
                        }
                    }

                    continue;
                }

                $taxonomyName = $this->sanitizeIdentifier((string) ($taxonomyCondition['taxonomy'] ?? ''));
                if ($taxonomyName === '') {
                    continue;
                }

                $terms = [];
                if (isset($taxonomyCondition['terms'])) {
                    $terms = $this->normalizeTerms($taxonomyCondition['terms']);
                }

                $normalized['taxonomies'][] = [
                    'taxonomy' => $taxonomyName,
                    'terms' => $terms,
                ];
            }
        }

        if (isset($conditions['roles']) && is_array($conditions['roles'])) {
            foreach ($conditions['roles'] as $role) {
                if (!is_string($role) && !is_numeric($role)) {
                    continue;
                }

                $sanitized = $this->sanitizeIdentifier((string) $role);
                if ($sanitized === '') {
                    continue;
                }

                $normalized['roles'][$sanitized] = true;
            }
        }

        if (isset($conditions['languages']) && is_array($conditions['languages'])) {
            foreach ($conditions['languages'] as $language) {
                if (!is_string($language) && !is_numeric($language)) {
                    continue;
                }

                $languageValue = strtolower(str_replace('-', '_', (string) $language));
                $sanitized = $this->sanitizeIdentifier($languageValue);
                if ($sanitized === '') {
                    continue;
                }

                $normalized['languages'][$sanitized] = true;
            }
        }

        if (isset($conditions['devices'])) {
            $normalized['devices'] = $this->normalizeDeviceConditions($conditions['devices']);
        }

        if (array_key_exists('logged_in', $conditions)) {
            $normalized['logged_in'] = $this->normalizeLoggedInCondition($conditions['logged_in']);
        }

        if (isset($conditions['schedule']) && is_array($conditions['schedule'])) {
            $normalized['schedule'] = $this->normalizeScheduleCondition($conditions['schedule']);
        }

        $normalized['post_types'] = array_keys($normalized['post_types']);
        $normalized['roles'] = array_keys($normalized['roles']);
        $normalized['languages'] = array_keys($normalized['languages']);

        return $normalized;
    }

    private function normalizeDeviceConditions($devices): array
    {
        $allowedDevices = ['mobile', 'desktop'];
        $normalized = [];

        $values = [];
        if (is_array($devices)) {
            $values = $devices;
        } elseif (is_string($devices) || is_numeric($devices)) {
            $values = [(string) $devices];
        }

        foreach ($values as $device) {
            if (!is_string($device) && !is_numeric($device)) {
                continue;
            }

            $sanitized = $this->sanitizeIdentifier((string) $device);
            if ($sanitized === '') {
                continue;
            }

            if (!in_array($sanitized, $allowedDevices, true)) {
                continue;
            }

            $normalized[$sanitized] = true;
        }

        return array_keys($normalized);
    }

    private function normalizeLoggedInCondition($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '' || $normalized === 'any') {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'logged-in', 'logged_in'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', 'logged-out', 'logged_out'], true)) {
                return false;
            }
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        return null;
    }

    private function normalizeScheduleCondition(array $schedule): array
    {
        $normalized = [
            'start' => null,
            'end' => null,
            'days' => [],
        ];

        $start = $schedule['start'] ?? ($schedule['from'] ?? null);
        $end = $schedule['end'] ?? ($schedule['to'] ?? null);
        $days = $schedule['days'] ?? [];

        $normalized['start'] = $this->normalizeScheduleTime($start);
        $normalized['end'] = $this->normalizeScheduleTime($end);
        $normalized['days'] = $this->normalizeScheduleDays($days);

        return $normalized;
    }

    private function normalizeScheduleTime($time): ?string
    {
        if (!is_string($time) && !is_numeric($time)) {
            return null;
        }

        $stringTime = trim((string) $time);
        if ($stringTime === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $stringTime, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function normalizeScheduleDays($days): array
    {
        $allowedDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $normalized = [];

        if (is_array($days)) {
            $values = $days;
        } elseif (is_string($days) || is_numeric($days)) {
            $values = preg_split('/[\s,]+/', (string) $days) ?: [];
        } else {
            $values = [];
        }

        foreach ($values as $day) {
            if (!is_string($day) && !is_numeric($day)) {
                continue;
            }

            $candidate = strtolower(trim((string) $day));

            if ($candidate === '') {
                continue;
            }

            switch ($candidate) {
                case 'monday':
                case 'mon':
                case '1':
                case '01':
                    $candidate = 'mon';
                    break;
                case 'tuesday':
                case 'tue':
                case '2':
                case '02':
                    $candidate = 'tue';
                    break;
                case 'wednesday':
                case 'wed':
                case '3':
                case '03':
                    $candidate = 'wed';
                    break;
                case 'thursday':
                case 'thu':
                case '4':
                case '04':
                    $candidate = 'thu';
                    break;
                case 'friday':
                case 'fri':
                case '5':
                case '05':
                    $candidate = 'fri';
                    break;
                case 'saturday':
                case 'sat':
                case '6':
                case '06':
                    $candidate = 'sat';
                    break;
                case 'sunday':
                case 'sun':
                case '0':
                case '00':
                case '7':
                case '07':
                    $candidate = 'sun';
                    break;
            }

            if (!in_array($candidate, $allowedDays, true)) {
                continue;
            }

            $normalized[$candidate] = true;
        }

        return array_keys($normalized);
    }

    private function normalizeTerms($terms): array
    {
        $normalized = [];

        if (is_array($terms)) {
            foreach ($terms as $term) {
                $normalizedTerm = $this->normalizeTermValue($term);
                if ($normalizedTerm === null) {
                    continue;
                }

                $normalized[$normalizedTerm] = true;
            }
        } elseif (is_string($terms) || is_numeric($terms)) {
            $normalizedTerm = $this->normalizeTermValue($terms);
            if ($normalizedTerm !== null) {
                $normalized[$normalizedTerm] = true;
            }
        }

        return array_keys($normalized);
    }

    private function normalizeTermValue($term): ?string
    {
        if (is_int($term) || (is_string($term) && preg_match('/^-?\d+$/', $term) === 1)) {
            $termId = absint((int) $term);
            if ($termId > 0) {
                return (string) $termId;
            }

            return null;
        }

        if (!is_string($term)) {
            return null;
        }

        $sanitized = $this->sanitizeIdentifier($term);

        return $sanitized === '' ? null : $sanitized;
    }

    private function matchesConditions(array $conditions, array $context): bool
    {
        if ($conditions['post_types'] !== []) {
            $intersection = array_intersect($conditions['post_types'], $context['post_types']);
            if ($intersection === []) {
                return false;
            }
        }

        if ($conditions['roles'] !== []) {
            $intersection = array_intersect($conditions['roles'], $context['roles']);
            if ($intersection === []) {
                return false;
            }
        }

        if ($conditions['languages'] !== []) {
            $language = $context['language'];
            if ($language === '' || !in_array($language, $conditions['languages'], true)) {
                return false;
            }
        }

        foreach ($conditions['taxonomies'] as $taxonomyCondition) {
            if (!$this->matchesTaxonomyCondition($taxonomyCondition, $context['taxonomies'])) {
                return false;
            }
        }

        if ($conditions['devices'] !== []) {
            $device = $context['device'] ?? '';
            if (!is_string($device) || !in_array($device, $conditions['devices'], true)) {
                return false;
            }
        }

        if ($conditions['logged_in'] !== null) {
            $isLoggedIn = $context['is_logged_in'] ?? null;
            if (!is_bool($isLoggedIn) || $isLoggedIn !== $conditions['logged_in']) {
                return false;
            }
        }

        if (!$this->matchesScheduleCondition($conditions['schedule'], $context)) {
            return false;
        }

        return true;
    }

    private function matchesScheduleCondition(array $schedule, array $context): bool
    {
        $hasTimeRestriction = ($schedule['start'] ?? null) !== null || ($schedule['end'] ?? null) !== null;
        $hasDayRestriction = isset($schedule['days']) && $schedule['days'] !== [];

        if (!$hasTimeRestriction && !$hasDayRestriction) {
            return true;
        }

        $timestamp = $context['timestamp'] ?? null;
        $timeOfDay = $context['time_of_day_minutes'] ?? null;
        $dayOfWeek = $context['day_of_week'] ?? '';

        if (!is_int($timestamp) || $timestamp <= 0) {
            $timestamp = null;
        }

        if (!is_int($timeOfDay)) {
            if ($timestamp !== null) {
                $timeOfDay = (int) date('G', $timestamp) * 60 + (int) date('i', $timestamp);
            }
        }

        if ($hasDayRestriction) {
            if (!is_string($dayOfWeek) || $dayOfWeek === '') {
                if ($timestamp === null) {
                    return false;
                }

                $dayOfWeek = strtolower(date('D', $timestamp));
            }

            $dayNormalized = $this->normalizeScheduleDays([$dayOfWeek]);
            if ($dayNormalized === []) {
                return false;
            }

            $currentDay = $dayNormalized[0];

            if (!in_array($currentDay, $schedule['days'], true)) {
                return false;
            }
        }

        if (!$hasTimeRestriction) {
            return true;
        }

        if (!is_int($timeOfDay)) {
            return false;
        }

        $start = $this->convertScheduleTimeToMinutes($schedule['start'] ?? null);
        $end = $this->convertScheduleTimeToMinutes($schedule['end'] ?? null);

        if ($start === null && $end === null) {
            return true;
        }

        if ($start === null) {
            $start = 0;
        }

        if ($end === null) {
            $end = 24 * 60 - 1;
        }

        if ($start <= $end) {
            return $timeOfDay >= $start && $timeOfDay <= $end;
        }

        return $timeOfDay >= $start || $timeOfDay <= $end;
    }

    private function convertScheduleTimeToMinutes(?string $time): ?int
    {
        if ($time === null) {
            return null;
        }

        if (preg_match('/^(\d{2}):(\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        return $hour * 60 + $minute;
    }

    private function matchesTaxonomyCondition(array $taxonomyCondition, array $contextTaxonomies): bool
    {
        $taxonomy = $taxonomyCondition['taxonomy'] ?? '';
        if ($taxonomy === '') {
            return false;
        }

        if (!isset($contextTaxonomies[$taxonomy])) {
            return false;
        }

        $contextTerms = $contextTaxonomies[$taxonomy];

        if (!is_array($contextTerms)) {
            return false;
        }

        if ($taxonomyCondition['terms'] === []) {
            return true;
        }

        foreach ($taxonomyCondition['terms'] as $term) {
            if (in_array($term, $contextTerms, true)) {
                return true;
            }
        }

        return false;
    }

    private function calculateSpecificity(array $conditions): int
    {
        $score = 0;

        $score += count($conditions['post_types']);
        $score += count($conditions['roles']);
        $score += count($conditions['languages']);
        $score += count($conditions['devices']);

        if ($conditions['logged_in'] !== null) {
            $score += 1;
        }

        foreach ($conditions['taxonomies'] as $taxonomyCondition) {
            $score += 1;
            $score += count($taxonomyCondition['terms']);
        }

        if (($conditions['schedule']['start'] ?? null) !== null || ($conditions['schedule']['end'] ?? null) !== null) {
            $score += 1;
        }

        if (($conditions['schedule']['days'] ?? []) !== []) {
            $score += count($conditions['schedule']['days']);
        }

        return $score;
    }

    private function buildRequestContext(): array
    {
        $postTypes = [];
        $taxonomies = [];

        $postType = null;
        if (function_exists('get_post_type')) {
            $postTypeValue = get_post_type();
            if (is_string($postTypeValue) || is_numeric($postTypeValue)) {
                $postType = $this->sanitizeIdentifier((string) $postTypeValue);
            }
        }

        if ($postType !== null && $postType !== '') {
            $postTypes[$postType] = true;
        }

        $currentObjectId = 0;
        if (function_exists('get_queried_object_id')) {
            $queriedId = get_queried_object_id();
            if (is_int($queriedId) || (is_string($queriedId) && preg_match('/^-?\d+$/', $queriedId) === 1)) {
                $currentObjectId = absint((int) $queriedId);
            }
        }

        $queriedObject = null;
        if (function_exists('get_queried_object')) {
            $queriedObject = get_queried_object();
        }

        if (is_object($queriedObject)) {
            if (isset($queriedObject->post_type)) {
                $queriedPostType = $this->sanitizeIdentifier((string) $queriedObject->post_type);
                if ($queriedPostType !== '') {
                    $postTypes[$queriedPostType] = true;
                }
            }

            if (isset($queriedObject->taxonomy)) {
                $taxonomyName = $this->sanitizeIdentifier((string) $queriedObject->taxonomy);
                if ($taxonomyName !== '') {
                    $terms = [];

                    if (isset($queriedObject->term_id)) {
                        $termId = absint((int) $queriedObject->term_id);
                        if ($termId > 0) {
                            $terms[(string) $termId] = true;
                        }
                    }

                    if (isset($queriedObject->slug) && is_string($queriedObject->slug)) {
                        $slug = $this->sanitizeIdentifier($queriedObject->slug);
                        if ($slug !== '') {
                            $terms[$slug] = true;
                        }
                    }

                    $taxonomies[$taxonomyName] = array_keys($terms);
                }
            }
        }

        if ($currentObjectId === 0 && is_object($queriedObject)) {
            if (isset($queriedObject->ID) && (is_int($queriedObject->ID) || (is_string($queriedObject->ID) && preg_match('/^-?\d+$/', $queriedObject->ID) === 1))) {
                $currentObjectId = absint((int) $queriedObject->ID);
            } elseif (isset($queriedObject->id) && (is_int($queriedObject->id) || (is_string($queriedObject->id) && preg_match('/^-?\d+$/', $queriedObject->id) === 1))) {
                $currentObjectId = absint((int) $queriedObject->id);
            }
        }

        if ($currentObjectId > 0 && function_exists('get_post_type')) {
            $resolvedPostType = get_post_type($currentObjectId);
            if (is_string($resolvedPostType) || is_numeric($resolvedPostType)) {
                $resolvedPostType = $this->sanitizeIdentifier((string) $resolvedPostType);
                if ($resolvedPostType !== '') {
                    $postTypes[$resolvedPostType] = true;

                    if ($postType === null || $postType === '') {
                        $postType = $resolvedPostType;
                    }
                }
            }
        }

        if ($currentObjectId > 0 && $postType !== null && $postType !== '' && function_exists('get_object_taxonomies')) {
            $objectTaxonomies = get_object_taxonomies($postType);
            if (is_array($objectTaxonomies)) {
                foreach ($objectTaxonomies as $objectTaxonomy) {
                    if (!is_string($objectTaxonomy) && !is_numeric($objectTaxonomy)) {
                        continue;
                    }

                    $taxonomyName = $this->sanitizeIdentifier((string) $objectTaxonomy);
                    if ($taxonomyName === '') {
                        continue;
                    }

                    $existingTerms = [];
                    if (isset($taxonomies[$taxonomyName]) && is_array($taxonomies[$taxonomyName])) {
                        foreach ($taxonomies[$taxonomyName] as $existingTerm) {
                            if (!is_string($existingTerm) && !is_numeric($existingTerm)) {
                                continue;
                            }

                            $existingTerms[(string) $existingTerm] = true;
                        }
                    }

                    $fetchedTerms = null;
                    if (function_exists('wp_get_post_terms')) {
                        $fetchedTerms = wp_get_post_terms($currentObjectId, $objectTaxonomy, ['fields' => 'all']);
                    } elseif (function_exists('get_the_terms')) {
                        $fetchedTerms = get_the_terms($currentObjectId, $objectTaxonomy);
                    }

                    if ($fetchedTerms === null) {
                        continue;
                    }

                    if (function_exists('is_wp_error') && is_wp_error($fetchedTerms)) {
                        continue;
                    }

                    if (!is_array($fetchedTerms)) {
                        continue;
                    }

                    foreach ($fetchedTerms as $term) {
                        if (is_object($term)) {
                            if (isset($term->term_id) && (is_int($term->term_id) || (is_string($term->term_id) && preg_match('/^-?\d+$/', $term->term_id) === 1))) {
                                $termId = absint((int) $term->term_id);
                                if ($termId > 0) {
                                    $existingTerms[(string) $termId] = true;
                                }
                            }

                            if (isset($term->slug) && is_string($term->slug)) {
                                $slug = $this->sanitizeIdentifier($term->slug);
                                if ($slug !== '') {
                                    $existingTerms[$slug] = true;
                                }
                            }
                        } elseif (is_array($term)) {
                            if (isset($term['term_id']) && (is_int($term['term_id']) || (is_string($term['term_id']) && preg_match('/^-?\d+$/', (string) $term['term_id']) === 1))) {
                                $termId = absint((int) $term['term_id']);
                                if ($termId > 0) {
                                    $existingTerms[(string) $termId] = true;
                                }
                            }

                            if (isset($term['slug']) && is_string($term['slug'])) {
                                $slug = $this->sanitizeIdentifier($term['slug']);
                                if ($slug !== '') {
                                    $existingTerms[$slug] = true;
                                }
                            }
                        } elseif (is_int($term) || (is_string($term) && preg_match('/^-?\d+$/', $term) === 1)) {
                            $termId = absint((int) $term);
                            if ($termId > 0) {
                                $existingTerms[(string) $termId] = true;
                            }
                        } elseif (is_string($term)) {
                            $slug = $this->sanitizeIdentifier($term);
                            if ($slug !== '') {
                                $existingTerms[$slug] = true;
                            }
                        }
                    }

                    if ($existingTerms !== []) {
                        $taxonomies[$taxonomyName] = array_keys($existingTerms);
                    }
                }
            }
        }

        $roles = [];
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if (is_object($user) && isset($user->roles) && is_array($user->roles)) {
                foreach ($user->roles as $role) {
                    if (!is_string($role) && !is_numeric($role)) {
                        continue;
                    }

                    $sanitizedRole = $this->sanitizeIdentifier((string) $role);
                    if ($sanitizedRole === '') {
                        continue;
                    }

                    $roles[$sanitizedRole] = true;
                }
            }
        }

        $language = '';
        $locale = '';

        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } elseif (function_exists('get_locale')) {
            $locale = get_locale();
        }

        if (is_string($locale) && $locale !== '') {
            $locale = strtolower(str_replace('-', '_', $locale));
            $language = $this->sanitizeIdentifier($locale);
        }

        $device = 'desktop';
        if (function_exists('wp_is_mobile')) {
            $isMobile = wp_is_mobile();
            if (is_bool($isMobile) && $isMobile) {
                $device = 'mobile';
            }
        }

        $isLoggedIn = null;
        if (function_exists('is_user_logged_in')) {
            $loggedIn = is_user_logged_in();
            if (is_bool($loggedIn)) {
                $isLoggedIn = $loggedIn;
            }
        }

        $timestamp = null;
        if (function_exists('current_time')) {
            $maybeTimestamp = current_time('timestamp');
            if (is_int($maybeTimestamp) || (is_string($maybeTimestamp) && preg_match('/^-?\d+$/', $maybeTimestamp) === 1)) {
                $timestamp = (int) $maybeTimestamp;
            }
        }

        if ($timestamp === null) {
            $timestamp = time();
        }

        $dayOfWeek = strtolower(date('D', $timestamp));
        $timeOfDayMinutes = (int) date('G', $timestamp) * 60 + (int) date('i', $timestamp);

        return [
            'post_types' => array_keys($postTypes),
            'taxonomies' => $taxonomies,
            'roles' => array_keys($roles),
            'language' => $language,
            'device' => $device,
            'is_logged_in' => $isLoggedIn,
            'timestamp' => $timestamp,
            'day_of_week' => $this->normalizeScheduleDays([$dayOfWeek])[0] ?? $dayOfWeek,
            'time_of_day_minutes' => $timeOfDayMinutes,
        ];
    }

    private function sanitizeIdentifier(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('sanitize_key')) {
            $sanitized = sanitize_key($value);
        } else {
            $sanitized = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '', $value));
        }

        return (string) $sanitized;
    }
}
