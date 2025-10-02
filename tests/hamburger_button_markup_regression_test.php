<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$defaultSettings = $settingsRepository->getDefaultSettings();
$defaultSettings['enable_sidebar'] = true;
$defaultSettings['menu_items'] = [];
$defaultSettings['social_icons'] = [];

$settingsRepository->saveOptions($defaultSettings);
$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];

ob_start();
$renderer->render();
$html = ob_get_clean();

$testsPassed = true;

$assertTrue = static function ($condition, string $message) use (&$testsPassed): void {
    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$assertNotContains = static function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) === false, $message);
};

$assertContains('aria-controls="pro-sidebar"', $html, 'Hamburger button retains aria-controls attribute');
$assertNotContains('\\" aria-controls', $html, 'Hamburger button markup does not include escaped aria-controls attribute');

if ($testsPassed) {
    echo "Hamburger button markup regression tests passed.\n";
    exit(0);
}

echo "Hamburger button markup regression tests failed.\n";
exit(1);
