<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Frontend\Blocks\SearchBlock;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/autoload.php';

$GLOBALS['test_current_user_can'] = false;
$GLOBALS['test_is_admin'] = false;

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        return !empty($GLOBALS['test_current_user_can']);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return !empty($GLOBALS['test_is_admin']);
    }
}

$testsPassed = true;

function assertSameValue($expected, $actual, string $message): void
{
    global $testsPassed;

    if ($expected === $actual) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
    echo 'Expected: ' . var_export($expected, true) . "\n";
    echo 'Actual: ' . var_export($actual, true) . "\n";
}

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
$defaults = new DefaultSettings();
$iconLibrary = new IconLibrary($pluginFile);

$createBlock = static function () use ($defaults, $iconLibrary, $pluginFile): SearchBlock {
    $sanitizer = new SettingsSanitizer($defaults, $iconLibrary);
    $settings = new SettingsRepository($defaults, $iconLibrary, $sanitizer);

    return new SearchBlock($settings, $pluginFile, 'test-version');
};

$initialOptions = [
    'enable_search' => false,
    'search_method' => 'default',
    'search_alignment' => 'flex-start',
    'search_shortcode' => '',
];

$attributes = [
    'enable_search' => true,
    'search_method' => 'shortcode',
    'search_alignment' => 'center',
    'search_shortcode' => '[example] ',
];

// Scenario 1: visitor or unauthorized user should not trigger option synchronization.
$GLOBALS['test_current_user_can'] = false;
$GLOBALS['test_is_admin'] = false;
update_option('sidebar_jlg_settings', $initialOptions);

$unauthorizedBlock = $createBlock();
$unauthorizedBlock->render($attributes);

$storedAfterUnauthorized = get_option('sidebar_jlg_settings');
assertSameValue($initialOptions, $storedAfterUnauthorized, 'Options remain unchanged for unauthorized users.');

// Scenario 2: admin context with proper capability should synchronize options.
$GLOBALS['test_current_user_can'] = true;
$GLOBALS['test_is_admin'] = true;
update_option('sidebar_jlg_settings', $initialOptions);

$authorizedBlock = $createBlock();
$authorizedBlock->render($attributes);

$storedAfterAuthorized = get_option('sidebar_jlg_settings');
assertSameValue(true, $storedAfterAuthorized['enable_search'] ?? null, 'Enable search flag synchronized for authorized users.');
assertSameValue('shortcode', $storedAfterAuthorized['search_method'] ?? null, 'Search method synchronized for authorized users.');
assertSameValue('center', $storedAfterAuthorized['search_alignment'] ?? null, 'Search alignment synchronized for authorized users.');
assertSameValue('[example]', $storedAfterAuthorized['search_shortcode'] ?? null, 'Search shortcode synchronized and trimmed for authorized users.');

// Scenario 3: rendered markup exposes contrast helpers and scheme metadata.
$GLOBALS['test_current_user_can'] = false;
$GLOBALS['test_is_admin'] = false;

$renderBlock = $createBlock();
$markup = $renderBlock->render([
    'enable_search' => true,
    'search_method' => 'default',
    'search_alignment' => 'flex-start',
    'search_shortcode' => '',
]);

assertSameValue(true, strpos($markup, 'data-sidebar-search-scheme="auto"') !== false, 'Markup exposes automatic scheme data attribute.');
assertSameValue(true, strpos($markup, 'sidebar-search--scheme-light') !== false, 'Markup includes the light scheme class by default.');

if ($testsPassed) {
    echo "Search block option synchronization permission tests passed.\n";
    exit(0);
}

echo "Search block option synchronization permission tests failed.\n";
exit(1);
