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
$controls_description = isset( $content['controls_description'] ) ? trim( (string) $content['controls_description'] ) : '';
$theme       = isset( $style['theme'] ) ? sanitize_key( (string) $style['theme'] ) : 'cards';
$autoplay    = ! empty( $style['autoplay'] );
$delay       = isset( $style['autoplay_delay'] ) && is_numeric( $style['autoplay_delay'] ) ? (int) $style['autoplay_delay'] : 4500;

$title_id            = $title !== '' ? $widget_id . '-slider-title' : '';
$slides_id           = $widget_id . '-slider-list';
$controls_id         = $widget_id . '-slider-controls';
$description_id      = $controls_description !== '' ? $widget_id . '-slider-instructions' : '';
$live_when_playing   = 'off';
$live_when_paused    = 'polite';
$aria_live           = $autoplay ? $live_when_playing : $live_when_paused;
$status_template     = __( 'Diapositive %1$s sur %2$s', 'sidebar-jlg' );
$controls_label      = __( 'Contrôles du slider', 'sidebar-jlg' );
$previous_label      = __( 'Diapositive précédente', 'sidebar-jlg' );
$next_label          = __( 'Diapositive suivante', 'sidebar-jlg' );
$play_label          = __( 'Lecture', 'sidebar-jlg' );
$pause_label         = __( 'Pause', 'sidebar-jlg' );
$total_slides        = count( $items );

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
    data-slider-status-template="<?php echo esc_attr( $status_template ); ?>"
    data-slider-live-playing="<?php echo esc_attr( $live_when_playing ); ?>"
    data-slider-live-paused="<?php echo esc_attr( $live_when_paused ); ?>"
    data-slider-total="<?php echo esc_attr( (string) $total_slides ); ?>"
    role="region"
    aria-roledescription="carousel"
    aria-live="<?php echo esc_attr( $aria_live ); ?>"
    <?php if ( $title_id !== '' ) : ?>
        aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
    <?php endif; ?>
    <?php if ( $description_id !== '' ) : ?>
        aria-describedby="<?php echo esc_attr( $description_id ); ?>"
    <?php endif; ?>
    <?php if ( $goal_id !== '' && $enable_goal ) : ?>
        data-widget-goal="<?php echo esc_attr( $goal_id ); ?>"
    <?php endif; ?>
>
    <div class="sidebar-widget__body" data-slider-analytics="container">
        <?php if ( $title !== '' ) : ?>
            <h3 class="sidebar-widget__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $title ); ?></h3>
        <?php endif; ?>

        <?php if ( $description_id !== '' ) : ?>
            <p class="screen-reader-text" id="<?php echo esc_attr( $description_id ); ?>"><?php echo esc_html( $controls_description ); ?></p>
        <?php endif; ?>

        <?php if ( $items !== [] ) : ?>
            <ul
                class="sidebar-widget__slides"
                id="<?php echo esc_attr( $slides_id ); ?>"
                data-widget-action="slider_interaction"
                tabindex="0"
            >
                <?php foreach ( $items as $item_index => $item ) :
                    if ( ! is_array( $item ) ) {
                        continue;
                    }

                    $item_heading = isset( $item['heading'] ) ? trim( (string) $item['heading'] ) : '';
                    $item_text    = isset( $item['text'] ) ? $item['text'] : '';
                    $item_media   = isset( $item['media'] ) ? esc_url( (string) $item['media'] ) : '';
                    $item_media_alt = isset( $item['media_alt'] ) ? trim( (string) $item['media_alt'] ) : '';
                    $item_caption   = isset( $item['caption'] ) ? trim( (string) $item['caption'] ) : '';
                    $is_active      = $item_index === 0;
                    $alt_text       = $item_media_alt !== '' ? $item_media_alt : $item_heading;
                    ?>
                    <li
                        class="sidebar-widget__slide<?php echo $is_active ? ' is-active' : ''; ?>"
                        id="<?php echo esc_attr( $widget_id . '-slide-' . $item_index ); ?>"
                        data-slide-index="<?php echo esc_attr( (string) $item_index ); ?>"
                        aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
                    >
                        <?php if ( $item_media !== '' ) : ?>
                            <figure class="sidebar-widget__media">
                                <img
                                    src="<?php echo esc_url( $item_media ); ?>"
                                    alt="<?php echo esc_attr( $alt_text ); ?>"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <?php if ( $item_caption !== '' ) : ?>
                                    <figcaption class="sidebar-widget__media-caption"><?php echo esc_html( $item_caption ); ?></figcaption>
                                <?php endif; ?>
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

            <?php if ( $total_slides > 0 ) : ?>
                <div class="sidebar-widget__slider-controls" id="<?php echo esc_attr( $controls_id ); ?>" role="group" aria-label="<?php echo esc_attr( $controls_label ); ?>">
                    <button
                        type="button"
                        class="sidebar-widget__slider-button sidebar-widget__slider-button--prev"
                        data-slider-action="prev"
                        aria-controls="<?php echo esc_attr( $slides_id ); ?>"
                    >
                        <?php echo esc_html( $previous_label ); ?>
                    </button>
                    <button
                        type="button"
                        class="sidebar-widget__slider-button sidebar-widget__slider-button--next"
                        data-slider-action="next"
                        aria-controls="<?php echo esc_attr( $slides_id ); ?>"
                    >
                        <?php echo esc_html( $next_label ); ?>
                    </button>
                    <button
                        type="button"
                        class="sidebar-widget__slider-button sidebar-widget__slider-button--toggle"
                        data-slider-action="toggle"
                        data-label-play="<?php echo esc_attr( $play_label ); ?>"
                        data-label-pause="<?php echo esc_attr( $pause_label ); ?>"
                        aria-controls="<?php echo esc_attr( $slides_id ); ?>"
                        aria-pressed="<?php echo $autoplay ? 'true' : 'false'; ?>"
                    >
                        <?php echo esc_html( $autoplay ? $pause_label : $play_label ); ?>
                    </button>
                    <p class="sidebar-widget__slider-status" data-slider-status aria-live="polite"></p>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <p class="description"><?php esc_html_e( 'Ajoutez des éléments au slider pour activer le carrousel.', 'sidebar-jlg' ); ?></p>
        <?php endif; ?>
    </div>
</div>
