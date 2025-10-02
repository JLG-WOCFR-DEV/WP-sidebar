<?php
declare(strict_types=1);

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

$createRepository = static function () use ($defaults, $iconLibrary): SettingsRepository {
    return new SettingsRepository($defaults, $iconLibrary);
};

$createBlock = static function () use ($createRepository, $pluginFile): SearchBlock {
    $settings = $createRepository();

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
$createRepository()->saveOptions($initialOptions);

$unauthorizedBlock = $createBlock();
$unauthorizedBlock->render($attributes);

$storedAfterUnauthorized = $createRepository()->getRawProfileOptions();
assertSameValue($initialOptions, $storedAfterUnauthorized, 'Options remain unchanged for unauthorized users.');

// Scenario 2: admin context with proper capability should synchronize options.
$GLOBALS['test_current_user_can'] = true;
$GLOBALS['test_is_admin'] = true;
$createRepository()->saveOptions($initialOptions);

$authorizedBlock = $createBlock();
$authorizedBlock->render($attributes);

$expectedOptions = $initialOptions;
$expectedOptions['enable_search'] = true;
$expectedOptions['search_method'] = 'shortcode';
$expectedOptions['search_alignment'] = 'center';
$expectedOptions['search_shortcode'] = '[example]';

$storedAfterAuthorized = $createRepository()->getRawProfileOptions();
assertSameValue($expectedOptions, $storedAfterAuthorized, 'Options are synchronized for authorized users in admin context.');

if ($testsPassed) {
    echo "Search block option synchronization permission tests passed.\n";
    exit(0);
}

echo "Search block option synchronization permission tests failed.\n";
exit(1);
