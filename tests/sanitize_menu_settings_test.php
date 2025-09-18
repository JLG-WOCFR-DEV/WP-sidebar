<?php
declare(strict_types=1);

use JLG\Sidebar\Sidebar_JLG;

define('ABSPATH', true);
define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void {
        // No-op for tests.
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', $value);

        return trim($value);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value): string {
        return (string) $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int {
        return abs((int) $value);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($value): string {
        return (string) $value;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($value): string {
        return (string) $value;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($value): string {
        return (string) $value;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($value): void {
        echo (string) $value;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = ''): string {
        return 'Test Blog';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file): string {
        return 'https://example.com/plugin/';
    }
}

$permalinkCalls = [];
$categoryLinkCalls = [];

if (!function_exists('get_permalink')) {
    function get_permalink($id): string {
        global $permalinkCalls;
        $permalinkCalls[] = $id;
        return 'https://example.com/post/' . $id;
    }
}

if (!function_exists('get_category_link')) {
    function get_category_link($id): string {
        global $categoryLinkCalls;
        $categoryLinkCalls[] = $id;
        return 'https://example.com/category/' . $id;
    }
}

$storedOptions = [];

if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        global $storedOptions;
        if ($name === 'sidebar_jlg_settings') {
            return $storedOptions ?: $default;
        }

        return $default;
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$reflection = new ReflectionClass(Sidebar_JLG::class);
$instance = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('sanitize_menu_settings');
$method->setAccessible(true);

$existingOptions = $instance->get_default_settings();
$input = [
    'menu_items' => [
        [
            'label'     => 'Featured Post',
            'type'      => 'post',
            'icon_type' => 'svg_url',
            'icon'      => 'https://example.com/icon.svg',
            'value'     => '123',
        ],
    ],
];

$result = $method->invoke($instance, $input, $existingOptions);

$testsPassed = true;

function assertSame($expected, $actual, string $message): void {
    global $testsPassed;

    if ($expected === $actual) {
        echo "[PASS] {$message}\n";
        return;
    }

    $testsPassed = false;
    echo sprintf(
        "[FAIL] %s - expected %s got %s\n",
        $message,
        var_export($expected, true),
        var_export($actual, true)
    );
}

$postItem = $result['menu_items'][0] ?? null;
assertSame(123, $postItem['value'] ?? null, 'Post IDs remain integers when sanitized');

// Prepare the instance for template rendering.
$instanceProperty = $reflection->getProperty('instance');
$instanceProperty->setAccessible(true);
$instanceProperty->setValue(null, $instance);

$allIconsProperty = $reflection->getProperty('all_icons');
$allIconsProperty->setAccessible(true);
$allIconsProperty->setValue($instance, [
    'default_icon' => '<span class="icon">default</span>',
    'close_white'  => '<span class="icon">close</span>',
]);

$storedOptions = Sidebar_JLG::get_instance()->get_default_settings();
$storedOptions['menu_items'] = [
    [
        'label'     => 'Post link',
        'type'      => 'post',
        'icon_type' => 'svg_url',
        'icon'      => 'https://example.com/icon.svg',
        'value'     => 321,
    ],
    [
        'label'     => 'Category link',
        'type'      => 'category',
        'icon_type' => 'default',
        'icon'      => 'default_icon',
        'value'     => 654,
    ],
];
$storedOptions['social_icons'] = [];

ob_start();
require __DIR__ . '/../sidebar-jlg/includes/sidebar-template.php';
$templateHtml = ob_get_clean();

assertSame([321], $permalinkCalls, 'Sidebar links use post permalinks with sanitized IDs');
assertSame([654], $categoryLinkCalls, 'Sidebar links use category permalinks with sanitized IDs');

if (strpos($templateHtml, 'https://example.com/post/321') === false) {
    $testsPassed = false;
    echo "[FAIL] Rendered HTML should contain the post permalink.\n";
} else {
    echo "[PASS] Rendered HTML contains the post permalink.\n";
}

if (strpos($templateHtml, 'https://example.com/category/654') === false) {
    $testsPassed = false;
    echo "[FAIL] Rendered HTML should contain the category link.\n";
} else {
    echo "[PASS] Rendered HTML contains the category link.\n";
}

if ($testsPassed) {
    echo "All sanitize_menu_settings tests passed.\n";
    exit(0);
}

echo "sanitize_menu_settings tests failed.\n";
exit(1);
