<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\ProfileSelector;
use JLG\Sidebar\Frontend\RequestContextResolver;
use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

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

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();

$baseSettings = $settingsRepository->getDefaultSettings();
$baseSettings['enable_sidebar'] = true;

$GLOBALS['test_post_type'] = 'post';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'post'];
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];
$GLOBALS['test_is_mobile'] = false;
$GLOBALS['test_is_logged_in'] = false;
$GLOBALS['test_current_time'] = strtotime('2024-04-08 10:00:00'); // Monday 10:00

$profiles = [
    [
        'id' => 'mobile-only',
        'priority' => 30,
        'conditions' => [
            'post_types' => ['post'],
            'devices' => ['mobile'],
        ],
        'settings' => [
            'animation_type' => 'fade',
        ],
    ],
    [
        'id' => 'logged-in-only',
        'priority' => 40,
        'conditions' => [
            'post_types' => ['post'],
            'logged_in' => true,
        ],
        'settings' => [
            'animation_type' => 'slide-left',
        ],
    ],
    [
        'id' => 'friday-evening',
        'priority' => 50,
        'conditions' => [
            'post_types' => ['post'],
            'schedule' => [
                'start' => '21:00',
                'end' => '23:59',
                'days' => ['fri'],
            ],
        ],
        'settings' => [
            'animation_type' => 'zoom-in',
        ],
    ],
];

$settings = $baseSettings;
$settings['profiles'] = $profiles;
$settingsRepository->saveOptions($settings);

$resolver = new RequestContextResolver();
$selector = new ProfileSelector($settingsRepository, $resolver);

$selection = $selector->selectProfile();
if (($selection['is_fallback'] ?? null) !== true || ($selection['id'] ?? '') !== 'default') {
    echo "Default profile should be selected when no contextual filter matches.\n";
    exit(1);
}

$GLOBALS['test_is_mobile'] = true;
$selection = $selector->selectProfile();
if (($selection['id'] ?? '') !== 'mobile-only') {
    echo "Mobile-specific profile was not selected when device matched.\n";
    exit(1);
}
if (($selection['is_fallback'] ?? null) !== false) {
    echo "Mobile profile should not be marked as fallback.\n";
    exit(1);
}

$GLOBALS['test_is_mobile'] = false;
$GLOBALS['test_is_logged_in'] = true;
$selection = $selector->selectProfile();
if (($selection['id'] ?? '') !== 'logged-in-only') {
    echo "Logged-in profile was not selected for authenticated users.\n";
    exit(1);
}

$GLOBALS['test_is_logged_in'] = false;
$GLOBALS['test_current_time'] = strtotime('2024-04-12 22:30:00'); // Friday 22:30
$selection = $selector->selectProfile();
if (($selection['id'] ?? '') !== 'friday-evening') {
    echo "Schedule-based profile was not selected during allowed window.\n";
    exit(1);
}

$GLOBALS['test_current_time'] = strtotime('2024-04-12 20:00:00');
$selection = $selector->selectProfile();
if (($selection['id'] ?? '') === 'friday-evening') {
    echo "Schedule-based profile should not apply outside the configured time range.\n";
    exit(1);
}

$settingsRepository->deleteOptions();

echo "";
