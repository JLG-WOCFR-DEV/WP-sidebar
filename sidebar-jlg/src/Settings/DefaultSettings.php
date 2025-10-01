<?php

namespace JLG\Sidebar\Settings;

class DefaultSettings
{
    /**
     * Returns the default plugin settings.
     */
    public function all(): array
    {
        return [
            'enable_sidebar'    => true,
            'app_name'          => 'Sidebar JLG',
            'layout_style'      => 'full',
            'floating_vertical_margin'   => '4rem',
            'horizontal_bar_height'      => '4rem',
            'horizontal_bar_alignment'   => 'space-between',
            'horizontal_bar_position'    => 'top',
            'horizontal_bar_sticky'      => true,
            'border_radius'     => '12px',
            'border_width'      => 1,
            'border_color'      => 'rgba(255,255,255,0.2)',
            'desktop_behavior'  => 'push',
            'overlay_color'     => 'rgba(0, 0, 0, 1)',
            'overlay_opacity'   => 0.5,
            'content_margin'    => '2rem',
            'width_desktop'     => 280,
            'width_tablet'      => 320,
            'enable_search'     => false,
            'search_method'     => 'default',
            'search_shortcode'  => '',
            'search_alignment'  => 'flex-start',
            'debug_mode'        => false,
            'show_close_button' => true,
            // Automatically close the sidebar after clicking menu or social links.
            'close_on_link_click' => false,
            'hamburger_top_position' => '4rem',
            'hamburger_color'      => 'rgba(255, 255, 255, 1)',
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
                ['url' => '#', 'icon' => 'youtube_white', 'label' => ''],
                ['url' => '#', 'icon' => 'x_white', 'label' => ''],
                ['url' => '#', 'icon' => 'facebook_white', 'label' => ''],
                ['url' => '#', 'icon' => 'instagram_white', 'label' => ''],
            ],
            'social_orientation'=> 'horizontal',
            'social_position'   => 'footer',
            'social_icon_size'  => 100,
        ];
    }
}
