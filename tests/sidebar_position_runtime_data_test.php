<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settings = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();

$defaults = $settings->getDefaultSettings();
$testsPassed = true;

function sidebar_position_assert(bool $condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    echo "[FAIL] {$message}\n";
    $testsPassed = false;
}

$invalidOptions = $defaults;
$invalidOptions['sidebar_position'] = 'invalid-value';
$settings->saveOptions($invalidOptions);
$normalizedOptions = $settings->getOptions();
sidebar_position_assert(
    ($normalizedOptions['sidebar_position'] ?? null) === ($defaults['sidebar_position'] ?? 'left'),
    'Invalid sidebar orientation falls back to default'
);

$rightOptions = $defaults;
$rightOptions['sidebar_position'] = 'right';
$settings->saveOptions($rightOptions);

ob_start();
$renderer->outputBodyDataScript();
$bodyDataOutput = (string) ob_get_clean();
sidebar_position_assert(
    strpos($bodyDataOutput, 'sidebarPosition="right"') !== false,
    'Body data script outputs right orientation'
);

ob_start();
$renderer->outputBodyDataScriptFallback();
$secondOutput = (string) ob_get_clean();
sidebar_position_assert($secondOutput === '', 'Body data script prints only once');

$settings->saveOptions($defaults);

try {
    $reflection = new ReflectionObject($renderer);
    if ($reflection->hasProperty('bodyDataPrinted')) {
        $property = $reflection->getProperty('bodyDataPrinted');
        $property->setAccessible(true);
        $property->setValue($renderer, false);
    }
} catch (\ReflectionException $exception) {
    // Ignore if the property is not accessible in this environment.
}

if ($testsPassed) {
    echo "Sidebar position normalization tests passed.\n";
    exit(0);
}

echo "Sidebar position normalization tests failed.\n";
exit(1);
