<?php
use JLG\Sidebar\Frontend\Templating;

if ( ! defined( 'ABSPATH' ) ) exit;

$options = $options ?? [];
$allIcons = $allIcons ?? [];
$layoutStyle = $options['layout_style'] ?? 'full';
$horizontalPosition = $options['horizontal_bar_position'] ?? 'top';
$horizontalSticky = !empty($options['horizontal_bar_sticky']);
$wpMenuId = isset($options['wp_menu_id']) ? absint($options['wp_menu_id']) : 0;

$navigationClasses = ['sidebar-navigation'];
if ($layoutStyle === 'horizontal-bar') {
    $navigationClasses[] = 'is-horizontal';
}
$navigationClassAttr = implode(' ', array_map('sanitize_html_class', $navigationClasses));

$menuClasses = ['sidebar-menu'];
if ($layoutStyle === 'horizontal-bar') {
    $menuClasses[] = 'is-horizontal';
}
$menuClassAttr = implode(' ', array_map('sanitize_html_class', $menuClasses));
$menuItems = [];
if (!empty($options['menu_items']) && is_array($options['menu_items'])) {
    $menuItems = $options['menu_items'];
}

$menuSocialItemsMarkup = '';
if (($options['social_position'] ?? '') === 'in-menu' && !empty($options['social_icons']) && is_array($options['social_icons'])) {
    $menuSocialIcons = Templating::renderSocialIcons($options['social_icons'], $allIcons, $options['social_orientation']);
    if ($menuSocialIcons !== '') {
        $menuSocialItemsMarkup = '<li class="menu-separator" aria-hidden="true"><hr></li>';
        $menuSocialItemsMarkup .= '<li class="social-icons-wrapper">' . $menuSocialIcons . '</li>';
    }
}

ob_start();
?>
<nav class="<?php echo esc_attr($navigationClassAttr); ?>" role="navigation" aria-label="<?php esc_attr_e('Navigation principale', 'sidebar-jlg'); ?>">
    <?php
    $renderedWpMenu = '';
    if ($wpMenuId > 0 && function_exists('wp_get_nav_menu_object') && function_exists('wp_nav_menu')) {
        $menuObject = wp_get_nav_menu_object($wpMenuId);
        if ($menuObject && !is_wp_error($menuObject)) {
            $itemsWrap = '<ul id="%1$s" class="%2$s">%3$s';
            if ($menuSocialItemsMarkup !== '') {
                $itemsWrap .= $menuSocialItemsMarkup;
            }
            $itemsWrap .= '</ul>';

            $renderedWpMenu = wp_nav_menu([
                'menu' => $menuObject,
                'container' => false,
                'echo' => false,
                'menu_class' => $menuClassAttr,
                'items_wrap' => $itemsWrap,
                'fallback_cb' => '__return_empty_string',
            ]);

            if (!is_string($renderedWpMenu)) {
                $renderedWpMenu = '';
            }
        }
    }

    if ($renderedWpMenu !== '') :
        echo $renderedWpMenu;
    else :
    ?>
    <ul class="<?php echo esc_attr($menuClassAttr); ?>">
        <?php if (!empty($menuItems)) : ?>
            <?php foreach ($menuItems as $item) : ?>
                <?php
                $url = '#';
                $raw_url = '';

                if (($item['type'] ?? '') === 'custom') {
                    $raw_url = $item['value'] ?? '';
                } elseif (($item['type'] ?? '') === 'post' || ($item['type'] ?? '') === 'page') {
                    $raw_url = get_permalink(absint($item['value']));
                } elseif (($item['type'] ?? '') === 'category') {
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
                ?>
                <li>
                    <a href="<?php echo esc_url($url); ?>">
                        <?php if (!empty($item['icon'])) : ?>
                            <?php if (!empty($item['icon_type']) && $item['icon_type'] === 'svg_url' && filter_var($item['icon'], FILTER_VALIDATE_URL)) : ?>
                                <span class="menu-icon svg-icon"><img src="<?php echo esc_url($item['icon']); ?>" alt=""></span>
                            <?php elseif (isset($allIcons[$item['icon']])) : ?>
                                <?php $icon_markup = (string) $allIcons[$item['icon']]; ?>
                                <?php if (strpos($item['icon'], 'custom_') === 0) : ?>
                                    <span class="menu-icon svg-icon"><?php echo $icon_markup; ?></span>
                                <?php else : ?>
                                    <span class="menu-icon"><?php echo $icon_markup; ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span><?php echo esc_html($item['label'] ?? ''); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php echo $menuSocialItemsMarkup; ?>
    </ul>
    <?php endif; ?>
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

<?php
    $hamburger_open_label  = esc_attr__( 'Ouvrir le menu', 'sidebar-jlg' );
    $hamburger_close_label = esc_attr__( 'Fermer le menu', 'sidebar-jlg' );
?>
<button
    class="hamburger-menu"
    id="hamburger-btn"
    type="button"
    aria-label="<?php echo esc_attr( $hamburger_open_label ); ?>"
    aria-controls="pro-sidebar"
    aria-expanded="false"
    data-open-label="<?php echo esc_attr( $hamburger_open_label ); ?>"
    data-close-label="<?php echo esc_attr( $hamburger_close_label ); ?>">
    <div class="hamburger-icon">
        <div class="icon-1"></div>
        <div class="icon-2"></div>
        <div class="icon-3"></div>
    </div>
</button>

<?php
$asideClasses = ['pro-sidebar', 'layout-' . ($layoutStyle ? sanitize_html_class($layoutStyle) : 'full')];
if ($layoutStyle === 'horizontal-bar') {
    $asideClasses[] = 'position-' . sanitize_html_class($horizontalPosition ?: 'top');
    if ($horizontalSticky) {
        $asideClasses[] = 'is-sticky';
    }
}
$asideClassAttr = implode(' ', array_unique(array_map('sanitize_html_class', $asideClasses)));
$horizontalAlignment = $options['horizontal_bar_alignment'] ?? 'space-between';
?>
<aside class="<?php echo esc_attr($asideClassAttr); ?>" id="pro-sidebar" data-hover-desktop="<?php echo esc_attr($options['hover_effect_desktop']); ?>" data-hover-mobile="<?php echo esc_attr($options['hover_effect_mobile']); ?>" data-layout="<?php echo esc_attr($layoutStyle); ?>" data-horizontal-alignment="<?php echo esc_attr($horizontalAlignment); ?>">
    <div class="sidebar-inner">
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
                $close_button_markup = (string) $allIcons['close_white'];
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
    </div>
</aside>
