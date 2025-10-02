<?php

namespace JLG\Sidebar\Ajax;

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;
use function __;
use function current_time;
use function gmdate;
use function home_url;
use function is_readable;
use function json_decode;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function plugin_dir_path;
use function sanitize_option;
use function update_option;
use function time;
use function wp_parse_args;
use function wp_unslash;

class Endpoints
{
    private const MAX_ICONS_PER_REQUEST = 20;
    private const MAX_RESULTS_PER_REQUEST = 100;

    private SettingsRepository $settings;
    private MenuCache $cache;
    private IconLibrary $icons;
    private SettingsSanitizer $sanitizer;
    private string $pluginFile;

    public function __construct(
        SettingsRepository $settings,
        MenuCache $cache,
        IconLibrary $icons,
        SettingsSanitizer $sanitizer,
        string $pluginFile
    )
    {
        $this->settings = $settings;
        $this->cache = $cache;
        $this->icons = $icons;
        $this->sanitizer = $sanitizer;
        $this->pluginFile = $pluginFile;
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

        $sanitized = sanitize_option('sidebar_jlg_settings', $settings);

        if (!is_array($sanitized)) {
            $sanitized = [];
        }

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
        $allIcons = $this->icons->getAllIcons();

        $templatePath = plugin_dir_path($this->pluginFile) . 'includes/sidebar-template.php';

        if (!is_readable($templatePath)) {
            wp_send_json_error([
                'message' => __('Le template d’aperçu est introuvable.', 'sidebar-jlg'),
            ]);
        }

        $bufferStarted = ob_start();
        if ($bufferStarted === false) {
            wp_send_json_error([
                'message' => __('Impossible de générer l’aperçu.', 'sidebar-jlg'),
            ]);
        }

        $bufferLevel = ob_get_level();

        $optionsForTemplate = $options;
        $allIconsForTemplate = $allIcons;
        $options = $optionsForTemplate;
        $allIcons = $allIconsForTemplate;

        require $templatePath;

        $html = ob_get_clean();

        if ($html === false || $html === null) {
            if (ob_get_level() >= $bufferLevel) {
                ob_end_clean();
            }

            wp_send_json_error([
                'message' => __('La génération de l’aperçu a échoué.', 'sidebar-jlg'),
            ]);
        }

        wp_send_json_success([
            'html' => $html,
            'options' => $options,
        ]);
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
}
