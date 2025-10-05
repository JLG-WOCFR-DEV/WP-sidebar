<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\RequestContextResolver;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        return $GLOBALS['resolver_test_post_type'] ?? null;
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return $GLOBALS['resolver_test_queried_object'] ?? null;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        return $GLOBALS['resolver_test_queried_object_id'] ?? 0;
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object)
    {
        return $GLOBALS['resolver_test_object_taxonomies'] ?? [];
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($postId, $taxonomy, $args = [])
    {
        return $GLOBALS['resolver_test_terms'][$taxonomy] ?? [];
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        return $GLOBALS['resolver_test_current_user'] ?? (object) ['roles' => []];
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return $GLOBALS['resolver_test_is_logged_in'] ?? false;
    }
}

if (!function_exists('wp_is_mobile')) {
    function wp_is_mobile()
    {
        return $GLOBALS['resolver_test_is_mobile'] ?? false;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'timestamp')
    {
        $timestamp = $GLOBALS['resolver_test_current_time'] ?? time();

        if ($type === 'timestamp') {
            return $timestamp;
        }

        return date($type, $timestamp);
    }
}

if (!function_exists('determine_locale')) {
    function determine_locale()
    {
        return $GLOBALS['resolver_test_locale'] ?? 'en_US';
    }
}

function assertHasKeys(array $context, array $keys, string $message): void
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $context)) {
            echo $message . "\n";
            exit(1);
        }
    }
}

$resolver = new RequestContextResolver();

// Page scenario.
$GLOBALS['resolver_test_post_type'] = 'page';
$GLOBALS['resolver_test_queried_object'] = (object) [
    'ID' => 42,
    'post_type' => 'page',
];
$GLOBALS['resolver_test_queried_object_id'] = 42;
$GLOBALS['resolver_test_object_taxonomies'] = [];
$GLOBALS['resolver_test_terms'] = [];
$GLOBALS['resolver_test_current_user'] = (object) ['roles' => []];
$GLOBALS['resolver_test_is_logged_in'] = false;
$GLOBALS['resolver_test_is_mobile'] = false;
$GLOBALS['resolver_test_locale'] = 'fr_FR';
$GLOBALS['resolver_test_current_time'] = strtotime('2024-04-08 10:00:00');
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['REQUEST_URI'] = '/sample-page/';
$_SERVER['HTTPS'] = 'on';

$pageContext = $resolver->resolve();
assertHasKeys($pageContext, [
    'current_post_ids',
    'current_post_types',
    'current_category_ids',
    'current_url',
    'post_types',
    'taxonomies',
    'roles',
    'language',
    'device',
    'is_logged_in',
    'timestamp',
    'day_of_week',
    'time_of_day_minutes',
], 'Request context is missing expected keys for page scenario.');

if (!in_array(42, $pageContext['current_post_ids'], true)) {
    echo "Current post ID should include the queried page.\n";
    exit(1);
}

if (!in_array('page', $pageContext['post_types'], true)) {
    echo "Post types should include the queried page type.\n";
    exit(1);
}

if ($pageContext['current_url'] !== 'https://example.com/sample-page') {
    echo "Normalized URL did not match expected HTTPS page URL.\n";
    exit(1);
}

if ($pageContext['language'] !== 'fr_fr') {
    echo "Locale should be normalized to fr_fr.\n";
    exit(1);
}

if ($pageContext['device'] !== 'desktop') {
    echo "Desktop device should be detected by default.\n";
    exit(1);
}

// Category archive scenario.
$GLOBALS['resolver_test_post_type'] = null;
$GLOBALS['resolver_test_queried_object'] = (object) [
    'term_id' => 9,
    'taxonomy' => 'category',
    'slug' => 'news',
];
$GLOBALS['resolver_test_queried_object_id'] = 9;
$GLOBALS['resolver_test_object_taxonomies'] = [];
$GLOBALS['resolver_test_terms'] = [];
$_SERVER['REQUEST_URI'] = '/category/news/';
$_SERVER['HTTPS'] = '';

$categoryContext = $resolver->resolve();
if (!in_array(9, $categoryContext['current_category_ids'], true)) {
    echo "Category context should include the queried term ID.\n";
    exit(1);
}

if (!isset($categoryContext['taxonomies']['category']) || !in_array('news', $categoryContext['taxonomies']['category'], true)) {
    echo "Category taxonomy terms should include the queried slug.\n";
    exit(1);
}

// Logged-in mobile user scenario.
$GLOBALS['resolver_test_post_type'] = 'post';
$GLOBALS['resolver_test_queried_object'] = (object) [
    'ID' => 75,
    'post_type' => 'post',
];
$GLOBALS['resolver_test_queried_object_id'] = 75;
$GLOBALS['resolver_test_current_user'] = (object) ['roles' => ['editor']];
$GLOBALS['resolver_test_is_logged_in'] = true;
$GLOBALS['resolver_test_is_mobile'] = true;
$GLOBALS['resolver_test_locale'] = 'en_US';
$GLOBALS['resolver_test_current_time'] = strtotime('2024-04-12 22:30:00');
$_SERVER['REQUEST_URI'] = '/post/sample/';
$_SERVER['HTTPS'] = 'on';

$loggedInContext = $resolver->resolve();
if (!in_array('editor', $loggedInContext['roles'], true)) {
    echo "Roles should include the current user role.\n";
    exit(1);
}

if ($loggedInContext['is_logged_in'] !== true) {
    echo "Logged-in status should be true for authenticated users.\n";
    exit(1);
}

if ($loggedInContext['device'] !== 'mobile') {
    echo "Mobile device should be detected when wp_is_mobile returns true.\n";
    exit(1);
}

if ($loggedInContext['day_of_week'] !== 'fri') {
    echo "Day of week should normalize to short identifier.\n";
    exit(1);
}

if (!is_int($loggedInContext['time_of_day_minutes']) || $loggedInContext['time_of_day_minutes'] !== 22 * 60 + 30) {
    echo "Time of day minutes should reflect the resolved timestamp.\n";
    exit(1);
}

echo "";
