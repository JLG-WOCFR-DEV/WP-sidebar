<?php
declare(strict_types=1);

namespace JLG\Sidebar\Ajax {
    function error_log($message): bool
    {
        $GLOBALS['logged_errors'][] = $message;

        return true;
    }
}

namespace JLG\Sidebar\Icons {
    function wp_kses($string, $allowedHtml)
    {
        return $string;
    }
}

namespace {
    use JLG\Sidebar\Ajax\Endpoints;
    use function JLG\Sidebar\plugin;

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

$GLOBALS['wp_test_options'] = [];
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['wp_test_current_locale'] = 'fr_FR';
$GLOBALS['registered_actions'] = [];
$GLOBALS['registered_filters'] = [];
$GLOBALS['test_current_user_can'] = true;
$GLOBALS['json_success_payloads'] = [];
$GLOBALS['json_error_payloads'] = [];
$GLOBALS['checked_nonces'] = [];
$GLOBALS['test_get_posts_queue'] = [];
$GLOBALS['test_get_posts_requests'] = [];
$GLOBALS['test_get_categories_queue'] = [];
$GLOBALS['test_get_categories_requests'] = [];
$GLOBALS['triggered_actions'] = [];
$GLOBALS['logged_errors'] = [];
$GLOBALS['test_nonce_results'] = [];

function register_activation_hook($file, $callback): void {}
function wp_upload_dir(): array
{
    return [
        'basedir' => sys_get_temp_dir() . '/sidebar-jlg-test',
        'baseurl' => 'http://example.com/uploads',
    ];
}
function wp_mkdir_p(string $dir): bool { return true; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
{
    $GLOBALS['registered_actions'][] = compact('hook', 'callback', 'priority', 'accepted_args');
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
{
    $GLOBALS['registered_filters'][$hook][$priority][] = ['callback' => $callback, 'accepted_args' => $accepted_args];
}
function do_action($hook, ...$args): void
{
    $GLOBALS['triggered_actions'][] = ['hook' => $hook, 'args' => $args];
}
function apply_filters($hook, $value, ...$args)
{
    if (empty($GLOBALS['registered_filters'][$hook])) {
        return $value;
    }

    ksort($GLOBALS['registered_filters'][$hook]);
    foreach ($GLOBALS['registered_filters'][$hook] as $callbacks) {
        foreach ($callbacks as $data) {
            $callback = $data['callback'];
            $accepted = $data['accepted_args'];
            $value = $callback(...array_slice(array_merge([$value], $args), 0, $accepted));
        }
    }

    return $value;
}
function wp_parse_args($args, $defaults = [])
{
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
function trailingslashit($value): string
{
    return rtrim($value, "/\\") . '/';
}
function plugin_dir_path($file): string
{
    return trailingslashit(dirname($file));
}
function plugin_dir_url($file): string
{
    return 'http://example.com/plugin/';
}
function wp_enqueue_style(...$args): void {}
function wp_enqueue_script(...$args): void {}
function wp_register_script(...$args): void {}
function wp_enqueue_media(): void {}
function wp_localize_script(...$args): void {}
function wp_create_nonce($action): string
{
    return 'nonce-' . $action;
}
function admin_url($path = ''): string
{
    return 'http://example.com/wp-admin/' . ltrim($path, '/');
}
function get_option($name, $default = false)
{
    $store = $GLOBALS['wp_test_options'];
    return array_key_exists($name, $store) ? $store[$name] : $default;
}
function update_option($name, $value, $autoload = null): bool
{
    $GLOBALS['wp_test_options'][$name] = $value;
    return true;
}
function add_option($name, $value, $deprecated = '', $autoload = 'yes'): bool
{
    if (array_key_exists($name, $GLOBALS['wp_test_options'])) {
        return false;
    }

    $GLOBALS['wp_test_options'][$name] = $value;
    return true;
}
function delete_option($name): bool
{
    if (array_key_exists($name, $GLOBALS['wp_test_options'])) {
        unset($GLOBALS['wp_test_options'][$name]);
    }

    return true;
}
function get_transient($key)
{
    return $GLOBALS['wp_test_transients'][$key] ?? false;
}
function set_transient($key, $value, $expiration = 0): bool
{
    $GLOBALS['wp_test_transients'][$key] = $value;
    return true;
}
function delete_transient($key): bool
{
    if (array_key_exists($key, $GLOBALS['wp_test_transients'])) {
        unset($GLOBALS['wp_test_transients'][$key]);
    }

    return true;
}
function determine_locale(): string
{
    return $GLOBALS['wp_test_current_locale'];
}
function get_locale(): string
{
    return determine_locale();
}
function absint($value): int
{
    return abs((int) $value);
}
function wp_unslash($value)
{
    return $value;
}
function sanitize_key($key)
{
    $key = strtolower((string) $key);

    return preg_replace('/[^a-z0-9_\-]/', '', $key);
}
function sanitize_text_field($value)
{
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $value = (string) $value;
    $value = strip_tags($value);
    $value = preg_replace('/[\r\n\t ]+/', ' ', $value);

    return trim($value);
}
function wp_strip_all_tags($string, $remove_breaks = false): string
{
    $string = strip_tags((string) $string);

    if ($remove_breaks) {
        $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
    }

    return trim($string);
}
function add_menu_page(...$args): void {}
function register_setting(...$args): void {}
function esc_attr($value)
{
    return $value;
}
function esc_html($value)
{
    return $value;
}
function esc_url($value)
{
    return $value;
}
function esc_attr_e($text, $domain = 'default'): void
{
    echo esc_attr($text);
}
function __($text, $domain = 'default')
{
    return $text;
}
function esc_url_raw($value)
{
    return $value;
}

function current_user_can($capability): bool
{
    return $GLOBALS['test_current_user_can'];
}
function check_ajax_referer($action, $query_arg = false)
{
    $value = $query_arg !== false && isset($_POST[$query_arg]) ? $_POST[$query_arg] : null;
    $GLOBALS['checked_nonces'][] = [$action, $query_arg, $value];

    if (!empty($GLOBALS['test_nonce_results'])) {
        $outcome = $GLOBALS['test_nonce_results'][$action] ?? true;
        if ($outcome !== true) {
            $message = is_string($outcome) ? $outcome : 'Nonce invalide.';
            wp_send_json_error($message);
        }
    }
}

function wp_reset_postdata(): void {}

class WP_Die_Exception extends Exception {}

function wp_send_json_success($data = null): void
{
    $GLOBALS['json_success_payloads'][] = $data;
    throw new WP_Die_Exception('success');
}
function wp_send_json_error($data = null): void
{
    $GLOBALS['json_error_payloads'][] = $data;
    throw new WP_Die_Exception('error');
}

function get_posts($args = []): array
{
    $GLOBALS['test_get_posts_requests'][] = $args;
    if (empty($GLOBALS['test_get_posts_queue'])) {
        return [];
    }

    $next = array_shift($GLOBALS['test_get_posts_queue']);
    return $next['return'];
}
function get_categories($args = []): array
{
    $GLOBALS['test_get_categories_requests'][] = $args;
    if (empty($GLOBALS['test_get_categories_queue'])) {
        return [];
    }

    $next = array_shift($GLOBALS['test_get_categories_queue']);
    return $next['return'];
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$pluginInstance = plugin();
$endpoints = new Endpoints(
    $pluginInstance->getSettingsRepository(),
    $pluginInstance->getMenuCache(),
    $pluginInstance->getIconLibrary()
);

$testsPassed = true;

function assertTrue($condition, string $message): void
{
    global $testsPassed;
    if ($condition) {
        echo "[PASS] {$message}\n";
        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertSame($expected, $actual, string $message): void
{
    assertTrue($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

function reset_test_environment(): void
{
    $GLOBALS['json_success_payloads'] = [];
    $GLOBALS['json_error_payloads'] = [];
    $GLOBALS['checked_nonces'] = [];
    $GLOBALS['test_get_posts_queue'] = [];
    $GLOBALS['test_get_posts_requests'] = [];
    $GLOBALS['test_get_categories_queue'] = [];
    $GLOBALS['test_get_categories_requests'] = [];
    $GLOBALS['test_current_user_can'] = true;
    $GLOBALS['test_nonce_results'] = [];
    $GLOBALS['triggered_actions'] = [];
    $GLOBALS['logged_errors'] = [];
    $_POST = [];
}

function invoke_endpoint(Endpoints $endpoints, string $method): void
{
    try {
        $endpoints->$method();
    } catch (WP_Die_Exception $e) {
        // Expected to stop execution in tests.
    }
}

reset_test_environment();
$GLOBALS['test_current_user_can'] = false;
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame('Permission refusée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Unauthorized posts request rejected');
assertSame(0, count($GLOBALS['test_get_posts_requests']), 'Unauthorized posts request does not call get_posts');

reset_test_environment();
$GLOBALS['test_nonce_results']['jlg_ajax_nonce'] = 'Nonce invalide.';
$_POST = ['nonce' => 'invalid'];
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame('Nonce invalide.', $GLOBALS['json_error_payloads'][0] ?? null, 'Posts nonce failure returns error');
assertSame(['jlg_ajax_nonce', 'nonce', 'invalid'], $GLOBALS['checked_nonces'][0] ?? null, 'Posts nonce validation recorded before failure');
assertSame(0, count($GLOBALS['test_get_posts_requests']), 'Nonce failure prevents posts query');

reset_test_environment();
$_POST = ['nonce' => 'posts-nonce', 'posts_per_page' => '75'];
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame('Le paramètre posts_per_page ne peut pas dépasser 50.', $GLOBALS['json_error_payloads'][0] ?? null, 'Posts per-page cap enforced');
assertSame(['jlg_ajax_nonce', 'nonce', 'posts-nonce'], $GLOBALS['checked_nonces'][0] ?? null, 'Posts nonce checked before error');
assertSame(0, count($GLOBALS['test_get_posts_requests']), 'No posts queried when per-page cap exceeded');

reset_test_environment();
$GLOBALS['test_get_posts_queue'] = [
    ['return' => [
        (object) ['ID' => 2, 'post_title' => 'Second'],
        (object) ['ID' => 3, 'post_title' => 'Third'],
    ]],
    ['return' => [
        (object) ['ID' => 5, 'post_title' => 'Fifth'],
        (object) ['ID' => 4, 'post_title' => 'Fourth'],
    ]],
];
$_POST = [
    'nonce' => 'posts-success',
    'page' => '1',
    'posts_per_page' => '3',
    'include' => '5,2,4',
];
invoke_endpoint($endpoints, 'ajax_get_posts');
$expectedPosts = [
    ['id' => 5, 'title' => 'Fifth'],
    ['id' => 2, 'title' => 'Second'],
    ['id' => 4, 'title' => 'Fourth'],
    ['id' => 3, 'title' => 'Third'],
];
assertSame($expectedPosts, $GLOBALS['json_success_payloads'][0] ?? null, 'Posts include ordering respected');
assertSame(3, $GLOBALS['test_get_posts_requests'][0]['posts_per_page'] ?? null, 'Posts query limited to requested page size');
assertSame(['jlg_ajax_nonce', 'nonce', 'posts-success'], $GLOBALS['checked_nonces'][0] ?? null, 'Posts nonce validated for successful request');
assertSame(1, $GLOBALS['test_get_posts_requests'][0]['paged'] ?? null, 'Posts query requested correct page');
assertSame('post', $GLOBALS['test_get_posts_requests'][0]['post_type'] ?? null, 'Posts query requested default post type');
assertSame(2, $GLOBALS['test_get_posts_requests'][1]['posts_per_page'] ?? null, 'Posts include lookup limited to missing IDs');
assertSame([5, 4], array_values($GLOBALS['test_get_posts_requests'][1]['post__in'] ?? []), 'Posts include lookup requests missing IDs in order');
assertSame('post__in', $GLOBALS['test_get_posts_requests'][1]['orderby'] ?? null, 'Posts include lookup orders by requested IDs');
assertSame('post', $GLOBALS['test_get_posts_requests'][1]['post_type'] ?? null, 'Posts include lookup respects post type');

reset_test_environment();
$GLOBALS['test_get_posts_queue'] = [
    ['return' => []],
];
$_POST = [
    'nonce' => 'posts-search',
    'search' => '  Hello <em>World</em>  ',
    'post_type' => 'post',
];
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame('Hello World', $GLOBALS['test_get_posts_requests'][0]['s'] ?? null, 'Posts request includes sanitized search term');

reset_test_environment();
$GLOBALS['test_current_user_can'] = false;
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame('Permission refusée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Unauthorized categories request rejected');
assertSame(0, count($GLOBALS['test_get_categories_requests']), 'Unauthorized categories request does not call get_categories');

reset_test_environment();
$GLOBALS['test_nonce_results']['jlg_ajax_nonce'] = 'Nonce invalide.';
$_POST = ['nonce' => 'invalid'];
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame('Nonce invalide.', $GLOBALS['json_error_payloads'][0] ?? null, 'Categories nonce failure returns error');
assertSame(['jlg_ajax_nonce', 'nonce', 'invalid'], $GLOBALS['checked_nonces'][0] ?? null, 'Categories nonce validation recorded before failure');
assertSame(0, count($GLOBALS['test_get_categories_requests']), 'Nonce failure prevents categories query');

reset_test_environment();
$_POST = ['nonce' => 'cats-nonce', 'posts_per_page' => '120'];
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame('Le paramètre posts_per_page ne peut pas dépasser 50.', $GLOBALS['json_error_payloads'][0] ?? null, 'Categories per-page cap enforced');
assertSame(['jlg_ajax_nonce', 'nonce', 'cats-nonce'], $GLOBALS['checked_nonces'][0] ?? null, 'Categories nonce checked before per-page error');
assertSame(0, count($GLOBALS['test_get_categories_requests']), 'No categories queried when per-page cap exceeded');

reset_test_environment();
$GLOBALS['test_get_categories_queue'] = [
    ['return' => [
        (object) ['term_id' => 8, 'name' => 'Eight'],
        (object) ['term_id' => 11, 'name' => 'Eleven'],
    ]],
    ['return' => [
        (object) ['term_id' => 9, 'name' => 'Nine'],
        (object) ['term_id' => 10, 'name' => 'Ten'],
    ]],
];
$_POST = [
    'nonce' => 'cats-success',
    'page' => '2',
    'posts_per_page' => '40',
    'include' => ['9', '8', '10'],
];
invoke_endpoint($endpoints, 'ajax_get_categories');
$expectedCategories = [
    ['id' => 9, 'name' => 'Nine'],
    ['id' => 8, 'name' => 'Eight'],
    ['id' => 10, 'name' => 'Ten'],
    ['id' => 11, 'name' => 'Eleven'],
];
assertSame($expectedCategories, $GLOBALS['json_success_payloads'][0] ?? null, 'Categories include ordering respected');
assertSame(40, $GLOBALS['test_get_categories_requests'][0]['number'] ?? null, 'Categories query limited to requested per-page value');
assertSame(['jlg_ajax_nonce', 'nonce', 'cats-success'], $GLOBALS['checked_nonces'][0] ?? null, 'Categories nonce validated for successful request');
assertSame(40, $GLOBALS['test_get_categories_requests'][0]['offset'] ?? null, 'Categories query offset honors page');
assertSame(false, $GLOBALS['test_get_categories_requests'][0]['hide_empty'] ?? null, 'Categories query disables hide_empty');
assertSame(false, $GLOBALS['test_get_categories_requests'][1]['hide_empty'] ?? null, 'Categories include lookup disables hide_empty');
assertSame([9, 10], array_values($GLOBALS['test_get_categories_requests'][1]['include'] ?? []), 'Categories include lookup requests missing IDs in order');
assertSame(2, $GLOBALS['test_get_categories_requests'][1]['number'] ?? null, 'Categories include lookup limited to missing IDs');
assertSame('include', $GLOBALS['test_get_categories_requests'][1]['orderby'] ?? null, 'Categories include lookup orders by requested IDs');

reset_test_environment();
$GLOBALS['test_get_categories_queue'] = [
    ['return' => []],
];
$_POST = [
    'nonce' => 'cats-search',
    'search' => "  Termé <script>alert('x')</script>  ",
];
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame("Termé alert('x')", $GLOBALS['test_get_categories_requests'][0]['search'] ?? null, 'Categories request includes sanitized search term');

reset_test_environment();
$GLOBALS['test_current_user_can'] = false;
$GLOBALS['wp_test_options'] = ['sidebar_jlg_settings' => ['should' => 'stay']];
$GLOBALS['wp_test_transients'] = ['sidebar_jlg_full_html_default' => '<div>keep</div>'];
invoke_endpoint($endpoints, 'ajax_reset_settings');
assertSame('Permission refusée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Unauthorized reset request rejected');
assertSame([], $GLOBALS['checked_nonces'], 'Nonce not validated when reset unauthorized');
assertSame(['should' => 'stay'], get_option('sidebar_jlg_settings'), 'Unauthorized reset leaves settings untouched');
assertSame('<div>keep</div>', get_transient('sidebar_jlg_full_html_default'), 'Unauthorized reset leaves caches untouched');

reset_test_environment();
$GLOBALS['wp_test_options'] = ['sidebar_jlg_settings' => ['should' => 'stay']];
$GLOBALS['wp_test_transients'] = ['sidebar_jlg_full_html_default' => '<div>keep</div>'];
$GLOBALS['test_nonce_results']['jlg_reset_nonce'] = 'Nonce invalide.';
$_POST = ['nonce' => 'bad'];
invoke_endpoint($endpoints, 'ajax_reset_settings');
assertSame('Nonce invalide.', $GLOBALS['json_error_payloads'][0] ?? null, 'Reset nonce failure returns error');
assertSame(['jlg_reset_nonce', 'nonce', 'bad'], $GLOBALS['checked_nonces'][0] ?? null, 'Reset nonce validated before failure');
assertSame(['should' => 'stay'], get_option('sidebar_jlg_settings'), 'Nonce failure leaves settings untouched');
assertSame('<div>keep</div>', get_transient('sidebar_jlg_full_html_default'), 'Nonce failure leaves caches untouched');

reset_test_environment();
$GLOBALS['wp_test_options'] = [
    'sidebar_jlg_settings' => ['foo' => 'bar'],
    'sidebar_jlg_cached_locales' => ['default', 'fr_FR'],
];
$GLOBALS['wp_test_transients'] = [
    'sidebar_jlg_full_html_default' => '<div>cached</div>',
    'sidebar_jlg_full_html_fr_FR' => '<div>cached-fr</div>',
    'sidebar_jlg_full_html' => '<div>legacy</div>',
];
$_POST = ['nonce' => 'reset-nonce'];
invoke_endpoint($endpoints, 'ajax_reset_settings');
assertSame('Réglages réinitialisés.', $GLOBALS['json_success_payloads'][0] ?? null, 'Reset settings success message returned');
assertSame('missing', get_option('sidebar_jlg_settings', 'missing'), 'Settings option deleted during reset');
assertSame('missing', get_option('sidebar_jlg_cached_locales', 'missing'), 'Cached locales option deleted during reset');
assertSame(false, get_transient('sidebar_jlg_full_html_default'), 'Default locale cache cleared during reset');
assertSame(false, get_transient('sidebar_jlg_full_html_fr_FR'), 'Additional locale cache cleared during reset');
assertSame(false, get_transient('sidebar_jlg_full_html'), 'Legacy cache cleared during reset');
assertSame(['jlg_reset_nonce', 'nonce', 'reset-nonce'], $GLOBALS['checked_nonces'][0] ?? null, 'Reset nonce validated before clearing settings');

reset_test_environment();
$GLOBALS['test_current_user_can'] = false;
invoke_endpoint($endpoints, 'ajax_get_icon_svg');
assertSame('Permission refusée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Unauthorized icon request rejected');
assertSame([], $GLOBALS['checked_nonces'], 'Unauthorized icon request skips nonce validation');

reset_test_environment();
$_POST = ['nonce' => 'icons-empty'];
invoke_endpoint($endpoints, 'ajax_get_icon_svg');
assertSame('Aucune icône demandée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Icon request without keys returns error');
assertSame(['jlg_ajax_nonce', 'nonce', 'icons-empty'], $GLOBALS['checked_nonces'][0] ?? null, 'Icon nonce validated before missing request error');

reset_test_environment();
$_POST = ['nonce' => 'icons-missing', 'icons' => ['unknown']];
invoke_endpoint($endpoints, 'ajax_get_icon_svg');
assertSame('Icône introuvable.', $GLOBALS['json_error_payloads'][0] ?? null, 'Icon request with unknown key returns error');
assertSame(['jlg_ajax_nonce', 'nonce', 'icons-missing'], $GLOBALS['checked_nonces'][0] ?? null, 'Icon nonce validated before unknown icon error');

reset_test_environment();
$_POST = ['nonce' => 'icons-success', 'icons' => ['home_white', 'custom_missing', 'HOME_WHITE']];
invoke_endpoint($endpoints, 'ajax_get_icon_svg');
$iconPayload = $GLOBALS['json_success_payloads'][0] ?? [];
assertTrue(isset($iconPayload['home_white']), 'Icon response includes sanitized key');
assertSame(1, count($iconPayload), 'Icon response excludes duplicates and unknown values');

reset_test_environment();
$icons = [];
for ($i = 0; $i < 25; $i++) {
    $icons[] = 'icon_' . $i;
}
$_POST = ['nonce' => 'icons-limit', 'icons' => $icons];
invoke_endpoint($endpoints, 'ajax_get_icon_svg');
assertSame('Vous ne pouvez demander que 20 icônes à la fois.', $GLOBALS['json_error_payloads'][0] ?? null, 'Icon limit enforcement returns localized error');
assertSame([], $GLOBALS['json_success_payloads'], 'Icon limit enforcement does not return icon payload');
$triggered = $GLOBALS['triggered_actions'][0] ?? null;
assertSame('sidebar_jlg_icon_request_limit_exceeded', $triggered['hook'] ?? null, 'Icon limit hook triggered when request exceeds cap');
assertSame(25, $triggered['args'][0] ?? null, 'Icon limit hook receives total icon count');
assertSame(25, count($triggered['args'][1] ?? []), 'Icon limit hook receives sanitized icon list');
assertSame('[Sidebar JLG] Icon SVG request rejected: 25 icons requested (limit: 20).', $GLOBALS['logged_errors'][0] ?? null, 'Icon limit rejection logged');

if ($testsPassed) {
    echo "AJAX endpoints tests passed.\n";
    exit(0);
}

echo "AJAX endpoints tests failed.\n";
exit(1);
}
