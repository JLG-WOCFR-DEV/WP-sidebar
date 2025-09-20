<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

if (!defined('SIDEBAR_JLG_SKIP_BOOTSTRAP')) {
    define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);
}

$GLOBALS['wp_test_options']          = $GLOBALS['wp_test_options'] ?? [];
$GLOBALS['wp_test_transients']       = $GLOBALS['wp_test_transients'] ?? [];
$GLOBALS['wp_test_current_locale']   = $GLOBALS['wp_test_current_locale'] ?? 'en_US';
$GLOBALS['wp_test_translations']     = $GLOBALS['wp_test_translations'] ?? [];
$GLOBALS['wp_test_category_link_return'] = $GLOBALS['wp_test_category_link_return'] ?? null;
$GLOBALS['wp_test_do_shortcode_callback'] = $GLOBALS['wp_test_do_shortcode_callback'] ?? null;
$GLOBALS['wp_test_shortcode_calls']  = $GLOBALS['wp_test_shortcode_calls'] ?? 0;

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->code    = $code;
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
        // No-op for tests.
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return [
            'basedir' => sys_get_temp_dir() . '/sidebar-jlg-test',
            'baseurl' => 'http://example.com/uploads',
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): bool
    {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args)
    {
        return $value;
    }
}

if (!function_exists('wp_parse_args')) {
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
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value): string
    {
        return rtrim((string) $value, "/\\") . '/';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string
    {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file): string
    {
        return 'http://example.com/plugin/';
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args): void
    {
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args): void
    {
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script(...$args): void
    {
    }
}

if (!function_exists('wp_enqueue_media')) {
    function wp_enqueue_media(): void
    {
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(...$args): void
    {
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action): string
    {
        return 'nonce-' . $action;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = ''): string
    {
        return 'http://example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        global $wp_test_options;
        if (array_key_exists($name, $wp_test_options)) {
            return $wp_test_options[$name];
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null): bool
    {
        global $wp_test_options;
        $wp_test_options[$name] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($name, $value, $deprecated = '', $autoload = 'yes'): bool
    {
        global $wp_test_options;
        if (array_key_exists($name, $wp_test_options)) {
            return false;
        }

        $wp_test_options[$name] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name): bool
    {
        global $wp_test_options;
        if (array_key_exists($name, $wp_test_options)) {
            unset($wp_test_options[$name]);
        }

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key)
    {
        global $wp_test_transients;
        return $wp_test_transients[$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0): bool
    {
        global $wp_test_transients;
        $wp_test_transients[$key] = $value;

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key): bool
    {
        global $wp_test_transients;
        if (array_key_exists($key, $wp_test_transients)) {
            unset($wp_test_transients[$key]);
        }

        return true;
    }
}

if (!function_exists('determine_locale')) {
    function determine_locale(): string
    {
        return $GLOBALS['wp_test_current_locale'];
    }
}

if (!function_exists('get_locale')) {
    function get_locale(): string
    {
        return determine_locale();
    }
}

if (!function_exists('switch_to_locale')) {
    function switch_to_locale(string $locale): bool
    {
        $GLOBALS['wp_test_current_locale'] = $locale;

        return true;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($value)
    {
        return $value;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($value)
    {
        return $value;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($value)
    {
        return $value;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default'): void
    {
        echo esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value)
    {
        return (string) $value;
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($file, $allowed = [])
    {
        $extension = pathinfo((string) $file, PATHINFO_EXTENSION);

        if ($extension === '') {
            return ['ext' => '', 'type' => ''];
        }

        return [
            'ext'  => $extension,
            'type' => $allowed[$extension] ?? 'image/' . $extension,
        ];
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html = [])
    {
        return $string;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = (string) $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

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

if (!function_exists('get_permalink')) {
    function get_permalink($post_id)
    {
        return 'http://example.com/post/' . $post_id;
    }
}

if (!function_exists('get_category_link')) {
    function get_category_link($cat_id)
    {
        if ($GLOBALS['wp_test_category_link_return'] instanceof WP_Error) {
            return $GLOBALS['wp_test_category_link_return'];
        }

        if ($GLOBALS['wp_test_category_link_return'] !== null) {
            return $GLOBALS['wp_test_category_link_return'];
        }

        return 'http://example.com/category/' . $cat_id;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        return 'Test Blog';
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($content)
    {
        if (is_callable($GLOBALS['wp_test_do_shortcode_callback'] ?? null)) {
            return ($GLOBALS['wp_test_do_shortcode_callback'])($content);
        }

        return $content;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args): void
    {
    }
}

if (!function_exists('get_search_form')) {
    function get_search_form(): string
    {
        return 'SEARCH_FORM';
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($string)
    {
        return $string;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        $locale       = determine_locale();
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
        echo __($text, $domain);
    }
}

