<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$repository = new SettingsRepository($defaults, $icons);
$sanitizer = new SettingsSanitizer($defaults, $icons, $repository);

$input = [
    'menu_items' => [
        [
            'label' => 'Traversal Icon',
            'type' => 'custom',
            'value' => 'https://example.com/page',
            'icon_type' => 'svg_url',
            'icon' => 'http://example.com/uploads/sidebar-jlg/../evil.svg',
        ],
    ],
];

$sanitized = $sanitizer->sanitize_settings($input);
$menuItems = $sanitized['menu_items'] ?? [];

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

function assertSame($expected, $actual, string $message): void
{
    $condition = $expected === $actual;
    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $expectedExport = var_export($expected, true);
    $actualExport = var_export($actual, true);
    assertTrue(false, "{$message} - expected {$expectedExport} got {$actualExport}");
}

assertSame(1, count($menuItems), 'Menu item preserved after sanitization');
$menuItem = $menuItems[0];

assertSame('svg_inline', $menuItem['icon_type'], 'Traversal SVG URL falls back to inline icon type');
assertSame('', $menuItem['icon'], 'Traversal SVG URL value cleared during sanitization');

$rejections = $icons->consumeRejectedCustomIcons();
assertTrue(!empty($rejections), 'Rejection recorded for traversal SVG URL');

$options = $sanitized;
$options['social_icons'] = [];
$allIcons = [];

ob_start();
require __DIR__ . '/../sidebar-jlg/includes/sidebar-template.php';
$html = ob_get_clean();

assertTrue(strpos($html, '<img') === false, 'No <img> tag rendered for rejected traversal SVG icon');

if ($testsPassed) {
    echo "SVG URL traversal rejection tests passed.\n";
    exit(0);
}

echo "SVG URL traversal rejection tests failed.\n";
exit(1);
