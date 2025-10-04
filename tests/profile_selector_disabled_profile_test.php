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

$GLOBALS['test_post_type'] = 'post';
$GLOBALS['test_queried_object'] = (object) ['post_type' => 'post'];
$GLOBALS['test_current_user'] = (object) ['roles' => ['editor']];

$disabledFlagVariants = [
    ['label' => 'enabled empty string', 'flags' => ['enabled' => '']],
    ['label' => 'enabled zero', 'flags' => ['enabled' => '0']],
    ['label' => 'enabled false', 'flags' => ['enabled' => false]],
    ['label' => 'is_enabled zero', 'flags' => ['is_enabled' => '0']],
    ['label' => 'is_enabled false', 'flags' => ['is_enabled' => false]],
    ['label' => 'active zero', 'flags' => ['active' => '0']],
    ['label' => 'active no', 'flags' => ['active' => 'no']],
    ['label' => 'is_active zero', 'flags' => ['is_active' => '0']],
    ['label' => 'is_active false', 'flags' => ['is_active' => false]],
    ['label' => 'disabled true', 'flags' => ['disabled' => true]],
    ['label' => 'disabled truthy string', 'flags' => ['disabled' => 'yes']],
    ['label' => 'is_disabled true', 'flags' => ['is_disabled' => true]],
    ['label' => 'is_disabled numeric string', 'flags' => ['is_disabled' => '1']],
];

$disabledProfileTemplate = [
    'id' => 'disabled-profile',
    'priority' => 50,
    'conditions' => [
        'post_types' => ['post'],
    ],
    'settings' => [
        'animation_type' => 'fade',
    ],
];

$activeProfile = [
    'id' => 'active-profile',
    'priority' => 10,
    'conditions' => [
        'post_types' => ['post'],
    ],
    'settings' => [
        'animation_type' => 'slide-right',
    ],
];

foreach ($disabledFlagVariants as $variant) {
    $settings = $baseSettings;
    $settings['profiles'] = [
        array_merge($disabledProfileTemplate, $variant['flags']),
        $activeProfile,
    ];

    $settingsRepository->saveOptions($settings);

    $selector = new ProfileSelector($settingsRepository);
    $selection = $selector->selectProfile();

    if (($selection['id'] ?? '') !== 'active-profile') {
        echo 'Disabled profile should not be selected when ' . $variant['label'] . " flag is present.\n";
        exit(1);
    }

    if (($selection['is_fallback'] ?? null) !== false) {
        echo 'Active profile should not be treated as fallback when ' . $variant['label'] . " flag is present.\n";
        exit(1);
    }

    if (($selection['settings']['animation_type'] ?? '') !== 'slide-right') {
        echo 'Active profile settings should be applied when ' . $variant['label'] . " flag is present.\n";
        exit(1);
    }
}

$settingsRepository->deleteOptions();

exit(0);
