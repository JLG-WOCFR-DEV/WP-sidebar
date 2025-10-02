<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

$GLOBALS['wp_test_translations'] = [
    'fr_FR' => [
        'Navigation principale' => 'Navigation principale',
        'Ouvrir le menu'        => 'Ouvrir le menu',
        'Fermer le menu'        => 'Fermer le menu',
    ],
];

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$default_settings = $settingsRepository->getDefaultSettings();
$default_settings['menu_items'] = [
    [
        'label'     => 'Category Error Item',
        'type'      => 'category',
        'icon_type' => 'svg_inline',
        'icon'      => '',
        'value'     => 123,
    ],
];
$default_settings['social_icons'] = [];

$settingsRepository->saveOptions($default_settings);
$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['test_category_link_return'] = new WP_Error('invalid_term', 'Invalid term');

ob_start();
$renderer->render();
$html = ob_get_clean();

$testsPassed = true;

function assertTrue($condition, string $message): void {
    global $testsPassed;
    if ($condition) {
        echo "[PASS] {$message}\n";
        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertContains(string $needle, string $haystack, string $message): void {
    assertTrue(strpos($haystack, $needle) !== false, $message);
}

assertContains('<nav class="sidebar-navigation"', $html, 'Sidebar markup rendered');
assertContains('href="#"', $html, 'Category link falls back to hash when WP_Error returned');

if ($testsPassed) {
    echo "Render sidebar WP_Error handling tests passed.\n";
    exit(0);
}

echo "Render sidebar WP_Error handling tests failed.\n";
exit(1);
