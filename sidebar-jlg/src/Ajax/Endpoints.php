<?php

namespace JLG\Sidebar\Ajax;

use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Settings\SettingsRepository;
use function __;

class Endpoints
{
    private SettingsRepository $settings;
    private MenuCache $cache;

    public function __construct(SettingsRepository $settings, MenuCache $cache)
    {
        $this->settings = $settings;
        $this->cache = $cache;
    }

    public function registerHooks(): void
    {
        add_action('wp_ajax_jlg_get_posts', [$this, 'ajax_get_posts']);
        add_action('wp_ajax_jlg_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_jlg_reset_settings', [$this, 'ajax_reset_settings']);
    }

    /**
     * Handle Select2 post lookups for the settings UI.
     *
     * Regression note: reset the global $post after get_posts() usage so other
     * queries on the request are not polluted.
     */
    public function ajax_get_posts(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_ajax_nonce', 'nonce');
        $searchTerm = '';
        if (isset($_POST['search'])) {
            $searchTerm = sanitize_text_field(wp_unslash($_POST['search']));
        }
        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $maxPerPage = 50;
        $requestedPerPage = isset($_POST['posts_per_page']) ? intval(wp_unslash($_POST['posts_per_page'])) : 20;

        if ($requestedPerPage > $maxPerPage) {
            wp_send_json_error(sprintf(__('Le paramètre posts_per_page ne peut pas dépasser %d.', 'sidebar-jlg'), $maxPerPage));
        }

        $perPage = min(max(1, $requestedPerPage), $maxPerPage);
        $allowedPostTypes = ['post', 'page'];
        $requestedPostType = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $postType = in_array($requestedPostType, $allowedPostTypes, true) ? $requestedPostType : 'post';
        $includeIds = [];
        if (isset($_POST['include'])) {
            $rawInclude = wp_unslash($_POST['include']);
            $includeSource = is_array($rawInclude) ? $rawInclude : explode(',', (string) $rawInclude);
            $includeIds = array_filter(array_map('absint', $includeSource));
        }

        $postArgs = [
            'posts_per_page' => $perPage,
            'paged' => $page,
            'post_type' => $postType,
        ];

        if ($searchTerm !== '') {
            $postArgs['s'] = $searchTerm;
        }

        $posts = get_posts($postArgs);

        $optionsById = [];
        foreach ($posts as $post) {
            $optionsById[$post->ID] = ['id' => $post->ID, 'title' => $post->post_title];
        }
        wp_reset_postdata();

        if (!empty($includeIds)) {
            $existingIds = array_keys($optionsById);
            $missingIds = array_diff($includeIds, $existingIds);

            if (!empty($missingIds)) {
                $additionalPosts = get_posts([
                    'posts_per_page' => count($missingIds),
                    'post__in'       => $missingIds,
                    'orderby'        => 'post__in',
                    'post_type'      => $postType,
                ]);

                foreach ($additionalPosts as $post) {
                    $optionsById[$post->ID] = ['id' => $post->ID, 'title' => $post->post_title];
                }
                wp_reset_postdata();
            }

            $orderedOptions = [];
            foreach ($includeIds as $includeId) {
                if (isset($optionsById[$includeId])) {
                    $orderedOptions[] = $optionsById[$includeId];
                    unset($optionsById[$includeId]);
                }
            }

            $options = array_merge($orderedOptions, array_values($optionsById));
        } else {
            $options = array_values($optionsById);
        }

        wp_send_json_success($options);
    }

    public function ajax_get_categories(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_ajax_nonce', 'nonce');
        $searchTerm = '';
        if (isset($_POST['search'])) {
            $searchTerm = sanitize_text_field(wp_unslash($_POST['search']));
        }
        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $maxPerPage = 50;
        $requestedPerPage = isset($_POST['posts_per_page']) ? intval(wp_unslash($_POST['posts_per_page'])) : 20;

        if ($requestedPerPage > $maxPerPage) {
            wp_send_json_error(sprintf(__('Le paramètre posts_per_page ne peut pas dépasser %d.', 'sidebar-jlg'), $maxPerPage));
        }

        $perPage = min(max(1, $requestedPerPage), $maxPerPage);
        $offset = ($page - 1) * $perPage;
        $includeIds = [];
        if (isset($_POST['include'])) {
            $rawInclude = wp_unslash($_POST['include']);
            $includeSource = is_array($rawInclude) ? $rawInclude : explode(',', (string) $rawInclude);
            $includeIds = array_filter(array_map('absint', $includeSource));
        }

        $categoryArgs = [
            'hide_empty' => false,
            'number' => $perPage,
            'offset' => $offset,
        ];

        if ($searchTerm !== '') {
            $categoryArgs['search'] = $searchTerm;
        }

        $categories = get_categories($categoryArgs);

        $optionsById = [];
        foreach ($categories as $category) {
            $optionsById[$category->term_id] = ['id' => $category->term_id, 'name' => $category->name];
        }

        if (!empty($includeIds)) {
            $existingIds = array_keys($optionsById);
            $missingIds = array_diff($includeIds, $existingIds);

            if (!empty($missingIds)) {
                $additionalCategories = get_categories([
                    'hide_empty' => false,
                    'include'    => $missingIds,
                    'number'     => count($missingIds),
                    'orderby'    => 'include',
                ]);

                foreach ($additionalCategories as $category) {
                    $optionsById[$category->term_id] = ['id' => $category->term_id, 'name' => $category->name];
                }
            }

            $orderedOptions = [];
            foreach ($includeIds as $includeId) {
                if (isset($optionsById[$includeId])) {
                    $orderedOptions[] = $optionsById[$includeId];
                    unset($optionsById[$includeId]);
                }
            }

            $options = array_merge($orderedOptions, array_values($optionsById));
        } else {
            $options = array_values($optionsById);
        }

        wp_send_json_success($options);
    }

    public function ajax_reset_settings(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_reset_nonce', 'nonce');
        $this->settings->deleteOptions();
        $this->cache->clear();
        wp_send_json_success(__('Réglages réinitialisés.', 'sidebar-jlg'));
    }

    private function get_ajax_capability(): string
    {
        return apply_filters('sidebar_jlg_ajax_capability', 'manage_options');
    }
}
