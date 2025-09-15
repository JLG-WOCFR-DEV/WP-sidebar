<?php
/**
 * Plugin Name:       Sidebar - JLG
 * Description:       Une sidebar professionnelle, animée et entièrement personnalisable pour votre site WordPress.
 * Version:           4.9.2
 * Author:            Jérôme Le Gousse
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidebar-jlg
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Définir la version du plugin
if ( ! defined( 'SIDEBAR_JLG_VERSION' ) ) {
    define( 'SIDEBAR_JLG_VERSION', '4.9.2' );
}

// Créer le dossier d'icônes personnalisées à l'activation
register_activation_hook(__FILE__, function() {
    $icons_dir = wp_upload_dir()['basedir'] . '/sidebar-jlg/icons/';
    if (!is_dir($icons_dir)) {
        wp_mkdir_p($icons_dir);
    }
});

class Sidebar_JLG {

    private static $instance;
    private $options;
    private $all_icons = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $db_options = get_option('sidebar_jlg_settings');
        $this->options = wp_parse_args($db_options, $this->get_default_settings());

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        if ( ! empty( $this->options['enable_sidebar'] ) ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
            add_action( 'wp_footer', [ $this, 'render_sidebar_html' ] );
            add_filter( 'body_class', [ $this, 'add_body_classes' ] );
        }
        
        add_action( 'wp_ajax_jlg_get_posts', [ $this, 'ajax_get_posts' ] );
        add_action( 'wp_ajax_jlg_get_categories', [ $this, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_jlg_reset_settings', [ $this, 'ajax_reset_settings' ] );
        
        add_action( 'update_option_sidebar_jlg_settings', [ $this, 'clear_menu_cache' ], 10, 2 );
    }

    public function get_all_available_icons() {
        if ($this->all_icons === null) {
            $standard_icons = $this->get_standard_icons();
            $custom_icons = $this->get_custom_icons();
            $this->all_icons = array_merge($standard_icons, $custom_icons);
        }
        return $this->all_icons;
    }

    private function get_standard_icons() {
        return [
            'home_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'user_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'settings_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 0 2.4l-.15.08a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1 0-2.4l.15-.08a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
            'search_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
            'close_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            'youtube_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M10 15l5.19-3L10 9v6zm11.56-7.36c-.77-2.63-2.63-4.5-5.28-5.28C14.53 2 12 2 12 2s-2.53 0-4.28.36c-2.65.78-4.5 2.65-5.28 5.28C2 9.47 2 12 2 12s0 2.53.36 4.28c.78 2.63 2.63 4.5 5.28 5.28C9.47 22 12 22 12 22s2.53 0 4.28-.36c2.65-.78 4.5-2.65 5.28-5.28C22 14.53 22 12 22 12s0-2.53-.44-4.28z"/></svg>',
            'facebook_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18.77,0H5.23C2.344,0,0,2.344,0,5.23v13.54C0,21.656,2.344,24,5.23,24h7.292v-9.094H9.698V11.06h2.824V8.51c0-2.791,1.707-4.312,4.199-4.312c1.198,0,2.229,0.089,2.529,0.129v3.424h-2.021c-1.354,0-1.617,0.643-1.617,1.589v2.084h3.79l-0.493,3.846h-3.297V24h3.875C21.656,24,24,21.656,24,18.77V5.23C24,2.344,21.656,0,18.77,0z"/></svg>',
            'instagram_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.011 3.585-.069 4.85c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.585-.012-4.85-.07c-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.069-1.645-.069-4.85s.011-3.585.069-4.85c.149-3.225 1.664-4.771 4.919-4.919C8.415 2.175 8.796 2.163 12 2.163zm0 1.442c-3.2 0-3.557.012-4.788.069-2.75.126-3.922 1.29-4.042 4.042-.057 1.23-.068 1.582-.068 4.788s.011 3.557.068 4.788c.12 2.752 1.292 3.918 4.042 4.042 1.23.056 1.588.068 4.788.068s3.557-.012 4.788-.068c2.75-.124 3.918-1.29 4.042-4.042.056-1.23.068-1.582.068-4.788s-.012-3.557-.068-4.788c-.124-2.752-1.29-3.918-4.042-4.042-1.23-.056-1.582-.068-4.788-.068zm0 3.19a4.8 4.8 0 1 0 0 9.6 4.8 4.8 0 0 0 0-9.6zm0 1.44a3.36 3.36 0 1 1 0 6.72 3.36 3.36 0 0 1 0-6.72zm5.405-4.32a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z"/></svg>',
            'x_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'threads_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.25c-5.376 0-9.75 4.374-9.75 9.75s4.374 9.75 9.75 9.75S21.75 17.376 21.75 12 17.376 2.25 12 2.25zm0 1.5c4.557 0 8.25 3.693 8.25 8.25s-3.693 8.25-8.25 8.25S3.75 16.557 3.75 12 7.443 3.75 12 3.75zm-3.938 6.094c.11-.22.327-.353.563-.353h6.75c.236 0 .453.133.563.353.11.22.07.484-.103.664-.174.18-.424.23-.647-.124-1.21-.6-2.618-.938-4.063-.938s-2.853.338-4.063.938c-.223.105-.473.056-.647-.124-.173-.18-.213-.445-.103-.665zm7.876 3.75c-.11.22-.327.353-.563-.353h-6.75c-.236 0-.453-.133-.563-.353-.11-.22-.07-.484.103-.664.174-.18.424-.23.647-.124 1.21.6 2.618.938 4.063.938s2.853-.338 4.063-.938c.223-.105.473-.056.647.124.173.18.213.445.103-.665z"/></svg>',
            'steam_white' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12,2C6.48,2,2,6.48,2,12s4.48,10,10,10s10-4.48,10-10S17.52,2,12,2z M12.03,17.96c-3.23,0-5.86-2.63-5.86-5.86s2.63-5.86,5.86-5.86s5.86,2.63,5.86,5.86S15.26,17.96,12.03,17.96z M16.96,15.02l-4.89-2.83V7.98h2.83L16.96,15.02z M12.03,13.01c-0.58,0-1.05-0.47-1.05-1.05c0-0.58,0.47-1.05,1.05-1.05c0.58,0,1.05,0.47,1.05,1.05C13.08,12.54,12.61,13.01,12.03,13.01z"/></svg>',
            'home_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'user_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'settings_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 0 2.4l-.15.08a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1 0-2.4l.15-.08a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
            'search_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
            'close_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            'youtube_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000000"><path d="M10 15l5.19-3L10 9v6zm11.56-7.36c-.77-2.63-2.63-4.5-5.28-5.28C14.53 2 12 2 12 2s-2.53 0-4.28.36c-2.65.78-4.5 2.65-5.28 5.28C2 9.47 2 12 2 12s0 2.53.36 4.28c.78 2.63 2.63 4.5 5.28 5.28C9.47 22 12 22 12 22s2.53 0 4.28-.36c2.65-.78 4.5-2.65 5.28-5.28C22 14.53 22 12 22 12s0-2.53-.44-4.28z"/></svg>',
            'facebook_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000000"><path d="M18.77,0H5.23C2.344,0,0,2.344,0,5.23v13.54C0,21.656,2.344,24,5.23,24h7.292v-9.094H9.698V11.06h2.824V8.51c0-2.791,1.707-4.312,4.199-4.312c1.198,0,2.229,0.089,2.529,0.129v3.424h-2.021c-1.354,0-1.617,0.643-1.617,1.589v2.084h3.79l-0.493,3.846h-3.297V24h3.875C21.656,24,24,21.656,24,18.77V5.23C24,2.344,21.656,0,18.77,0z"/></svg>',
            'instagram_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000000"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.011 3.585-.069 4.85c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.585-.012-4.85-.07c-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.069-1.645-.069-4.85s.011-3.585.069-4.85c.149-3.225 1.664-4.771 4.919-4.919C8.415 2.175 8.796 2.163 12 2.163zm0 1.442c-3.2 0-3.557.012-4.788.069-2.75.126-3.922 1.29-4.042 4.042-.057 1.23-.068 1.582-.068 4.788s.011 3.557.068 4.788c.12 2.752 1.292 3.918 4.042 4.042 1.23.056 1.588.068 4.788.068s3.557-.012 4.788-.068c2.75-.124 3.918-1.29 4.042-4.042.056-1.23.068-1.582.068-4.788s-.012-3.557-.068-4.788c-.124-2.752-1.29-3.918-4.042-4.042-1.23-.056-1.582-.068-4.788-.068zm0 3.19a4.8 4.8 0 1 0 0 9.6 4.8 4.8 0 0 0 0-9.6zm0 1.44a3.36 3.36 0 1 1 0 6.72 3.36 3.36 0 0 1 0-6.72zm5.405-4.32a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z"/></svg>',
            'x_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000000"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'threads_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000000"><path d="M12 2.25c-5.376 0-9.75 4.374-9.75 9.75s4.374 9.75 9.75 9.75S21.75 17.376 21.75 12 17.376 2.25 12 2.25zm0 1.5c4.557 0 8.25 3.693 8.25 8.25s-3.693 8.25-8.25 8.25S3.75 16.557 3.75 12 7.443 3.75 12 3.75zm-3.938 6.094c.11-.22.327-.353.563-.353h6.75c.236 0 .453.133.563.353.11.22.07.484-.103.664-.174.18-.424.23-.647-.124-1.21-.6-2.618-.938-4.063-.938s-2.853.338-4.063.938c-.223.105-.473.056-.647-.124-.173-.18-.213-.445-.103-.665zm7.876 3.75c-.11.22-.327.353-.563-.353h-6.75c-.236 0-.453-.133-.563-.353-.11-.22-.07-.484.103-.664.174-.18.424-.23.647-.124 1.21.6 2.618.938 4.063.938s2.853-.338 4.063-.938c.223-.105.473-.056.647.124.173.18.213.445.103-.665z"/></svg>',
            'steam_black' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000000"><path d="M12,2C6.48,2,2,6.48,2,12s4.48,10,10,10s10-4.48,10-10S17.52,2,12,2z M12.03,17.96c-3.23,0-5.86-2.63-5.86-5.86s2.63-5.86,5.86-5.86s5.86,2.63,5.86,5.86S15.26,17.96,12.03,17.96z M16.96,15.02l-4.89-2.83V7.98h2.83L16.96,15.02z M12.03,13.01c-0.58,0-1.05-0.47-1.05-1.05c0-0.58,0.47-1.05,1.05-1.05c0.58,0,1.05,0.47,1.05,1.05C13.08,12.54,12.61,13.01,12.03,13.01z"/></svg>',
        ];
    }

    private function get_custom_icons() {
        $custom_icons = [];
        $upload_dir = wp_upload_dir();
        $icons_dir = $upload_dir['basedir'] . '/sidebar-jlg/icons/';
        if (is_dir($icons_dir)) {
            $files = scandir($icons_dir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
                    $icon_name = 'custom_' . sanitize_key(pathinfo($file, PATHINFO_FILENAME));
                    $custom_icons[$icon_name] = $upload_dir['baseurl'] . '/sidebar-jlg/icons/' . $file;
                }
            }
        }
        return $custom_icons;
    }

    public function add_admin_menu() {
        add_menu_page( 'Sidebar JLG Settings', 'Sidebar JLG', 'manage_options', 'sidebar-jlg', [ $this, 'create_admin_page' ], 'dashicons-slides', 100 );
    }

    public function create_admin_page() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/admin-page.php';
    }

    public function register_settings() {
        register_setting( 'sidebar_jlg_options_group', 'sidebar_jlg_settings', [ $this, 'sanitize_settings' ] );
    }
    
    public function sanitize_settings( $input ) {
        $defaults = $this->get_default_settings();
        $existing_options = get_option('sidebar_jlg_settings', $defaults);
        
        $sanitized_input = is_array($input) ? $input : [];

        // Onglet Général
        $sanitized_input['enable_sidebar'] = isset($sanitized_input['enable_sidebar']) ? 1 : 0;
        $sanitized_input['layout_style'] = sanitize_text_field($sanitized_input['layout_style'] ?? $existing_options['layout_style']);
        $sanitized_input['floating_vertical_margin'] = sanitize_text_field($sanitized_input['floating_vertical_margin'] ?? $existing_options['floating_vertical_margin']);
        $sanitized_input['border_radius'] = sanitize_text_field($sanitized_input['border_radius'] ?? $existing_options['border_radius']);
        $sanitized_input['border_width'] = absint($sanitized_input['border_width'] ?? $existing_options['border_width']);
        $sanitized_input['border_color'] = $this->sanitize_rgba_color($sanitized_input['border_color'] ?? $existing_options['border_color']);
        $sanitized_input['desktop_behavior'] = sanitize_text_field($sanitized_input['desktop_behavior'] ?? $existing_options['desktop_behavior']);
        $sanitized_input['content_margin'] = sanitize_text_field($sanitized_input['content_margin'] ?? $existing_options['content_margin']);
        $sanitized_input['width_desktop'] = absint($sanitized_input['width_desktop'] ?? $existing_options['width_desktop']);
        $sanitized_input['width_tablet'] = absint($sanitized_input['width_tablet'] ?? $existing_options['width_tablet']);
        $sanitized_input['enable_search'] = isset($sanitized_input['enable_search']) ? 1 : 0;
        $sanitized_input['search_method'] = sanitize_text_field($sanitized_input['search_method'] ?? $existing_options['search_method']);
        $sanitized_input['search_shortcode'] = sanitize_text_field($sanitized_input['search_shortcode'] ?? $existing_options['search_shortcode']);
        $sanitized_input['search_alignment'] = sanitize_text_field($sanitized_input['search_alignment'] ?? $existing_options['search_alignment']);
        $sanitized_input['debug_mode'] = isset($sanitized_input['debug_mode']) ? 1 : 0;
        $sanitized_input['show_close_button'] = isset($sanitized_input['show_close_button']) ? 1 : 0;
        $sanitized_input['hamburger_top_position'] = sanitize_text_field($sanitized_input['hamburger_top_position'] ?? $existing_options['hamburger_top_position']);

        // Onglet Style & Préréglages
        $sanitized_input['style_preset'] = sanitize_text_field($sanitized_input['style_preset'] ?? $existing_options['style_preset']);
        $colors = ['bg_color', 'accent_color', 'font_color', 'font_hover_color'];
        foreach ($colors as $color_key) {
            $sanitized_input[$color_key.'_type'] = sanitize_text_field($sanitized_input[$color_key.'_type'] ?? $existing_options[$color_key.'_type']);
            if ($sanitized_input[$color_key.'_type'] === 'gradient') {
                $sanitized_input[$color_key.'_start'] = $this->sanitize_rgba_color($sanitized_input[$color_key.'_start'] ?? $existing_options[$color_key.'_start']);
                $sanitized_input[$color_key.'_end'] = $this->sanitize_rgba_color($sanitized_input[$color_key.'_end'] ?? $existing_options[$color_key.'_end']);
            } else {
                $sanitized_input[$color_key] = $this->sanitize_rgba_color($sanitized_input[$color_key] ?? $existing_options[$color_key]);
            }
        }
        $sanitized_input['header_logo_type'] = sanitize_text_field($sanitized_input['header_logo_type'] ?? $existing_options['header_logo_type']);
        $sanitized_input['app_name'] = sanitize_text_field($sanitized_input['app_name'] ?? $existing_options['app_name']);
        $sanitized_input['header_logo_image'] = esc_url_raw($sanitized_input['header_logo_image'] ?? $existing_options['header_logo_image']);
        $sanitized_input['header_logo_size'] = absint($sanitized_input['header_logo_size'] ?? $existing_options['header_logo_size']);
        $sanitized_input['header_alignment_desktop'] = sanitize_text_field($sanitized_input['header_alignment_desktop'] ?? $existing_options['header_alignment_desktop']);
        $sanitized_input['header_alignment_mobile'] = sanitize_text_field($sanitized_input['header_alignment_mobile'] ?? $existing_options['header_alignment_mobile']);
        $sanitized_input['header_padding_top'] = sanitize_text_field($sanitized_input['header_padding_top'] ?? $existing_options['header_padding_top']);
        $sanitized_input['font_size'] = absint($sanitized_input['font_size'] ?? $existing_options['font_size']);
        $sanitized_input['mobile_bg_color'] = $this->sanitize_rgba_color($sanitized_input['mobile_bg_color'] ?? $existing_options['mobile_bg_color']);
        $sanitized_input['mobile_bg_opacity'] = floatval($sanitized_input['mobile_bg_opacity'] ?? $existing_options['mobile_bg_opacity']);
        $sanitized_input['mobile_blur'] = absint($sanitized_input['mobile_blur'] ?? $existing_options['mobile_blur']);

        // Onglet Effets
        $sanitized_input['hover_effect_desktop'] = sanitize_text_field($sanitized_input['hover_effect_desktop'] ?? $existing_options['hover_effect_desktop']);
        $sanitized_input['hover_effect_mobile'] = sanitize_text_field($sanitized_input['hover_effect_mobile'] ?? $existing_options['hover_effect_mobile']);
        $sanitized_input['animation_speed'] = absint($sanitized_input['animation_speed'] ?? $existing_options['animation_speed']);
        $sanitized_input['animation_type'] = sanitize_text_field($sanitized_input['animation_type'] ?? $existing_options['animation_type']);
        $sanitized_input['neon_blur'] = absint($sanitized_input['neon_blur'] ?? $existing_options['neon_blur']);
        $sanitized_input['neon_spread'] = absint($sanitized_input['neon_spread'] ?? $existing_options['neon_spread']);

        // Menu Items & Alignment
        $sanitized_input['menu_alignment_desktop'] = sanitize_text_field($sanitized_input['menu_alignment_desktop'] ?? $existing_options['menu_alignment_desktop']);
        $sanitized_input['menu_alignment_mobile'] = sanitize_text_field($sanitized_input['menu_alignment_mobile'] ?? $existing_options['menu_alignment_mobile']);
        $sanitized_menu_items = [];
        if (isset($sanitized_input['menu_items']) && is_array($sanitized_input['menu_items'])) {
            foreach ($sanitized_input['menu_items'] as $item) {
                $sanitized_item = [
                    'label' => sanitize_text_field($item['label']),
                    'type' => sanitize_text_field($item['type']),
                    'icon_type' => sanitize_text_field($item['icon_type']),
                    'icon' => sanitize_text_field($item['icon']),
                ];
                $sanitized_item['value'] = ($item['type'] === 'custom' || $item['icon_type'] === 'svg_url') ? esc_url_raw($item['value']) : absint($item['value']);
                $sanitized_menu_items[] = $sanitized_item;
            }
        }
        $sanitized_input['menu_items'] = $sanitized_menu_items;
        
        // Social Icons
        $sanitized_social_icons = [];
        if (isset($sanitized_input['social_icons']) && is_array($sanitized_input['social_icons'])) {
            foreach ($sanitized_input['social_icons'] as $item) {
                $sanitized_item = [
                    'url' => esc_url_raw($item['url']),
                    'icon' => sanitize_key($item['icon']),
                ];
                $sanitized_social_icons[] = $sanitized_item;
            }
        }
        $sanitized_input['social_icons'] = $sanitized_social_icons;
        $sanitized_input['social_orientation'] = sanitize_text_field($sanitized_input['social_orientation'] ?? $existing_options['social_orientation']);
        $sanitized_input['social_position'] = sanitize_text_field($sanitized_input['social_position'] ?? $existing_options['social_position']);
        $sanitized_input['social_icon_size'] = absint($sanitized_input['social_icon_size'] ?? $existing_options['social_icon_size']);

        return array_merge($existing_options, $sanitized_input);
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_sidebar-jlg' !== $hook ) return;

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();
        wp_enqueue_style( 'sidebar-jlg-admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css', [], SIDEBAR_JLG_VERSION );

        wp_enqueue_script( 'sidebar-jlg-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-script.js', [ 'jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-util' ], SIDEBAR_JLG_VERSION, true );

        wp_localize_script('sidebar-jlg-admin-js', 'sidebarJLG', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jlg_ajax_nonce'),
            'reset_nonce' => wp_create_nonce('jlg_reset_nonce'),
            'options' => get_option('sidebar_jlg_settings', $this->get_default_settings()),
            'all_icons' => $this->get_all_available_icons()
        ]);
    }

    public function enqueue_public_assets() {
        wp_enqueue_style( 'sidebar-jlg-public-css', plugin_dir_url( __FILE__ ) . 'assets/css/public-style.css', [], SIDEBAR_JLG_VERSION );
        wp_enqueue_script( 'sidebar-jlg-public-js', plugin_dir_url( __FILE__ ) . 'assets/js/public-script.js', [], SIDEBAR_JLG_VERSION, true );
        
        $options = get_option( 'sidebar_jlg_settings', $this->get_default_settings() );
        wp_localize_script( 'sidebar-jlg-public-js', 'sidebarSettings', $options );
    }
    
    public function render_sidebar_html() {
        $html = get_transient( 'sidebar_jlg_full_html' );
        if ( false === $html ) {
            ob_start();
            require plugin_dir_path( __FILE__ ) . 'includes/sidebar-template.php';
            $html = ob_get_clean();
            set_transient( 'sidebar_jlg_full_html', $html );
        }

        echo $html;
    }

    public function get_default_settings() {
        return [
            'enable_sidebar'    => true,
            'app_name'          => 'Sidebar JLG',
            'layout_style'      => 'full',
            'floating_vertical_margin'   => '4rem',
            'border_radius'     => '12px',
            'border_width'      => 1,
            'border_color'      => 'rgba(255,255,255,0.2)',
            'desktop_behavior'  => 'push',
            'content_margin'    => '2rem',
            'width_desktop'     => 280,
            'width_tablet'      => 320,
            'enable_search'     => false,
            'search_method'     => 'default',
            'search_shortcode'  => '',
            'search_alignment'  => 'flex-start',
            'debug_mode'        => false,
            'show_close_button' => true,
            'hamburger_top_position' => '4rem',
            'header_logo_type'  => 'text',
            'header_logo_image' => '',
            'header_logo_size'  => 150,
            'header_alignment_desktop'  => 'flex-start',
            'header_alignment_mobile'   => 'center',
            'header_padding_top' => '2.5rem',
            'style_preset'      => 'custom',
            'bg_color_type'     => 'solid',
            'bg_color'          => 'rgba(26, 29, 36, 1)',
            'bg_color_start'    => '#18181b',
            'bg_color_end'      => '#27272a',
            'accent_color_type' => 'solid',
            'accent_color'      => 'rgba(13, 110, 253, 1)',
            'accent_color_start'=> '#60a5fa',
            'accent_color_end'  => '#c084fc',
            'font_size'         => 16,
            'font_color_type'   => 'solid',
            'font_color'        => 'rgba(224, 224, 224, 1)',
            'font_color_start'  => '#fafafa',
            'font_color_end'    => '#e0e0e0',
            'font_hover_color_type' => 'solid',
            'font_hover_color'  => 'rgba(255, 255, 255, 1)',
            'font_hover_color_start' => '#ffffff',
            'font_hover_color_end'   => '#fafafa',
            'mobile_bg_color'   => 'rgba(26, 29, 36, 0.8)',
            'mobile_bg_opacity' => 0.8,
            'mobile_blur'       => 10,
            'hover_effect_desktop'   => 'none',
            'hover_effect_mobile'    => 'none',
            'animation_speed'   => 400,
            'animation_type'    => 'slide-left',
            'neon_blur'         => 15,
            'neon_spread'       => 5,
            'menu_items'        => [],
            'menu_alignment_desktop' => 'flex-start',
            'menu_alignment_mobile'  => 'flex-start',
            'social_icons'      => [
                ['url' => '#', 'icon' => 'youtube_white'],
                ['url' => '#', 'icon' => 'x_white'],
                ['url' => '#', 'icon' => 'facebook_white'],
                ['url' => '#', 'icon' => 'instagram_white'],
            ],
            'social_orientation'=> 'horizontal',
            'social_position'   => 'footer',
            'social_icon_size'  => 100,
        ];
    }
    
    public function clear_menu_cache() {
        delete_transient('sidebar_jlg_full_html');
    }

    public function add_body_classes( $classes ) {
        $options = wp_parse_args(get_option('sidebar_jlg_settings'), $this->get_default_settings());
        $classes[] = 'jlg-sidebar-active';
        if ($options['desktop_behavior'] === 'push') {
            $classes[] = 'jlg-sidebar-push';
        } else {
            $classes[] = 'jlg-sidebar-overlay';
        }
        if ($options['layout_style'] === 'floating') {
            $classes[] = 'jlg-sidebar-floating';
        }
        return $classes;
    }

    public function ajax_get_posts() {
        check_ajax_referer('jlg_ajax_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Permission refusée.');
        }
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 20;
        $posts = get_posts([
            'posts_per_page' => $per_page,
            'paged' => $page,
        ]);
        $options = [];
        foreach ($posts as $post) {
            $options[] = ['id' => $post->ID, 'title' => $post->post_title];
        }
        wp_send_json_success($options);
    }

    public function ajax_get_categories() {
        check_ajax_referer('jlg_ajax_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Permission refusée.');
        }
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        $categories = get_categories([
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => $offset,
        ]);
        $options = [];
        foreach ($categories as $category) {
            $options[] = ['id' => $category->term_id, 'name' => $category->name];
        }
        wp_send_json_success($options);
    }
    
    public function ajax_reset_settings() {
        check_ajax_referer('jlg_reset_nonce', 'nonce');
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission refusée.' );
        }
        delete_option( 'sidebar_jlg_settings' );
        delete_transient( 'sidebar_jlg_full_html' );
        wp_send_json_success( 'Réglages réinitialisés.' );
    }

    private function color_picker($name, $options) {
        $type = $options[$name . '_type'] ?? 'solid';
        $solid_color = $options[$name] ?? '#ffffff';
        $start_color = $options[$name . '_start'] ?? '#000000';
        $end_color = $options[$name . '_end'] ?? '#ffffff';
        ?>
        <div class="color-picker-wrapper" data-color-name="<?php echo esc_attr($name); ?>">
            <p>
                <label><input type="radio" name="sidebar_jlg_settings[<?php echo $name; ?>_type]" value="solid" <?php checked($type, 'solid'); ?>> <?php echo __('Solide', 'sidebar-jlg'); ?></label>
                <label><input type="radio" name="sidebar_jlg_settings[<?php echo $name; ?>_type]" value="gradient" <?php checked($type, 'gradient'); ?>> <?php echo __('Dégradé', 'sidebar-jlg'); ?></label>
            </p>
            <div class="color-solid-field" style="<?php echo $type === 'solid' ? '' : 'display:none;'; ?>">
                <input type="text" name="sidebar_jlg_settings[<?php echo $name; ?>]" value="<?php echo esc_attr($solid_color); ?>" class="color-picker-rgba"/>
            </div>
            <div class="color-gradient-field" style="<?php echo $type === 'gradient' ? '' : 'display:none;'; ?>">
                <input type="text" name="sidebar_jlg_settings[<?php echo $name; ?>_start]" value="<?php echo esc_attr($start_color); ?>" class="color-picker-rgba"/>
                <input type="text" name="sidebar_jlg_settings[<?php echo $name; ?>_end]" value="<?php echo esc_attr($end_color); ?>" class="color-picker-rgba"/>
            </div>
        </div>
        <?php
    }

    private function sanitize_rgba_color( $color ) {
        if ( empty( $color ) || is_array( $color ) ) return '';
        if ( false === strpos( $color, 'rgba' ) ) return sanitize_hex_color( $color );
        sscanf( $color, 'rgba(%d, %d, %d, %f)', $r, $g, $b, $a );
        return 'rgba('.absint($r).','.absint($g).','.absint($b).','.floatval($a).')';
    }
}

Sidebar_JLG::get_instance();
