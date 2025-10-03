<?php
declare(strict_types=1);

use JLG\Sidebar\Settings\ValueNormalizer;
use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$repository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$defaults = $repository->getDefaultSettings();
$defaultContentMargin = $defaults['content_margin'] ?? '';

$GLOBALS['wp_test_options']['sidebar_jlg_settings'] = [
    'enable_sidebar' => true,
    'content_margin' => '',
    'width_mobile'   => 'calc(100% - 20pt)',
];

$menuCache->clear();
$repository->revalidateStoredOptions();

$storedAfterRevalidation = $GLOBALS['wp_test_options']['sidebar_jlg_settings'] ?? [];

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
assertSame(
    $defaults['width_mobile'],
    $storedAfterRevalidation['width_mobile'] ?? null,
    'Mobile width reverts to default when stored value is invalid'
);

$GLOBALS['wp_test_inline_styles'] = [];
$renderer->enqueueAssets();
$inlineStyles = wp_test_get_inline_styles('sidebar-jlg-public-css');

ob_start();
$renderer->render();
ob_end_clean();

$defaultContentMarginCss = is_array($defaultContentMargin)
    ? ValueNormalizer::dimensionToCss($defaultContentMargin, '')
    : (string) $defaultContentMargin;
$expectedCss = '--content-margin: calc(var(--sidebar-width-desktop) + ' . $defaultContentMarginCss . ');';
assertContains($expectedCss, $inlineStyles, 'Rendered CSS uses default content margin after revalidation');
$expectedMobileWidthCss = '--sidebar-width-mobile: ' . $defaults['width_mobile'] . ';';
assertContains($expectedMobileWidthCss, $inlineStyles, 'Rendered CSS exposes mobile width variable');

if (!$testsPassed) {
    exit(1);
}

echo "All tests passed.\n";
