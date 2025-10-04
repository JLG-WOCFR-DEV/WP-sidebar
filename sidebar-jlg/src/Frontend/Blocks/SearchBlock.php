<?php

namespace JLG\Sidebar\Frontend\Blocks;

use JLG\Sidebar\Settings\SettingsRepository;
use WP_Block;
use WP_Block_Type;

class SearchBlock
{
    private const BLOCK_NAME = 'jlg/sidebar-search';

    private const METHODS = ['default', 'shortcode', 'hook'];
    private const ALIGNMENTS = ['flex-start', 'center', 'flex-end'];
    private const HEADING_LEVELS = ['h2', 'h3', 'h4', 'h5', 'h6'];

    private SettingsRepository $settings;
    private string $pluginFile;
    private string $version;

    public function __construct(SettingsRepository $settings, string $pluginFile, string $version)
    {
        $this->settings = $settings;
        $this->pluginFile = $pluginFile;
        $this->version = $version;
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        $blockDir = plugin_dir_path($this->pluginFile) . 'assets/blocks/sidebar-search';
        $styleHandle = 'sidebar-jlg-sidebar-search';
        $stylePath = plugin_dir_path($this->pluginFile) . 'assets/build/sidebar-search.css';

        if (file_exists($stylePath)) {
            wp_register_style(
                $styleHandle,
                plugin_dir_url($this->pluginFile) . 'assets/build/sidebar-search.css',
                [],
                $this->version
            );
        } else {
            $styleHandle = null;
        }

        $registerArgs = [
            'render_callback' => [$this, 'render'],
        ];

        if ($styleHandle !== null) {
            $registerArgs['style'] = $styleHandle;
            $registerArgs['editor_style'] = $styleHandle;
        }

        $blockType = register_block_type($blockDir, $registerArgs);

        if ($blockType instanceof WP_Block_Type) {
            // Ensure both block editor scripts and preview scripts load translations.
            $handles = array_filter([
                $blockType->editor_script ?? null,
                $blockType->script ?? null,
                $blockType->view_script ?? null,
            ], static fn($handle) => is_string($handle) && $handle !== '');

            foreach ($handles as $handle) {
                if (wp_script_is($handle, 'registered')) {
                    // Translation JSON files must be generated via `wp i18n make-json`.
                    wp_set_script_translations(
                        $handle,
                        'sidebar-jlg',
                        plugin_dir_path($this->pluginFile) . 'languages'
                    );
                }
            }

            $this->localizeEditorDefaults($blockType);
        }
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes = [], string $content = '', ?WP_Block $block = null): string
    {
        $options = $this->settings->getOptions();
        $normalized = $this->normalizeAttributes($attributes, $options);

        if (!$this->isRestRequest() && $this->canSynchronizeOptions()) {
            $this->maybeSynchronizeOptions($normalized, $options);
        }

        return $this->renderSearchMarkup($normalized);
    }

    /**
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function normalizeAttributes(array $attributes, array $options): array
    {
        $normalized = [];

        $normalized['enable_search'] = (bool) ($attributes['enable_search'] ?? $options['enable_search'] ?? false);

        $rawMethod = $attributes['search_method'] ?? $options['search_method'] ?? 'default';
        $normalized['search_method'] = in_array($rawMethod, self::METHODS, true) ? $rawMethod : 'default';

        $rawAlignment = $attributes['search_alignment'] ?? $options['search_alignment'] ?? 'flex-start';
        $normalized['search_alignment'] = in_array($rawAlignment, self::ALIGNMENTS, true) ? $rawAlignment : 'flex-start';

        $rawShortcode = $attributes['search_shortcode'] ?? $options['search_shortcode'] ?? '';
        if (is_string($rawShortcode)) {
            $rawShortcode = trim($rawShortcode);
        } else {
            $rawShortcode = '';
        }
        $normalized['search_shortcode'] = $rawShortcode === '' ? '' : wp_kses_post($rawShortcode);

        $rawHeading = $attributes['heading'] ?? $options['heading'] ?? '';
        if (is_string($rawHeading)) {
            $rawHeading = trim($rawHeading);
        } else {
            $rawHeading = '';
        }
        $normalized['heading'] = $rawHeading === '' ? '' : wp_kses_post($rawHeading);

        $rawDescription = $attributes['description'] ?? $options['description'] ?? '';
        if (is_string($rawDescription)) {
            $rawDescription = trim($rawDescription);
        } else {
            $rawDescription = '';
        }
        $normalized['description'] = $rawDescription === '' ? '' : wp_kses_post($rawDescription);

        $rawHeadingLevel = $attributes['heading_level'] ?? $options['heading_level'] ?? 'h2';
        if (!is_string($rawHeadingLevel) || !in_array($rawHeadingLevel, self::HEADING_LEVELS, true)) {
            $rawHeadingLevel = 'h2';
        }
        $normalized['heading_level'] = $rawHeadingLevel;

        return $normalized;
    }

    /**
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $options
     */
    private function maybeSynchronizeOptions(array $attributes, array $options): void
    {
        if (!$this->canSynchronizeOptions()) {
            return;
        }

        $updates = [];

        $currentEnable = isset($options['enable_search']) ? (bool) $options['enable_search'] : false;
        if ($currentEnable !== $attributes['enable_search']) {
            $updates['enable_search'] = $attributes['enable_search'];
        }

        $currentMethod = isset($options['search_method']) ? (string) $options['search_method'] : 'default';
        if ($currentMethod !== $attributes['search_method']) {
            $updates['search_method'] = $attributes['search_method'];
        }

        $currentAlignment = isset($options['search_alignment']) ? (string) $options['search_alignment'] : 'flex-start';
        if ($currentAlignment !== $attributes['search_alignment']) {
            $updates['search_alignment'] = $attributes['search_alignment'];
        }

        $currentShortcode = isset($options['search_shortcode']) ? (string) $options['search_shortcode'] : '';
        if ($currentShortcode !== $attributes['search_shortcode']) {
            $updates['search_shortcode'] = $attributes['search_shortcode'];
        }

        $currentHeading = isset($options['heading']) ? (string) $options['heading'] : '';
        if ($currentHeading !== $attributes['heading']) {
            $updates['heading'] = $attributes['heading'];
        }

        $currentDescription = isset($options['description']) ? (string) $options['description'] : '';
        if ($currentDescription !== $attributes['description']) {
            $updates['description'] = $attributes['description'];
        }

        $currentHeadingLevel = isset($options['heading_level']) ? (string) $options['heading_level'] : 'h2';
        if ($currentHeadingLevel !== $attributes['heading_level']) {
            $updates['heading_level'] = $attributes['heading_level'];
        }

        if ($updates === []) {
            return;
        }

        $storedOptions = get_option('sidebar_jlg_settings', []);
        if (!is_array($storedOptions)) {
            $storedOptions = [];
        }

        $this->settings->saveOptions(array_merge($storedOptions, $updates));
    }

    private function canSynchronizeOptions(): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        if (!function_exists('is_admin') || !is_admin()) {
            return false;
        }

        return current_user_can('manage_options');
    }

    private function renderSearchMarkup(array $options): string
    {
        if (empty($options['enable_search'])) {
            return '';
        }

        $alignment = $options['search_alignment'] ?? 'flex-start';
        $alignmentClass = $this->getAlignmentClass($alignment);
        $alignmentStyle = '--sidebar-search-alignment:' . $alignment . ';';
        $classNames = array_filter([
            'sidebar-search',
            $alignmentClass,
            'sidebar-search--scheme-light',
        ]);
        $classAttribute = implode(' ', $classNames);

        $heading = isset($options['heading']) ? (string) $options['heading'] : '';
        $heading = $heading !== '' ? wp_kses_post($heading) : '';
        $description = isset($options['description']) ? (string) $options['description'] : '';
        $description = $description !== '' ? wp_kses_post($description) : '';
        $headingLevel = isset($options['heading_level']) ? (string) $options['heading_level'] : 'h2';
        if (!in_array($headingLevel, self::HEADING_LEVELS, true)) {
            $headingLevel = 'h2';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classAttribute); ?>" data-sidebar-search-align="<?php echo esc_attr($alignment); ?>" data-sidebar-search-scheme="auto" style="<?php echo esc_attr($alignmentStyle); ?>">
            <?php if ($heading !== '') : ?>
                <<?php echo tag_escape($headingLevel); ?> class="sidebar-search__heading">
                    <?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </<?php echo tag_escape($headingLevel); ?>>
            <?php endif; ?>
            <?php if ($description !== '') : ?>
                <div class="sidebar-search__description">
                    <?php echo wp_kses_post(wpautop($description)); ?>
                </div>
            <?php endif; ?>
            <?php
            switch ($options['search_method']) {
                case 'shortcode':
                    if ($options['search_shortcode'] !== '') {
                        echo do_shortcode(wp_kses_post($options['search_shortcode']));
                    }
                    break;
                case 'hook':
                    ob_start();
                    do_action('jlg_sidebar_search_area');
                    echo ob_get_clean();
                    break;
                default:
                    echo get_search_form(false);
                    break;
            }
            ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function getAlignmentClass(string $alignment): string
    {
        switch ($alignment) {
            case 'center':
                return 'sidebar-search--align-center';
            case 'flex-end':
                return 'sidebar-search--align-end';
            default:
                return 'sidebar-search--align-start';
        }
    }

    private function isRestRequest(): bool
    {
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return true;
        }

        return defined('REST_REQUEST') && REST_REQUEST;
    }

    private function localizeEditorDefaults(WP_Block_Type $blockType): void
    {
        $handle = $blockType->editor_script;
        if (!is_string($handle) || $handle === '') {
            return;
        }

        if (!wp_script_is($handle, 'registered')) {
            return;
        }

        $options = $this->settings->getOptions();

        $data = [
            'blockName' => self::BLOCK_NAME,
            'defaults' => [
                'enable_search' => (bool) ($options['enable_search'] ?? false),
                'search_method' => (string) ($options['search_method'] ?? 'default'),
                'search_alignment' => (string) ($options['search_alignment'] ?? 'flex-start'),
                'search_shortcode' => (string) ($options['search_shortcode'] ?? ''),
                'heading' => (string) ($options['heading'] ?? ''),
                'description' => (string) ($options['description'] ?? ''),
                'heading_level' => in_array((string) ($options['heading_level'] ?? 'h2'), self::HEADING_LEVELS, true)
                    ? (string) ($options['heading_level'] ?? 'h2')
                    : 'h2',
            ],
            'version' => $this->version,
        ];

        wp_localize_script($handle, 'SidebarJlgSearchBlock', $data);
    }
}
