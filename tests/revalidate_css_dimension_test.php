<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$repository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$defaults = $repository->getDefaultSettings();
$defaultContentMargin = $defaults['content_margin'] ?? '';

$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    'active' => 'default',
    'profiles' => [
        'default' => [
            'enable_sidebar' => true,
            'content_margin' => '',
        ],
    ],
];

$menuCache->clear();
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

function assertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Needle `{$needle}` not found.\n";
}

assertSame(
    $defaultContentMargin,
    $storedAfterRevalidation['content_margin'] ?? null,
    'Content margin reverts to default when stored value is empty'
);

$GLOBALS['wp_test_inline_styles'] = [];
$renderer->enqueueAssets();
$inlineStyles = wp_test_get_inline_styles('sidebar-jlg-public-css');

ob_start();
$renderer->render();
ob_end_clean();

$expectedCss = '--content-margin: calc(var(--sidebar-width-desktop) + ' . $defaultContentMargin . ');';
assertContains($expectedCss, $inlineStyles, 'Rendered CSS uses default content margin after revalidation');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
