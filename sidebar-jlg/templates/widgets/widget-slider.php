<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$widget = isset( $widget ) && is_array( $widget ) ? $widget : [];
if ( empty( $widget['is_enabled'] ) ) {
    return;
}

$widget_id = isset( $widget['id'] ) ? sanitize_html_class( (string) $widget['id'] ) : 'slider-widget';
$content   = isset( $widget['content'] ) && is_array( $widget['content'] ) ? $widget['content'] : [];
$style     = isset( $widget['style'] ) && is_array( $widget['style'] ) ? $widget['style'] : [];
$tracking  = isset( $widget['tracking'] ) && is_array( $widget['tracking'] ) ? $widget['tracking'] : [];
$events    = isset( $tracking['events'] ) && is_array( $tracking['events'] ) ? $tracking['events'] : [];

$title       = isset( $content['title'] ) ? trim( (string) $content['title'] ) : '';
$items       = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : [];
$theme       = isset( $style['theme'] ) ? sanitize_key( (string) $style['theme'] ) : 'cards';
$autoplay    = ! empty( $style['autoplay'] );
$delay       = isset( $style['autoplay_delay'] ) && is_numeric( $style['autoplay_delay'] ) ? (int) $style['autoplay_delay'] : 4500;

$view_event        = isset( $events['view'] ) ? sanitize_key( (string) $events['view'] ) : 'slider_view';
$interaction_event = isset( $events['interaction'] ) ? sanitize_key( (string) $events['interaction'] ) : 'slider_interaction';
$conversion_event  = isset( $events['conversion'] ) ? sanitize_key( (string) $events['conversion'] ) : 'slider_cta';
$goal_id           = isset( $tracking['goal_id'] ) ? sanitize_key( (string) $tracking['goal_id'] ) : '';

$enable_goal = isset( $tracking['enable_goal'] ) ? (bool) $tracking['enable_goal'] : true;
?>
<div
    class="sidebar-widget sidebar-widget--slider theme-<?php echo esc_attr( $theme ); ?>"
    data-widget-type="slider"
    data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
    data-widget-view-event="<?php echo esc_attr( $view_event ); ?>"
    data-widget-interaction-event="<?php echo esc_attr( $interaction_event ); ?>"
    data-widget-conversion-event="<?php echo esc_attr( $conversion_event ); ?>"
    data-widget-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>"
    data-widget-autoplay-delay="<?php echo esc_attr( (string) $delay ); ?>"
    <?php if ( $goal_id !== '' && $enable_goal ) : ?>
        data-widget-goal="<?php echo esc_attr( $goal_id ); ?>"
    <?php endif; ?>
>
    <div class="sidebar-widget__body" data-slider-analytics="container">
        <?php if ( $title !== '' ) : ?>
            <h3 class="sidebar-widget__title"><?php echo esc_html( $title ); ?></h3>
        <?php endif; ?>

        <?php if ( $items !== [] ) : ?>
            <ul class="sidebar-widget__slides" data-widget-action="slider_interaction">
                <?php foreach ( $items as $item_index => $item ) :
                    if ( ! is_array( $item ) ) {
                        continue;
                    }

                    $item_heading = isset( $item['heading'] ) ? trim( (string) $item['heading'] ) : '';
                    $item_text    = isset( $item['text'] ) ? $item['text'] : '';
                    $item_media   = isset( $item['media'] ) ? esc_url( (string) $item['media'] ) : '';
                    ?>
                    <li class="sidebar-widget__slide" data-slide-index="<?php echo esc_attr( (string) $item_index ); ?>">
                        <?php if ( $item_media !== '' ) : ?>
                            <figure class="sidebar-widget__media">
                                <img src="<?php echo esc_url( $item_media ); ?>" alt="" loading="lazy" decoding="async" />
                            </figure>
                        <?php endif; ?>

                        <?php if ( $item_heading !== '' ) : ?>
                            <strong class="sidebar-widget__slide-title"><?php echo esc_html( $item_heading ); ?></strong>
                        <?php endif; ?>

                        <?php if ( $item_text !== '' ) : ?>
                            <p class="sidebar-widget__slide-text"><?php echo esc_html( $item_text ); ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="description"><?php esc_html_e( 'Ajoutez des éléments au slider pour activer le carrousel.', 'sidebar-jlg' ); ?></p>
        <?php endif; ?>
    </div>
</div>
