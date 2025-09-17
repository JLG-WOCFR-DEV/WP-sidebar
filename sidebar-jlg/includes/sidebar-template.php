<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$options = get_option('sidebar_jlg_settings', \JLG\Sidebar\Sidebar_JLG::get_instance()->get_default_settings());
$all_icons = \JLG\Sidebar\Sidebar_JLG::get_instance()->get_all_available_icons();

ob_start();
?>
<nav class="sidebar-navigation" role="navigation" aria-label="<?php esc_attr_e('Navigation principale', 'sidebar-jlg'); ?>">
    <ul class="sidebar-menu">
        <?php
        if (!empty($options['menu_items'])) {
            foreach ($options['menu_items'] as $item) {
                $url = '#';
                if ($item['type'] === 'custom') $url = esc_url($item['value']);
                elseif ($item['type'] === 'post') $url = get_permalink(absint($item['value']));
                elseif ($item['type'] === 'category') $url = get_category_link(absint($item['value']));
                
                echo '<li><a href="' . esc_url( $url ) . '">';
                if ( ! empty( $item['icon'] ) ) {
                    if ( ! empty( $item['icon_type'] ) && $item['icon_type'] === 'svg_url' && filter_var($item['icon'], FILTER_VALIDATE_URL)) {
                        echo '<span class="menu-icon svg-icon"><img src="' . esc_url($item['icon']) . '" alt=""></span>';
                    } elseif (isset($all_icons[$item['icon']])) {
                        if (strpos($item['icon'], 'custom_') === 0) {
                            echo '<span class="menu-icon svg-icon"><img src="' . esc_url($all_icons[$item['icon']]) . '" alt=""></span>';
                        } else {
                            echo '<span class="menu-icon">' . $all_icons[$item['icon']] . '</span>';
                        }
                    }
                }
                echo '<span>' . esc_html($item['label']) . '</span></a></li>';
            }
        }
        
        if ($options['social_position'] === 'in-menu' && !empty($options['social_icons'])) {
            echo '<li class="menu-separator" aria-hidden="true"><hr></li>';
            echo '<li class="social-icons-wrapper">';
            if ( ! empty( $options['social_icons'] ) ) {
                echo '<div class="social-icons ' . esc_attr($options['social_orientation']) . '">';
                foreach($options['social_icons'] as $social) {
                    if ( ! empty( $social['icon'] ) && ! empty( $social['url'] ) && isset($all_icons[$social['icon']]) ) {
                        $icon_parts = explode('_', $social['icon']);
                        $icon_label = (isset($icon_parts[0]) && $icon_parts[0] !== '') ? $icon_parts[0] : 'unknown';

                        echo '<a href="' . esc_url($social['url']) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr($icon_label) . '">';
                        if (strpos($social['icon'], 'custom_') === 0) {
                            echo '<img class="social-svg-icon" src="' . esc_url($all_icons[$social['icon']]) . '" alt="">';
                        } else {
                            echo $all_icons[$social['icon']]; 
                        }
                        echo '</a>';
                    }
                }
                echo '</div>';
            }
            echo '</li>';
        }
        ?>
    </ul>
</nav>

<?php
if ($options['social_position'] === 'footer' && !empty($options['social_icons'])) {
    echo '<div class="sidebar-footer">';
    if ( ! empty( $options['social_icons'] ) ) {
        echo '<div class="social-icons ' . esc_attr($options['social_orientation']) . '">';
        foreach($options['social_icons'] as $social) {
            if ( ! empty( $social['icon'] ) && ! empty( $social['url'] ) && isset($all_icons[$social['icon']]) ) {
                $icon_parts = explode('_', $social['icon']);
                $icon_label = (isset($icon_parts[0]) && $icon_parts[0] !== '') ? $icon_parts[0] : 'unknown';

                echo '<a href="' . esc_url($social['url']) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr($icon_label) . '">';
                if (strpos($social['icon'], 'custom_') === 0) {
                    echo '<img class="social-svg-icon" src="' . esc_url($all_icons[$social['icon']]) . '" alt="">';
                } else {
                    echo $all_icons[$social['icon']]; 
                }
                echo '</a>';
            }
        }
        echo '</div>';
    }
    echo '</div>';
}

$sidebar_content_html = ob_get_clean();

$dynamic_styles = ":root {";
$dynamic_styles .= "--sidebar-width-desktop: " . esc_attr($options['width_desktop']) . "px;";
$dynamic_styles .= "--sidebar-width-tablet: " . esc_attr($options['width_tablet']) . "px;";

if (($options['bg_color_type'] ?? 'solid') === 'gradient') {
    $dynamic_styles .= "--sidebar-bg-image: linear-gradient(180deg, " . esc_attr($options['bg_color_start']) . " 0%, " . esc_attr($options['bg_color_end']) . " 100%);";
    $dynamic_styles .= "--sidebar-bg-color: " . esc_attr($options['bg_color_start']) . ";";
} else {
    $dynamic_styles .= "--sidebar-bg-image: none;";
    $dynamic_styles .= "--sidebar-bg-color: " . esc_attr($options['bg_color']) . ";";
}

if (($options['accent_color_type'] ?? 'solid') === 'gradient') {
    $dynamic_styles .= "--primary-accent-image: linear-gradient(90deg, " . esc_attr($options['accent_color_start']) . " 0%, " . esc_attr($options['accent_color_end']) . " 100%);";
    $dynamic_styles .= "--primary-accent-color: " . esc_attr($options['accent_color_start']) . ";";
} else {
    $dynamic_styles .= "--primary-accent-image: none;";
    $dynamic_styles .= "--primary-accent-color: " . esc_attr($options['accent_color']) . ";";
}

$dynamic_styles .= "--sidebar-font-size: " . esc_attr($options['font_size']) . "px;";
$dynamic_styles .= "--sidebar-text-color: " . esc_attr($options['font_color']) . ";";
$dynamic_styles .= "--sidebar-text-hover-color: " . esc_attr($options['font_hover_color']) . ";";
$dynamic_styles .= "--transition-speed: " . esc_attr($options['animation_speed']) . "ms;";
$dynamic_styles .= "--header-padding-top: " . esc_attr($options['header_padding_top']) . ";";
$dynamic_styles .= "--header-alignment-desktop: " . esc_attr($options['header_alignment_desktop']) . ";";
$dynamic_styles .= "--header-alignment-mobile: " . esc_attr($options['header_alignment_mobile']) . ";";
$dynamic_styles .= "--header-logo-size: " . esc_attr($options['header_logo_size']) . "px;";
$dynamic_styles .= "--hamburger-top-position: " . esc_attr($options['hamburger_top_position']) . ";";
$dynamic_styles .= "--content-margin-left: calc(var(--sidebar-width-desktop) + " . esc_attr($options['content_margin']) . ");";
$dynamic_styles .= "--floating-vertical-margin: " . esc_attr($options['floating_vertical_margin']) . ";";
$dynamic_styles .= "--border-radius: " . esc_attr($options['border_radius']) . ";";
$dynamic_styles .= "--border-width: " . esc_attr($options['border_width']) . "px;";
$dynamic_styles .= "--border-color: " . esc_attr($options['border_color']) . ";";
$dynamic_styles .= "--mobile-bg-color: " . esc_attr($options['mobile_bg_color']) . ";";
$dynamic_styles .= "--mobile-bg-opacity: " . esc_attr($options['mobile_bg_opacity']) . ";";
$dynamic_styles .= "--mobile-blur: " . esc_attr($options['mobile_blur']) . "px;";
$dynamic_styles .= "--menu-alignment-desktop: " . esc_attr($options['menu_alignment_desktop']) . ";";
$dynamic_styles .= "--menu-alignment-mobile: " . esc_attr($options['menu_alignment_mobile']) . ";";
$dynamic_styles .= "--search-alignment: " . esc_attr($options['search_alignment']) . ";";
$social_icon_size_factor = ($options['social_icon_size'] ?? 100) / 100;
$dynamic_styles .= "--social-icon-size-factor: " . esc_attr($social_icon_size_factor) . ";";
if ($options['hover_effect_desktop'] === 'neon' || $options['hover_effect_mobile'] === 'neon') {
    $dynamic_styles .= "--neon-blur: " . esc_attr($options['neon_blur']) . "px;";
    $dynamic_styles .= "--neon-spread: " . esc_attr($options['neon_spread']) . "px;";
}
$dynamic_styles .= "}";
?>
<style type="text/css"><?php echo $dynamic_styles; ?></style>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<button class="hamburger-menu" id="hamburger-btn" aria-label="<?php esc_attr_e('Ouvrir le menu', 'sidebar-jlg'); ?>" aria-controls="pro-sidebar" aria-expanded="false">
    <div class="hamburger-icon">
        <div class="icon-1"></div>
        <div class="icon-2"></div>
        <div class="icon-3"></div>
    </div>
</button>

<aside class="pro-sidebar" id="pro-sidebar" data-hover-desktop="<?php echo esc_attr($options['hover_effect_desktop']); ?>" data-hover-mobile="<?php echo esc_attr($options['hover_effect_mobile']); ?>">
    <div class="sidebar-header">
        <?php if ($options['header_logo_type'] === 'image' && !empty($options['header_logo_image'])): ?>
            <img src="<?php echo esc_url($options['header_logo_image']); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="sidebar-logo-image">
        <?php else: ?>
            <span class="logo-text"><?php echo esc_html($options['app_name']); ?></span>
        <?php endif; ?>
        
        <?php if ($options['show_close_button']): ?>
            <button class="close-sidebar-btn" aria-label="<?php esc_attr_e('Fermer le menu', 'sidebar-jlg'); ?>">
                <?php echo $all_icons['close_white']; ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($options['enable_search']): ?>
    <div class="sidebar-search">
        <?php
        switch ($options['search_method']) {
            case 'shortcode':
                if (!empty($options['search_shortcode'])) echo do_shortcode(wp_kses_post($options['search_shortcode']));
                break;
            case 'hook':
                do_action('jlg_sidebar_search_area');
                break;
            default:
                get_search_form();
                break;
        }
        ?>
    </div>
    <?php endif; ?>

    <?php echo $sidebar_content_html; ?>

</aside>
