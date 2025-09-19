<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

if (!defined('SIDEBAR_JLG_SKIP_BOOTSTRAP')) {
    define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);
}

$GLOBALS['wp_test_options']    = [];
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['wp_test_current_locale'] = 'fr_FR';
$GLOBALS['wp_test_translations'] = [
    'fr_FR' => [
        'Navigation principale' => 'Navigation principale',
        'Ouvrir le menu'        => 'Ouvrir le menu',
        'Fermer le menu'        => 'Fermer le menu',
    ],
    'en_US' => [
        'Navigation principale' => 'Main navigation',
        'Ouvrir le menu'        => 'Open menu',
        'Fermer le menu'        => 'Close menu',
    ],
];

$GLOBALS['wp_test_posts'] = [
    'post' => [
        ['ID' => 101, 'post_title' => 'Premier article'],
        ['ID' => 102, 'post_title' => 'Deuxième article'],
        ['ID' => 103, 'post_title' => 'Troisième article'],
        ['ID' => 104, 'post_title' => 'Quatrième article'],
    ],
    'page' => [
        ['ID' => 201, 'post_title' => 'Page spéciale'],
    ],
];

$GLOBALS['wp_test_categories'] = [
    ['term_id' => 301, 'name' => 'Actualités'],
    ['term_id' => 302, 'name' => 'Événements'],
    ['term_id' => 303, 'name' => 'Dossiers'],
];

$GLOBALS['wp_test_current_user_can'] = true;
$GLOBALS['wp_test_last_json_response'] = null;
$GLOBALS['wp_test_last_referer_check'] = null;

class WP_Test_Ajax_Stop extends Exception {}

function register_activation_hook($file, $callback): void {}
function wp_upload_dir(): array {
    return [
        'basedir' => sys_get_temp_dir() . '/sidebar-jlg-test',
        'baseurl' => 'http://example.com/uploads',
    ];
}
function wp_mkdir_p(string $dir): bool { return true; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void {}
function apply_filters($hook, $value, ...$args) {
    return $value;
}
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
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

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
function sanitize_hex_color($color) {
    $color = trim((string) $color);
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
        return strtolower($color);
    }

    return '';
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
    $locale = determine_locale();
    $translations = $GLOBALS['wp_test_translations'];

    if (isset($translations[$locale][$text])) {
        return $translations[$locale][$text];
    }

    return $text;
}
function _e($text, $domain = 'default'): void {
    echo __($text, $domain);
}

function current_user_can($capability): bool {
    return !empty($GLOBALS['wp_test_current_user_can']);
}
function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
    $value = null;
    if ($query_arg && isset($_POST[$query_arg])) {
        $value = $_POST[$query_arg];
    }

    $GLOBALS['wp_test_last_referer_check'] = [
        'action' => $action,
        'query_arg' => $query_arg,
        'value' => $value,
    ];

    return true;
}
function wp_send_json_success($data = null) {
    $GLOBALS['wp_test_last_json_response'] = [
        'success' => true,
        'data'    => $data,
    ];

    throw new WP_Test_Ajax_Stop();
}
function wp_send_json_error($data = null) {
    $GLOBALS['wp_test_last_json_response'] = [
        'success' => false,
        'data'    => $data,
    ];

    throw new WP_Test_Ajax_Stop();
}
function get_posts($args = []) {
    $defaults = [
        'posts_per_page' => 20,
        'paged' => 1,
        'post_type' => 'post',
        'post__in' => null,
    ];
    $args = wp_parse_args($args, $defaults);
    $postType = $args['post_type'];
    $allPosts = $GLOBALS['wp_test_posts'][$postType] ?? [];

    if (!empty($args['post__in'])) {
        $includeIds = array_map('intval', (array) $args['post__in']);
        $filtered = [];
        foreach ($includeIds as $includeId) {
            foreach ($allPosts as $post) {
                if ((int) $post['ID'] === $includeId) {
                    $filtered[] = $post;
                    break;
                }
            }
        }

        return array_map(static function ($post) {
            return (object) $post;
        }, $filtered);
    }

    $perPage = max(1, (int) $args['posts_per_page']);
    $page = max(1, (int) $args['paged']);
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($allPosts, $offset, $perPage);

    return array_map(static function ($post) {
        return (object) $post;
    }, $slice);
}
function get_categories($args = []) {
    $defaults = [
        'hide_empty' => false,
        'number' => 20,
        'offset' => 0,
        'include' => [],
    ];
    $args = wp_parse_args($args, $defaults);
    $allCategories = $GLOBALS['wp_test_categories'];

    if (!empty($args['include'])) {
        $includeIds = array_map('intval', (array) $args['include']);
        $filtered = [];
        foreach ($includeIds as $includeId) {
            foreach ($allCategories as $category) {
                if ((int) $category['term_id'] === $includeId) {
                    $filtered[] = $category;
                    break;
                }
            }
        }

        return array_map(static function ($category) {
            return (object) $category;
        }, $filtered);
    }

    $number = max(1, (int) $args['number']);
    $offset = max(0, (int) $args['offset']);
    $slice = array_slice($allCategories, $offset, $number);

    return array_map(static function ($category) {
        return (object) $category;
    }, $slice);
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$menuCache = $plugin->getMenuCache();

$endpoints = new JLG\Sidebar\Ajax\Endpoints($settingsRepository, $menuCache);

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

function assertSame($expected, $actual, string $message): void {
    assertTrue($expected === $actual, $message);
}

function assertContains(string $needle, string $haystack, string $message): void {
    assertTrue(strpos($haystack, $needle) !== false, $message);
}

function reset_json_response(): void {
    $GLOBALS['wp_test_last_json_response'] = null;
}

function run_ajax(callable $callback): void {
    try {
        $callback();
    } catch (WP_Test_Ajax_Stop $e) {
        // Swallow the simulated exit.
    }
}

reset_json_response();
$GLOBALS['wp_test_current_user_can'] = true;
$_POST = [
    'nonce' => 'abc',
    'posts_per_page' => 2,
    'post_type' => 'post',
    'include' => [102, 101],
];
run_ajax([$endpoints, 'ajax_get_posts']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue($response['success'] ?? false, 'Authorized posts request returns success');
assertSame(2, is_array($response['data']) ? count($response['data']) : 0, 'Posts response limited to requested per-page count');
assertSame(102, $response['data'][0]['id'] ?? null, 'Posts include ordering preserved for first item');
assertSame(101, $response['data'][1]['id'] ?? null, 'Posts include ordering preserved for second item');

reset_json_response();
$_POST = [
    'nonce' => 'abc',
    'posts_per_page' => 60,
];
run_ajax([$endpoints, 'ajax_get_posts']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue(($response['success'] ?? true) === false, 'Posts request exceeding cap returns error');
assertContains('ne peut pas dépasser 50', (string) ($response['data'] ?? ''), 'Posts cap error message mentions limit');

reset_json_response();
$GLOBALS['wp_test_current_user_can'] = false;
$_POST = [];
run_ajax([$endpoints, 'ajax_get_posts']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue(($response['success'] ?? true) === false, 'Unauthorized posts request short-circuits with error');
assertSame('Permission refusée.', $response['data'] ?? '', 'Unauthorized posts request returns permission error');

reset_json_response();
$GLOBALS['wp_test_current_user_can'] = true;
$_POST = [
    'nonce' => 'def',
    'posts_per_page' => 2,
    'include' => [302, 301],
];
run_ajax([$endpoints, 'ajax_get_categories']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue($response['success'] ?? false, 'Authorized categories request returns success');
assertSame(2, is_array($response['data']) ? count($response['data']) : 0, 'Categories response limited to requested per-page count');
assertSame(302, $response['data'][0]['id'] ?? null, 'Categories include ordering preserved for first item');
assertSame(301, $response['data'][1]['id'] ?? null, 'Categories include ordering preserved for second item');

reset_json_response();
$_POST = [
    'nonce' => 'def',
    'posts_per_page' => 75,
];
run_ajax([$endpoints, 'ajax_get_categories']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue(($response['success'] ?? true) === false, 'Categories request exceeding cap returns error');
assertContains('ne peut pas dépasser 50', (string) ($response['data'] ?? ''), 'Categories cap error message mentions limit');

reset_json_response();
$GLOBALS['wp_test_current_user_can'] = false;
$_POST = [];
run_ajax([$endpoints, 'ajax_get_categories']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue(($response['success'] ?? true) === false, 'Unauthorized categories request short-circuits with error');
assertSame('Permission refusée.', $response['data'] ?? '', 'Unauthorized categories request returns permission error');

$GLOBALS['wp_test_current_user_can'] = true;
reset_json_response();
update_option('sidebar_jlg_settings', ['foo' => 'bar']);
set_transient('sidebar_jlg_full_html_fr_FR', '<div>cache</div>');
set_transient('sidebar_jlg_full_html', '<div>legacy</div>');
update_option('sidebar_jlg_cached_locales', ['fr_FR', 'en_US']);
$_POST = [
    'nonce' => 'reset',
];
run_ajax([$endpoints, 'ajax_reset_settings']);
$response = $GLOBALS['wp_test_last_json_response'];
assertTrue($response['success'] ?? false, 'Reset settings request returns success');
assertSame('Réglages réinitialisés.', $response['data'] ?? '', 'Reset settings returns confirmation message');
assertSame('default', get_option('sidebar_jlg_settings', 'default'), 'Sidebar settings option deleted during reset');
assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']), 'Locale cache transient cleared during reset');
assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html']), 'Legacy transient cleared during reset');
assertTrue(!isset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']), 'Cached locales option removed during reset');

if ($testsPassed) {
    echo "AJAX endpoints tests passed.\n";
    exit(0);
}

echo "AJAX endpoints tests failed.\n";
exit(1);
