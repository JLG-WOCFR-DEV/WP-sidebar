<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$repository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();

$defaults = $repository->getDefaultSettings();

$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    'active' => 'default',
    'profiles' => [
        'default' => [
            'desktop_behavior' => 'invalid-behavior',
        ],
    ],
];

$repository->revalidateStoredOptions();

$storedAfter = $repository->getRawProfileOptions();

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

function assertContains($needle, array $haystack, string $message): void
{
    if (in_array($needle, $haystack, true)) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Needle `" . var_export($needle, true) . "` not found in `" . var_export($haystack, true) . "`.\n";
}

assertSame(
    $defaults['desktop_behavior'],
    $storedAfter['desktop_behavior'] ?? null,
    'Desktop behavior falls back to default when stored value is invalid'
);

$bodyClasses = $renderer->addBodyClasses([]);
assertContains('jlg-sidebar-push', $bodyClasses, 'Sidebar body classes include push modifier by default');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
