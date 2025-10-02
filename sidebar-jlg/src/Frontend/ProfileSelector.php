<?php

namespace JLG\Sidebar\Frontend;

use JLG\Sidebar\Settings\SettingsRepository;

class ProfileSelector
{
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

    private function normalizeConditions(array $conditions): array
    {
        $normalized = [
            'post_types' => [],
            'taxonomies' => [],
            'roles' => [],
            'languages' => [],
        ];

        if (isset($conditions['post_types']) && is_array($conditions['post_types'])) {
            foreach ($conditions['post_types'] as $postType) {
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

        $normalized['post_types'] = array_keys($normalized['post_types']);
        $normalized['roles'] = array_keys($normalized['roles']);
        $normalized['languages'] = array_keys($normalized['languages']);

        return $normalized;
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

        return true;
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

        foreach ($conditions['taxonomies'] as $taxonomyCondition) {
            $score += 1;
            $score += count($taxonomyCondition['terms']);
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

        return [
            'post_types' => array_keys($postTypes),
            'taxonomies' => $taxonomies,
            'roles' => array_keys($roles),
            'language' => $language,
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
