<?php
declare(strict_types=1);

use JLG\Sidebar\Icons\IconLibrary;

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

function assertFalse($condition, string $message): void
{
    assertTrue(!$condition, $message);
}

$baseDir = rtrim(sys_get_temp_dir(), '/\\') . '/sidebar-jlg-test-default-port';
$baseUrl = 'https://example.com/uploads';

$previousUploadDirOverride = $GLOBALS['wp_test_function_overrides']['wp_upload_dir'] ?? null;
$GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = static function () use ($baseDir, $baseUrl): array {
    return [
        'basedir' => $baseDir,
        'baseurl' => $baseUrl,
    ];
};

$iconsRootDir = $baseDir . '/sidebar-jlg';
$iconsDir = $iconsRootDir . '/icons';

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
    cleanup();
    exit(1);
}

$referenceSvgPath = $iconsDir . '/ref.svg';
file_put_contents($referenceSvgPath, '<svg xmlns="http://www.w3.org/2000/svg"><symbol id="ref"></symbol></svg>');

$customSvgPath = $iconsDir . '/good.svg';
$customSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <use xlink:href="https://example.com:443/uploads/sidebar-jlg/icons/ref.svg#ref" />
</svg>
SVG;

file_put_contents($customSvgPath, $customSvg);

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$iconLibrary = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$allIcons = $iconLibrary->getAllIcons();
$rejected = $iconLibrary->consumeRejectedCustomIcons();

assertTrue(isset($allIcons['custom_good']), 'Custom icon referencing default HTTPS port is accepted');
assertFalse(in_array('good.svg', $rejected, true), 'Custom icon is not reported as rejected');

cleanup();

if ($testsPassed) {
    echo "Custom icon default port reference test passed.\n";
    exit(0);
}

echo "Custom icon default port reference test failed.\n";
exit(1);

function cleanup(): void
{
    global $iconsDir, $iconsRootDir, $baseDir, $previousUploadDirOverride;

    @unlink($iconsDir . '/good.svg');
    @unlink($iconsDir . '/ref.svg');
    if (is_dir($iconsDir)) {
        @rmdir($iconsDir);
    }
    if (is_dir($iconsRootDir)) {
        @rmdir($iconsRootDir);
    }
    if (is_dir($baseDir)) {
        @rmdir($baseDir);
    }

    if ($previousUploadDirOverride === null) {
        unset($GLOBALS['wp_test_function_overrides']['wp_upload_dir']);
    } else {
        $GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = $previousUploadDirOverride;
    }
}
