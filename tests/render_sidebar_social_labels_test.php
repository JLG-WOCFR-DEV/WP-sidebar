<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$defaults = $settingsRepository->getDefaultSettings();
$defaults['social_position'] = 'footer';
$defaults['social_icons'] = [
    [
        'url' => 'https://example.com/profile',
        'icon' => 'facebook_white',
        'label' => 'Label "Quote"',
    ],
    [
        'url' => 'https://example.com/photos',
        'icon' => 'instagram_white',
        'label' => '',
    ],
];

$GLOBALS['wp_test_function_overrides']['esc_attr'] = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

update_option('sidebar_jlg_settings', $defaults);
$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];

ob_start();
$renderer->render();
$html = ob_get_clean();

unset($GLOBALS['wp_test_function_overrides']['esc_attr']);

$testsPassed = true;

function assertTrue($condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertContains(string $needle, string $haystack, string $message): void
{
    assertTrue(strpos($haystack, $needle) !== false, $message);
}

assertContains('aria-label="Label &quot;Quote&quot;"', $html, 'Custom social icon label is escaped in HTML output');
assertContains('aria-label="instagram"', $html, 'Fallback label uses icon key when custom label is empty');

if (!$testsPassed) {
    exit(1);
}

echo "Render sidebar social labels test passed.\n";
