<?php

namespace JLG\Sidebar\Frontend;

class RequestContextResolver
{
    public function resolve(): array
    {
        $contentSignals = $this->resolveContentSignals();

        $context = [
            'current_post_ids' => $contentSignals['current_post_ids'],
            'current_post_types' => $contentSignals['current_post_types'],
            'current_category_ids' => $contentSignals['current_category_ids'],
            'current_url' => null,
            'post_types' => $contentSignals['post_types'],
            'taxonomies' => $contentSignals['taxonomies'],
            'roles' => $this->resolveCurrentUserRoles(),
            'language' => $this->resolveCurrentLanguage(),
            'device' => $this->resolveCurrentDevice(),
            'is_logged_in' => $this->resolveIsLoggedIn(),
            'timestamp' => $this->resolveCurrentTimestamp(),
            'day_of_week' => '',
            'time_of_day_minutes' => 0,
        ];

        $currentUrl = self::buildCurrentUrl();
        if ($currentUrl !== null) {
            $context['current_url'] = self::normalizeUrlForComparison($currentUrl);
        }

        $timestamp = $context['timestamp'];
        $dayOfWeek = strtolower(date('D', $timestamp));
        $normalizedDay = $this->normalizeScheduleDays([$dayOfWeek]);
        $context['day_of_week'] = $normalizedDay[0] ?? $dayOfWeek;
        $context['time_of_day_minutes'] = (int) date('G', $timestamp) * 60 + (int) date('i', $timestamp);

        return $context;
    }

    private function resolveContentSignals(): array
    {
        $postTypes = [];
        $currentPostTypes = [];
        $currentPostIds = [];
        $currentCategoryIds = [];
        $taxonomies = [];

        $postType = null;
        if (function_exists('get_post_type')) {
            $postTypeValue = get_post_type();
            if (is_string($postTypeValue) || is_numeric($postTypeValue)) {
                $postType = $this->sanitizeIdentifier((string) $postTypeValue);
                if ($postType !== '') {
                    $postTypes[$postType] = true;
                }
            }
        }

        $currentObjectId = 0;
        if (function_exists('get_queried_object_id')) {
            $queriedId = get_queried_object_id();
            if (is_int($queriedId) || (is_string($queriedId) && preg_match('/^-?\d+$/', $queriedId) === 1)) {
                $currentObjectId = absint((int) $queriedId);
                if ($currentObjectId > 0) {
                    $currentPostIds[] = $currentObjectId;
                }
            }
        }

        $queriedObject = null;
        if (function_exists('get_queried_object')) {
            $queriedObject = get_queried_object();
        }

        if (is_object($queriedObject)) {
            if (isset($queriedObject->ID) && (is_int($queriedObject->ID) || (is_string($queriedObject->ID) && preg_match('/^-?\d+$/', (string) $queriedObject->ID) === 1))) {
                $objectId = absint((int) $queriedObject->ID);
                if ($objectId > 0 && !in_array($objectId, $currentPostIds, true)) {
                    $currentPostIds[] = $objectId;
                }
            } elseif (isset($queriedObject->id) && (is_int($queriedObject->id) || (is_string($queriedObject->id) && preg_match('/^-?\d+$/', (string) $queriedObject->id) === 1))) {
                $objectId = absint((int) $queriedObject->id);
                if ($objectId > 0 && !in_array($objectId, $currentPostIds, true)) {
                    $currentPostIds[] = $objectId;
                }
            }

            if (isset($queriedObject->post_type)) {
                $queriedPostType = $this->sanitizeIdentifier((string) $queriedObject->post_type);
                if ($queriedPostType !== '') {
                    $currentPostTypes[] = $queriedPostType;
                    $postTypes[$queriedPostType] = true;
                }
            }

            if (isset($queriedObject->taxonomy)) {
                $taxonomyName = $this->sanitizeIdentifier((string) $queriedObject->taxonomy);
                if ($taxonomyName !== '') {
                    $terms = [];

                    if (isset($queriedObject->term_id) && (is_int($queriedObject->term_id) || (is_string($queriedObject->term_id) && preg_match('/^-?\d+$/', (string) $queriedObject->term_id) === 1))) {
                        $termId = absint((int) $queriedObject->term_id);
                        if ($termId > 0) {
                            $terms[(string) $termId] = true;
                            if ($taxonomyName === 'category') {
                                $currentCategoryIds[] = $termId;
                            }
                        }
                    }

                    if (isset($queriedObject->slug) && is_string($queriedObject->slug)) {
                        $slug = $this->sanitizeIdentifier($queriedObject->slug);
                        if ($slug !== '') {
                            $terms[$slug] = true;
                        }
                    }

                    if ($terms !== []) {
                        $taxonomies[$taxonomyName] = array_keys($terms);
                    }
                }
            }
        }

        if ($currentObjectId === 0 && is_object($queriedObject)) {
            if (isset($queriedObject->ID) && (is_int($queriedObject->ID) || (is_string($queriedObject->ID) && preg_match('/^-?\d+$/', (string) $queriedObject->ID) === 1))) {
                $currentObjectId = absint((int) $queriedObject->ID);
            } elseif (isset($queriedObject->id) && (is_int($queriedObject->id) || (is_string($queriedObject->id) && preg_match('/^-?\d+$/', (string) $queriedObject->id) === 1))) {
                $currentObjectId = absint((int) $queriedObject->id);
            }
        }

        if ($currentObjectId > 0 && function_exists('get_post_type')) {
            $resolvedPostType = get_post_type($currentObjectId);
            if (is_string($resolvedPostType) || is_numeric($resolvedPostType)) {
                $resolvedPostType = $this->sanitizeIdentifier((string) $resolvedPostType);
                if ($resolvedPostType !== '') {
                    $postTypes[$resolvedPostType] = true;
                    if (!in_array($resolvedPostType, $currentPostTypes, true)) {
                        $currentPostTypes[] = $resolvedPostType;
                    }

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
                            if (isset($term->term_id) && (is_int($term->term_id) || (is_string($term->term_id) && preg_match('/^-?\d+$/', (string) $term->term_id) === 1))) {
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
                        } elseif (is_int($term) || (is_string($term) && preg_match('/^-?\d+$/', (string) $term) === 1)) {
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

        $currentPostIds = array_values(array_unique($currentPostIds));
        $currentCategoryIds = array_values(array_unique($currentCategoryIds));
        $currentPostTypes = array_values(array_unique($currentPostTypes));

        return [
            'current_post_ids' => $currentPostIds,
            'current_post_types' => $currentPostTypes,
            'current_category_ids' => $currentCategoryIds,
            'post_types' => array_keys($postTypes),
            'taxonomies' => $taxonomies,
        ];
    }

    private function resolveCurrentUserRoles(): array
    {
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

        return array_keys($roles);
    }

    private function resolveCurrentLanguage(): string
    {
        $locale = '';
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } elseif (function_exists('get_locale')) {
            $locale = get_locale();
        }

        if (!is_string($locale) || $locale === '') {
            return '';
        }

        $locale = strtolower(str_replace('-', '_', $locale));

        return $this->sanitizeIdentifier($locale);
    }

    private function resolveCurrentDevice(): string
    {
        if (function_exists('wp_is_mobile')) {
            $isMobile = wp_is_mobile();
            if (is_bool($isMobile) && $isMobile) {
                return 'mobile';
            }
        }

        return 'desktop';
    }

    private function resolveIsLoggedIn(): ?bool
    {
        if (!function_exists('is_user_logged_in')) {
            return null;
        }

        $loggedIn = is_user_logged_in();
        if (!is_bool($loggedIn)) {
            return null;
        }

        return $loggedIn;
    }

    private function resolveCurrentTimestamp(): int
    {
        $timestamp = null;
        if (function_exists('current_time')) {
            $maybeTimestamp = current_time('timestamp');
            if (is_int($maybeTimestamp) || (is_string($maybeTimestamp) && preg_match('/^-?\d+$/', (string) $maybeTimestamp) === 1)) {
                $timestamp = (int) $maybeTimestamp;
            }
        }

        if ($timestamp === null) {
            $timestamp = time();
        }

        return $timestamp;
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

    public static function buildCurrentUrl(): ?string
    {
        $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
        if ($host === '') {
            return null;
        }

        $scheme = 'http';
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            $scheme = 'https';
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($requestUri === '') {
            $requestUri = '/';
        }

        return $scheme . '://' . $host . $requestUri;
    }

    public static function normalizeUrlForComparison(?string $url): string
    {
        if (!is_string($url)) {
            return '';
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = @parse_url($url);
        if ($parts === false) {
            return self::trimPath($url);
        }

        if (!isset($parts['scheme']) || !isset($parts['host'])) {
            $absoluteUrl = self::convertRelativeUrlToAbsolute($url);
            if ($absoluteUrl !== null) {
                $parts = @parse_url($absoluteUrl);
                if ($parts === false) {
                    return self::trimPath($absoluteUrl);
                }

                $url = $absoluteUrl;
            }
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        if ($path === '') {
            $path = '/';
        }
        $path = '/' . ltrim($path, '/');
        $path = self::trimPath($path);

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        if (isset($parts['scheme']) && isset($parts['host'])) {
            $scheme = strtolower((string) $parts['scheme']);
            $host = strtolower((string) $parts['host']);
            $normalized = $scheme . '://' . $host;

            if (isset($parts['port']) && $parts['port'] !== null) {
                $port = (int) $parts['port'];
                if (!self::isDefaultPortForScheme($port, $scheme)) {
                    $normalized .= ':' . $port;
                }
            }

            return $normalized . $path . $query;
        }

        return $path . $query;
    }

    private static function convertRelativeUrlToAbsolute(string $url): ?string
    {
        $homeUrl = self::getHomeUrlForNormalization();
        if ($homeUrl === null) {
            return null;
        }

        $homeUrl = rtrim($homeUrl, '/');
        if ($homeUrl === '') {
            return null;
        }

        if ($url === '' || $url === '/') {
            return $homeUrl . '/';
        }

        $firstChar = $url[0];
        if ($firstChar === '/') {
            return $homeUrl . $url;
        }

        if ($firstChar === '?' || $firstChar === '#') {
            return $homeUrl . '/' . $url;
        }

        return $homeUrl . '/' . $url;
    }

    private static function getHomeUrlForNormalization(): ?string
    {
        if (!function_exists('home_url')) {
            return null;
        }

        $homeUrl = home_url('/');
        if (!is_string($homeUrl)) {
            return null;
        }

        $homeUrl = trim($homeUrl);
        if ($homeUrl === '') {
            return null;
        }

        return $homeUrl;
    }

    private static function trimPath(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private static function isDefaultPortForScheme(int $port, string $scheme): bool
    {
        $scheme = strtolower($scheme);

        if ($scheme === 'http') {
            return $port === 80;
        }

        if ($scheme === 'https') {
            return $port === 443;
        }

        return false;
    }
}
