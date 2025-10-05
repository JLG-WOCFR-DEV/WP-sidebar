<?php

namespace JLG\Sidebar\Admin;

use JLG\Sidebar\Admin\View\ColorPickerField;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;

class MenuPage
{
    private SettingsRepository $settings;
    private SettingsSanitizer $sanitizer;
    private IconLibrary $icons;
    private ColorPickerField $colorPicker;
    private string $pluginFile;
    private string $version;

    public function __construct(
        SettingsRepository $settings,
        SettingsSanitizer $sanitizer,
        IconLibrary $icons,
        ColorPickerField $colorPicker,
        string $pluginFile,
        string $version
    ) {
        $this->settings = $settings;
        $this->sanitizer = $sanitizer;
        $this->icons = $icons;
        $this->colorPicker = $colorPicker;
        $this->pluginFile = $pluginFile;
        $this->version = $version;
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'renderCustomIconNotice']);
    }

    public function addAdminMenu(): void
    {
        add_menu_page(
            __('Sidebar JLG Settings', 'sidebar-jlg'),
            __('Sidebar JLG', 'sidebar-jlg'),
            'manage_options',
            'sidebar-jlg',
            [$this, 'render'],
            'dashicons-slides',
            100
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_settings',
            [$this->sanitizer, 'sanitize_settings']
        );

        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_profiles',
            [
                'type' => 'array',
                'sanitize_callback' => [$this->sanitizer, 'sanitize_profiles'],
                'default' => [],
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ]
        );

        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_active_profile',
            [
                'type' => 'string',
                'sanitize_callback' => [$this->sanitizer, 'sanitize_active_profile'],
                'default' => '',
                'show_in_rest' => true,
            ]
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ('toplevel_page_sidebar-jlg' !== $hook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_style(
            'sidebar-jlg-admin-css',
            plugin_dir_url($this->pluginFile) . 'assets/css/admin-style.css',
            [],
            $this->version
        );

        wp_enqueue_style(
            'sidebar-jlg-admin-preview-css',
            plugin_dir_url($this->pluginFile) . 'assets/css/admin-preview.css',
            ['sidebar-jlg-admin-css'],
            $this->version
        );

        wp_enqueue_script(
            'sidebar-jlg-admin-js',
            plugin_dir_url($this->pluginFile) . 'assets/js/admin-script.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-util', 'wp-data', 'wp-api-fetch', 'wp-element', 'wp-components'],
            $this->version,
            true
        );

        $defaults = $this->settings->getDefaultSettings();
        $options = $this->settings->getOptionsWithRevalidation();
        $rawProfiles = get_option('sidebar_jlg_profiles', []);
        $profiles = $this->sanitizer->sanitize_profiles_collection($rawProfiles);
        $activeProfile = get_option('sidebar_jlg_active_profile', '');
        $activeProfile = $this->sanitizer->sanitize_active_profile($activeProfile, 'sidebar_jlg_active_profile', $profiles);

        $profileChoices = [
            'post_types' => $this->getProfilePostTypeChoices(),
            'taxonomies' => $this->getProfileTaxonomyChoices(),
            'roles' => $this->getProfileRoleChoices(),
            'languages' => $this->getProfileLanguageChoices(),
            'devices' => $this->getProfileDeviceChoices(),
            'login_states' => $this->getProfileLoginStateChoices(),
            'schedule_days' => $this->getProfileScheduleDayChoices(),
        ];

        wp_localize_script('sidebar-jlg-admin-js', 'sidebarJLG', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jlg_ajax_nonce'),
            'reset_nonce' => wp_create_nonce('jlg_reset_nonce'),
            'tools_nonce' => wp_create_nonce('jlg_tools_nonce'),
            'preview_nonce' => wp_create_nonce('jlg_preview_nonce'),
            'options' => wp_parse_args($options, $defaults),
            'profiles' => $profiles,
            'active_profile' => $activeProfile,
            'profiles_nonce' => wp_create_nonce('sidebar_jlg_profiles'),
            'profile_choices' => $profileChoices,
            'icons_manifest' => $this->icons->getIconManifest(),
            'icon_fetch_action' => 'jlg_get_icon_svg',
            'icon_upload_action' => 'jlg_upload_custom_icon',
            'icon_upload_max_size' => IconLibrary::MAX_CUSTOM_ICON_FILESIZE,
            'preview_action' => 'jlg_render_preview',
            'svg_url_restrictions' => $this->sanitizer->getSvgUrlRestrictions(),
            'i18n' => [
                'menuItemDefaultTitle' => __('Nouvel élément', 'sidebar-jlg'),
                'socialIconDefaultTitle' => __('Nouvelle icône', 'sidebar-jlg'),
                'svgUrlOutOfScopeWithDescription' => __('Cette URL ne sera pas enregistrée. Utilisez une adresse dans %s.', 'sidebar-jlg'),
                'svgUrlOutOfScope' => __('Cette URL ne sera pas enregistrée car elle est en dehors de la zone autorisée.', 'sidebar-jlg'),
                'invalidUrl' => __('URL invalide.', 'sidebar-jlg'),
                'httpOnly' => __('Seuls les liens HTTP(S) sont autorisés.', 'sidebar-jlg'),
                'iconPreviewAlt' => __('Aperçu', 'sidebar-jlg'),
                'iconUploadMediaTitle' => __('Sélectionner un fichier SVG', 'sidebar-jlg'),
                'iconUploadMediaButton' => __('Utiliser ce SVG', 'sidebar-jlg'),
                'iconUploadInProgress' => __('Téléversement du SVG en cours…', 'sidebar-jlg'),
                'iconUploadPreparing' => __('Préparation du fichier…', 'sidebar-jlg'),
                'iconUploadSuccess' => __('Icône SVG ajoutée.', 'sidebar-jlg'),
                'iconUploadErrorGeneric' => __('Le téléversement du SVG a échoué.', 'sidebar-jlg'),
                'iconUploadErrorMime' => __('Seuls les fichiers SVG sont acceptés.', 'sidebar-jlg'),
                'iconUploadErrorSize' => __('Le fichier dépasse la taille maximale autorisée de %d Ko.', 'sidebar-jlg'),
                'iconUploadErrorFetch' => __('Impossible de récupérer le fichier depuis la médiathèque.', 'sidebar-jlg'),
                'dismissNotice' => __('Ignorer cette notification.', 'sidebar-jlg'),
                'exportConfirm' => __('Voulez-vous exporter les réglages actuels ?', 'sidebar-jlg'),
                'exportInProgress' => __('Export en cours…', 'sidebar-jlg'),
                'exportSuccess' => __('Export terminé. Le téléchargement va démarrer.', 'sidebar-jlg'),
                'exportError' => __('Impossible de générer l’export.', 'sidebar-jlg'),
                'importConfirm' => __('Importer ces réglages écrasera la configuration actuelle. Continuer ?', 'sidebar-jlg'),
                'importInProgress' => __('Import en cours…', 'sidebar-jlg'),
                'importSuccess' => __('Réglages importés avec succès. Rechargement de la page…', 'sidebar-jlg'),
                'importError' => __('L’import des réglages a échoué.', 'sidebar-jlg'),
                'importMissingFile' => __('Veuillez sélectionner un fichier JSON avant de lancer l’import.', 'sidebar-jlg'),
                'navMenuFieldLabel' => __('Menu WordPress', 'sidebar-jlg'),
                'navMenuSelectPlaceholder' => __('Sélectionnez un menu…', 'sidebar-jlg'),
                'navMenuDepthLabel' => __('Profondeur maximale', 'sidebar-jlg'),
                'navMenuDepthHelp' => __('0 = illimité', 'sidebar-jlg'),
                'navMenuFilterLabel' => __('Filtrage', 'sidebar-jlg'),
                'navMenuFilterAll' => __('Tous les éléments', 'sidebar-jlg'),
                'navMenuFilterTopLevel' => __('Uniquement le niveau 1', 'sidebar-jlg'),
                'navMenuFilterBranch' => __('Branche de la page courante', 'sidebar-jlg'),
                'profilesDefaultTitle' => __('Nouveau profil', 'sidebar-jlg'),
                'profilesListEmpty' => __('Aucun profil n’a encore été créé.', 'sidebar-jlg'),
                'profilesActionsLabel' => __('Actions sur les profils', 'sidebar-jlg'),
                'profilesActiveLabel' => __('Profil actif', 'sidebar-jlg'),
                'profilesDeleteConfirm' => __('Supprimer ce profil ?', 'sidebar-jlg'),
                'profilesSettingsEmpty' => __('Aucun réglage personnalisé n’est défini pour ce profil.', 'sidebar-jlg'),
                'profilesSettingsSummary' => __('Réglages personnalisés : %d champ(s).', 'sidebar-jlg'),
                'profilesCloneSuccess' => __('Les réglages actuels ont été associés au profil.', 'sidebar-jlg'),
                'profilesCloneError' => __('Impossible de copier les réglages actuels.', 'sidebar-jlg'),
                'profilesTaxonomyTermsPlaceholder' => __('Slugs ou IDs séparés par des virgules', 'sidebar-jlg'),
                'profilesConditionsDescription' => __('Définissez les règles qui activent ce profil.', 'sidebar-jlg'),
                'profilesInactiveBadge' => __('Profil désactivé', 'sidebar-jlg'),
                'profilesUseCurrentSettings' => __('Utiliser les réglages actuels', 'sidebar-jlg'),
                'profilesClearSettings' => __('Réinitialiser les réglages du profil', 'sidebar-jlg'),
                'profilesClearActive' => __('Ne sélectionner aucun profil actif', 'sidebar-jlg'),
                'profilesDefaultActiveLabel' => __('Réglages globaux', 'sidebar-jlg'),
                'profilesDeleteLabel' => __('Supprimer', 'sidebar-jlg'),
            ],
            'preview_messages' => [
                'loading' => __('Chargement de l’aperçu…', 'sidebar-jlg'),
                'error' => __('Impossible de charger l’aperçu. Vérifiez vos droits ou votre connexion réseau.', 'sidebar-jlg'),
                'emptyMenu' => __('Ajoutez des éléments de menu pour alimenter la prévisualisation.', 'sidebar-jlg'),
                'refresh' => __('Actualiser l’aperçu', 'sidebar-jlg'),
                'activeProfile' => __('Profil actif : %s', 'sidebar-jlg'),
            ],
        ]);
    }

    public function render(): void
    {
        $colorPicker = $this->colorPicker;
        $defaults = $this->settings->getDefaultSettings();
        $options = $this->settings->getOptionsWithRevalidation();
        $allIcons = $this->icons->getAllIcons();

        require plugin_dir_path($this->pluginFile) . 'includes/admin-page.php';
    }

    public function renderCustomIconNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $rejected = $this->icons->consumeRejectedCustomIcons();
        if (empty($rejected)) {
            return;
        }

        $message = sprintf(
            /* translators: %s: comma-separated list of SVG filenames. */
            __('Sidebar JLG: the following SVG files were ignored: %s.', 'sidebar-jlg'),
            implode(', ', array_map('sanitize_text_field', $rejected))
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfilePostTypeChoices(): array
    {
        if (!function_exists('get_post_types')) {
            return [
                ['value' => 'post', 'label' => __('Article', 'sidebar-jlg')],
                ['value' => 'page', 'label' => __('Page', 'sidebar-jlg')],
            ];
        }

        $objects = get_post_types(['public' => true], 'objects');
        if (!is_array($objects)) {
            $objects = [];
        }

        $choices = [];
        foreach ($objects as $object) {
            if (!is_object($object)) {
                continue;
            }

            $name = isset($object->name) ? sanitize_key((string) $object->name) : '';
            if ($name === '') {
                continue;
            }

            $label = '';
            if (isset($object->labels->singular_name) && is_string($object->labels->singular_name)) {
                $label = $object->labels->singular_name;
            } elseif (isset($object->label) && is_string($object->label)) {
                $label = $object->label;
            } else {
                $label = ucfirst($name);
            }

            $choices[$name] = [
                'value' => $name,
                'label' => $label,
            ];
        }

        if (!isset($choices['post'])) {
            $choices['post'] = ['value' => 'post', 'label' => __('Article', 'sidebar-jlg')];
        }

        if (!isset($choices['page'])) {
            $choices['page'] = ['value' => 'page', 'label' => __('Page', 'sidebar-jlg')];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileTaxonomyChoices(): array
    {
        if (!function_exists('get_taxonomies')) {
            return [];
        }

        $objects = get_taxonomies(['public' => true], 'objects');
        if (!is_array($objects)) {
            $objects = [];
        }

        $choices = [];
        foreach ($objects as $object) {
            if (!is_object($object)) {
                continue;
            }

            $name = isset($object->name) ? sanitize_key((string) $object->name) : '';
            if ($name === '') {
                continue;
            }

            $label = '';
            if (isset($object->labels->singular_name) && is_string($object->labels->singular_name)) {
                $label = $object->labels->singular_name;
            } elseif (isset($object->label) && is_string($object->label)) {
                $label = $object->label;
            } else {
                $label = ucfirst($name);
            }

            $choices[$name] = [
                'value' => $name,
                'label' => $label,
            ];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileRoleChoices(): array
    {
        if (!function_exists('get_editable_roles')) {
            return [];
        }

        $roles = get_editable_roles();
        if (!is_array($roles)) {
            $roles = [];
        }

        $choices = [];
        foreach ($roles as $slug => $role) {
            $roleSlug = sanitize_key((string) $slug);
            if ($roleSlug === '') {
                continue;
            }

            $label = '';
            if (isset($role['name']) && is_string($role['name'])) {
                $label = $role['name'];
            } else {
                $label = ucfirst($roleSlug);
            }

            $choices[$roleSlug] = [
                'value' => $roleSlug,
                'label' => $label,
            ];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileLanguageChoices(): array
    {
        if (!function_exists('get_available_languages')) {
            return [];
        }

        $languages = get_available_languages();
        if (!is_array($languages)) {
            $languages = [];
        }

        $choices = [];
        foreach ($languages as $language) {
            $code = is_string($language) ? trim($language) : '';
            if ($code === '') {
                continue;
            }

            $label = $code;
            if (function_exists('locale_get_display_language')) {
                $display = locale_get_display_language($code, get_locale());
                if (is_string($display) && $display !== '') {
                    $label = sprintf('%s (%s)', $display, $code);
                }
            }

            $choices[$code] = [
                'value' => $code,
                'label' => $label,
            ];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileDeviceChoices(): array
    {
        return [
            ['value' => 'desktop', 'label' => __('Ordinateur (bureau)', 'sidebar-jlg')],
            ['value' => 'mobile', 'label' => __('Mobile', 'sidebar-jlg')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileLoginStateChoices(): array
    {
        return [
            ['value' => 'any', 'label' => __('Tous les visiteurs', 'sidebar-jlg')],
            ['value' => 'logged-in', 'label' => __('Utilisateurs connectés', 'sidebar-jlg')],
            ['value' => 'logged-out', 'label' => __('Visiteurs non connectés', 'sidebar-jlg')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileScheduleDayChoices(): array
    {
        return [
            ['value' => 'mon', 'label' => __('Lundi', 'sidebar-jlg')],
            ['value' => 'tue', 'label' => __('Mardi', 'sidebar-jlg')],
            ['value' => 'wed', 'label' => __('Mercredi', 'sidebar-jlg')],
            ['value' => 'thu', 'label' => __('Jeudi', 'sidebar-jlg')],
            ['value' => 'fri', 'label' => __('Vendredi', 'sidebar-jlg')],
            ['value' => 'sat', 'label' => __('Samedi', 'sidebar-jlg')],
            ['value' => 'sun', 'label' => __('Dimanche', 'sidebar-jlg')],
        ];
    }
}
