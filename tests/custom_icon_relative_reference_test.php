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

$baseDir = rtrim(sys_get_temp_dir(), '/\\') . '/sidebar-jlg-test-relative';
$baseUrl = 'https://example.com/wp-content/uploads';

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

$rootRelativeSvgPath = $iconsDir . '/root.svg';
$rootRelativeSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <use xlink:href="/wp-content/uploads/sidebar-jlg/icons/ref.svg#ref" />
</svg>
SVG;
file_put_contents($rootRelativeSvgPath, $rootRelativeSvg);

$relativeSvgPath = $iconsDir . '/relative.svg';
$relativeSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <use xlink:href="sidebar-jlg/icons/ref.svg#ref" />
</svg>
SVG;
file_put_contents($relativeSvgPath, $relativeSvg);

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$iconLibrary = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$allIcons = $iconLibrary->getAllIcons();
$rejected = $iconLibrary->consumeRejectedCustomIcons();

assertTrue(isset($allIcons['custom_root']), 'Custom icon with root-relative reference is accepted');
assertTrue(isset($allIcons['custom_relative']), 'Custom icon with relative reference is accepted');
assertFalse(in_array('root.svg', $rejected, true), 'Root-relative icon is not reported as rejected');
assertFalse(in_array('relative.svg', $rejected, true), 'Relative icon is not reported as rejected');

cleanup();

if ($testsPassed) {
    echo "Custom icon relative reference test passed.\n";
    exit(0);
}

echo "Custom icon relative reference test failed.\n";
exit(1);

function cleanup(): void
{
    global $iconsDir, $iconsRootDir, $baseDir, $previousUploadDirOverride;

    @unlink($iconsDir . '/root.svg');
    @unlink($iconsDir . '/relative.svg');
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
