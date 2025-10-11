<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$widget = isset( $widget ) && is_array( $widget ) ? $widget : [];
if ( empty( $widget['is_enabled'] ) ) {
    return;
}

$widget_id = isset( $widget['id'] ) ? sanitize_html_class( (string) $widget['id'] ) : 'cta-widget';
$content   = isset( $widget['content'] ) && is_array( $widget['content'] ) ? $widget['content'] : [];
$style     = isset( $widget['style'] ) && is_array( $widget['style'] ) ? $widget['style'] : [];
$tracking  = isset( $widget['tracking'] ) && is_array( $widget['tracking'] ) ? $widget['tracking'] : [];
$events    = isset( $tracking['events'] ) && is_array( $tracking['events'] ) ? $tracking['events'] : [];

$title         = isset( $content['title'] ) ? trim( (string) $content['title'] ) : '';
$message       = isset( $content['message'] ) ? $content['message'] : '';
$button_label  = isset( $content['button_label'] ) ? trim( (string) $content['button_label'] ) : '';
$button_url    = isset( $content['button_url'] ) ? esc_url( (string) $content['button_url'] ) : '#';
$embed_content = isset( $content['shortcode'] ) ? $content['shortcode'] : '';

$layout        = isset( $style['layout'] ) ? sanitize_key( (string) $style['layout'] ) : 'stacked';
$background    = isset( $style['background_color'] ) ? (string) $style['background_color'] : '';
$accent        = isset( $style['accent_color'] ) ? (string) $style['accent_color'] : '';
$text_color    = isset( $style['text_color'] ) ? (string) $style['text_color'] : '';
$shadow        = isset( $style['shadow'] ) ? sanitize_key( (string) $style['shadow'] ) : 'none';
$radius        = '';
if ( isset( $style['border_radius'] ) && is_array( $style['border_radius'] ) ) {
    $radius_value = isset( $style['border_radius']['value'] ) ? (string) $style['border_radius']['value'] : '';
    $radius_unit  = isset( $style['border_radius']['unit'] ) ? (string) $style['border_radius']['unit'] : 'px';
    if ( $radius_value !== '' ) {
        $radius = $radius_value . $radius_unit;
    }
}

$view_event        = isset( $events['view'] ) ? sanitize_key( (string) $events['view'] ) : 'cta_view';
$interaction_event = isset( $events['interaction'] ) ? sanitize_key( (string) $events['interaction'] ) : 'cta_click';
$conversion_event  = isset( $events['conversion'] ) ? sanitize_key( (string) $events['conversion'] ) : 'cta_conversion';
$goal_id           = isset( $tracking['goal_id'] ) ? sanitize_key( (string) $tracking['goal_id'] ) : '';
$conversion_label  = isset( $tracking['conversion_label'] ) ? sanitize_text_field( (string) $tracking['conversion_label'] ) : '';

$style_rules = [];
if ( $background !== '' ) {
    $style_rules[] = '--widget-background:' . $background;
}
if ( $text_color !== '' ) {
    $style_rules[] = '--widget-text:' . $text_color;
}
if ( $accent !== '' ) {
    $style_rules[] = '--widget-accent:' . $accent;
}
if ( $radius !== '' ) {
    $style_rules[] = '--widget-radius:' . $radius;
}
$style_attribute = $style_rules !== [] ? ' style="' . esc_attr( implode( ';', $style_rules ) ) . '"' : '';
?>
<div
    class="sidebar-widget sidebar-widget--cta layout-<?php echo esc_attr( $layout ); ?> shadow-<?php echo esc_attr( $shadow ); ?>"
    data-widget-type="cta"
    data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
    data-widget-view-event="<?php echo esc_attr( $view_event ); ?>"
    data-widget-interaction-event="<?php echo esc_attr( $interaction_event ); ?>"
    data-widget-conversion-event="<?php echo esc_attr( $conversion_event ); ?>"
    <?php echo $style_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php if ( $goal_id !== '' ) : ?>
        data-widget-goal="<?php echo esc_attr( $goal_id ); ?>"
    <?php endif; ?>
    <?php if ( $conversion_label !== '' ) : ?>
        data-widget-label="<?php echo esc_attr( $conversion_label ); ?>"
    <?php endif; ?>
>
    <div class="sidebar-widget__body" data-cta-analytics="entry">
        <?php if ( $title !== '' ) : ?>
            <h3 class="sidebar-widget__title"><?php echo esc_html( $title ); ?></h3>
        <?php endif; ?>

        <?php if ( $message !== '' ) : ?>
            <div class="sidebar-widget__content"><?php echo wp_kses_post( $message ); ?></div>
        <?php endif; ?>

        <?php if ( $embed_content !== '' ) : ?>
            <div class="sidebar-widget__embed"><?php echo do_shortcode( $embed_content ); ?></div>
        <?php endif; ?>

        <?php if ( $button_label !== '' ) : ?>
            <div class="sidebar-widget__actions">
                <a
                    class="sidebar-widget__button"
                    href="<?php echo esc_url( $button_url !== '' ? $button_url : '#' ); ?>"
                    data-cta-action="button"
                    data-widget-action="cta_button"
                >
                    <span><?php echo esc_html( $button_label ); ?></span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
