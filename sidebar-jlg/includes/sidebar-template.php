<?php
use JLG\Sidebar\Frontend\Templating;

if ( ! defined( 'ABSPATH' ) ) exit;

$options = $options ?? [];
$allIcons = $allIcons ?? [];

ob_start();
?>
<nav class="sidebar-navigation" role="navigation" aria-label="<?php esc_attr_e('Navigation principale', 'sidebar-jlg'); ?>">
    <ul class="sidebar-menu">
        <?php
        if (!empty($options['menu_items']) && is_array($options['menu_items'])) {
            foreach ($options['menu_items'] as $item) {
                $url = '#';
                $raw_url = '';

                if ($item['type'] === 'custom') {
                    $raw_url = $item['value'] ?? '';
                } elseif ($item['type'] === 'post' || $item['type'] === 'page') {
                    $raw_url = get_permalink(absint($item['value']));
                } elseif ($item['type'] === 'category') {
                    $raw_url = get_category_link(absint($item['value']));
                }

                $is_valid_url = true;

                if (function_exists('is_wp_error') && is_wp_error($raw_url)) {
                    $is_valid_url = false;
                }

                if (!is_string($raw_url) || $raw_url === '') {
                    $is_valid_url = false;
                }

                if ($is_valid_url) {
                    $url = $raw_url;
                }

                echo '<li><a href="' . esc_url( $url ) . '">';
                if ( ! empty( $item['icon'] ) ) {
                    if ( ! empty( $item['icon_type'] ) && $item['icon_type'] === 'svg_url' && filter_var($item['icon'], FILTER_VALIDATE_URL) ) {
                        echo '<span class="menu-icon svg-icon"><img src="' . esc_url( $item['icon'] ) . '" alt=""></span>';
                    } elseif ( isset( $allIcons[ $item['icon'] ] ) ) {
                        $icon_markup = wp_kses_post( $allIcons[ $item['icon'] ] );

                        if ( strpos( $item['icon'], 'custom_' ) === 0 ) {
                            echo '<span class="menu-icon svg-icon">' . $icon_markup . '</span>';
                        } else {
                            echo '<span class="menu-icon">' . $icon_markup . '</span>';
                        }
                    }
                }
                echo '<span>' . esc_html($item['label']) . '</span></a></li>';
            }
        }
        
        if ($options['social_position'] === 'in-menu' && !empty($options['social_icons']) && is_array($options['social_icons'])) {
            $menuSocialIcons = Templating::renderSocialIcons($options['social_icons'], $allIcons, $options['social_orientation']);
            if ($menuSocialIcons !== '') {
                echo '<li class="menu-separator" aria-hidden="true"><hr></li>';
                echo '<li class="social-icons-wrapper">' . $menuSocialIcons . '</li>';
            }
        }
        ?>
    </ul>
</nav>

<?php
if ($options['social_position'] === 'footer' && !empty($options['social_icons']) && is_array($options['social_icons'])) {
    $footerSocialIcons = Templating::renderSocialIcons($options['social_icons'], $allIcons, $options['social_orientation']);
    if ($footerSocialIcons !== '') {
        echo '<div class="sidebar-footer">' . $footerSocialIcons . '</div>';
    }
}

$sidebar_content_html = ob_get_clean();
?>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<button class="hamburger-menu" id="hamburger-btn" type="button" aria-label="<?php esc_attr_e('Ouvrir le menu', 'sidebar-jlg'); ?>" aria-controls="pro-sidebar" aria-expanded="false">
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
        
        <?php if (!empty($options['show_close_button'])): ?>
            <?php
            $close_button_markup = '<span class="close-sidebar-fallback" aria-hidden="true">&times;</span>';

            if (isset($allIcons['close_white']) && $allIcons['close_white'] !== '') {
                $close_button_markup = wp_kses_post($allIcons['close_white']);
            } else {
                $close_button_markup = wp_kses_post($close_button_markup);
            }
            ?>
            <button class="close-sidebar-btn" type="button" aria-label="<?php esc_attr_e('Fermer le menu', 'sidebar-jlg'); ?>">
                <?php echo $close_button_markup; ?>
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
