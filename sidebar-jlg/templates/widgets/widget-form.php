<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$widget = isset( $widget ) && is_array( $widget ) ? $widget : [];
if ( empty( $widget['is_enabled'] ) ) {
    return;
}

$widget_id = isset( $widget['id'] ) ? sanitize_html_class( (string) $widget['id'] ) : 'form-widget';
$content   = isset( $widget['content'] ) && is_array( $widget['content'] ) ? $widget['content'] : [];
$style     = isset( $widget['style'] ) && is_array( $widget['style'] ) ? $widget['style'] : [];
$tracking  = isset( $widget['tracking'] ) && is_array( $widget['tracking'] ) ? $widget['tracking'] : [];
$events    = isset( $tracking['events'] ) && is_array( $tracking['events'] ) ? $tracking['events'] : [];

$title            = isset( $content['title'] ) ? trim( (string) $content['title'] ) : '';
$intro            = isset( $content['intro'] ) ? $content['intro'] : '';
$shortcode        = isset( $content['shortcode'] ) ? $content['shortcode'] : '';
$success_message  = isset( $content['success_message'] ) ? trim( (string) $content['success_message'] ) : '';

$background       = isset( $style['background_color'] ) ? (string) $style['background_color'] : '';
$text_color       = isset( $style['text_color'] ) ? (string) $style['text_color'] : '';
$button_style     = isset( $style['button_style'] ) ? sanitize_key( (string) $style['button_style'] ) : 'solid';
$padding          = '';
if ( isset( $style['padding'] ) && is_array( $style['padding'] ) ) {
    $padding_value = isset( $style['padding']['value'] ) ? (string) $style['padding']['value'] : '';
    $padding_unit  = isset( $style['padding']['unit'] ) ? (string) $style['padding']['unit'] : 'px';
    if ( $padding_value !== '' ) {
        $padding = $padding_value . $padding_unit;
    }
}
$radius           = '';
if ( isset( $style['border_radius'] ) && is_array( $style['border_radius'] ) ) {
    $radius_value = isset( $style['border_radius']['value'] ) ? (string) $style['border_radius']['value'] : '';
    $radius_unit  = isset( $style['border_radius']['unit'] ) ? (string) $style['border_radius']['unit'] : 'px';
    if ( $radius_value !== '' ) {
        $radius = $radius_value . $radius_unit;
    }
}

$view_event        = isset( $events['view'] ) ? sanitize_key( (string) $events['view'] ) : 'form_view';
$interaction_event = isset( $events['interaction'] ) ? sanitize_key( (string) $events['interaction'] ) : 'form_start';
$conversion_event  = isset( $events['conversion'] ) ? sanitize_key( (string) $events['conversion'] ) : 'form_submit';
$goal_id           = isset( $tracking['goal_id'] ) ? sanitize_key( (string) $tracking['goal_id'] ) : '';
$integration       = isset( $tracking['integration'] ) ? sanitize_key( (string) $tracking['integration'] ) : 'shortcode';

$style_rules = [];
if ( $background !== '' ) {
    $style_rules[] = '--widget-background:' . $background;
}
if ( $text_color !== '' ) {
    $style_rules[] = '--widget-text:' . $text_color;
}
if ( $padding !== '' ) {
    $style_rules[] = '--widget-padding:' . $padding;
}
if ( $radius !== '' ) {
    $style_rules[] = '--widget-radius:' . $radius;
}
$style_attribute = $style_rules !== [] ? ' style="' . esc_attr( implode( ';', $style_rules ) ) . '"' : '';
?>
<div
    class="sidebar-widget sidebar-widget--form button-<?php echo esc_attr( $button_style ); ?>"
    data-widget-type="form"
    data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
    data-widget-view-event="<?php echo esc_attr( $view_event ); ?>"
    data-widget-interaction-event="<?php echo esc_attr( $interaction_event ); ?>"
    data-widget-conversion-event="<?php echo esc_attr( $conversion_event ); ?>"
    data-widget-integration="<?php echo esc_attr( $integration ); ?>"
    <?php echo $style_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php if ( $goal_id !== '' ) : ?>
        data-widget-goal="<?php echo esc_attr( $goal_id ); ?>"
    <?php endif; ?>
>
    <div class="sidebar-widget__body">
        <?php if ( $title !== '' ) : ?>
            <h3 class="sidebar-widget__title"><?php echo esc_html( $title ); ?></h3>
        <?php endif; ?>

        <?php if ( $intro !== '' ) : ?>
            <div class="sidebar-widget__content"><?php echo wp_kses_post( $intro ); ?></div>
        <?php endif; ?>

        <div class="sidebar-widget__form" data-form-analytics="container" data-widget-action="form_start">
            <?php if ( $shortcode !== '' ) : ?>
                <?php echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <form method="post" action="#" data-widget-form="<?php echo esc_attr( $widget_id ); ?>">
                    <label>
                        <span class="screen-reader-text"><?php esc_html_e( 'Votre e-mail', 'sidebar-jlg' ); ?></span>
                        <input type="email" name="sidebar_widget_email" placeholder="<?php esc_attr_e( 'vous@example.com', 'sidebar-jlg' ); ?>" required />
                    </label>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Envoyer', 'sidebar-jlg' ); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ( $success_message !== '' ) : ?>
            <p
                class="sidebar-widget__success"
                data-widget-success-message
                role="status"
                aria-live="polite"
                aria-atomic="true"
            ><?php echo esc_html( $success_message ); ?></p>
        <?php endif; ?>
    </div>
</div>
