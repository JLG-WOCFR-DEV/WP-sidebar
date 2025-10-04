<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$testsPassed = true;

function assertTrue($condition, string $message): void
{
    global $testsPassed;
    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function assertSame($expected, $actual, string $message): void
{
    assertTrue($expected === $actual, $message);
}

function assertFalse($actual, string $message): void
{
    assertTrue($actual === false, $message);
}

$locale = 'fr_FR';
$rawSuffix = ' Admin ';
$expectedSuffix = 'admin';

update_option('sidebar_jlg_cached_locales', [
    ['locale' => $locale, 'suffix' => $rawSuffix],
]);

$baseTransient = 'sidebar_jlg_full_html_' . $locale;
$profileTransient = $baseTransient . '_' . $expectedSuffix;

set_transient($baseTransient, '<div>base</div>');
set_transient($profileTransient, '<div>profile</div>');

assertSame('<div>base</div>', get_transient($baseTransient), 'Base cache transient is seeded before uninstall');
assertSame('<div>profile</div>', get_transient($profileTransient), 'Profile cache transient is seeded before uninstall');

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}

require __DIR__ . '/../sidebar-jlg/uninstall.php';

assertFalse(get_transient($baseTransient), 'Base cache transient is removed during uninstall');
assertFalse(get_transient($profileTransient), 'Profile cache transient is removed during uninstall');

$localesOption = get_option('sidebar_jlg_cached_locales', null);
assertSame(null, $localesOption, 'Cached locales option is removed during uninstall');

if ($testsPassed) {
    echo "Uninstall cache cleanup test passed.\n";
    exit(0);
}

echo "Uninstall cache cleanup test failed.\n";
exit(1);
