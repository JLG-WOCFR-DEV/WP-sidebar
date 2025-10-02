<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$repository = $plugin->getSettingsRepository();

$defaults = $repository->getDefaultSettings();

$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    'active' => 'default',
    'profiles' => [
        'default' => [
            'overlay_opacity' => 5.2,
            'mobile_bg_opacity' => 'not-a-number',
            'mobile_blur' => '-15',
            'animation_speed' => '200ms',
            'social_icon_size' => '90%',
        ],
    ],
];

$repository->revalidateStoredOptions();

$storedAfterRevalidation = $repository->getRawProfileOptions();

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

assertSame(1.0, $storedAfterRevalidation['overlay_opacity'] ?? null, 'Overlay opacity is clamped to 1.0');
assertSame(
    $defaults['mobile_bg_opacity'],
    $storedAfterRevalidation['mobile_bg_opacity'] ?? null,
    'Mobile background opacity falls back to default when invalid'
);
assertSame(15, $storedAfterRevalidation['mobile_blur'] ?? null, 'Mobile blur uses absolute integer value');
assertSame(200, $storedAfterRevalidation['animation_speed'] ?? null, 'Animation speed is normalized using absint logic');
assertSame(90, $storedAfterRevalidation['social_icon_size'] ?? null, 'Social icon size is normalized using absint logic');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
