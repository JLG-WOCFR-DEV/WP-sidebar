<?php
use JLG\Sidebar\Frontend\SidebarRenderer;
use JLG\Sidebar\Frontend\Templating;

if ( ! defined( 'ABSPATH' ) ) exit;

$options = $options ?? [];
$allIcons = $allIcons ?? [];
$layoutStyle = $options['layout_style'] ?? 'full';
$horizontalPosition = $options['horizontal_bar_position'] ?? 'top';
$horizontalSticky = !empty($options['horizontal_bar_sticky']);
$rawSidebarPosition = $options['sidebar_position'] ?? 'left';
$sidebarPosition = sanitize_key(is_string($rawSidebarPosition) ? $rawSidebarPosition : 'left');
if ($sidebarPosition !== 'right') {
    $sidebarPosition = 'left';
}

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

$currentRequestContext = SidebarRenderer::getCurrentRequestContext();
$menuNodes = SidebarRenderer::buildMenuTree($options, $allIcons, $currentRequestContext);

$socialOrientation = '';
if (isset($options['social_orientation']) && is_string($options['social_orientation'])) {
    $socialOrientation = $options['social_orientation'];
}

$socialIcons = [];
if (!empty($options['social_icons']) && is_array($options['social_icons'])) {
    $socialIcons = $options['social_icons'];
}

$socialPosition = '';
if (isset($options['social_position']) && is_string($options['social_position'])) {
    $socialPosition = $options['social_position'];
}

$defaultNavAriaLabel = __('Navigation principale', 'sidebar-jlg');
$navAriaLabelOption = '';
if (isset($options['nav_aria_label']) && is_string($options['nav_aria_label'])) {
    $navAriaLabelOption = trim((string) $options['nav_aria_label']);
}
if ($navAriaLabelOption !== '') {
    $navAriaLabelOption = sanitize_text_field($navAriaLabelOption);
}
$navAriaLabel = $navAriaLabelOption !== '' ? $navAriaLabelOption : $defaultNavAriaLabel;

$defaultToggleExpandLabel = __('Afficher le sous-menu', 'sidebar-jlg');
$defaultToggleCollapseLabel = __('Masquer le sous-menu', 'sidebar-jlg');

$toggleExpandLabelOption = '';
if (isset($options['toggle_open_label']) && is_string($options['toggle_open_label'])) {
    $toggleExpandLabelOption = trim((string) $options['toggle_open_label']);
}
if ($toggleExpandLabelOption !== '') {
    $toggleExpandLabelOption = sanitize_text_field($toggleExpandLabelOption);
}
$toggleExpandLabel = $toggleExpandLabelOption !== '' ? $toggleExpandLabelOption : $defaultToggleExpandLabel;

$toggleCollapseLabelOption = '';
if (isset($options['toggle_close_label']) && is_string($options['toggle_close_label'])) {
    $toggleCollapseLabelOption = trim((string) $options['toggle_close_label']);
}
if ($toggleCollapseLabelOption !== '') {
    $toggleCollapseLabelOption = sanitize_text_field($toggleCollapseLabelOption);
}
$toggleCollapseLabel = $toggleCollapseLabelOption !== '' ? $toggleCollapseLabelOption : $defaultToggleCollapseLabel;

$submenuIndex = 0;

$renderMenuNodes = static function (array $nodes, string $layout) use (&$renderMenuNodes, $toggleExpandLabel, $toggleCollapseLabel, &$submenuIndex): string {
    if ($nodes === []) {
        return '';
    }

    $html = '';

    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }

        $classes = ['menu-item'];
        if (!empty($node['classes']) && is_array($node['classes'])) {
            $classes = array_merge($classes, $node['classes']);
        }
        $hasChildren = !empty($node['children']) && is_array($node['children']);
        if ($hasChildren) {
            $classes[] = 'has-submenu-toggle';
        }
        $classes = array_unique(array_filter(array_map('sanitize_html_class', $classes)));

        $initiallyExpandedClasses = [
            'current-menu-ancestor',
            'current-menu-parent',
            'current_page_parent',
            'current_page_ancestor',
            'current_page_item',
            'current-menu-item',
        ];
        $isInitiallyExpanded = $hasChildren && !empty(array_intersect($classes, $initiallyExpandedClasses));
        if ($isInitiallyExpanded && !in_array('is-open', $classes, true)) {
            $classes[] = 'is-open';
        }
        $classes = array_unique(array_filter(array_map('sanitize_html_class', $classes)));
        $classAttr = '';
        if (!empty($classes)) {
            $classAttr = ' class="' . esc_attr(implode(' ', $classes)) . '"';
        }

        $dataAttributes = '';
        if (!empty($node['data_attributes']) && is_array($node['data_attributes'])) {
            foreach ($node['data_attributes'] as $attrName => $attrValue) {
                if (!is_string($attrName) || strpos($attrName, 'data-') !== 0) {
                    continue;
                }

                if (!preg_match('/^data-[a-z0-9_-]+$/i', $attrName)) {
                    continue;
                }

                if (!is_scalar($attrValue)) {
                    continue;
                }

                $dataAttributes .= sprintf(' %s="%s"', esc_attr($attrName), esc_attr((string) $attrValue));
            }
        }

        $nodeType = isset($node['type']) ? (string) $node['type'] : '';

        if ($nodeType === 'cta') {
            $ctaData = is_array($node['cta'] ?? null) ? $node['cta'] : [];
            $ctaTitle = isset($ctaData['title']) && is_string($ctaData['title']) ? $ctaData['title'] : '';
            $ctaDescription = isset($ctaData['description']) && is_string($ctaData['description']) ? $ctaData['description'] : '';
            $ctaButtonLabel = isset($ctaData['button_label']) && is_string($ctaData['button_label']) ? $ctaData['button_label'] : '';
            $ctaButtonUrl = isset($ctaData['button_url']) && is_string($ctaData['button_url']) ? $ctaData['button_url'] : '#';
            $ctaShortcode = isset($ctaData['shortcode']) && is_string($ctaData['shortcode']) ? $ctaData['shortcode'] : '';

            ob_start();
            ?>
            <li<?php echo $classAttr; ?><?php echo $dataAttributes; ?>>
                <div class="menu-cta" data-cta-analytics="entry">
                    <?php if ($ctaTitle !== '') : ?>
                        <h3 class="menu-cta__title"><?php echo esc_html($ctaTitle); ?></h3>
                    <?php endif; ?>

                    <?php if ($ctaDescription !== '') : ?>
                        <div class="menu-cta__description"><?php echo wp_kses_post($ctaDescription); ?></div>
                    <?php endif; ?>

                    <?php if ($ctaShortcode !== '') : ?>
                        <div class="menu-cta__shortcode"><?php echo wp_kses_post($ctaShortcode); ?></div>
                    <?php endif; ?>

                    <?php if ($ctaButtonLabel !== '') : ?>
                        <a class="menu-cta__button" href="<?php echo esc_url($ctaButtonUrl); ?>" data-cta-action="button">
                            <span><?php echo esc_html($ctaButtonLabel); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </li>
            <?php
            $html .= ob_get_clean();
            continue;
        }

        $url = isset($node['url']) && is_string($node['url']) && $node['url'] !== '' ? $node['url'] : '#';
        $ariaCurrent = !empty($node['is_current']) ? ' aria-current="page"' : '';

        $submenuId = '';
        $toggleExpandedAttr = 'false';
        if ($isInitiallyExpanded) {
            $toggleExpandedAttr = 'true';
        }

        ob_start();
        ?>
        <li<?php echo $classAttr; ?><?php echo $dataAttributes; ?>>
            <a href="<?php echo esc_url($url); ?>"<?php echo $ariaCurrent; ?>>
                <?php
                $icon = $node['icon'] ?? null;
                if (is_array($icon)) {
                    if (($icon['type'] ?? '') === 'svg_url' && !empty($icon['url'])) {
                        ?>
                        <span class="menu-icon svg-icon"><img src="<?php echo esc_url($icon['url']); ?>" alt="" loading="lazy" decoding="async"></span>
                        <?php
                    } elseif (($icon['type'] ?? '') === 'svg_inline' && !empty($icon['markup'])) {
                        $iconClass = ($icon['is_custom'] ?? false) ? 'menu-icon svg-icon' : 'menu-icon';
                        ?>
                        <span class="<?php echo esc_attr($iconClass); ?>"><?php echo wp_kses_post((string) $icon['markup']); ?></span>
                        <?php
                    }
                }
                ?>
                <span><?php echo esc_html((string) ($node['label'] ?? '')); ?></span>
            </a>
            <?php if ($hasChildren) : ?>
                <?php
                $submenuIndex++;
                $submenuId = 'sidebar-submenu-' . $submenuIndex;
                $submenuClasses = ['submenu'];
                if ($layout === 'horizontal-bar') {
                    $submenuClasses[] = 'is-mega';
                }
                if ($isInitiallyExpanded) {
                    $submenuClasses[] = 'is-open';
                }
                $submenuClassAttr = implode(' ', array_unique(array_filter(array_map('sanitize_html_class', $submenuClasses))));
                ?>
                <button
                    class="submenu-toggle"
                    type="button"
                    aria-expanded="<?php echo esc_attr($toggleExpandedAttr); ?>"
                    aria-controls="<?php echo esc_attr($submenuId); ?>"
                    aria-haspopup="true"
                    aria-label="<?php echo esc_attr($toggleExpandedAttr === 'true' ? $toggleCollapseLabel : $toggleExpandLabel); ?>"
                    data-label-expand="<?php echo esc_attr($toggleExpandLabel); ?>"
                    data-label-collapse="<?php echo esc_attr($toggleCollapseLabel); ?>"
                >
                    <span class="screen-reader-text"><?php echo esc_html($toggleExpandedAttr === 'true' ? $toggleCollapseLabel : $toggleExpandLabel); ?></span>
                    <span aria-hidden="true" class="submenu-toggle-indicator"></span>
                </button>
                <ul
                    id="<?php echo esc_attr($submenuId); ?>"
                    class="<?php echo esc_attr($submenuClassAttr); ?>"
                    aria-hidden="<?php echo $isInitiallyExpanded ? 'false' : 'true'; ?>"
                >
                    <?php echo $renderMenuNodes($node['children'], $layout); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
        $html .= ob_get_clean();
    }

    return $html;
};

ob_start();
?>
<nav
    class="<?php echo esc_attr($navigationClassAttr); ?>"
    role="navigation"
    aria-label="<?php echo esc_attr($navAriaLabel); ?>"
    data-default-aria-label="<?php echo esc_attr($defaultNavAriaLabel); ?>"
    data-default-toggle-expand="<?php echo esc_attr($defaultToggleExpandLabel); ?>"
    data-default-toggle-collapse="<?php echo esc_attr($defaultToggleCollapseLabel); ?>"
>
    <ul class="<?php echo esc_attr($menuClassAttr); ?>">
        <?php echo $renderMenuNodes($menuNodes, $layoutStyle); ?>

        <?php if ($socialPosition === 'in-menu' && $socialIcons !== []) : ?>
            <?php $menuSocialIcons = Templating::renderSocialIcons($socialIcons, $allIcons, $socialOrientation); ?>
            <?php if ($menuSocialIcons !== '') : ?>
                <li class="menu-separator" aria-hidden="true"><hr></li>
                <li class="social-icons-wrapper"><?php echo $menuSocialIcons; ?></li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
</nav>

<?php
if ($socialPosition === 'footer' && $socialIcons !== []) {
    $footerSocialIcons = Templating::renderSocialIcons($socialIcons, $allIcons, $socialOrientation);
    if ($footerSocialIcons !== '') {
        echo '<div class="sidebar-footer">' . $footerSocialIcons . '</div>';
    }
}

$sidebar_content_html = ob_get_clean();
?>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true" tabindex="-1"></div>

<?php
    $hamburger_open_label  = esc_attr__( 'Ouvrir le menu', 'sidebar-jlg' );
    $hamburger_close_label = esc_attr__( 'Fermer le menu', 'sidebar-jlg' );
?>
<button
    class="hamburger-menu orientation-<?php echo esc_attr($sidebarPosition); ?>"
    id="hamburger-btn"
    type="button"
    aria-label="<?php echo esc_attr( $hamburger_open_label ); ?>"
    aria-controls="pro-sidebar"
    aria-expanded="false"
    data-open-label="<?php echo esc_attr( $hamburger_open_label ); ?>"
    data-close-label="<?php echo esc_attr( $hamburger_close_label ); ?>"
    data-position="<?php echo esc_attr($sidebarPosition); ?>">
    <div class="hamburger-icon">
        <div class="icon-1"></div>
        <div class="icon-2"></div>
        <div class="icon-3"></div>
    </div>
</button>

<?php
$asideClasses = [
    'pro-sidebar',
    'layout-' . ($layoutStyle ? sanitize_html_class($layoutStyle) : 'full'),
    'orientation-' . sanitize_html_class($sidebarPosition),
];
if ($layoutStyle === 'horizontal-bar') {
    $asideClasses[] = 'position-' . sanitize_html_class($horizontalPosition ?: 'top');
    if ($horizontalSticky) {
        $asideClasses[] = 'is-sticky';
    }
}
$asideClassAttr = implode(' ', array_unique(array_map('sanitize_html_class', $asideClasses)));
$horizontalAlignment = $options['horizontal_bar_alignment'] ?? 'space-between';
?>
<aside class="<?php echo esc_attr($asideClassAttr); ?>" id="pro-sidebar" data-hover-desktop="<?php echo esc_attr($options['hover_effect_desktop']); ?>" data-hover-mobile="<?php echo esc_attr($options['hover_effect_mobile']); ?>" data-layout="<?php echo esc_attr($layoutStyle); ?>" data-horizontal-alignment="<?php echo esc_attr($horizontalAlignment); ?>" data-position="<?php echo esc_attr($sidebarPosition); ?>">
    <div class="sidebar-inner">
        <div class="sidebar-header">
        <?php if ($options['header_logo_type'] === 'image' && !empty($options['header_logo_image'])): ?>
            <img src="<?php echo esc_url($options['header_logo_image']); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="sidebar-logo-image" loading="lazy" decoding="async">
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
