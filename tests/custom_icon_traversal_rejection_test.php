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

$evilSvgPath = $uploadsBaseDir . '/evil.svg';
file_put_contents($evilSvgPath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

$baseUrl = trailingslashit((string) ($uploads['baseurl'] ?? '')) . 'sidebar-jlg/icons';
$maliciousSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <use xlink:href="{$baseUrl}/../evil.svg" />
</svg>
SVG;

file_put_contents($iconsDir . '/bad.svg', $maliciousSvg);

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$iconLibrary = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$allIcons = $iconLibrary->getAllIcons();
$rejected = $iconLibrary->consumeRejectedCustomIcons();

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

function assertFalse($condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertContains(string $needle, array $haystack, string $message): void
{
    assertTrue(in_array($needle, $haystack, true), $message);
}

assertFalse(isset($allIcons['custom_bad']), 'Malicious icon is rejected from the library');
assertContains('bad.svg', $rejected, 'Malicious icon is reported as rejected');

@unlink($iconsDir . '/bad.svg');
@unlink($evilSvgPath);
if (is_dir($iconsDir)) {
    @rmdir($iconsDir);
}
if (is_dir($iconsRootDir)) {
    @rmdir($iconsRootDir);
}

if ($testsPassed) {
    echo "Custom icon traversal rejection test passed.\n";
    exit(0);
}

echo "Custom icon traversal rejection test failed.\n";
exit(1);
