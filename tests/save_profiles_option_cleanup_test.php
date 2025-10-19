<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$testsPassed = true;

function assertSameValue($expected, $actual, string $message): void
{
    global $testsPassed;

    if ($expected === $actual) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
}

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
$defaults = new DefaultSettings();
$icons = new IconLibrary($pluginFile);
$sanitizer = new SettingsSanitizer($defaults, $icons);
$repository = new SettingsRepository($defaults, $icons, $sanitizer);

$baseOptions = $repository->getDefaultSettings();
$baseOptions['enable_sidebar'] = true;
$baseOptions['profiles'] = [
    [
        'id' => 'profile-alpha',
        'priority' => 5,
        'settings' => [
            'enable_sidebar' => true,
        ],
    ],
];

$repository->saveOptions($baseOptions);

$storedProfiles = get_option('sidebar_jlg_profiles', []);
assertSameValue(1, is_array($storedProfiles) ? count($storedProfiles) : 0, 'Profiles option stores sanitized entries after save.');

$storedSettings = get_option('sidebar_jlg_settings', []);
assertSameValue(false, isset($storedSettings['profiles']), 'Profiles removed from main settings option during save.');

$retrievedProfiles = $repository->getProfiles();
assertSameValue('profile-alpha', $retrievedProfiles[0]['id'] ?? null, 'Repository returns stored profile after save.');

$baseOptionsWithoutProfiles = $baseOptions;
$baseOptionsWithoutProfiles['profiles'] = [];

$repository->saveOptions($baseOptionsWithoutProfiles);

$deletedProfiles = get_option('sidebar_jlg_profiles', 'missing');
assertSameValue('missing', $deletedProfiles, 'Profiles option deleted when empty payload is provided.');

assertSameValue([], $repository->getProfiles(), 'Repository returns empty array after profiles are cleared.');

if ($testsPassed) {
    echo "Profiles save option cleanup tests passed.\n";
    exit(0);
}

echo "Profiles save option cleanup tests failed.\n";
exit(1);
