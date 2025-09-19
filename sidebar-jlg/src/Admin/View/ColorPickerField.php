<?php

namespace JLG\Sidebar\Admin\View;

class ColorPickerField
{
    public function render(string $name, array $options): void
    {
        $type = $options[$name . '_type'] ?? 'solid';
        $solidColor = $options[$name] ?? '#ffffff';
        $startColor = $options[$name . '_start'] ?? '#000000';
        $endColor = $options[$name . '_end'] ?? '#ffffff';
        ?>
        <div class="color-picker-wrapper" data-color-name="<?php echo esc_attr($name); ?>">
            <p>
                <label><input type="radio" name="sidebar_jlg_settings[<?php echo esc_attr($name); ?>_type]" value="solid" <?php checked($type, 'solid'); ?>> <?php esc_html_e('Solide', 'sidebar-jlg'); ?></label>
                <label><input type="radio" name="sidebar_jlg_settings[<?php echo esc_attr($name); ?>_type]" value="gradient" <?php checked($type, 'gradient'); ?>> <?php esc_html_e('Dégradé', 'sidebar-jlg'); ?></label>
            </p>
            <div class="color-solid-field" style="<?php echo $type === 'solid' ? '' : 'display:none;'; ?>">
                <input type="text" name="sidebar_jlg_settings[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($solidColor); ?>" class="color-picker-rgba"/>
            </div>
            <div class="color-gradient-field" style="<?php echo $type === 'gradient' ? '' : 'display:none;'; ?>">
                <input type="text" name="sidebar_jlg_settings[<?php echo esc_attr($name); ?>_start]" value="<?php echo esc_attr($startColor); ?>" class="color-picker-rgba"/>
                <input type="text" name="sidebar_jlg_settings[<?php echo esc_attr($name); ?>_end]" value="<?php echo esc_attr($endColor); ?>" class="color-picker-rgba"/>
            </div>
        </div>
        <?php
    }
}
