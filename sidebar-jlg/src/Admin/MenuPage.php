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
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-util', 'wp-data', 'wp-api-fetch'],
            $this->version,
            true
        );

        $defaults = $this->settings->getDefaultSettings();
        $options = $this->settings->getOptionsWithRevalidation();

        wp_localize_script('sidebar-jlg-admin-js', 'sidebarJLG', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jlg_ajax_nonce'),
            'reset_nonce' => wp_create_nonce('jlg_reset_nonce'),
            'tools_nonce' => wp_create_nonce('jlg_tools_nonce'),
            'preview_nonce' => wp_create_nonce('jlg_preview_nonce'),
            'options' => wp_parse_args($options, $defaults),
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
            ],
            'preview_messages' => [
                'loading' => __('Chargement de l’aperçu…', 'sidebar-jlg'),
                'error' => __('Impossible de charger l’aperçu. Vérifiez vos droits ou votre connexion réseau.', 'sidebar-jlg'),
                'emptyMenu' => __('Ajoutez des éléments de menu pour alimenter la prévisualisation.', 'sidebar-jlg'),
                'refresh' => __('Actualiser l’aperçu', 'sidebar-jlg'),
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
}
