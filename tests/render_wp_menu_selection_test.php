<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$GLOBALS['wp_test_nav_menus'] = [
    7 => (object) [
        'term_id' => 7,
        'name' => 'Sidebar Menu',
    ],
];

$GLOBALS['wp_test_nav_menu_items'] = [
    7 => [
        ['title' => 'Accueil', 'url' => 'https://example.com/'],
        ['title' => 'Contact', 'url' => 'https://example.com/contact'],
    ],
];

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$settings = $settingsRepository->getDefaultSettings();
$settings['wp_menu_id'] = 7;
$settings['menu_items'] = [
    [
        'label' => 'Fallback Item',
        'type' => 'custom',
        'icon_type' => 'svg_inline',
        'icon' => '',
        'value' => 'https://fallback.local',
    ],
];
$settings['social_position'] = 'in-menu';
$settings['social_icons'] = [
    ['url' => 'https://social.example', 'icon' => 'facebook_white', 'label' => ''],
];

update_option('sidebar_jlg_settings', $settings);
$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];

ob_start();
$renderer->render();
$html = ob_get_clean();

$testsPassed = true;

function assertStringContains(string $needle, string $haystack, string $message): void
{
    global $testsPassed;
    if (strpos($haystack, $needle) !== false) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertStringNotContains(string $needle, string $haystack, string $message): void
{
    global $testsPassed;
    if (strpos($haystack, $needle) === false) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

assertStringContains('class="sidebar-menu"', $html, 'Sidebar menu wrapper rendered');
assertStringContains('https://example.com/contact', $html, 'WordPress menu link is rendered');
assertStringNotContains('Fallback Item', $html, 'Custom fallback item is not rendered when WP menu is selected');
assertStringContains('class="social-icons-wrapper"', $html, 'Social icons are appended inside the WordPress menu');

if ($testsPassed) {
    echo "Render WordPress menu selection test passed.\n";
    exit(0);
}

echo "Render WordPress menu selection test failed.\n";
exit(1);
