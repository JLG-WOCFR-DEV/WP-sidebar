<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\RequestContextResolver;

require __DIR__ . '/bootstrap.php';

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        return $GLOBALS['test_post_type'] ?? null;
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return $GLOBALS['test_queried_object'] ?? null;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        return $GLOBALS['test_queried_object_id'] ?? 0;
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object)
    {
        $key = is_string($object) || is_int($object) ? (string) $object : '';

        return $GLOBALS['test_object_taxonomies'][$key] ?? [];
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($postId, $taxonomy, $args = [])
    {
        $postKey = (int) $postId;
        $taxonomyKey = is_string($taxonomy) ? $taxonomy : (string) $taxonomy;

        return $GLOBALS['test_post_terms'][$postKey][$taxonomyKey] ?? [];
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($postId, $taxonomy)
    {
        return wp_get_post_terms($postId, $taxonomy);
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        return $GLOBALS['test_current_user'] ?? (object) ['roles' => []];
    }
}

if (!function_exists('wp_is_mobile')) {
    function wp_is_mobile()
    {
        return $GLOBALS['test_is_mobile'] ?? false;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return $GLOBALS['test_is_logged_in'] ?? false;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'timestamp')
    {
        $timestamp = $GLOBALS['test_current_time'] ?? time();

        if ($type === 'timestamp') {
            return $timestamp;
        }

        return date($type, $timestamp);
    }
}

$resolver = new RequestContextResolver();
$testsPassed = true;

function assertTrue(bool $condition, string $message): void
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
    assertTrue($expected === $actual, $message);
}

$resetEnvironment = static function (): void {
    $GLOBALS['test_post_type'] = null;
    $GLOBALS['test_queried_object'] = null;
    $GLOBALS['test_queried_object_id'] = 0;
    $GLOBALS['test_object_taxonomies'] = [];
    $GLOBALS['test_post_terms'] = [];
    $GLOBALS['test_current_user'] = (object) ['roles' => []];
    $GLOBALS['test_is_mobile'] = false;
    $GLOBALS['test_is_logged_in'] = false;
    $GLOBALS['test_current_time'] = strtotime('2024-04-08 10:30:00');
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/';
    unset($_SERVER['HTTPS']);
};

// Scenario 1: Standard page request.
$resetEnvironment();
$GLOBALS['test_post_type'] = 'page';
$GLOBALS['test_queried_object'] = (object) [
    'ID'        => 42,
    'post_type' => 'page',
];
$GLOBALS['test_queried_object_id'] = 42;
$_SERVER['REQUEST_URI'] = '/sample-page/';
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];
$GLOBALS['test_current_time'] = strtotime('2024-04-08 10:30:00');

$pageContext = $resolver->resolve();
assertTrue(in_array(42, $pageContext['current_post_ids'], true), 'Page context includes current post ID');
assertTrue(in_array('page', $pageContext['current_post_types'], true), 'Page context includes current post type');
assertSame('http://example.com/sample-page', $pageContext['current_url'], 'Normalized URL strips trailing slash');
assertTrue(in_array('page', $pageContext['post_types'], true), 'Profile context exposes post type');
assertTrue(in_array('editor', $pageContext['roles'], true), 'Roles include current user role');
assertSame('fr_fr', $pageContext['language'], 'Locale normalized to language key');
assertSame('mon', $pageContext['day_of_week'], 'Day of week normalized from timestamp');
assertSame(630, $pageContext['time_of_day_minutes'], 'Time of day converted to minutes');

// Scenario 2: Category archive with taxonomy terms.
$resetEnvironment();
$GLOBALS['test_post_type'] = 'post';
$GLOBALS['test_queried_object'] = (object) [
    'term_id'  => 9,
    'taxonomy' => 'category',
    'slug'     => 'news',
];
$GLOBALS['test_queried_object_id'] = 9;
$_SERVER['REQUEST_URI'] = '/category/news/';
$GLOBALS['test_object_taxonomies'] = ['post' => ['category']];
$GLOBALS['test_post_terms'] = [
    9 => [
        'category' => [
            (object) ['term_id' => 9, 'slug' => 'news'],
            'feature',
        ],
    ],
];

$categoryContext = $resolver->resolve();
assertTrue(in_array(9, $categoryContext['current_category_ids'], true), 'Category context includes queried term ID');
assertTrue(isset($categoryContext['taxonomies']['category']), 'Category taxonomy present in context');
assertTrue(in_array('9', $categoryContext['taxonomies']['category'], true), 'Category taxonomy exports numeric term ID');
assertSame('http://example.com/category/news', $categoryContext['current_url'], 'Category URL normalized without trailing slash');

// Scenario 3: Logged-in mobile user with custom timestamp.
$resetEnvironment();
$GLOBALS['test_is_mobile'] = true;
$GLOBALS['test_is_logged_in'] = true;
$GLOBALS['test_current_user'] = (object) ['roles' => ['subscriber']];
$GLOBALS['test_current_time'] = strtotime('2024-04-12 22:45:00'); // Friday 22:45
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/dashboard/';

$userContext = $resolver->resolve();
assertSame('mobile', $userContext['device'], 'Device detection flags mobile environment');
assertSame(true, $userContext['is_logged_in'], 'Login state propagated in context');
assertTrue(in_array('subscriber', $userContext['roles'], true), 'Roles include subscriber');
assertSame('fri', $userContext['day_of_week'], 'Friday mapped to abbreviated key');
assertSame(1365, $userContext['time_of_day_minutes'], '22:45 converted to minutes');
assertSame('https://example.com/dashboard', $userContext['current_url'], 'HTTPS requests preserved in normalized URL');

exit($testsPassed ? 0 : 1);
