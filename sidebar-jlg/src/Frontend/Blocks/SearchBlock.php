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

        $blockType = register_block_type($blockDir, [
            'render_callback' => [$this, 'render'],
        ]);

        if ($blockType instanceof WP_Block_Type) {
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

        if (!$this->isRestRequest()) {
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

        return $normalized;
    }

    /**
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $options
     */
    private function maybeSynchronizeOptions(array $attributes, array $options): void
    {
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

        if ($updates === []) {
            return;
        }

        $storedOptions = get_option('sidebar_jlg_settings', []);
        if (!is_array($storedOptions)) {
            $storedOptions = [];
        }

        $this->settings->saveOptions(array_merge($storedOptions, $updates));
    }

    private function renderSearchMarkup(array $options): string
    {
        if (empty($options['enable_search'])) {
            return '';
        }

        $alignment = $options['search_alignment'] ?? 'flex-start';
        $alignmentClass = $this->getAlignmentClass($alignment);
        $alignmentStyle = 'justify-content:' . $alignment . ';';

        ob_start();
        ?>
        <div class="sidebar-search sidebar-search--block <?php echo esc_attr($alignmentClass); ?>" data-sidebar-search-align="<?php echo esc_attr($alignment); ?>" style="<?php echo esc_attr($alignmentStyle); ?>">
            <div class="sidebar-search__inner">
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
            ],
            'version' => $this->version,
        ];

        wp_localize_script($handle, 'SidebarJlgSearchBlock', $data);
    }
}
