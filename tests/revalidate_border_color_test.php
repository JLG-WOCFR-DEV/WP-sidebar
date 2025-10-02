<?php
declare(strict_types=1);

use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$repository = new SettingsRepository($defaults, $icons);

$defaultSettings = $defaults->all();
$defaultBorderColor = $defaultSettings['border_color'];

$testsPassed = true;

function assertSame($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
}

$maliciousBorderColor = '#fff; background: url(javascript:alert(1))';
$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    'active' => 'default',
    'profiles' => [
        'default' => [
            'border_color' => $maliciousBorderColor,
        ],
    ],
];

$repository->revalidateStoredOptions();
$storedAfterRevalidation = $repository->getRawProfileOptions();

assertSame(
    $defaultBorderColor,
    $storedAfterRevalidation['border_color'] ?? null,
    'Border color falls back to the default when persisted value includes CSS injection'
);

$validBorderColor = '#123abc';
$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    'active' => 'default',
    'profiles' => [
        'default' => [
            'border_color' => $validBorderColor,
        ],
    ],
];

$repository->revalidateStoredOptions();
$storedAfterValidRevalidation = $repository->getRawProfileOptions();

assertSame(
    $validBorderColor,
    $storedAfterValidRevalidation['border_color'] ?? null,
    'Border color keeps a valid persisted value after revalidation'
);

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
