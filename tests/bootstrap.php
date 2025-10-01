<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

if (!defined('SIDEBAR_JLG_SKIP_BOOTSTRAP')) {
    define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);
}

$GLOBALS['wp_test_options'] = $GLOBALS['wp_test_options'] ?? [];
$GLOBALS['wp_test_transients'] = $GLOBALS['wp_test_transients'] ?? [];
$GLOBALS['wp_test_current_locale'] = $GLOBALS['wp_test_current_locale'] ?? 'fr_FR';
$GLOBALS['wp_test_translations'] = $GLOBALS['wp_test_translations'] ?? [
    'fr_FR' => [
        'Navigation principale' => 'Navigation principale',
        'Ouvrir le menu'        => 'Ouvrir le menu',
        'Fermer le menu'        => 'Fermer le menu',
    ],
];
$GLOBALS['wp_test_function_overrides'] = $GLOBALS['wp_test_function_overrides'] ?? [];
$GLOBALS['wp_test_inline_styles'] = $GLOBALS['wp_test_inline_styles'] ?? [];

if (!function_exists('wp_test_call_override')) {
    function wp_test_call_override(string $function, array $args, ?bool &$handled = null)
    {
        $handled = false;
        if (!isset($GLOBALS['wp_test_function_overrides'][$function])) {
            return null;
        }

        $override = $GLOBALS['wp_test_function_overrides'][$function];
        if (!is_callable($override)) {
            return null;
        }

        $handled = true;

        return $override(...$args);
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $code;
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return [
            'basedir' => rtrim(sys_get_temp_dir(), '/\\') . '/sidebar-jlg-test',
            'baseurl' => 'http://example.com/uploads',
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $value;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

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
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return rtrim((string) $value, "/\\") . '/';
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        $path = (string) $path;

        if ($path === '') {
            return '';
        }

        $path = str_replace("\\", '/', $path);

        while (strpos($path, '//') !== false) {
            $path = str_replace('//', '/', $path);
        }

        if (strlen($path) > 1 && ':' === substr($path, 1, 1)) {
            $path = ucfirst($path);
        }

        return $path;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        if ($component === -1) {
            $parts = parse_url((string) $url);

            return $parts === false ? false : $parts;
        }

        return parse_url((string) $url, $component);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return trailingslashit(dirname((string) $file));
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return 'http://example.com/plugin/';
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, $args, $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }

        if (!isset($GLOBALS['wp_test_inline_styles'][$handle]) || !is_array($GLOBALS['wp_test_inline_styles'][$handle])) {
            $GLOBALS['wp_test_inline_styles'][$handle] = [];
        }

        $GLOBALS['wp_test_inline_styles'][$handle][] = (string) $data;
    }
}

if (!function_exists('wp_test_get_inline_styles')) {
    function wp_test_get_inline_styles(string $handle): string
    {
        $styles = $GLOBALS['wp_test_inline_styles'][$handle] ?? [];

        if (!is_array($styles)) {
            $styles = [$styles];
        }

        return implode("\n", array_map('strval', $styles));
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, $args, $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script(...$args): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, $args, $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('wp_enqueue_media')) {
    function wp_enqueue_media(): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(...$args): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, $args, $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return 'nonce-' . $action;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = ''): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return 'http://example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        if (array_key_exists($name, $GLOBALS['wp_test_options'])) {
            return $GLOBALS['wp_test_options'][$name];
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        $GLOBALS['wp_test_options'][$name] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($name, $value, $deprecated = '', $autoload = 'yes'): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        if (array_key_exists($name, $GLOBALS['wp_test_options'])) {
            return false;
        }

        $GLOBALS['wp_test_options'][$name] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        if (array_key_exists($name, $GLOBALS['wp_test_options'])) {
            unset($GLOBALS['wp_test_options'][$name]);
        }

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $GLOBALS['wp_test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        $GLOBALS['wp_test_transients'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        if (array_key_exists($key, $GLOBALS['wp_test_transients'])) {
            unset($GLOBALS['wp_test_transients'][$key]);
        }

        return true;
    }
}

if (!function_exists('determine_locale')) {
    function determine_locale(): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return $GLOBALS['wp_test_current_locale'];
    }
}

if (!function_exists('get_locale')) {
    function get_locale(): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return determine_locale();
    }
}

if (!function_exists('switch_to_locale')) {
    function switch_to_locale(string $locale): bool
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (bool) $result;
        }

        $GLOBALS['wp_test_current_locale'] = $locale;

        return true;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($value)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $value;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($value)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $value;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($value)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $value;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default'): void
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }

        echo esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default')
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return (string) $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (int) $result;
        }

        return abs((int) $value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $value;
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($file, $allowed = [])
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        $extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));

        if ($extension === '') {
            return ['ext' => '', 'type' => ''];
        }

        if (isset($allowed[$extension])) {
            return ['ext' => $extension, 'type' => $allowed[$extension]];
        }

        return ['ext' => '', 'type' => ''];
    }
}

if (!function_exists('wp_check_filetype_and_ext')) {
    function wp_check_filetype_and_ext($file, $filename, $mimes = null)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        $checked = wp_check_filetype($filename, is_array($mimes) ? $mimes : []);

        if (!isset($checked['ext'])) {
            $checked['ext'] = '';
        }

        if (!isset($checked['type'])) {
            $checked['type'] = '';
        }

        return [
            'ext' => $checked['ext'],
            'type' => $checked['type'],
            'proper_filename' => false,
        ];
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html = [])
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $string;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', $value);

        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        $key = strtolower((string) $key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($class, $fallback = '')
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);

        if ($sanitized === '' && $fallback !== '') {
            $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $fallback);
        }

        return (string) $sanitized;
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return 'http://example.com/post/' . $post_id;
    }
}

if (!function_exists('get_category_link')) {
    function get_category_link($cat_id)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        if (isset($GLOBALS['test_category_link_return']) && $GLOBALS['test_category_link_return'] !== '') {
            return $GLOBALS['test_category_link_return'];
        }

        return 'http://example.com/category/' . $cat_id;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return 'Test Blog';
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($content)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $content;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args): void
    {
        $handled = false;
        wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }
    }
}

if (!function_exists('get_search_form')) {
    function get_search_form(): string
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return (string) $result;
        }

        return 'SEARCH_FORM';
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($string)
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        return $string;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return $result;
        }

        $locale = determine_locale();
        $translations = $GLOBALS['wp_test_translations'];

        if (isset($translations[$locale][$text])) {
            return $translations[$locale][$text];
        }

        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default'): void
    {
        $handled = false;
        $result = wp_test_call_override(__FUNCTION__, func_get_args(), $handled);
        if ($handled) {
            return;
        }

        echo __($text, $domain);
    }
}
