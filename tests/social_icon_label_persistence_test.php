<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$reflection = new ReflectionClass(SettingsSanitizer::class);
$method = $reflection->getMethod('sanitize_social_settings');
$method->setAccessible(true);

$existingOptions = $defaults->all();

$input = [
    'social_icons' => [
        [
            'url'   => 'https://example.com/custom',
            'icon'  => 'facebook_white',
            'label' => '  Label "Test & Co"  ',
        ],
        [
            'url'   => 'https://example.com/empty',
            'icon'  => 'x_white',
            'label' => '',
        ],
        [
            'url'   => 'https://example.com/invalid',
            'icon'  => 'does_not_exist',
            'label' => 'Invalid should be ignored',
        ],
    ],
    'social_orientation' => 'vertical',
    'social_position'    => 'footer',
    'social_icon_size'   => '150',
];

$result = $method->invoke($sanitizer, $input, $existingOptions);

$testsPassed = true;

function assertSame($expected, $actual, string $message): void
{
    global $testsPassed;
    if ($expected !== $actual) {
        $testsPassed = false;
        echo "Assertion failed: {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    global $testsPassed;
    if (strpos($haystack, $needle) === false) {
        $testsPassed = false;
        echo "Assertion failed: {$message}. Needle `{$needle}` not found.\n";
    }
}

$socialIcons = $result['social_icons'] ?? [];

assertSame(2, count($socialIcons), 'Only valid social icons are kept after sanitization');
assertSame('https://example.com/custom', $socialIcons[0]['url'] ?? null, 'First icon URL preserved');
assertSame('facebook_white', $socialIcons[0]['icon'] ?? null, 'First icon key preserved');
assertSame('Label "Test & Co"', $socialIcons[0]['label'] ?? null, 'Label is trimmed and sanitized');
assertSame('', $socialIcons[1]['label'] ?? null, 'Empty label persists as empty string');
assertSame(150, $result['social_icon_size'] ?? null, 'Icon size sanitized to integer');
assertSame('vertical', $result['social_orientation'] ?? null, 'Orientation sanitized via sanitize_key');
assertSame('footer', $result['social_position'] ?? null, 'Position sanitized via sanitize_key');

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$savedSettings = $defaults->all();
$savedSettings['menu_items'] = [];
$savedSettings['social_icons'] = $socialIcons;
$savedSettings['social_position'] = $result['social_position'];
$savedSettings['social_orientation'] = $result['social_orientation'];
$savedSettings['social_icon_size'] = $result['social_icon_size'];

update_option('sidebar_jlg_settings', $savedSettings);
$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];

$GLOBALS['wp_test_function_overrides']['esc_attr'] = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$html = $renderer->render();
assertSame(true, is_string($html), 'Sidebar renderer returned HTML for social icons scenario');
$html = (string) $html;

unset($GLOBALS['wp_test_function_overrides']['esc_attr']);

$windowAnnouncement = __('s’ouvre dans une nouvelle fenêtre', 'sidebar-jlg');
assertContains(
    sprintf('aria-label="Label &quot;Test &amp; Co&quot; – %s"', $windowAnnouncement),
    $html,
    'Custom label is escaped in aria-label attribute'
);
assertContains(
    sprintf('aria-label="X – %s"', $windowAnnouncement),
    $html,
    'Empty label falls back to icon name in aria-label'
);

if (!$testsPassed) {
    exit(1);
}

echo "Social icon label persistence tests passed.\n";
