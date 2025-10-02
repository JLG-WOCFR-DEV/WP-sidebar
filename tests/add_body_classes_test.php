<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();

$defaultSettings = $settingsRepository->getDefaultSettings();
$baselineClasses = ['baseline-class'];

$testsPassed = true;

function assertSameClasses(array $expected, array $actual, string $message): void
{
    global $testsPassed;

    if ($expected === $actual) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
    echo 'Expected: ' . json_encode($expected) . "\n";
    echo 'Actual:   ' . json_encode($actual) . "\n";
}

$scenarios = [
    'Disabled sidebar leaves classes unchanged' => function (array $settings) use ($settingsRepository, $renderer, $baselineClasses): array {
        $settings['enable_sidebar'] = false;
        $settingsRepository->saveOptions($settings);

        return $renderer->addBodyClasses($baselineClasses);
    },
    'Push behavior adds active and push classes' => function (array $settings) use ($settingsRepository, $renderer, $baselineClasses): array {
        $settings['enable_sidebar'] = true;
        $settings['desktop_behavior'] = 'push';
        $settings['layout_style'] = 'full';
        $settingsRepository->saveOptions($settings);

        return $renderer->addBodyClasses($baselineClasses);
    },
    'Overlay behavior adds overlay class instead of push' => function (array $settings) use ($settingsRepository, $renderer, $baselineClasses): array {
        $settings['enable_sidebar'] = true;
        $settings['desktop_behavior'] = 'overlay';
        $settings['layout_style'] = 'full';
        $settingsRepository->saveOptions($settings);

        return $renderer->addBodyClasses($baselineClasses);
    },
    'Right orientation adds right class' => function (array $settings) use ($settingsRepository, $renderer, $baselineClasses): array {
        $settings['enable_sidebar'] = true;
        $settings['sidebar_position'] = 'right';
        $settingsRepository->saveOptions($settings);

        return $renderer->addBodyClasses($baselineClasses);
    },
    'Floating layout adds floating class' => function (array $settings) use ($settingsRepository, $renderer, $baselineClasses): array {
        $settings['enable_sidebar'] = true;
        $settings['desktop_behavior'] = 'push';
        $settings['layout_style'] = 'floating';
        $settingsRepository->saveOptions($settings);

        return $renderer->addBodyClasses($baselineClasses);
    },
    'Horizontal layout adds horizontal classes' => function (array $settings) use ($settingsRepository, $renderer, $baselineClasses): array {
        $settings['enable_sidebar'] = true;
        $settings['layout_style'] = 'horizontal-bar';
        $settings['horizontal_bar_position'] = 'bottom';
        $settings['horizontal_bar_sticky'] = true;
        $settingsRepository->saveOptions($settings);

        return $renderer->addBodyClasses($baselineClasses);
    },
];

$expectedResults = [
    'Disabled sidebar leaves classes unchanged' => ['baseline-class'],
    'Push behavior adds active and push classes' => ['baseline-class', 'jlg-sidebar-active', 'jlg-sidebar-position-left', 'jlg-sidebar-push'],
    'Overlay behavior adds overlay class instead of push' => ['baseline-class', 'jlg-sidebar-active', 'jlg-sidebar-position-left', 'jlg-sidebar-overlay'],
    'Right orientation adds right class' => ['baseline-class', 'jlg-sidebar-active', 'jlg-sidebar-position-right', 'jlg-sidebar-push'],
    'Floating layout adds floating class' => ['baseline-class', 'jlg-sidebar-active', 'jlg-sidebar-position-left', 'jlg-sidebar-push', 'jlg-sidebar-floating'],
    'Horizontal layout adds horizontal classes' => ['baseline-class', 'jlg-sidebar-active', 'jlg-sidebar-position-left', 'jlg-sidebar-horizontal-bar', 'jlg-horizontal-position-bottom', 'jlg-horizontal-sticky'],
];

foreach ($scenarios as $label => $runner) {
    $currentSettings = $defaultSettings;
    $resultingClasses = $runner($currentSettings);
    assertSameClasses($expectedResults[$label], $resultingClasses, $label);
}

if ($testsPassed) {
    echo "Sidebar body class tests passed.\n";
    exit(0);
}

echo "Sidebar body class tests failed.\n";
exit(1);
