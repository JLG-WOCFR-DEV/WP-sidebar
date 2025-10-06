<?php

namespace JLG\Sidebar\Frontend;

class RequestContextResolver
{
    private ?array $cachedContext = null;

    public function resolve(): array
    {
        if ($this->cachedContext !== null) {
            return $this->cachedContext;
        }

        $postContext = $this->resolvePostContext();
        $userContext = $this->resolveUserContext();

        $currentUrl = $this->buildCurrentUrl();
        $normalizedUrl = null;
        if ($currentUrl !== null) {
            $normalizedUrl = $this->normalizeUrlForComparison($currentUrl);
        }

        $context = $postContext + $userContext + [
            'current_url' => $normalizedUrl,
        ];

        $this->cachedContext = $context;

        return $context;
    }

    public function resetCachedContext(): void
    {
        $this->cachedContext = null;
    }

    public function normalizeUrlForComparison(?string $url): string
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
            return $this->trimPath($url);
        }

        $rawScheme = isset($parts['scheme']) ? (string) $parts['scheme'] : '';
        $scheme = $rawScheme !== '' ? strtolower($rawScheme) : '';
        $hasScheme = $scheme !== '';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $hasHost = $host !== '';

        if ($hasScheme && !$hasHost && !in_array($scheme, ['http', 'https'], true)) {
            return $this->normalizeNonHttpUrl($rawScheme, $url);
        }

        if (!$hasScheme && !$hasHost) {
            $absoluteUrl = $this->convertRelativeUrlToAbsolute($url);
            if ($absoluteUrl !== null) {
                $parts = @parse_url($absoluteUrl);
                if ($parts === false) {
                    return $this->trimPath($absoluteUrl);
                }

                $rawScheme = isset($parts['scheme']) ? (string) $parts['scheme'] : '';
                $scheme = $rawScheme !== '' ? strtolower($rawScheme) : '';
                $hasScheme = $scheme !== '';
                $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
                $hasHost = $host !== '';
                $url = $absoluteUrl;
            }
        }

        if (!$hasScheme && $hasHost) {
            $scheme = $this->detectCurrentScheme();
            $hasScheme = true;
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        if ($path === '') {
            $path = '/';
        }
        $path = '/' . ltrim($path, '/');
        $path = $this->trimPath($path);

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        if ($hasScheme && $hasHost) {
            $normalized = $scheme . '://' . $host;

            if (isset($parts['port']) && $parts['port'] !== null) {
                $port = (int) $parts['port'];
                if (!$this->isDefaultPortForScheme($port, $scheme)) {
                    $normalized .= ':' . $port;
                }
            }

            return $normalized . $path . $query;
        }

        return $path . $query;
    }

    private function resolvePostContext(): array
    {
        $currentPostIds = [];
        $currentPostTypes = [];
        $currentCategoryIds = [];
        $postTypes = [];
        $taxonomies = [];

        $primaryPostType = null;
        if (function_exists('get_post_type')) {
            $postTypeValue = get_post_type();
        }
        if (isset($postTypeValue) && (is_string($postTypeValue) || is_numeric($postTypeValue))) {
            $normalizedPostType = $this->sanitizeIdentifier((string) $postTypeValue);
            if ($normalizedPostType !== '') {
                $postTypes[$normalizedPostType] = true;
                $primaryPostType = $normalizedPostType;
            }
        }

        $queriedObject = null;
        if (function_exists('get_queried_object')) {
            $queriedObject = get_queried_object();
        }

        $currentObjectId = 0;
        if (function_exists('get_queried_object_id')) {
            $queriedId = get_queried_object_id();
            if (is_int($queriedId) || (is_string($queriedId) && preg_match('/^-?\d+$/', $queriedId) === 1)) {
                $currentObjectId = absint((int) $queriedId);
            }
        }

        if (is_object($queriedObject)) {
            if (isset($queriedObject->ID) && (is_int($queriedObject->ID) || (is_string($queriedObject->ID) && preg_match('/^-?\d+$/', $queriedObject->ID) === 1))) {
                $objectId = absint((int) $queriedObject->ID);
                if ($objectId > 0) {
                    $currentPostIds[$objectId] = true;
                    if ($currentObjectId === 0) {
                        $currentObjectId = $objectId;
                    }
                }
            }

            if (isset($queriedObject->post_type)) {
                $queriedPostType = $this->sanitizeIdentifier((string) $queriedObject->post_type);
                if ($queriedPostType !== '') {
                    $postTypes[$queriedPostType] = true;
                    $currentPostTypes[$queriedPostType] = true;
                    if ($primaryPostType === null) {
                        $primaryPostType = $queriedPostType;
                    }
                }
            }

            if (isset($queriedObject->taxonomy)) {
                $taxonomyName = $this->sanitizeIdentifier((string) $queriedObject->taxonomy);
                if ($taxonomyName !== '') {
                    $terms = [];

                    if (isset($queriedObject->term_id) && (is_int($queriedObject->term_id) || (is_string($queriedObject->term_id) && preg_match('/^-?\d+$/', $queriedObject->term_id) === 1))) {
                        $termId = absint((int) $queriedObject->term_id);
                        if ($termId > 0) {
                            $terms[(string) $termId] = true;
                            if ($taxonomyName === 'category') {
                                $currentCategoryIds[$termId] = true;
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

        if ($currentObjectId > 0 && function_exists('get_post_type')) {
            $resolvedPostType = get_post_type($currentObjectId);
            if (is_string($resolvedPostType) || is_numeric($resolvedPostType)) {
                $resolvedPostType = $this->sanitizeIdentifier((string) $resolvedPostType);
                if ($resolvedPostType !== '') {
                    $postTypes[$resolvedPostType] = true;
                    $currentPostTypes[$resolvedPostType] = true;
                    if ($primaryPostType === null) {
                        $primaryPostType = $resolvedPostType;
                    }
                }
            }
        }

        if ($currentObjectId > 0 && $primaryPostType !== null && $primaryPostType !== '' && function_exists('get_object_taxonomies')) {
            $objectTaxonomies = get_object_taxonomies($primaryPostType);
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

        return [
            'current_post_ids' => array_values(array_map('intval', array_keys($currentPostIds))),
            'current_post_types' => array_values(array_keys($currentPostTypes)),
            'current_category_ids' => array_values(array_map('intval', array_keys($currentCategoryIds))),
            'post_types' => array_values(array_keys($postTypes)),
            'taxonomies' => $taxonomies,
        ];
    }

    private function resolveUserContext(): array
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
        $normalizedDay = $this->normalizeScheduleDays([$dayOfWeek])[0] ?? $dayOfWeek;
        $timeOfDayMinutes = (int) date('G', $timestamp) * 60 + (int) date('i', $timestamp);

        return [
            'roles' => array_values(array_keys($roles)),
            'language' => $language,
            'device' => $device,
            'is_logged_in' => $isLoggedIn,
            'timestamp' => $timestamp,
            'day_of_week' => $normalizedDay,
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

    private function normalizeScheduleDays(array $days): array
    {
        $allowedDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $normalized = [];

        foreach ($days as $day) {
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

    private function buildCurrentUrl(): ?string
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

    private function convertRelativeUrlToAbsolute(string $url): ?string
    {
        $homeUrl = $this->getHomeUrlForNormalization();
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

    private function getHomeUrlForNormalization(): ?string
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

    private function trimPath(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private function normalizeNonHttpUrl(string $scheme, string $original): string
    {
        $normalizedScheme = strtolower($scheme);
        $separatorPosition = strpos($original, ':');

        if ($separatorPosition === false) {
            return $normalizedScheme . ':';
        }

        $afterScheme = substr($original, $separatorPosition + 1);
        if ($afterScheme === false) {
            $afterScheme = '';
        }

        return $normalizedScheme . ':' . ltrim((string) $afterScheme);
    }

    private function detectCurrentScheme(): string
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return 'https';
        }

        return 'http';
    }

    private function isDefaultPortForScheme(int $port, string $scheme): bool
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
