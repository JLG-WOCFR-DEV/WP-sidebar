<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\ProfileSelector;
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
        $user = $GLOBALS['test_current_user'] ?? null;

        if ($user === null) {
            return (object) ['roles' => []];
        }

        return $user;
    }
}

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();

$baseSettings = $settingsRepository->getDefaultSettings();
$baseSettings['enable_sidebar'] = true;
$baseSettings['profiles'] = [
    [
        'id' => 'disabled-profile',
        'is_enabled' => '0',
        'priority' => 50,
        'conditions' => [
            'post_types' => ['post'],
        ],
        'settings' => [
            'animation_type' => 'fade',
        ],
    ],
    [
        'id' => 'active-profile',
        'priority' => 10,
        'conditions' => [
            'post_types' => ['post'],
        ],
        'settings' => [
            'animation_type' => 'slide-right',
        ],
    ],
];

$settingsRepository->saveOptions($baseSettings);

$GLOBALS['test_post_type'] = 'post';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'post'];
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];

$selector = new ProfileSelector($settingsRepository);
$selection = $selector->selectProfile();

if (($selection['id'] ?? '') !== 'active-profile') {
    echo 'Disabled profile should not be selected even with higher priority.' . "\n";
    exit(1);
}

if (($selection['is_fallback'] ?? null) !== false) {
    echo 'Active profile should not be treated as fallback.' . "\n";
    exit(1);
}

if (($selection['settings']['animation_type'] ?? '') !== 'slide-right') {
    echo 'Active profile settings should be applied when disabled profile is ignored.' . "\n";
    exit(1);
}

exit(0);
