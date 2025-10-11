<?php

namespace JLG\Sidebar\Ajax;

use JLG\Sidebar\Accessibility\AuditRunner;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Analytics\AnalyticsEventQueue;
use JLG\Sidebar\Analytics\AnalyticsRepository;
use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Frontend\SidebarRenderer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;
use function __;
use function current_time;
use function esc_attr;
use function esc_url_raw;
use function gmdate;
use function home_url;
use function human_time_diff;
use function json_decode;
use function sanitize_key;
use function sanitize_text_field;
use function update_option;
use function time;
use function get_option;
use function wp_date;
use const DAY_IN_SECONDS;
use function wp_parse_args;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

class Endpoints
{
    private const MAX_ICONS_PER_REQUEST = 20;
    private const MAX_RESULTS_PER_REQUEST = 100;

    private SettingsRepository $settings;
    private MenuCache $cache;
    private IconLibrary $icons;
    private SettingsSanitizer $sanitizer;
    private AnalyticsRepository $analytics;
    private AnalyticsEventQueue $analyticsQueue;
    private string $pluginFile;
    private SidebarRenderer $renderer;
    private AuditRunner $auditRunner;

    public function __construct(
        SettingsRepository $settings,
        MenuCache $cache,
        IconLibrary $icons,
        SettingsSanitizer $sanitizer,
        AnalyticsRepository $analytics,
        AnalyticsEventQueue $analyticsQueue,
        string $pluginFile,
        SidebarRenderer $renderer,
        AuditRunner $auditRunner
    )
    {
        $this->settings = $settings;
        $this->cache = $cache;
        $this->icons = $icons;
        $this->sanitizer = $sanitizer;
        $this->analytics = $analytics;
        $this->analyticsQueue = $analyticsQueue;
        $this->pluginFile = $pluginFile;
        $this->renderer = $renderer;
        $this->auditRunner = $auditRunner;
    }

    public function registerHooks(): void
    {
        add_action('wp_ajax_jlg_get_posts', [$this, 'ajax_get_posts']);
        add_action('wp_ajax_jlg_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_jlg_reset_settings', [$this, 'ajax_reset_settings']);
        add_action('wp_ajax_jlg_get_icon_svg', [$this, 'ajax_get_icon_svg']);
        add_action('wp_ajax_jlg_upload_custom_icon', [$this, 'ajax_upload_custom_icon']);
        add_action('wp_ajax_jlg_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_jlg_import_settings', [$this, 'ajax_import_settings']);
        add_action('wp_ajax_jlg_render_preview', [$this, 'ajax_render_preview']);
        add_action('wp_ajax_jlg_track_event', [$this, 'ajax_track_event']);
        add_action('wp_ajax_nopriv_jlg_track_event', [$this, 'ajax_track_event']);
        add_action('wp_ajax_jlg_run_accessibility_audit', [$this, 'ajax_run_accessibility_audit']);
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
        $includeIds = [];
        if (isset($_POST['include'])) {
            $rawInclude = wp_unslash($_POST['include']);
            $includeSource = is_array($rawInclude) ? $rawInclude : explode(',', (string) $rawInclude);
            $includeIds = array_filter(array_map('absint', $includeSource));
        }

        if (count($includeIds) > self::MAX_RESULTS_PER_REQUEST) {
            wp_send_json_error(sprintf(__('Vous ne pouvez pas inclure plus de %d éléments à la fois.', 'sidebar-jlg'), self::MAX_RESULTS_PER_REQUEST));
        }

        $searchTerm = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $maxPerPage = self::MAX_RESULTS_PER_REQUEST;
        $requestedPerPage = isset($_POST['posts_per_page']) ? intval(wp_unslash($_POST['posts_per_page'])) : 20;

        if ($requestedPerPage > $maxPerPage) {
            wp_send_json_error(sprintf(__('Le paramètre posts_per_page ne peut pas dépasser %d.', 'sidebar-jlg'), $maxPerPage));
        }

        $perPage = min(max(1, $requestedPerPage), $maxPerPage);
        $allowedPostTypes = ['post', 'page'];
        $requestedPostType = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $postType = in_array($requestedPostType, $allowedPostTypes, true) ? $requestedPostType : 'post';

        $queryArgs = [
            'posts_per_page' => $perPage,
            'paged' => $page,
            'post_type' => $postType,
        ];

        if ($searchTerm !== '') {
            $queryArgs['s'] = $searchTerm;
        }

        $posts = get_posts($queryArgs);

        $optionsById = [];
        foreach ($posts as $post) {
            $optionsById[$post->ID] = [
                'id' => $post->ID,
                'title' => wp_strip_all_tags($post->post_title, true),
            ];
        }
        wp_reset_postdata();

        if (!empty($includeIds)) {
            $existingIds = array_keys($optionsById);
            $missingIds = array_diff($includeIds, $existingIds);

            if (!empty($missingIds)) {
                $limitedMissingIds = array_slice($missingIds, 0, self::MAX_RESULTS_PER_REQUEST);
                $additionalPosts = get_posts([
                    'posts_per_page' => min(count($limitedMissingIds), self::MAX_RESULTS_PER_REQUEST),
                    'post__in'       => $limitedMissingIds,
                    'orderby'        => 'post__in',
                    'post_type'      => $postType,
                ]);

                foreach ($additionalPosts as $post) {
                    $optionsById[$post->ID] = [
                        'id' => $post->ID,
                        'title' => wp_strip_all_tags($post->post_title, true),
                    ];
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
        $includeIds = [];
        if (isset($_POST['include'])) {
            $rawInclude = wp_unslash($_POST['include']);
            $includeSource = is_array($rawInclude) ? $rawInclude : explode(',', (string) $rawInclude);
            $includeIds = array_filter(array_map('absint', $includeSource));
        }

        if (count($includeIds) > self::MAX_RESULTS_PER_REQUEST) {
            wp_send_json_error(sprintf(__('Vous ne pouvez pas inclure plus de %d éléments à la fois.', 'sidebar-jlg'), self::MAX_RESULTS_PER_REQUEST));
        }

        $searchTerm = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $maxPerPage = self::MAX_RESULTS_PER_REQUEST;
        $requestedPerPage = isset($_POST['posts_per_page']) ? intval(wp_unslash($_POST['posts_per_page'])) : 20;

        if ($requestedPerPage > $maxPerPage) {
            wp_send_json_error(sprintf(__('Le paramètre posts_per_page ne peut pas dépasser %d.', 'sidebar-jlg'), $maxPerPage));
        }

        $perPage = min(max(1, $requestedPerPage), $maxPerPage);
        $offset = ($page - 1) * $perPage;

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
            $optionsById[$category->term_id] = [
                'id' => $category->term_id,
                'name' => wp_strip_all_tags($category->name, true),
            ];
        }

        if (!empty($includeIds)) {
            $existingIds = array_keys($optionsById);
            $missingIds = array_diff($includeIds, $existingIds);

            if (!empty($missingIds)) {
                $limitedMissingIds = array_slice($missingIds, 0, self::MAX_RESULTS_PER_REQUEST);
                $additionalCategories = get_categories([
                    'hide_empty' => false,
                    'include'    => $limitedMissingIds,
                    'number'     => min(count($limitedMissingIds), self::MAX_RESULTS_PER_REQUEST),
                    'orderby'    => 'include',
                ]);

                foreach ($additionalCategories as $category) {
                    $optionsById[$category->term_id] = [
                        'id' => $category->term_id,
                        'name' => wp_strip_all_tags($category->name, true),
                    ];
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

    public function ajax_get_icon_svg(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_ajax_nonce', 'nonce');

        $requested = [];

        if (isset($_POST['icons'])) {
            $rawIcons = wp_unslash($_POST['icons']);
            if (is_array($rawIcons)) {
                $requested = array_merge($requested, $rawIcons);
            } elseif (is_string($rawIcons)) {
                $requested = array_merge($requested, explode(',', $rawIcons));
            }
        }

        if (isset($_POST['icon'])) {
            $requested[] = wp_unslash($_POST['icon']);
        }

        $sanitized = array_unique(array_filter(array_map(static function ($value) {
            if (!is_string($value)) {
                return '';
            }

            return sanitize_key($value);
        }, $requested)));

        $iconCount = count($sanitized);

        if ($iconCount > self::MAX_ICONS_PER_REQUEST) {
            if (function_exists('error_log')) {
                error_log(sprintf('[Sidebar JLG] Icon SVG request rejected: %d icons requested (limit: %d).', $iconCount, self::MAX_ICONS_PER_REQUEST));
            }

            do_action('sidebar_jlg_icon_request_limit_exceeded', $iconCount, $sanitized);

            wp_send_json_error(
                sprintf(
                    __('Vous ne pouvez demander que %d icônes à la fois.', 'sidebar-jlg'),
                    self::MAX_ICONS_PER_REQUEST
                )
            );
        }

        if (empty($sanitized)) {
            wp_send_json_error(__('Aucune icône demandée.', 'sidebar-jlg'));
        }

        $available = $this->icons->getAllIcons();
        $response = [];

        foreach ($sanitized as $iconKey) {
            if (!isset($available[$iconKey])) {
                continue;
            }

            $markup = $available[$iconKey];

            if (is_string($markup) && $markup !== '') {
                $response[$iconKey] = $markup;
            }
        }

        if (empty($response)) {
            wp_send_json_error(__('Icône introuvable.', 'sidebar-jlg'));
        }

        wp_send_json_success($response);
    }

    public function ajax_upload_custom_icon(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_ajax_nonce', 'nonce');

        if (!isset($_FILES['icon_file']) || !is_array($_FILES['icon_file'])) {
            wp_send_json_error(__('Aucun fichier reçu.', 'sidebar-jlg'));
        }

        $file = $_FILES['icon_file'];
        $uploadError = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_OK;

        if ($uploadError !== UPLOAD_ERR_OK) {
            wp_send_json_error($this->describeUploadError($uploadError));
        }

        $tmpName = isset($file['tmp_name']) ? $file['tmp_name'] : '';

        if (!is_string($tmpName) || $tmpName === '' || !file_exists($tmpName) || !is_uploaded_file($tmpName)) {
            wp_send_json_error(__('Le fichier téléversé est invalide ou introuvable.', 'sidebar-jlg'));
        }

        $originalName = isset($file['name']) && is_string($file['name']) ? $file['name'] : 'icon.svg';
        $sanitizedOriginalName = sanitize_file_name($originalName);

        if ($sanitizedOriginalName === '') {
            $sanitizedOriginalName = 'icon.svg';
        }

        $fileSize = isset($file['size']) ? (int) $file['size'] : filesize($tmpName);

        if (!is_int($fileSize) || $fileSize <= 0) {
            wp_send_json_error(__('La taille du fichier n’a pas pu être déterminée.', 'sidebar-jlg'));
        }

        if ($fileSize > IconLibrary::MAX_CUSTOM_ICON_FILESIZE) {
            wp_send_json_error(
                sprintf(
                    __('Le fichier dépasse la taille maximale autorisée (%d Ko).', 'sidebar-jlg'),
                    (int) ceil(IconLibrary::MAX_CUSTOM_ICON_FILESIZE / 1024)
                )
            );
        }

        $allowedMimes = ['svg' => 'image/svg+xml'];
        $typeCheck = wp_check_filetype_and_ext($tmpName, $sanitizedOriginalName, $allowedMimes);

        if (empty($typeCheck['ext']) || $typeCheck['ext'] !== 'svg' || empty($typeCheck['type'])) {
            wp_send_json_error(__('Seuls les fichiers SVG sont autorisés.', 'sidebar-jlg'));
        }

        $rawContents = file_get_contents($tmpName);

        if (!is_string($rawContents) || $rawContents === '') {
            wp_send_json_error(__('Le fichier SVG est vide ou illisible.', 'sidebar-jlg'));
        }

        $uploadDir = wp_upload_dir();

        if (!is_array($uploadDir)) {
            wp_send_json_error(__('Le répertoire de téléversement est indisponible.', 'sidebar-jlg'));
        }

        $baseDir = isset($uploadDir['basedir']) ? (string) $uploadDir['basedir'] : '';
        $baseUrl = isset($uploadDir['baseurl']) ? (string) $uploadDir['baseurl'] : '';
        $errorValue = $uploadDir['error'] ?? null;

        $hasError = false;
        if ($errorValue !== null) {
            if (is_wp_error($errorValue)) {
                $hasError = (string) $errorValue->get_error_message() !== '';
            } elseif (is_string($errorValue) && $errorValue !== '') {
                $hasError = true;
            }
        }

        if ($baseDir === '' || $hasError) {
            wp_send_json_error(__('Impossible d’accéder au dossier des téléversements.', 'sidebar-jlg'));
        }

        $iconsDir = trailingslashit($baseDir) . 'sidebar-jlg/icons/';

        if (!wp_mkdir_p($iconsDir) || !is_dir($iconsDir) || !is_writable($iconsDir)) {
            wp_send_json_error(__('Le dossier des icônes personnalisées est inaccessible en écriture.', 'sidebar-jlg'));
        }

        $uploadsContext = $this->icons->createUploadsContextFrom($baseDir, $baseUrl);

        if ($uploadsContext === null) {
            wp_send_json_error(__('Impossible de déterminer le contexte des téléversements.', 'sidebar-jlg'));
        }

        $sanitizationFailure = null;
        $sanitizationResult = $this->icons->sanitizeSvgMarkup($rawContents, $uploadsContext, $sanitizationFailure);

        if ($sanitizationResult === null) {
            $reasonKey = 'unknown';
            $context = [];

            if (is_array($sanitizationFailure)) {
                if (!empty($sanitizationFailure['reason'])) {
                    $reasonKey = (string) $sanitizationFailure['reason'];
                }
                if (!empty($sanitizationFailure['context']) && is_array($sanitizationFailure['context'])) {
                    $context = $sanitizationFailure['context'];
                }
            }

            $message = $this->icons->getRejectionReasonMessage($reasonKey, $context);
            $message = $message === '' ? __('Le fichier SVG a été rejeté lors de la validation.', 'sidebar-jlg') : $message;

            $this->icons->recordRejectedCustomIcon($sanitizedOriginalName, $reasonKey, $context);

            wp_send_json_error($message);
        }

        $sanitizedSvg = $sanitizationResult['svg'];
        $baseName = pathinfo($sanitizedOriginalName, PATHINFO_FILENAME);
        $slug = sanitize_key($baseName);

        if ($slug === '') {
            $slug = 'icone';
        }

        $candidateSlug = $slug;
        $fileName = $candidateSlug . '.svg';
        $targetPath = $iconsDir . $fileName;
        $suffix = 1;

        while (file_exists($targetPath)) {
            $candidateSlug = $slug . '-' . $suffix;
            $fileName = $candidateSlug . '.svg';
            $targetPath = $iconsDir . $fileName;
            $suffix++;
        }

        $bytesWritten = file_put_contents($targetPath, $sanitizedSvg);

        if ($bytesWritten === false) {
            wp_send_json_error(__('Impossible d’enregistrer le SVG nettoyé.', 'sidebar-jlg'));
        }

        if (function_exists('wp_chmod_file')) {
            wp_chmod_file($targetPath);
        }

        $this->icons->clearCustomIconCache();
        $this->cache->clear();

        $manifest = $this->icons->getIconManifest();
        $iconKey = 'custom_' . $candidateSlug;
        $iconSource = $this->icons->getCustomIconSource($iconKey);

        $iconUrl = '';
        if (is_array($iconSource) && !empty($iconSource['url'])) {
            $iconUrl = (string) $iconSource['url'];
        } elseif ($baseUrl !== '') {
            $iconUrl = trailingslashit($baseUrl) . 'sidebar-jlg/icons/' . rawurlencode($fileName);
        }

        $response = [
            'icon_key' => $iconKey,
            'icon_markup' => $sanitizedSvg,
            'icon_manifest' => $manifest,
            'icon_url' => $iconUrl,
            'message' => __('Icône SVG téléversée avec succès.', 'sidebar-jlg'),
        ];

        if (!empty($sanitizationResult['modified'])) {
            $response['was_modified'] = true;
        }

        wp_send_json_success($response);
    }

    public function ajax_export_settings(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_tools_nonce', 'nonce');

        $options = $this->settings->getOptionsWithRevalidation();

        $timestamp = current_time('timestamp', true);
        if (!is_int($timestamp)) {
            $timestamp = time();
        }

        $payload = [
            'settings' => $options,
            'generated_at' => gmdate('c', $timestamp),
            'site_url' => home_url('/'),
        ];

        if (defined('SIDEBAR_JLG_VERSION')) {
            $payload['plugin_version'] = SIDEBAR_JLG_VERSION;
        }

        $fileName = sprintf('sidebar-jlg-settings-%s.json', gmdate('Ymd-His', $timestamp));

        wp_send_json_success([
            'message' => __('Export des réglages généré.', 'sidebar-jlg'),
            'file_name' => $fileName,
            'payload' => $payload,
        ]);
    }

    public function ajax_import_settings(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_tools_nonce', 'nonce');

        if (!isset($_FILES['settings_file']) || !is_array($_FILES['settings_file'])) {
            wp_send_json_error(__('Aucun fichier reçu.', 'sidebar-jlg'));
        }

        $file = $_FILES['settings_file'];
        $uploadError = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_OK;

        if ($uploadError !== UPLOAD_ERR_OK) {
            wp_send_json_error($this->describeUploadError($uploadError));
        }

        $tmpName = isset($file['tmp_name']) ? $file['tmp_name'] : '';

        if (!is_string($tmpName) || $tmpName === '' || !file_exists($tmpName) || !is_uploaded_file($tmpName)) {
            wp_send_json_error(__('Le fichier téléversé est invalide ou introuvable.', 'sidebar-jlg'));
        }

        $rawContents = file_get_contents($tmpName);

        if (!is_string($rawContents) || $rawContents === '') {
            wp_send_json_error(__('Le fichier JSON est vide ou illisible.', 'sidebar-jlg'));
        }

        $decoded = json_decode($rawContents, true);

        if (!is_array($decoded)) {
            $jsonErrorMessage = function_exists('json_last_error_msg')
                ? json_last_error_msg()
                : __('Format JSON invalide.', 'sidebar-jlg');

            wp_send_json_error(
                sprintf(
                    __('Impossible d’importer le fichier : %s', 'sidebar-jlg'),
                    $jsonErrorMessage
                )
            );
        }

        $settings = $this->extractSettingsFromImport($decoded);
        $profilesPayload = $this->extractProfilesFromImport($decoded);
        $activeProfilePayload = $this->extractActiveProfileFromImport($decoded);

        if (!is_array($settings)) {
            wp_send_json_error(__('Le fichier ne contient pas de réglages valides.', 'sidebar-jlg'));
        }

        $existingOptions = $this->settings->getOptions();

        if (!is_array($existingOptions) || $existingOptions === []) {
            $rawExistingOptions = get_option('sidebar_jlg_settings', []);
            if (is_array($rawExistingOptions) && $rawExistingOptions !== []) {
                $existingOptions = $rawExistingOptions;
            }
        }

        $sanitized = $this->sanitizer->sanitize_settings(
            $settings,
            is_array($existingOptions) ? $existingOptions : null
        );

        $sanitizedProfiles = null;

        if ($profilesPayload !== null) {
            $sanitizedProfiles = $this->sanitizer->sanitize_profiles_collection($profilesPayload);
            update_option('sidebar_jlg_profiles', $sanitizedProfiles);
        }

        if ($activeProfilePayload !== null) {
            $sanitizedActiveProfile = $this->sanitizer->sanitize_active_profile(
                $activeProfilePayload,
                '',
                $sanitizedProfiles
            );
            update_option('sidebar_jlg_active_profile', $sanitizedActiveProfile);
        }

        $this->settings->saveOptions($sanitized);
        $this->settings->revalidateStoredOptions();
        $this->cache->clear();

        if ($profilesPayload !== null || $activeProfilePayload !== null) {
            $this->cache->forgetLocaleIndex();
        }

        wp_send_json_success([
            'message' => __('Réglages importés avec succès.', 'sidebar-jlg'),
        ]);
    }

    public function ajax_run_accessibility_audit(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => __('Permission refusée.', 'sidebar-jlg')]);
        }

        check_ajax_referer('jlg_accessibility_audit', 'nonce');

        $targetUrl = '';
        if (isset($_POST['target_url'])) {
            $targetUrl = esc_url_raw(wp_unslash($_POST['target_url']));
        }

        if ($targetUrl === '') {
            wp_send_json_error(['message' => __('Veuillez saisir une URL à analyser.', 'sidebar-jlg')]);
        }

        $result = $this->auditRunner->run($targetUrl);

        if (empty($result['success'])) {
            $payload = [
                'message' => isset($result['message']) && is_string($result['message'])
                    ? $result['message']
                    : __('L’audit d’accessibilité a échoué.', 'sidebar-jlg'),
            ];

            if (!empty($result['log']) && is_string($result['log'])) {
                $payload['log'] = $result['log'];
            }

            if (isset($result['exit_code'])) {
                $payload['exit_code'] = (int) $result['exit_code'];
            }

            wp_send_json_error($payload);
        }

        $summary = isset($result['summary']) && is_array($result['summary']) ? $result['summary'] : [];
        $issues = isset($result['issues']) && is_array($result['issues']) ? $result['issues'] : [];
        $meta = isset($result['meta']) && is_array($result['meta']) ? $result['meta'] : [];

        $storedSummary = [];
        foreach ($summary as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $storedSummary[$key] = (int) $value;
        }

        $storedMeta = [
            'document_title' => isset($meta['document_title']) && is_string($meta['document_title'])
                ? sanitize_text_field($meta['document_title'])
                : '',
            'page_url' => isset($meta['page_url']) && is_string($meta['page_url'])
                ? esc_url_raw($meta['page_url'])
                : '',
            'execution_time_ms' => isset($meta['execution_time_ms'])
                ? max(0, (int) $meta['execution_time_ms'])
                : 0,
            'binary' => isset($meta['binary']) && is_string($meta['binary'])
                ? sanitize_text_field($meta['binary'])
                : '',
        ];

        $timestamp = current_time('timestamp');
        $stored = [
            'timestamp' => $timestamp,
            'site_time' => current_time('mysql'),
            'target_url' => esc_url_raw($targetUrl),
            'summary' => $storedSummary,
            'issues_count' => count($issues),
            'meta' => $storedMeta,
            'iso_time' => gmdate('c', time()),
        ];

        update_option('sidebar_jlg_accessibility_audit_last_run', $stored);

        $response = [
            'summary' => $summary,
            'issues' => $issues,
            'meta' => $meta,
            'last_run' => $this->formatAccessibilityAuditMetadata($stored),
        ];

        if (!empty($result['log']) && is_string($result['log'])) {
            $response['log'] = $result['log'];
        }

        wp_send_json_success($response);
    }

    public function ajax_render_preview(): void
    {
        $capability = $this->get_ajax_capability();

        if (!current_user_can($capability)) {
            wp_send_json_error(__('Permission refusée.', 'sidebar-jlg'));
        }

        check_ajax_referer('jlg_preview_nonce', 'nonce');

        $rawOptions = $_POST['options'] ?? [];

        if (is_string($rawOptions)) {
            $decoded = json_decode(wp_unslash($rawOptions), true);
        } elseif (is_array($rawOptions)) {
            $decoded = $rawOptions;
        } else {
            $decoded = [];
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $sanitized = $this->sanitizer->sanitize_settings($decoded);
        if (!is_array($sanitized)) {
            $sanitized = [];
        }

        $defaults = $this->settings->getDefaultSettings();
        $options = wp_parse_args($sanitized, $defaults);
        $html = $this->renderer->renderSidebarToHtml($options);

        if (!is_string($html)) {
            wp_send_json_error([
                'message' => __('La génération de l’aperçu a échoué.', 'sidebar-jlg'),
            ]);
        }

        $profileKey = isset($options['active_profile']) ? sanitize_key((string) $options['active_profile']) : 'default';
        if ($profileKey === '') {
            $profileKey = 'default';
        }

        $layoutStyle = isset($options['layout_style']) ? sanitize_key((string) $options['layout_style']) : 'full';
        if ($layoutStyle === '') {
            $layoutStyle = 'full';
        }

        $position = isset($options['sidebar_position']) ? sanitize_key((string) $options['sidebar_position']) : 'left';
        if ($position !== 'right') {
            $position = 'left';
        }

        $html = sprintf(
            '<div class="sidebar-jlg" data-sidebar-profile="%s" data-sidebar-layout="%s" data-sidebar-position="%s">%s</div>',
            esc_attr($profileKey),
            esc_attr($layoutStyle),
            esc_attr($position),
            $html
        );

        wp_send_json_success([
            'html' => $html,
            'options' => $options,
        ]);
    }

    public function ajax_track_event(): void
    {
        check_ajax_referer('jlg_track_event', 'nonce');

        $options = $this->settings->getOptions();
        if (empty($options['enable_analytics'])) {
            wp_send_json_error([
                'message' => __('La collecte des métriques est désactivée.', 'sidebar-jlg'),
            ]);
        }

        $rawEvent = isset($_POST['event_type']) ? wp_unslash($_POST['event_type']) : '';
        $eventType = is_string($rawEvent) ? sanitize_key($rawEvent) : '';
        $allowedEvents = $this->analytics->getSupportedEvents();

        if ($eventType === '' || !in_array($eventType, $allowedEvents, true)) {
            wp_send_json_error([
                'message' => __('Type d’événement invalide.', 'sidebar-jlg'),
            ]);
        }

        $rawProfileId = isset($_POST['profile_id']) ? wp_unslash($_POST['profile_id']) : '';
        $profileId = is_string($rawProfileId) ? sanitize_key($rawProfileId) : '';
        if ($profileId === '') {
            $profileId = 'default';
        }

        $contextPayload = $this->decodeAnalyticsContext($_POST['context'] ?? null);

        $recordContext = [
            'profile_id' => $profileId,
            'profile_label' => $this->resolveProfileLabel($profileId),
            'is_fallback_profile' => $this->isFallbackProfile($profileId),
        ];

        if (isset($contextPayload['target']) && is_string($contextPayload['target'])) {
            $recordContext['target'] = $contextPayload['target'];
        }

        if (isset($contextPayload['duration_ms']) && is_numeric($contextPayload['duration_ms'])) {
            $recordContext['duration_ms'] = (int) round((float) $contextPayload['duration_ms']);
        } elseif (isset($contextPayload['duration']) && is_numeric($contextPayload['duration'])) {
            $recordContext['duration_ms'] = (int) round((float) $contextPayload['duration']);
        }

        if (isset($contextPayload['close_reason']) && is_string($contextPayload['close_reason'])) {
            $recordContext['close_reason'] = $contextPayload['close_reason'];
        } elseif (isset($contextPayload['closeReason']) && is_string($contextPayload['closeReason'])) {
            $recordContext['close_reason'] = $contextPayload['closeReason'];
        }

        if (isset($contextPayload['interactions']) && is_array($contextPayload['interactions'])) {
            $recordContext['interactions'] = $contextPayload['interactions'];
        }

        $this->analyticsQueue->enqueue($eventType, $recordContext);
        $summary = $this->analytics->getSummary();

        wp_send_json_success([
            'message' => __('Événement enregistré.', 'sidebar-jlg'),
            'summary' => $summary,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function formatAccessibilityAuditMetadata(array $data): array
    {
        $timestamp = isset($data['timestamp']) ? (int) $data['timestamp'] : 0;
        $siteNow = current_time('timestamp');
        $dateFormat = (string) get_option('date_format');
        $timeFormat = (string) get_option('time_format');

        $readable = '';
        $relative = '';
        if ($timestamp > 0) {
            $format = trim($dateFormat . ' ' . $timeFormat);
            if ($format === '') {
                $format = 'Y-m-d H:i';
            }

            $readable = wp_date($format, $timestamp);
            $relative = human_time_diff($timestamp, $siteNow);
        }

        $summary = [];
        if (isset($data['summary']) && is_array($data['summary'])) {
            foreach ($data['summary'] as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $summary[$key] = (int) $value;
            }
        }

        $issuesCount = isset($data['issues_count']) ? max(0, (int) $data['issues_count']) : 0;

        $summaryText = '';
        if (!empty($summary)) {
            $summaryText = sprintf(
                /* translators: 1: number of errors, 2: number of warnings, 3: number of notices. */
                __('%1$d erreur(s), %2$d avertissement(s), %3$d notice(s).', 'sidebar-jlg'),
                $summary['error'] ?? 0,
                $summary['warning'] ?? 0,
                $summary['notice'] ?? 0
            );
        }

        $targetUrl = isset($data['target_url']) && is_string($data['target_url'])
            ? esc_url_raw($data['target_url'])
            : '';

        $targetLabel = '';
        if ($targetUrl !== '') {
            $targetLabel = sprintf(
                /* translators: %s: audited URL. */
                __('URL analysée : %s', 'sidebar-jlg'),
                $targetUrl
            );
        }

        $meta = [];
        if (isset($data['meta']) && is_array($data['meta'])) {
            $meta['document_title'] = isset($data['meta']['document_title']) && is_string($data['meta']['document_title'])
                ? sanitize_text_field($data['meta']['document_title'])
                : '';
            $meta['page_url'] = isset($data['meta']['page_url']) && is_string($data['meta']['page_url'])
                ? esc_url_raw($data['meta']['page_url'])
                : '';
            $meta['execution_time_ms'] = isset($data['meta']['execution_time_ms'])
                ? max(0, (int) $data['meta']['execution_time_ms'])
                : 0;
            $meta['binary'] = isset($data['meta']['binary']) && is_string($data['meta']['binary'])
                ? sanitize_text_field($data['meta']['binary'])
                : '';
        }

        $isStale = true;
        if ($timestamp > 0) {
            $isStale = ($siteNow - $timestamp) >= (30 * DAY_IN_SECONDS);
        }

        return [
            'timestamp' => $timestamp,
            'site_time' => isset($data['site_time']) && is_string($data['site_time']) ? $data['site_time'] : '',
            'iso_time' => isset($data['iso_time']) && is_string($data['iso_time']) ? $data['iso_time'] : '',
            'target_url' => $targetUrl,
            'target_label' => $targetLabel,
            'summary' => $summary,
            'summary_text' => $summaryText,
            'issues_count' => $issuesCount,
            'meta' => $meta,
            'relative' => $relative,
            'readable' => $readable,
            'is_stale' => $isStale,
            'has_run' => $timestamp > 0,
        ];
    }

    private function extractSettingsFromImport(array $decoded): array
    {
        if (isset($decoded['settings']) && is_array($decoded['settings'])) {
            return $decoded['settings'];
        }

        if (isset($decoded['sidebar_jlg_settings']) && is_array($decoded['sidebar_jlg_settings'])) {
            return $decoded['sidebar_jlg_settings'];
        }

        return $decoded;
    }

    private function extractProfilesFromImport(array $decoded): ?array
    {
        if (isset($decoded['profiles']) && is_array($decoded['profiles'])) {
            return $decoded['profiles'];
        }

        if (isset($decoded['sidebar_jlg_profiles']) && is_array($decoded['sidebar_jlg_profiles'])) {
            return $decoded['sidebar_jlg_profiles'];
        }

        return null;
    }

    /**
     * @return mixed
     */
    private function extractActiveProfileFromImport(array $decoded)
    {
        if (array_key_exists('active_profile', $decoded)) {
            return $decoded['active_profile'];
        }

        if (array_key_exists('sidebar_jlg_active_profile', $decoded)) {
            return $decoded['sidebar_jlg_active_profile'];
        }

        return null;
    }

    private function describeUploadError(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf(
                    __('Le fichier dépasse la taille maximale autorisée (%d Ko).', 'sidebar-jlg'),
                    (int) ceil(IconLibrary::MAX_CUSTOM_ICON_FILESIZE / 1024)
                );
            case UPLOAD_ERR_PARTIAL:
                return __('Le fichier n’a été que partiellement téléversé.', 'sidebar-jlg');
            case UPLOAD_ERR_NO_FILE:
                return __('Aucun fichier n’a été téléversé.', 'sidebar-jlg');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Le dossier temporaire est manquant.', 'sidebar-jlg');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Écriture du fichier impossible sur le disque.', 'sidebar-jlg');
            case UPLOAD_ERR_EXTENSION:
                return __('Le téléversement a été interrompu par une extension PHP.', 'sidebar-jlg');
        }

        return __('Le téléversement du fichier a échoué.', 'sidebar-jlg');
    }

    private function get_ajax_capability(): string
    {
        return apply_filters('sidebar_jlg_ajax_capability', 'manage_options');
    }

    /**
     * @param mixed $rawContext
     *
     * @return array<string, mixed>
     */
    private function decodeAnalyticsContext($rawContext): array
    {
        if (is_array($rawContext)) {
            return $rawContext;
        }

        if (is_string($rawContext)) {
            $decoded = json_decode(wp_unslash($rawContext), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function resolveProfileLabel(string $profileId): string
    {
        if ($profileId === '' || $profileId === 'default') {
            return __('Réglages globaux', 'sidebar-jlg');
        }

        $profiles = $this->settings->getProfiles();
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $candidateId = isset($profile['id']) ? sanitize_key((string) $profile['id']) : '';
            if ($candidateId !== $profileId) {
                continue;
            }

            foreach (['title', 'label', 'name'] as $labelKey) {
                if (isset($profile[$labelKey]) && is_string($profile[$labelKey]) && $profile[$labelKey] !== '') {
                    return sanitize_text_field($profile[$labelKey]);
                }
            }

            break;
        }

        return '';
    }

    private function isFallbackProfile(string $profileId): bool
    {
        if ($profileId === '' || $profileId === 'default') {
            return true;
        }

        $profiles = $this->settings->getProfiles();
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $candidateId = isset($profile['id']) ? sanitize_key((string) $profile['id']) : '';
            if ($candidateId !== $profileId) {
                continue;
            }

            foreach (['is_fallback', 'fallback', 'default', 'is_default'] as $flag) {
                if (!empty($profile[$flag])) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
}
