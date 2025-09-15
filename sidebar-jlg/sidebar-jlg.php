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
