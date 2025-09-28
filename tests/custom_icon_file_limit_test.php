<?php
declare(strict_types=1);

use JLG\Sidebar\Icons\IconLibrary;

require __DIR__ . '/bootstrap.php';

$uploads = wp_upload_dir();
$uploadsBaseDir = rtrim((string) ($uploads['basedir'] ?? ''), "/\\");
$iconsRootDir = $uploadsBaseDir . '/sidebar-jlg';
$iconsDir = $iconsRootDir . '/icons';

if ($uploadsBaseDir === '') {
    echo "[FAIL] Upload base directory is not defined.\n";
    exit(1);
}

if (is_dir($iconsRootDir)) {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($iconsRootDir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
            continue;
        }

        unlink($file->getPathname());
    }
}

if (!is_dir($iconsDir) && !mkdir($iconsDir, 0777, true) && !is_dir($iconsDir)) {
    echo "[FAIL] Unable to prepare custom icons directory.\n";
    exit(1);
}

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
require_once $pluginFile;

$limit = 200;
$limitReflection = new \ReflectionClass(IconLibrary::class);
$limitConstant = $limitReflection->getReflectionConstant('MAX_CUSTOM_ICON_FILES');
if ($limitConstant instanceof \ReflectionClassConstant) {
    $limit = (int) $limitConstant->getValue();
}

$svgTemplate = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" /></svg>';
$extraFiles = 5;
$totalFiles = $limit + $extraFiles;

for ($i = 0; $i < $totalFiles; $i++) {
    $fileName = sprintf('icon-%03d.svg', $i);
    file_put_contents($iconsDir . '/' . $fileName, $svgTemplate);
}

$iconLibrary = new IconLibrary($pluginFile);
$allIcons = $iconLibrary->getAllIcons();
$rejected = $iconLibrary->consumeRejectedCustomIcons();

$cache = get_transient('sidebar_jlg_custom_icons_cache');

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

function assertArrayContainsSubstring(string $needle, array $haystack, string $message): void
{
    foreach ($haystack as $value) {
        if (is_string($value) && strpos($value, $needle) !== false) {
            assertTrue(true, $message);

            return;
        }
    }

    assertTrue(false, $message);
}

$customIconCount = 0;
foreach ($allIcons as $key => $_markup) {
    if (!is_string($key)) {
        continue;
    }

    if (strpos($key, 'custom_') === 0) {
        $customIconCount++;
    }
}

assertSame($limit, $customIconCount, 'Only the first N custom icons are available in the library');
assertSame($extraFiles, count($rejected), 'Exceeded files are reported as rejected');
assertArrayContainsSubstring('maximum of ' . $limit . ' icons', $rejected, 'Rejection message mentions the processing limit');

if (is_array($cache) && isset($cache['icons']) && is_array($cache['icons'])) {
    $cachedCustomIcons = 0;
    foreach ($cache['icons'] as $key => $_markup) {
        if (is_string($key) && strpos($key, 'custom_') === 0) {
            $cachedCustomIcons++;
        }
    }

    assertSame($limit, $cachedCustomIcons, 'Cached icons respect the processing limit');
} else {
    assertTrue(false, 'Custom icons cache is available for inspection');
}

$followUpRejected = $iconLibrary->consumeRejectedCustomIcons();
assertSame(0, count($followUpRejected), 'Rejected icons are consumed after reporting');

for ($i = 0; $i < $totalFiles; $i++) {
    $fileName = sprintf('icon-%03d.svg', $i);
    @unlink($iconsDir . '/' . $fileName);
}
if (is_dir($iconsDir)) {
    @rmdir($iconsDir);
}
if (is_dir($iconsRootDir)) {
    @rmdir($iconsRootDir);
}

if ($testsPassed) {
    echo "Custom icon file limit test passed.\n";
    exit(0);
}

echo "Custom icon file limit test failed.\n";
exit(1);

