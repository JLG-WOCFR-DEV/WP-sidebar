<?php
declare(strict_types=1);

use JLG\Sidebar\Plugin as SidebarPlugin;

require __DIR__ . '/bootstrap.php';

$testsPassed = true;

function assertTrue($condition, string $message): void {
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . '/sidebar-jlg-custom-icon-cache-' . uniqid('', true);
$iconsDirectory = $temporaryRoot . '/sidebar-jlg/icons';

if (!is_dir($iconsDirectory)) {
    mkdir($iconsDirectory, 0777, true);
}

$iconFile = $iconsDirectory . '/custom-change.svg';
file_put_contents(
    $iconFile,
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" /></svg>'
);

$originalUploadOverride = $GLOBALS['wp_test_function_overrides']['wp_upload_dir'] ?? null;
$GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = static function () use ($temporaryRoot): array {
    return [
        'basedir' => $temporaryRoot,
        'baseurl' => 'http://example.com/uploads',
    ];
};

$originalAddActionOverride = $GLOBALS['wp_test_function_overrides']['add_action'] ?? null;
$originalDoActionOverride = $GLOBALS['wp_test_function_overrides']['do_action'] ?? null;
$registeredHooks = [];

$GLOBALS['wp_test_function_overrides']['add_action'] = static function ($hook, $callback, $priority = 10, $acceptedArgs = 1) use (&$registeredHooks): void {
    $registeredHooks[$hook][] = [
        'callback' => $callback,
        'accepted_args' => (int) $acceptedArgs,
    ];
};

$GLOBALS['wp_test_function_overrides']['do_action'] = static function ($hook, ...$args) use (&$registeredHooks): void {
    if (!isset($registeredHooks[$hook])) {
        return;
    }

    foreach ($registeredHooks[$hook] as $listener) {
        $callback = $listener['callback'];
        $acceptedArgs = $listener['accepted_args'];

        if ($acceptedArgs > 0) {
            $callArgs = array_slice($args, 0, $acceptedArgs);
            call_user_func_array($callback, $callArgs);
        } else {
            call_user_func($callback);
        }
    }
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] = SIDEBAR_JLG_VERSION;
$GLOBALS['wp_test_options']['sidebar_jlg_cached_locales'] = ['fr_FR', 'en_US'];
$GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR'] = '<div>FR</div>';
$GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US'] = '<div>EN</div>';
$GLOBALS['wp_test_options']['sidebar_jlg_custom_icon_index'] = [
    'custom-change.svg' => [
        'mtime' => 0,
        'size' => 0,
    ],
];

$plugin = new SidebarPlugin(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php', SIDEBAR_JLG_VERSION);
$plugin->register();

$iconLibrary = $plugin->getIconLibrary();
$iconLibrary->getAllIcons();

assertTrue(
    !isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']),
    'French sidebar cache cleared when custom icons change'
);
assertTrue(
    !isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US']),
    'English sidebar cache cleared when custom icons change'
);
assertTrue(
    !isset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']),
    'Cached locales option removed after custom icon change'
);

if ($originalAddActionOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['add_action']);
} else {
    $GLOBALS['wp_test_function_overrides']['add_action'] = $originalAddActionOverride;
}

if ($originalDoActionOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['do_action']);
} else {
    $GLOBALS['wp_test_function_overrides']['do_action'] = $originalDoActionOverride;
}

if ($originalUploadOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['wp_upload_dir']);
} else {
    $GLOBALS['wp_test_function_overrides']['wp_upload_dir'] = $originalUploadOverride;
}

unset($registeredHooks);

if (is_file($iconFile)) {
    unlink($iconFile);
}

if (is_dir($iconsDirectory)) {
    rmdir($iconsDirectory);
}

if (is_dir($temporaryRoot . '/sidebar-jlg')) {
    rmdir($temporaryRoot . '/sidebar-jlg');
}

if (is_dir($temporaryRoot)) {
    rmdir($temporaryRoot);
}

if ($testsPassed) {
    echo "Custom icon cache invalidation tests passed.\n";
    exit(0);
}

echo "Custom icon cache invalidation tests failed.\n";
exit(1);
