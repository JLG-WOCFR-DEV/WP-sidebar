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
        static $standard_icons = null;

        if ($standard_icons !== null) {
            return $standard_icons;
        }

        $icons_file = plugin_dir_path( __FILE__ ) . 'assets/icons/standard-icons.php';

        if (file_exists($icons_file)) {
            $icons = require $icons_file;
            $standard_icons = is_array($icons) ? $icons : [];
        } else {
            $standard_icons = [];
        }

        return $standard_icons;
    }

    private function get_custom_icons() {
        $custom_icons = [];
        $upload_dir = wp_upload_dir();
        $icons_dir = trailingslashit($upload_dir['basedir']) . 'sidebar-jlg/icons/';

        if ( ! is_dir($icons_dir) || ! is_readable($icons_dir) ) {
            return $custom_icons;
        }

        $files = scandir($icons_dir);
        if ( ! is_array($files) ) {
            return $custom_icons;
        }

        $allowed_mimes = [ 'svg' => 'image/svg+xml' ];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || strpos($file, '.') === 0) {
                continue;
            }

            $file_path = $icons_dir . $file;

            if ( ! is_file($file_path) || ! is_readable($file_path) ) {
                continue;
            }

            $filetype = wp_check_filetype($file_path, $allowed_mimes);
            if (empty($filetype['ext']) || $filetype['ext'] !== 'svg' || empty($filetype['type'])) {
                continue;
            }

            $raw_contents = file_get_contents($file_path);
            if ($raw_contents === false) {
                continue;
            }

            $sanitized_contents = wp_kses($raw_contents, $this->get_allowed_svg_elements());
            if (empty($sanitized_contents)) {
                continue;
            }

            $normalized_original = $this->normalize_svg_content($raw_contents);
            $normalized_sanitized = $this->normalize_svg_content($sanitized_contents);

            if ($normalized_original === '' || $normalized_original !== $normalized_sanitized) {
                continue;
            }

            $icon_key = sanitize_key(pathinfo($file, PATHINFO_FILENAME));
            if ($icon_key === '') {
                continue;
            }

            $icon_name = 'custom_' . $icon_key;
            $custom_icons[$icon_name] = trailingslashit($upload_dir['baseurl']) . 'sidebar-jlg/icons/' . rawurlencode($file);
        }

        return $custom_icons;
    }

    private function get_allowed_svg_elements() {
        static $allowed = null;

        if ($allowed !== null) {
            return $allowed;
        }

        $common_attributes = [
            'class' => true,
            'id' => true,
            'style' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'stroke-miterlimit' => true,
            'stroke-dasharray' => true,
            'stroke-dashoffset' => true,
            'fill-opacity' => true,
            'stroke-opacity' => true,
            'fill-rule' => true,
            'clip-rule' => true,
            'opacity' => true,
            'transform' => true,
            'data-name' => true,
            'focusable' => true,
        ];

        $allowed = [
            'svg' => array_merge(
                $common_attributes,
                [
                    'xmlns' => true,
                    'xmlns:xlink' => true,
                    'width' => true,
                    'height' => true,
                    'viewBox' => true,
                    'preserveAspectRatio' => true,
                    'aria-hidden' => true,
                    'aria-labelledby' => true,
                    'role' => true,
                    'version' => true,
                    'xml:space' => true,
                ]
            ),
            'g' => $common_attributes,
            'title' => [],
            'path' => array_merge($common_attributes, [ 'd' => true ]),
            'circle' => array_merge($common_attributes, [ 'cx' => true, 'cy' => true, 'r' => true ]),
            'ellipse' => array_merge($common_attributes, [ 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ]),
            'rect' => array_merge($common_attributes, [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ]),
            'line' => array_merge($common_attributes, [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ]),
            'polyline' => array_merge($common_attributes, [ 'points' => true ]),
            'polygon' => array_merge($common_attributes, [ 'points' => true ]),
            'linearGradient' => array_merge($common_attributes, [
                'gradientUnits' => true,
                'gradientTransform' => true,
                'x1' => true,
                'y1' => true,
                'x2' => true,
                'y2' => true,
            ]),
            'radialGradient' => array_merge($common_attributes, [
                'gradientUnits' => true,
                'gradientTransform' => true,
                'cx' => true,
                'cy' => true,
                'fx' => true,
                'fy' => true,
                'r' => true,
            ]),
            'stop' => array_merge($common_attributes, [
                'offset' => true,
                'stop-color' => true,
                'stop-opacity' => true,
            ]),
            'defs' => $common_attributes,
            'clipPath' => array_merge($common_attributes, [ 'id' => true, 'clipPathUnits' => true ]),
            'mask' => array_merge($common_attributes, [ 'id' => true, 'maskUnits' => true, 'maskContentUnits' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true ]),
            'symbol' => array_merge($common_attributes, [ 'viewBox' => true, 'preserveAspectRatio' => true ]),
            'use' => array_merge($common_attributes, [ 'x' => true, 'y' => true, 'xlink:href' => true, 'href' => true ]),
            'text' => array_merge($common_attributes, [
                'x' => true,
                'y' => true,
                'dx' => true,
                'dy' => true,
                'text-anchor' => true,
                'font-family' => true,
                'font-size' => true,
                'font-weight' => true,
                'letter-spacing' => true,
                'word-spacing' => true,
            ]),
            'tspan' => array_merge($common_attributes, [
                'x' => true,
                'y' => true,
                'dx' => true,
                'dy' => true,
                'text-anchor' => true,
            ]),
        ];

        return $allowed;
    }

    private function normalize_svg_content($content) {
        $content = preg_replace('/<\?xml.*?\?>/i', '', $content);
        $content = preg_replace('/<!DOCTYPE.*?>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $content = preg_replace('/\s+/', '', $content ?? '');

        return trim($content);
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

        $prepared_input = is_array($input) ? $input : [];

        $sanitized_input = array_merge(
            $this->sanitize_general_settings($prepared_input, $existing_options),
            $this->sanitize_style_settings($prepared_input, $existing_options),
            $this->sanitize_effects_settings($prepared_input, $existing_options),
            $this->sanitize_menu_settings($prepared_input, $existing_options),
            $this->sanitize_social_settings($prepared_input, $existing_options)
        );

        return array_merge($existing_options, $sanitized_input);
    }

    private function sanitize_general_settings($input, $existing_options) {
        $sanitized = [];

        $sanitized['enable_sidebar'] = isset($input['enable_sidebar']) ? 1 : 0;
        $sanitized['layout_style'] = sanitize_key($input['layout_style'] ?? $existing_options['layout_style']);
        $sanitized['floating_vertical_margin'] = sanitize_text_field($input['floating_vertical_margin'] ?? $existing_options['floating_vertical_margin']);
        $sanitized['border_radius'] = sanitize_text_field($input['border_radius'] ?? $existing_options['border_radius']);
        $sanitized['border_width'] = absint($input['border_width'] ?? $existing_options['border_width']);
        $sanitized['border_color'] = $this->sanitize_rgba_color($input['border_color'] ?? $existing_options['border_color']);
        $sanitized['desktop_behavior'] = sanitize_key($input['desktop_behavior'] ?? $existing_options['desktop_behavior']);
        $sanitized['content_margin'] = sanitize_text_field($input['content_margin'] ?? $existing_options['content_margin']);
        $sanitized['width_desktop'] = absint($input['width_desktop'] ?? $existing_options['width_desktop']);
        $sanitized['width_tablet'] = absint($input['width_tablet'] ?? $existing_options['width_tablet']);
        $sanitized['enable_search'] = isset($input['enable_search']) ? 1 : 0;
        $sanitized['search_method'] = sanitize_key($input['search_method'] ?? $existing_options['search_method']);
        $sanitized['search_shortcode'] = sanitize_text_field($input['search_shortcode'] ?? $existing_options['search_shortcode']);
        $sanitized['search_alignment'] = sanitize_key($input['search_alignment'] ?? $existing_options['search_alignment']);
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? 1 : 0;
        $sanitized['show_close_button'] = isset($input['show_close_button']) ? 1 : 0;
        $sanitized['hamburger_top_position'] = sanitize_text_field($input['hamburger_top_position'] ?? $existing_options['hamburger_top_position']);

        return $sanitized;
    }

    private function sanitize_style_settings($input, $existing_options) {
        $sanitized = [];

        $sanitized['style_preset'] = sanitize_key($input['style_preset'] ?? $existing_options['style_preset']);

        $colors = ['bg_color', 'accent_color', 'font_color', 'font_hover_color'];
        foreach ($colors as $color_key) {
            $type_key = $color_key . '_type';
            $color_type = sanitize_key($input[$type_key] ?? $existing_options[$type_key]);
            $sanitized[$type_key] = $color_type;

            if ($color_type === 'gradient') {
                $sanitized[$color_key . '_start'] = $this->sanitize_rgba_color($input[$color_key . '_start'] ?? $existing_options[$color_key . '_start']);
                $sanitized[$color_key . '_end'] = $this->sanitize_rgba_color($input[$color_key . '_end'] ?? $existing_options[$color_key . '_end']);
            } else {
                $sanitized[$color_key] = $this->sanitize_rgba_color($input[$color_key] ?? $existing_options[$color_key]);
            }
        }

        $sanitized['header_logo_type'] = sanitize_key($input['header_logo_type'] ?? $existing_options['header_logo_type']);
        $sanitized['app_name'] = sanitize_text_field($input['app_name'] ?? $existing_options['app_name']);
        $sanitized['header_logo_image'] = esc_url_raw($input['header_logo_image'] ?? $existing_options['header_logo_image']);
        $sanitized['header_logo_size'] = absint($input['header_logo_size'] ?? $existing_options['header_logo_size']);
        $sanitized['header_alignment_desktop'] = sanitize_key($input['header_alignment_desktop'] ?? $existing_options['header_alignment_desktop']);
        $sanitized['header_alignment_mobile'] = sanitize_key($input['header_alignment_mobile'] ?? $existing_options['header_alignment_mobile']);
        $sanitized['header_padding_top'] = sanitize_text_field($input['header_padding_top'] ?? $existing_options['header_padding_top']);
        $sanitized['font_size'] = absint($input['font_size'] ?? $existing_options['font_size']);
        $sanitized['mobile_bg_color'] = $this->sanitize_rgba_color($input['mobile_bg_color'] ?? $existing_options['mobile_bg_color']);
        $sanitized['mobile_bg_opacity'] = floatval($input['mobile_bg_opacity'] ?? $existing_options['mobile_bg_opacity']);
        $sanitized['mobile_blur'] = absint($input['mobile_blur'] ?? $existing_options['mobile_blur']);

        return $sanitized;
    }

    private function sanitize_effects_settings($input, $existing_options) {
        $sanitized = [];

        $sanitized['hover_effect_desktop'] = sanitize_key($input['hover_effect_desktop'] ?? $existing_options['hover_effect_desktop']);
        $sanitized['hover_effect_mobile'] = sanitize_key($input['hover_effect_mobile'] ?? $existing_options['hover_effect_mobile']);
        $sanitized['animation_speed'] = absint($input['animation_speed'] ?? $existing_options['animation_speed']);
        $sanitized['animation_type'] = sanitize_key($input['animation_type'] ?? $existing_options['animation_type']);
        $sanitized['neon_blur'] = absint($input['neon_blur'] ?? $existing_options['neon_blur']);
        $sanitized['neon_spread'] = absint($input['neon_spread'] ?? $existing_options['neon_spread']);

        return $sanitized;
    }

    private function sanitize_menu_settings($input, $existing_options) {
        $sanitized = [];

        $sanitized['menu_alignment_desktop'] = sanitize_key($input['menu_alignment_desktop'] ?? $existing_options['menu_alignment_desktop']);
        $sanitized['menu_alignment_mobile'] = sanitize_key($input['menu_alignment_mobile'] ?? $existing_options['menu_alignment_mobile']);

        $sanitized_menu_items = [];
        if (isset($input['menu_items']) && is_array($input['menu_items'])) {
            foreach ($input['menu_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $item_type = sanitize_key($item['type'] ?? '');
                $icon_type = sanitize_key($item['icon_type'] ?? '');

                $sanitized_item = [
                    'label' => sanitize_text_field($item['label'] ?? ''),
                    'type' => $item_type,
                    'icon_type' => $icon_type,
                ];

                if ($icon_type === 'svg_url') {
                    $sanitized_item['icon'] = esc_url_raw($item['icon'] ?? '');
                } else {
                    $sanitized_item['icon'] = sanitize_key($item['icon'] ?? '');
                }

                $sanitized_item['value'] = ($item_type === 'custom' || $icon_type === 'svg_url')
                    ? esc_url_raw($item['value'] ?? '')
                    : absint($item['value'] ?? 0);

                $sanitized_menu_items[] = $sanitized_item;
            }
        }

        $sanitized['menu_items'] = $sanitized_menu_items;

        return $sanitized;
    }

    private function sanitize_social_settings($input, $existing_options) {
        $sanitized = [];

        $sanitized_social_icons = [];
        if (isset($input['social_icons']) && is_array($input['social_icons'])) {
            foreach ($input['social_icons'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $url = esc_url_raw($item['url'] ?? '');
                $icon = sanitize_key($item['icon'] ?? '');

                if ($url === '' || $icon === '') {
                    continue;
                }

                $sanitized_social_icons[] = [
                    'url' => $url,
                    'icon' => $icon,
                ];
            }
        }

        $sanitized['social_icons'] = $sanitized_social_icons;
        $sanitized['social_orientation'] = sanitize_key($input['social_orientation'] ?? $existing_options['social_orientation']);
        $sanitized['social_position'] = sanitize_key($input['social_position'] ?? $existing_options['social_position']);
        $sanitized['social_icon_size'] = absint($input['social_icon_size'] ?? $existing_options['social_icon_size']);

        return $sanitized;
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
        $capability = $this->get_ajax_capability();

        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( 'Permission refusée.' );
        }

        check_ajax_referer( 'jlg_ajax_nonce', 'nonce' );
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $max_per_page = 50;
        $requested_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 20;

        if ($requested_per_page > $max_per_page) {
            wp_send_json_error( sprintf( 'Le paramètre posts_per_page ne peut pas dépasser %d.', $max_per_page ) );
        }

        $per_page = min( max(1, $requested_per_page), $max_per_page );
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
        $capability = $this->get_ajax_capability();

        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( 'Permission refusée.' );
        }

        check_ajax_referer( 'jlg_ajax_nonce', 'nonce' );
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $max_per_page = 50;
        $requested_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 20;

        if ($requested_per_page > $max_per_page) {
            wp_send_json_error( sprintf( 'Le paramètre posts_per_page ne peut pas dépasser %d.', $max_per_page ) );
        }

        $per_page = min( max(1, $requested_per_page), $max_per_page );
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
        $capability = $this->get_ajax_capability();

        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( 'Permission refusée.' );
        }

        check_ajax_referer( 'jlg_reset_nonce', 'nonce' );
        delete_option( 'sidebar_jlg_settings' );
        delete_transient( 'sidebar_jlg_full_html' );
        wp_send_json_success( 'Réglages réinitialisés.' );
    }

    private function get_ajax_capability() {
        /**
         * Filter the capability required to access the plugin's AJAX endpoints.
         *
         * Using this filter allows granting access to roles with lesser permissions
         * (e.g. `edit_posts`) when required.
         */
        return apply_filters( 'sidebar_jlg_ajax_capability', 'manage_options' );
    }

    private function color_picker($name, $options) {
        $type = $options[$name . '_type'] ?? 'solid';
        $solid_color = $options[$name] ?? '#ffffff';
        $start_color = $options[$name . '_start'] ?? '#000000';
        $end_color = $options[$name . '_end'] ?? '#ffffff';
        ?>
        <div class="color-picker-wrapper" data-color-name="<?php echo esc_attr( $name ); ?>">
            <p>
                <label><input type="radio" name="sidebar_jlg_settings[<?php echo esc_attr( $name ); ?>_type]" value="solid" <?php checked($type, 'solid'); ?>> <?php esc_html_e('Solide', 'sidebar-jlg'); ?></label>
                <label><input type="radio" name="sidebar_jlg_settings[<?php echo esc_attr( $name ); ?>_type]" value="gradient" <?php checked($type, 'gradient'); ?>> <?php esc_html_e('Dégradé', 'sidebar-jlg'); ?></label>
            </p>
            <div class="color-solid-field" style="<?php echo $type === 'solid' ? '' : 'display:none;'; ?>">
                <input type="text" name="sidebar_jlg_settings[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( $solid_color ); ?>" class="color-picker-rgba"/>
            </div>
            <div class="color-gradient-field" style="<?php echo $type === 'gradient' ? '' : 'display:none;'; ?>">
                <input type="text" name="sidebar_jlg_settings[<?php echo esc_attr( $name ); ?>_start]" value="<?php echo esc_attr( $start_color ); ?>" class="color-picker-rgba"/>
                <input type="text" name="sidebar_jlg_settings[<?php echo esc_attr( $name ); ?>_end]" value="<?php echo esc_attr( $end_color ); ?>" class="color-picker-rgba"/>
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
