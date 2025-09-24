<?php

namespace JLG\Sidebar\Frontend;

class Templating
{
    public static function renderSocialIcons(array $socialIcons, array $allIcons, string $orientation): string
    {
        if ($socialIcons === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="social-icons <?php echo esc_attr($orientation); ?>">
            <?php foreach ($socialIcons as $social) :
                if (empty($social['icon']) || empty($social['url']) || !isset($allIcons[$social['icon']])) {
                    continue;
                }

                $iconParts = explode('_', $social['icon']);
                $iconLabel = (isset($iconParts[0]) && $iconParts[0] !== '') ? $iconParts[0] : 'unknown';
                $customLabel = '';

                if (isset($social['label']) && is_string($social['label'])) {
                    $customLabel = trim($social['label']);
                }

                $ariaLabel = $customLabel !== '' ? $customLabel : $iconLabel;
                ?>
                <a href="<?php echo esc_url($social['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($ariaLabel); ?>">
                    <?php echo wp_kses_post($allIcons[$social['icon']]); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }
}
