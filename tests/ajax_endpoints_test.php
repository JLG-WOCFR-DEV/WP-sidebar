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
    use JLG\Sidebar\Accessibility\AuditRunner;
    use JLG\Sidebar\Ajax\Endpoints;
    use JLG\Sidebar\Analytics\AnalyticsEventQueue;
    use JLG\Sidebar\Analytics\EventRateLimiter;
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
$GLOBALS['wp_test_cron_events'] = [];
$GLOBALS['registered_rest_routes'] = [];
$GLOBALS['wp_test_object_cache'] = [];

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path)
    {
        $normalized = str_replace('\\', '/', (string) $path);

        return preg_replace('#/+#', '/', $normalized);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($string)
    {
        return $string;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null)
    {
        $groupKey = $group !== '' ? $group : 'default';
        $store = $GLOBALS['wp_test_object_cache'][$groupKey] ?? [];

        if (!array_key_exists($key, $store)) {
            if ($found !== null) {
                $found = false;
            }

            return false;
        }

        $item = $store[$key];
        $expiration = $item['expiration'] ?? 0;

        if ($expiration > 0 && $expiration < time()) {
            unset($GLOBALS['wp_test_object_cache'][$groupKey][$key]);
            if ($found !== null) {
                $found = false;
            }

            return false;
        }

        if ($found !== null) {
            $found = true;
        }

        return $item['value'];
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0): bool
    {
        $groupKey = $group !== '' ? $group : 'default';
        $expiration = $expire > 0 ? time() + (int) $expire : 0;

        if (!isset($GLOBALS['wp_test_object_cache'][$groupKey])) {
            $GLOBALS['wp_test_object_cache'][$groupKey] = [];
        }

        $GLOBALS['wp_test_object_cache'][$groupKey][$key] = [
            'value' => $data,
            'expiration' => $expiration,
        ];

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = ''): bool
    {
        $groupKey = $group !== '' ? $group : 'default';

        if (!isset($GLOBALS['wp_test_object_cache'][$groupKey][$key])) {
            return false;
        }

        unset($GLOBALS['wp_test_object_cache'][$groupKey][$key]);

        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string, array<int, string>> */
        private array $errors = [];

        public function __construct($code = '', $message = '')
        {
            if ($code !== '') {
                $this->errors[(string) $code][] = is_string($message) ? $message : '';
            }
        }

        public function get_error_message($code = ''): string
        {
            if ($code !== '' && isset($this->errors[$code][0])) {
                return $this->errors[$code][0];
            }

            foreach ($this->errors as $messages) {
                if (!empty($messages[0])) {
                    return $messages[0];
                }
            }

            return '';
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string, mixed> */
        private $params;

        /**
         * @param array<string, mixed> $params
         */
        public function __construct(array $params = [])
        {
            $this->params = $params;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_json_params(): array
        {
            return $this->params;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var mixed */
        private $data;

        /** @var int */
        private $status;

        public function __construct($data = null, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

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
function register_rest_route($namespace, $route, $args, $override = false)
{
    $GLOBALS['registered_rest_routes'][] = [
        'namespace' => $namespace,
        'route' => $route,
        'args' => $args,
        'override' => $override,
    ];

    return true;
}
function wp_schedule_single_event($timestamp, $hook, $args = []): bool
{
    if (!isset($GLOBALS['wp_test_cron_events'][$hook])) {
        $GLOBALS['wp_test_cron_events'][$hook] = [];
    }

    $GLOBALS['wp_test_cron_events'][$hook][] = [
        'timestamp' => (int) $timestamp,
        'args' => $args,
    ];

    return true;
}
function wp_next_scheduled($hook, $args = [])
{
    if (empty($GLOBALS['wp_test_cron_events'][$hook])) {
        return false;
    }

    $timestamps = array_map(static function ($event) {
        return (int) ($event['timestamp'] ?? 0);
    }, $GLOBALS['wp_test_cron_events'][$hook]);

    $timestamps = array_filter($timestamps, static function ($value) {
        return $value > 0;
    });

    if ($timestamps === []) {
        return false;
    }

    sort($timestamps);

    return $timestamps[0];
}
function wp_clear_scheduled_hook($hook, $args = []): bool
{
    unset($GLOBALS['wp_test_cron_events'][$hook]);

    return true;
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
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false): string
    {
        $string = strip_tags((string) $string);

        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}
function add_menu_page(...$args): void {}
if (!function_exists('register_setting')) {
    function register_setting(...$args): void {}
}
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
$GLOBALS['pluginInstance'] = $pluginInstance;
$auditRunner = new AuditRunner($pluginInstance->getPluginFile());
$rateLimiter = new EventRateLimiter();
$endpoints = new Endpoints(
    $pluginInstance->getSettingsRepository(),
    $pluginInstance->getMenuCache(),
    $pluginInstance->getIconLibrary(),
    $pluginInstance->getSanitizer(),
    $pluginInstance->getAnalyticsRepository(),
    $pluginInstance->getAnalyticsQueue(),
    $rateLimiter,
    $pluginInstance->getPluginFile(),
    $pluginInstance->getSidebarRenderer(),
    $auditRunner
);

$endpoints->registerHooks();

foreach ($GLOBALS['registered_actions'] as $registeredAction) {
    if (($registeredAction['hook'] ?? '') !== 'rest_api_init') {
        continue;
    }

    $callback = $registeredAction['callback'];
    if (is_callable($callback)) {
        $accepted = (int) ($registeredAction['accepted_args'] ?? 0);
        if ($accepted > 0) {
            $callback();
        } else {
            $callback();
        }
    }
}

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

$registeredHooks = array_column($GLOBALS['registered_actions'], 'hook');
assertTrue(in_array('wp_ajax_jlg_canvas_update_item', $registeredHooks, true), 'Canvas update AJAX hook registered');
assertTrue(in_array('wp_ajax_jlg_canvas_reorder_items', $registeredHooks, true), 'Canvas reorder AJAX hook registered');
$restRoutes = array_column($GLOBALS['registered_rest_routes'], 'route');
assertTrue(in_array('/canvas/item', $restRoutes, true), 'Canvas update REST route registered');
assertTrue(in_array('/canvas/order', $restRoutes, true), 'Canvas reorder REST route registered');

reset_test_environment();
$defaultsForCanvas = $pluginInstance->getSettingsRepository()->getDefaultSettings();
$defaultsForCanvas['menu_items'] = [
    [
        'type' => 'custom',
        'label' => 'Accueil',
        'value' => 12,
        'icon' => 'home_white',
        'icon_type' => 'svg_inline',
    ],
    [
        'type' => 'cta',
        'label' => 'CTA',
        'cta_title' => 'Titre CTA',
        'cta_description' => 'Description CTA',
        'cta_button_label' => 'Agir',
        'cta_button_url' => 'https://example.com',
        'icon' => 'star',
        'icon_type' => 'svg_inline',
        'cta_button_color' => 'rgba(0,0,0,1)',
    ],
];
$pluginInstance->getSettingsRepository()->saveOptions($defaultsForCanvas);
$_POST = [
    'nonce' => 'nonce-jlg_canvas_nonce',
    'index' => '1',
    'item' => json_encode([
        'label' => 'CTA Hero',
        'icon' => 'sparkle',
        'cta_button_color' => 'rgba(255,0,0,1)',
    ]),
];
invoke_endpoint($endpoints, 'ajax_canvas_update_item');
$canvasUpdatePayload = $GLOBALS['json_success_payloads'][0] ?? [];
assertSame('CTA Hero', $canvasUpdatePayload['items'][1]['label'] ?? null, 'Canvas AJAX update applies new label');
assertSame('rgba(255,0,0,1)', $canvasUpdatePayload['items'][1]['cta_button_color'] ?? null, 'Canvas AJAX update returns updated color');
$canvasStored = $pluginInstance->getSettingsRepository()->getOptions();
assertSame('rgba(255,0,0,1)', $canvasStored['menu_items'][1]['cta_button_color'] ?? null, 'Canvas AJAX update persists CTA color');

reset_test_environment();
$defaultsForReorder = $pluginInstance->getSettingsRepository()->getDefaultSettings();
$defaultsForReorder['menu_items'] = [
    [
        'type' => 'custom',
        'label' => 'Un',
        'value' => 1,
        'icon' => 'home_white',
        'icon_type' => 'svg_inline',
    ],
    [
        'type' => 'custom',
        'label' => 'Deux',
        'value' => 2,
        'icon' => 'menu_white',
        'icon_type' => 'svg_inline',
    ],
    [
        'type' => 'cta',
        'label' => 'Trois',
        'cta_title' => 'Trois CTA',
        'cta_button_label' => 'Essayer',
        'cta_button_url' => 'https://example.com/cta',
        'icon' => 'star',
        'icon_type' => 'svg_inline',
        'cta_button_color' => 'rgba(10,10,10,1)',
    ],
];
$pluginInstance->getSettingsRepository()->saveOptions($defaultsForReorder);
$_POST = [
    'nonce' => 'nonce-jlg_canvas_nonce',
    'items' => json_encode(array_reverse($defaultsForReorder['menu_items'])),
];
invoke_endpoint($endpoints, 'ajax_canvas_reorder_items');
$canvasReorderPayload = $GLOBALS['json_success_payloads'][0] ?? [];
assertSame('Trois', $canvasReorderPayload['items'][0]['label'] ?? null, 'Canvas AJAX reorder returns first item swapped');
$canvasReordered = $pluginInstance->getSettingsRepository()->getOptions();
assertSame('Trois', $canvasReordered['menu_items'][0]['label'] ?? null, 'Canvas AJAX reorder persists order');

reset_test_environment();
$defaultsForRest = $pluginInstance->getSettingsRepository()->getDefaultSettings();
$defaultsForRest['menu_items'] = [
    [
        'type' => 'custom',
        'label' => 'Accueil',
        'value' => 42,
        'icon' => 'home_white',
        'icon_type' => 'svg_inline',
    ],
];
$pluginInstance->getSettingsRepository()->saveOptions($defaultsForRest);
$restResponse = $endpoints->rest_canvas_update_item(new \WP_REST_Request([
    'index' => 0,
    'item' => [
        'label' => 'Accueil Canvas',
        'icon' => 'sparkle',
    ],
]));
assertSame(200, $restResponse->get_status(), 'Canvas REST update returns success status');
$restData = $restResponse->get_data();
assertSame('Accueil Canvas', $restData['items'][0]['label'] ?? null, 'Canvas REST update payload updated label');

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
    $GLOBALS['wp_test_cron_events'] = [];
    $GLOBALS['registered_rest_routes'] = [];
    $GLOBALS['wp_test_object_cache'] = [];
    $GLOBALS['wp_test_transients'] = [];
    $_POST = [];

    if (isset($GLOBALS['pluginInstance'])) {
        $cache = $GLOBALS['pluginInstance']->getMenuCache();
        $resetLoadedIndex = \Closure::bind(
            function () {
                $this->loadedLocaleIndex = null;
            },
            $cache,
            get_class($cache)
        );

        if ($resetLoadedIndex !== null) {
            $resetLoadedIndex();
        }
    }
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
$_POST = ['nonce' => 'posts-nonce', 'posts_per_page' => '150'];
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame('Le paramètre posts_per_page ne peut pas dépasser 100.', $GLOBALS['json_error_payloads'][0] ?? null, 'Posts per-page cap enforced');
assertSame(['jlg_ajax_nonce', 'nonce', 'posts-nonce'], $GLOBALS['checked_nonces'][0] ?? null, 'Posts nonce checked before error');
assertSame(0, count($GLOBALS['test_get_posts_requests']), 'No posts queried when per-page cap exceeded');

reset_test_environment();
$largeInclude = range(1, 120);
$_POST = [
    'nonce' => 'posts-include-overflow',
    'include' => $largeInclude,
];
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame('Vous ne pouvez pas inclure plus de 100 éléments à la fois.', $GLOBALS['json_error_payloads'][0] ?? null, 'Posts include limit enforced');
assertSame(0, count($GLOBALS['test_get_posts_requests']), 'No posts queried when include limit exceeded');

reset_test_environment();
$includeMax = [];
for ($i = 1; $i <= 100; $i++) {
    $includeMax[] = (string) $i;
}
$GLOBALS['test_get_posts_queue'] = [
    ['return' => []],
    ['return' => []],
];
$_POST = [
    'nonce' => 'posts-include-max',
    'include' => $includeMax,
];
invoke_endpoint($endpoints, 'ajax_get_posts');
assertSame([], $GLOBALS['json_success_payloads'][0] ?? null, 'Posts request with max include returns empty payload when nothing found');
assertSame(2, count($GLOBALS['test_get_posts_requests']), 'Posts include lookup triggered when initial query missing IDs');
assertSame(100, $GLOBALS['test_get_posts_requests'][1]['posts_per_page'] ?? null, 'Posts include lookup limited to include cap');
assertSame(range(1, 100), array_values($GLOBALS['test_get_posts_requests'][1]['post__in'] ?? []), 'Posts include lookup capped list of IDs');

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
$_POST = ['nonce' => 'cats-nonce', 'posts_per_page' => '150'];
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame('Le paramètre posts_per_page ne peut pas dépasser 100.', $GLOBALS['json_error_payloads'][0] ?? null, 'Categories per-page cap enforced');
assertSame(['jlg_ajax_nonce', 'nonce', 'cats-nonce'], $GLOBALS['checked_nonces'][0] ?? null, 'Categories nonce checked before per-page error');
assertSame(0, count($GLOBALS['test_get_categories_requests']), 'No categories queried when per-page cap exceeded');

reset_test_environment();
$largeCategoryInclude = range(1, 121);
$_POST = [
    'nonce' => 'cats-include-overflow',
    'include' => $largeCategoryInclude,
];
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame('Vous ne pouvez pas inclure plus de 100 éléments à la fois.', $GLOBALS['json_error_payloads'][0] ?? null, 'Categories include limit enforced');
assertSame(0, count($GLOBALS['test_get_categories_requests']), 'No categories queried when include limit exceeded');

reset_test_environment();
$categoryIncludeMax = [];
for ($i = 1; $i <= 100; $i++) {
    $categoryIncludeMax[] = (string) $i;
}
$GLOBALS['test_get_categories_queue'] = [
    ['return' => []],
    ['return' => []],
];
$_POST = [
    'nonce' => 'cats-include-max',
    'include' => $categoryIncludeMax,
];
invoke_endpoint($endpoints, 'ajax_get_categories');
assertSame([], $GLOBALS['json_success_payloads'][0] ?? null, 'Categories request with max include returns empty payload when nothing found');
assertSame(2, count($GLOBALS['test_get_categories_requests']), 'Categories include lookup triggered when initial query missing IDs');
assertSame(100, $GLOBALS['test_get_categories_requests'][1]['number'] ?? null, 'Categories include lookup limited to include cap');
assertSame(range(1, 100), array_values($GLOBALS['test_get_categories_requests'][1]['include'] ?? []), 'Categories include lookup capped list of IDs');

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
$GLOBALS['wp_test_transients'] = ['sidebar_jlg_full_html_default_default' => '<div>keep</div>'];
invoke_endpoint($endpoints, 'ajax_reset_settings');
assertSame('Permission refusée.', $GLOBALS['json_error_payloads'][0] ?? null, 'Unauthorized reset request rejected');
assertSame([], $GLOBALS['checked_nonces'], 'Nonce not validated when reset unauthorized');
assertSame(['should' => 'stay'], get_option('sidebar_jlg_settings'), 'Unauthorized reset leaves settings untouched');
assertSame('<div>keep</div>', get_transient('sidebar_jlg_full_html_default_default'), 'Unauthorized reset leaves caches untouched');

reset_test_environment();
$GLOBALS['wp_test_options'] = ['sidebar_jlg_settings' => ['should' => 'stay']];
$GLOBALS['wp_test_transients'] = ['sidebar_jlg_full_html_default_default' => '<div>keep</div>'];
$GLOBALS['test_nonce_results']['jlg_reset_nonce'] = 'Nonce invalide.';
$_POST = ['nonce' => 'bad'];
invoke_endpoint($endpoints, 'ajax_reset_settings');
assertSame('Nonce invalide.', $GLOBALS['json_error_payloads'][0] ?? null, 'Reset nonce failure returns error');
assertSame(['jlg_reset_nonce', 'nonce', 'bad'], $GLOBALS['checked_nonces'][0] ?? null, 'Reset nonce validated before failure');
assertSame(['should' => 'stay'], get_option('sidebar_jlg_settings'), 'Nonce failure leaves settings untouched');
assertSame('<div>keep</div>', get_transient('sidebar_jlg_full_html_default_default'), 'Nonce failure leaves caches untouched');

reset_test_environment();
$GLOBALS['wp_test_options'] = [
    'sidebar_jlg_settings' => ['foo' => 'bar'],
    'sidebar_jlg_cached_locales' => [
        'default',
        ['locale' => 'default', 'suffix' => 'default'],
        ['locale' => 'fr_FR', 'suffix' => 'default'],
    ],
];
$GLOBALS['wp_test_transients'] = [
    'sidebar_jlg_full_html_default' => '<div>legacy-default</div>',
    'sidebar_jlg_full_html_default_default' => '<div>cached</div>',
    'sidebar_jlg_full_html_fr_FR_default' => '<div>cached-fr</div>',
    'sidebar_jlg_full_html' => '<div>legacy</div>',
];
$_POST = ['nonce' => 'reset-nonce'];
invoke_endpoint($endpoints, 'ajax_reset_settings');
assertSame('Réglages réinitialisés.', $GLOBALS['json_success_payloads'][0] ?? null, 'Reset settings success message returned');
assertSame('missing', get_option('sidebar_jlg_settings', 'missing'), 'Settings option deleted during reset');
assertSame('missing', get_option('sidebar_jlg_cached_locales', 'missing'), 'Cached locales option deleted during reset');
assertSame(false, get_transient('sidebar_jlg_full_html_default'), 'Legacy default locale cache cleared during reset');
assertSame(false, get_transient('sidebar_jlg_full_html_default_default'), 'Default locale profile cache cleared during reset');
assertSame(false, get_transient('sidebar_jlg_full_html_fr_FR_default'), 'Additional locale cache cleared during reset');
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

reset_test_environment();
delete_option('sidebar_jlg_analytics');
$_POST = [
    'nonce' => 'analytics-disabled',
    'event_type' => 'sidebar_open',
    'profile_id' => 'default',
];
invoke_endpoint($endpoints, 'ajax_track_event');
$analyticsError = $GLOBALS['json_error_payloads'][0] ?? [];
assertSame('La collecte des métriques est désactivée.', $analyticsError['message'] ?? null, 'Analytics endpoint blocked when feature disabled');

reset_test_environment();
delete_option('sidebar_jlg_analytics');
$analyticsOptions = $pluginInstance->getSettingsRepository()->getDefaultSettings();
$analyticsOptions['enable_analytics'] = true;
$pluginInstance->getSettingsRepository()->saveOptions($analyticsOptions);
$GLOBALS['test_nonce_results']['jlg_track_event'] = true;
$_POST = [
    'nonce' => 'nonce-jlg_track_event',
    'event_type' => 'sidebar_open',
    'profile_id' => 'default',
    'context' => json_encode(['target' => 'toggle_button']),
];
invoke_endpoint($endpoints, 'ajax_track_event');
$analyticsSuccess = $GLOBALS['json_success_payloads'][0] ?? [];
assertSame('Événement enregistré.', $analyticsSuccess['message'] ?? null, 'Analytics event records success message');
$analyticsSummary = $analyticsSuccess['summary'] ?? [];
$queuedEvents = wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP);
assertSame(1, count($queuedEvents ?? []), 'Analytics event is buffered for deferred flush');
assertSame([], get_option('sidebar_jlg_analytics_queue', []), 'Analytics queue option remains empty while buffering events');
assertTrue(wp_next_scheduled('sidebar_jlg_flush_analytics_queue') !== false, 'Analytics flush job scheduled after enqueue');
assertSame(0, $analyticsSummary['totals']['sidebar_open'] ?? null, 'Immediate analytics summary remains unchanged before flush');

$pluginInstance->getAnalyticsQueue()->flushQueuedEvents();
$finalSummary = $pluginInstance->getAnalyticsRepository()->getSummary();
assertSame(1, $finalSummary['totals']['sidebar_open'] ?? null, 'Analytics totals updated after queue flush');
assertSame('toggle_button', array_key_first($finalSummary['targets']['sidebar_open'] ?? []) ?? null, 'Analytics target captured after flush');

reset_test_environment();
delete_option('sidebar_jlg_analytics');
$rateLimitOptions = $pluginInstance->getSettingsRepository()->getDefaultSettings();
$rateLimitOptions['enable_analytics'] = true;
$pluginInstance->getSettingsRepository()->saveOptions($rateLimitOptions);
$GLOBALS['test_nonce_results']['jlg_track_event'] = true;
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

for ($i = 0; $i < 20; $i++) {
    $_POST = [
        'nonce' => 'nonce-jlg_track_event',
        'event_type' => 'sidebar_open',
        'profile_id' => 'default',
    ];

    invoke_endpoint($endpoints, 'ajax_track_event');
}

$bufferAfterBurst = wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP);
assertSame(20, count($bufferAfterBurst ?? []), 'Twenty events are buffered before hitting the rate limit');

$_POST = [
    'nonce' => 'nonce-jlg_track_event',
    'event_type' => 'sidebar_open',
    'profile_id' => 'default',
];

invoke_endpoint($endpoints, 'ajax_track_event');
$rateLimitError = $GLOBALS['json_error_payloads'][0] ?? [];
assertSame('Trop de requêtes, veuillez patienter avant de réessayer.', $rateLimitError['message'] ?? null, 'Rate limiter blocks analytics event when quota exceeded');
$bufferAfterLimit = wp_cache_get(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP);
assertSame(20, count($bufferAfterLimit ?? []), 'Rate-limited event does not increase buffered analytics queue');

wp_cache_delete(AnalyticsEventQueue::CACHE_KEY, AnalyticsEventQueue::CACHE_GROUP);
unset($_SERVER['REMOTE_ADDR']);

if ($testsPassed) {
    echo "AJAX endpoints tests passed.\n";
    exit(0);
}

echo "AJAX endpoints tests failed.\n";
exit(1);
}
