<?php
declare(strict_types=1);

use JLG\Sidebar\Sidebar_JLG;

define('ABSPATH', true);
define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

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
    return 'http://example.com/category/' . $cat_id;
}
function get_bloginfo($show = '', $filter = 'raw') {
    return 'Test Blog';
}
function do_shortcode($content) {
    $GLOBALS['wp_test_shortcode_calls'] = ($GLOBALS['wp_test_shortcode_calls'] ?? 0) + 1;

    return $content . ' #' . $GLOBALS['wp_test_shortcode_calls'];
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

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = Sidebar_JLG::get_instance();

$default_settings = $plugin->get_default_settings();
$default_settings['social_icons'] = [];
update_option('sidebar_jlg_settings', $default_settings);

$input_settings = [
    'menu_items' => [
        [
            'label' => 'Article SVG',
            'type' => 'post',
            'icon_type' => 'svg_url',
            'icon' => 'https://example.com/icon.svg',
            'value' => '789',
        ],
        [
            'label' => 'Catégorie liens',
            'type' => 'category',
            'icon_type' => 'svg_inline',
            'icon' => 'folder',
            'value' => '321',
        ],
    ],
];

$sanitized_settings = $plugin->sanitize_settings($input_settings);

assertTrue(
    isset($sanitized_settings['menu_items'][0]['value']) && $sanitized_settings['menu_items'][0]['value'] === 789,
    'Post ID sanitized with absint even when icon type is svg_url'
);
assertTrue(
    isset($sanitized_settings['menu_items'][1]['value']) && $sanitized_settings['menu_items'][1]['value'] === 321,
    'Category ID preserved after sanitization'
);

update_option('sidebar_jlg_settings', $sanitized_settings);

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

function assertContains(string $needle, string $haystack, string $message): void {
    assertTrue(strpos($haystack, $needle) !== false, $message);
}

function assertNotContains(string $needle, string $haystack, string $message): void {
    assertTrue(strpos($haystack, $needle) === false, $message);
}

$plugin->clear_menu_cache();
$GLOBALS['wp_test_transients'] = [];

switch_to_locale('fr_FR');
ob_start();
$plugin->render_sidebar_html();
$french_html = ob_get_clean();

assertContains('Ouvrir le menu', $french_html, 'French menu label rendered');
assertContains('href="http://example.com/post/789"', $french_html, 'Post menu item links to the correct article');
assertContains('href="http://example.com/category/321"', $french_html, 'Category menu item links to the correct term');
assertNotContains('Open menu', $french_html, 'English menu label absent in French cache');
assertTrue(isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']), 'French transient stored');

switch_to_locale('en_US');
ob_start();
$plugin->render_sidebar_html();
$english_html = ob_get_clean();

assertContains('Open menu', $english_html, 'English menu label rendered after locale switch');
assertNotContains('Ouvrir le menu', $english_html, 'French label absent in English cache');
assertTrue(isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US']), 'English transient stored');

switch_to_locale('fr_FR');
ob_start();
$plugin->render_sidebar_html();
$french_cached_html = ob_get_clean();

assertContains('Ouvrir le menu', $french_cached_html, 'French cache reused correctly');

$cached_locales_option = get_option('sidebar_jlg_cached_locales', []);
assertTrue(in_array('fr_FR', $cached_locales_option, true), 'French locale tracked');
assertTrue(in_array('en_US', $cached_locales_option, true), 'English locale tracked');

$plugin->clear_menu_cache();

assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']), 'French transient cleared');
assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US']), 'English transient cleared');
assertTrue(!isset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']), 'Cached locales option cleared');

$plugin->clear_menu_cache();
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['wp_test_shortcode_calls'] = 0;

$dynamic_settings = $plugin->get_default_settings();
$dynamic_settings['social_icons'] = [];
$dynamic_settings['enable_search'] = true;
$dynamic_settings['search_method'] = 'shortcode';
$dynamic_settings['search_shortcode'] = '[dynamic]';

update_option('sidebar_jlg_settings', $dynamic_settings);

switch_to_locale('en_US');
ob_start();
$plugin->render_sidebar_html();
$first_dynamic_html = ob_get_clean();

$dynamic_transient_key = 'sidebar_jlg_full_html_en_US';
assertTrue(!isset($GLOBALS['wp_test_transients'][$dynamic_transient_key]), 'Dynamic sidebar render skips transient storage');
assertContains('#1', $first_dynamic_html, 'Dynamic render includes first shortcode marker');

ob_start();
$plugin->render_sidebar_html();
$second_dynamic_html = ob_get_clean();

assertContains('#2', $second_dynamic_html, 'Dynamic render increments shortcode marker on subsequent render');
assertTrue($first_dynamic_html !== $second_dynamic_html, 'Dynamic HTML regenerated for each render when cache disabled');
assertTrue(!isset($GLOBALS['wp_test_transients'][$dynamic_transient_key]), 'Dynamic sidebar never stores persistent transients');
assertTrue(empty(get_option('sidebar_jlg_cached_locales', [])), 'Cached locales not tracked when cache is disabled');

if ($testsPassed) {
    echo "Sidebar locale cache tests passed.\n";
    exit(0);
}

echo "Sidebar locale cache tests failed.\n";
exit(1);
