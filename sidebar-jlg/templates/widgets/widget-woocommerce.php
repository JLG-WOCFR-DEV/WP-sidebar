<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$widget = isset( $widget ) && is_array( $widget ) ? $widget : [];
if ( empty( $widget['is_enabled'] ) ) {
    return;
}

$widget_id = isset( $widget['id'] ) ? sanitize_html_class( (string) $widget['id'] ) : 'woocommerce-widget';
$content   = isset( $widget['content'] ) && is_array( $widget['content'] ) ? $widget['content'] : [];
$style     = isset( $widget['style'] ) && is_array( $widget['style'] ) ? $widget['style'] : [];
$tracking  = isset( $widget['tracking'] ) && is_array( $widget['tracking'] ) ? $widget['tracking'] : [];
$events    = isset( $tracking['events'] ) && is_array( $tracking['events'] ) ? $tracking['events'] : [];

$title             = isset( $content['title'] ) ? trim( (string) $content['title'] ) : '';
$mode              = isset( $content['mode'] ) ? sanitize_key( (string) $content['mode'] ) : 'product';
$ids               = isset( $content['ids'] ) ? trim( (string) $content['ids'] ) : '';
$fallback_message  = isset( $content['fallback_message'] ) ? trim( (string) $content['fallback_message'] ) : '';

$display           = isset( $style['display'] ) ? sanitize_key( (string) $style['display'] ) : 'compact';
$highlight_badges  = ! empty( $style['highlight_badges'] );
$background        = isset( $style['background_color'] ) ? (string) $style['background_color'] : '';

$view_event        = isset( $events['view'] ) ? sanitize_key( (string) $events['view'] ) : 'woocommerce_widget_view';
$interaction_event = isset( $events['interaction'] ) ? sanitize_key( (string) $events['interaction'] ) : 'woocommerce_widget_click';
$conversion_event  = isset( $events['conversion'] ) ? sanitize_key( (string) $events['conversion'] ) : 'woocommerce_widget_purchase';
$goal_id           = isset( $tracking['goal_id'] ) ? sanitize_key( (string) $tracking['goal_id'] ) : '';
$integration       = isset( $tracking['integration'] ) ? sanitize_key( (string) $tracking['integration'] ) : 'products';

$style_rules = [];
if ( $background !== '' ) {
    $style_rules[] = '--widget-background:' . $background;
}
$style_attribute = $style_rules !== [] ? ' style="' . esc_attr( implode( ';', $style_rules ) ) . '"' : '';
?>
<div
    class="sidebar-widget sidebar-widget--woocommerce display-<?php echo esc_attr( $display ); ?>"
    data-widget-type="woocommerce"
    data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
    data-widget-view-event="<?php echo esc_attr( $view_event ); ?>"
    data-widget-interaction-event="<?php echo esc_attr( $interaction_event ); ?>"
    data-widget-conversion-event="<?php echo esc_attr( $conversion_event ); ?>"
    data-widget-mode="<?php echo esc_attr( $mode ); ?>"
    data-widget-integration="<?php echo esc_attr( $integration ); ?>"
    data-widget-highlight-badges="<?php echo $highlight_badges ? 'true' : 'false'; ?>"
    <?php echo $style_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php if ( $goal_id !== '' ) : ?>
        data-widget-goal="<?php echo esc_attr( $goal_id ); ?>"
    <?php endif; ?>
>
    <div class="sidebar-widget__body" data-woocommerce-analytics="container">
        <?php if ( $title !== '' ) : ?>
            <h3 class="sidebar-widget__title"><?php echo esc_html( $title ); ?></h3>
        <?php endif; ?>

        <div class="sidebar-widget__meta">
            <span class="sidebar-widget__mode"><?php esc_html_e( 'Mode', 'sidebar-jlg' ); ?> : <strong><?php echo esc_html( $mode ); ?></strong></span>
            <?php if ( $ids !== '' ) : ?>
                <span class="sidebar-widget__ids">ID : <?php echo esc_html( $ids ); ?></span>
            <?php endif; ?>
        </div>

        <ul class="sidebar-widget__products" data-widget-action="product">
            <li>
                <span class="sidebar-widget__product-thumb" aria-hidden="true">🛒</span>
                <span class="sidebar-widget__product-label"><?php esc_html_e( 'Produit vedette', 'sidebar-jlg' ); ?></span>
            </li>
            <li>
                <span class="sidebar-widget__product-thumb" aria-hidden="true">🛍️</span>
                <span class="sidebar-widget__product-label"><?php esc_html_e( 'Promotion en cours', 'sidebar-jlg' ); ?></span>
            </li>
        </ul>

        <?php if ( $fallback_message !== '' ) : ?>
            <p class="sidebar-widget__fallback"><?php echo esc_html( $fallback_message ); ?></p>
        <?php endif; ?>
    </div>
</div>
