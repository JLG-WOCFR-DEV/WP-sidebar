<?php
declare(strict_types=1);

use JLG\Sidebar\Sidebar_JLG;

define('ABSPATH', true);
define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

$GLOBALS['wp_test_options']    = [];
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['wp_test_current_locale'] = 'fr_FR';
$GLOBALS['wp_test_force_category_link_error'] = true;

function register_activation_hook($file, $callback): void {}
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public $code = '', public $message = '', public $data = null) {}
    }
}
function is_wp_error($thing): bool {
    return $thing instanceof WP_Error;
}
function wp_upload_dir(): array {
    return [
        'basedir' => sys_get_temp_dir() . '/sidebar-jlg-test',
        'baseurl' => 'http://example.com/uploads',
    ];
}
function wp_mkdir_p(string $dir): bool { return true; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void {}
function wp_parse_args($args, $defaults = []) {
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (!is_array($args)) {
        $args = [];
    }

    if (!is_array($defaults)) {
        $defaults = [];
    }

    return array_merge($defaults, $args);
}
function trailingslashit($value): string {
    return rtrim($value, "/\\") . '/';
}
function plugin_dir_path($file): string {
    return trailingslashit(dirname($file));
}
function plugin_dir_url($file): string {
    return 'http://example.com/plugin/';
}
function wp_enqueue_style(...$args): void {}
function wp_enqueue_script(...$args): void {}
function wp_register_script(...$args): void {}
function wp_enqueue_media(): void {}
function wp_localize_script(...$args): void {}
function wp_create_nonce($action): string {
    return 'nonce-' . $action;
}
function admin_url($path = ''): string {
    return 'http://example.com/wp-admin/' . ltrim($path, '/');
}
function get_option($name, $default = false) {
    global $wp_test_options;
    if (array_key_exists($name, $wp_test_options)) {
        return $wp_test_options[$name];
    }

    return $default;
}
function update_option($name, $value, $autoload = null): bool {
    global $wp_test_options;
    $wp_test_options[$name] = $value;

    return true;
}
function add_option($name, $value, $deprecated = '', $autoload = 'yes'): bool {
    global $wp_test_options;
    if (array_key_exists($name, $wp_test_options)) {
        return false;
    }

    $wp_test_options[$name] = $value;

    return true;
}
function delete_option($name): bool {
    global $wp_test_options;
    if (array_key_exists($name, $wp_test_options)) {
        unset($wp_test_options[$name]);
    }

    return true;
}
function get_transient($key) {
    global $wp_test_transients;
    return $wp_test_transients[$key] ?? false;
}
function set_transient($key, $value, $expiration = 0): bool {
    global $wp_test_transients;
    $wp_test_transients[$key] = $value;

    return true;
}
function delete_transient($key): bool {
    global $wp_test_transients;
    if (array_key_exists($key, $wp_test_transients)) {
        unset($wp_test_transients[$key]);
    }

    return true;
}
function determine_locale(): string {
    return $GLOBALS['wp_test_current_locale'];
}
function get_locale(): string {
    return determine_locale();
}
function switch_to_locale(string $locale): bool {
    $GLOBALS['wp_test_current_locale'] = $locale;

    return true;
}
function esc_attr($value) {
    return $value;
}
function esc_html($value) {
    return $value;
}
function esc_url($value) {
    return $value;
}
function esc_attr_e($text, $domain = 'default'): void {
    echo esc_attr(__($text, $domain));
}
function esc_url_raw($value) {
    return $value;
}
function absint($value): int {
    return abs((int) $value);
}
function wp_unslash($value) {
    return $value;
}
function wp_check_filetype($file, $allowed = []) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);

    if ($extension === '') {
        return ['ext' => '', 'type' => ''];
    }

    return [
        'ext'  => $extension,
        'type' => $allowed[$extension] ?? 'image/' . $extension,
    ];
}
function wp_kses($string, $allowed_html = []) {
    return $string;
}
function sanitize_text_field($value) {
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $value = (string) $value;
    $value = strip_tags($value);
    $value = preg_replace('/[\r\n\t ]+/', ' ', $value);

    return trim($value);
}
function sanitize_key($key) {
    $key = strtolower((string) $key);
    return preg_replace('/[^a-z0-9_\-]/', '', $key);
}
function get_permalink($post_id) {
    return 'http://example.com/post/' . $post_id;
}
function get_category_link($cat_id) {
    if (!empty($GLOBALS['wp_test_force_category_link_error'])) {
        return new WP_Error('mock_error', 'Mocked category error');
    }

    return 'http://example.com/category/' . $cat_id;
}
function get_bloginfo($show = '', $filter = 'raw') {
    return 'Test Blog';
}
function do_shortcode($content) {
    return $content;
}
function do_action($hook, ...$args): void {}
function get_search_form(): string {
    return 'SEARCH_FORM';
}
function wp_kses_post($string) {
    return $string;
}
function __($text, $domain = 'default') {
    return $text;
}
function _e($text, $domain = 'default'): void {
    echo __($text, $domain);
}

update_option('sidebar_jlg_settings', [
    'enable_sidebar' => true,
    'menu_items' => [
        [
            'label' => 'Catégorie en erreur',
            'type'  => 'category',
            'icon'  => '',
            'icon_type' => '',
            'value' => '321',
        ],
    ],
    'social_icons' => [],
]);

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = Sidebar_JLG::get_instance();
$plugin->clear_menu_cache();

ob_start();
$plugin->render_sidebar_html();
$html = ob_get_clean();

$testsPassed = true;
function assertTrue($condition, string $message): void {
    global $testsPassed;
    if ($condition) {
        echo "[PASS] {$message}\n";
        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

assertTrue(strpos($html, 'href="#"') !== false, 'Category link falls back to hash when WP_Error returned');
assertTrue($html !== '', 'Sidebar HTML rendered despite WP_Error');

if ($testsPassed) {
    echo "Sidebar WP_Error handling test passed.\n";
    exit(0);
}

echo "Sidebar WP_Error handling test failed.\n";
exit(1);
