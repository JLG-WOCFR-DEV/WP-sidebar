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

        wp_enqueue_script(
            'sidebar-jlg-admin-js',
            plugin_dir_url($this->pluginFile) . 'assets/js/admin-script.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-util'],
            $this->version,
            true
        );

        $options = get_option('sidebar_jlg_settings', $this->settings->getDefaultSettings());
        wp_localize_script('sidebar-jlg-admin-js', 'sidebarJLG', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jlg_ajax_nonce'),
            'reset_nonce' => wp_create_nonce('jlg_reset_nonce'),
            'options' => wp_parse_args($options, $this->settings->getDefaultSettings()),
            'all_icons' => $this->icons->getAllIcons(),
        ]);
    }

    public function render(): void
    {
        $colorPicker = $this->colorPicker;
        $defaults = $this->settings->getDefaultSettings();
        $optionsFromDb = get_option('sidebar_jlg_settings');
        $options = wp_parse_args($optionsFromDb, $defaults);
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
