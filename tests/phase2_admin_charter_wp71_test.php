<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Frontend\Blocks\SearchBlock;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;
use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$testsPassed = true;

function phase2_assert(bool $condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
}

function phase2_assert_contains(string $haystack, string $needle, string $message): void
{
    phase2_assert(strpos($haystack, $needle) !== false, $message);
}

function phase2_assert_not_contains(string $haystack, string $needle, string $message): void
{
    phase2_assert(strpos($haystack, $needle) === false, $message);
}

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
$readmeFile = __DIR__ . '/../sidebar-jlg/readme.txt';
$rootReadme = __DIR__ . '/../README.md';
$adminPage = __DIR__ . '/../sidebar-jlg/includes/admin-page.php';
$menuPage = __DIR__ . '/../sidebar-jlg/src/Admin/MenuPage.php';
$adminCss = __DIR__ . '/../sidebar-jlg/assets/css/admin-style.css';
$canvasCss = __DIR__ . '/../sidebar-jlg/assets/css/admin-canvas.css';
$canvasScss = __DIR__ . '/../sidebar-jlg/assets/css/admin-canvas.scss';
$publicScript = __DIR__ . '/../sidebar-jlg/assets/js/public-script.js';
$legacyAdmin = __DIR__ . '/../sidebar-jlg/assets/js/admin/admin-legacy.ts';
$reactApp = __DIR__ . '/../sidebar-jlg/assets/js/admin-app/App.tsx';
$blockJson = __DIR__ . '/../sidebar-jlg/assets/blocks/sidebar-search/block.json';
$searchBlockSource = __DIR__ . '/../sidebar-jlg/src/Frontend/Blocks/SearchBlock.php';
$rendererSource = __DIR__ . '/../sidebar-jlg/src/Frontend/SidebarRenderer.php';

$pluginHeader = (string) file_get_contents($pluginFile);
$readme = (string) file_get_contents($readmeFile);
$root = (string) file_get_contents($rootReadme);
$adminMarkup = (string) file_get_contents($adminPage);
$menuSource = (string) file_get_contents($menuPage);
$adminStyle = (string) file_get_contents($adminCss);
$canvasStyle = (string) file_get_contents($canvasCss);
$canvasScssSource = (string) file_get_contents($canvasScss);
$publicJs = (string) file_get_contents($publicScript);
$legacyJs = (string) file_get_contents($legacyAdmin);
$appSource = (string) file_get_contents($reactApp);
$block = (string) file_get_contents($blockJson);
$searchSource = (string) file_get_contents($searchBlockSource);
$rendererPhp = (string) file_get_contents($rendererSource);

phase2_assert_contains($pluginHeader, 'Requires at least:', 'Plugin header declares Requires at least');
phase2_assert_contains($pluginHeader, 'Requires PHP:', 'Plugin header declares Requires PHP');
phase2_assert_contains($pluginHeader, 'Tested up to:     7.1', 'Plugin header declares Tested up to 7.1');
phase2_assert_contains($readme, 'Tested up to: 7.1', 'readme.txt declares Tested up to 7.1');
phase2_assert_contains($readme, 'Requires PHP: 7.4', 'readme.txt keeps Requires PHP 7.4');
phase2_assert_contains($root, '7.1', 'Root README mentions WordPress 7.1');

$h1Pos = strpos($adminMarkup, '<h1');
$wrapPos = strpos($adminMarkup, 'class="wrap sidebar-jlg-admin-wrap"');
$navPos = strpos($adminMarkup, 'class="nav-tab-wrapper"');
$appPos = strpos($adminMarkup, 'id="sidebar-jlg-admin-app-root"');
$legacyPos = strpos($adminMarkup, 'id="sidebar-jlg-legacy-settings"');

phase2_assert($wrapPos !== false, 'Admin page uses wrap sidebar-jlg-admin-wrap');
phase2_assert($h1Pos !== false && $wrapPos !== false && $h1Pos > $wrapPos, 'h1 is inside wrap');
phase2_assert($navPos !== false && $h1Pos !== false && $navPos > $h1Pos, 'nav-tab-wrapper is under h1');
phase2_assert($appPos !== false && $h1Pos !== false && $appPos > $h1Pos, 'React root does not precede h1');
phase2_assert($legacyPos !== false && $h1Pos !== false && $legacyPos > $h1Pos, 'Legacy form does not precede h1');
phase2_assert_contains($adminMarkup, 'settings_fields( \'sidebar_jlg_options_group\' )', 'Admin form uses Settings API settings_fields');
phase2_assert_contains($adminMarkup, 'class="form-table', 'Admin form uses form-table');
phase2_assert_contains($adminMarkup, 'button-primary', 'Admin form uses button-primary');
phase2_assert_contains($adminMarkup, 'submit_button()', 'Admin form uses submit_button()');
phase2_assert_contains($adminMarkup, 'id="sidebar-jlg-js-notices"', 'Admin page exposes a JS notice region');
phase2_assert_contains($menuSource, 'register_setting(', 'MenuPage registers settings via Settings API');

phase2_assert_not_contains(
    $canvasStyle,
    "[data-sidebar-experience][data-sidebar-experience-mode='canvas'] .nav-tab-wrapper",
    'Canvas CSS does not hide native nav-tab-wrapper'
);
phase2_assert_not_contains(
    $canvasScssSource,
    ".nav-tab-wrapper,",
    'Canvas SCSS does not hide native nav-tab-wrapper'
);
phase2_assert_not_contains(
    $canvasStyle,
    "position: fixed;",
    'Canvas overlay does not cover wp-admin chrome with position:fixed'
);
phase2_assert_not_contains(
    $adminStyle,
    '.sidebar-jlg-admin-app__root.is-mounted + #sidebar-jlg-legacy-settings #tab-general-tab',
    'Mounted React app does not hide native nav-tab items'
);
phase2_assert_not_contains($adminStyle, '#00875b', 'Admin CSS does not use custom toast success green');
phase2_assert(
    !preg_match('/^\s*\.button(?:\.button-primary)?\s*\{/m', $adminStyle),
    'Admin CSS does not restyle unscoped WP .button / .button-primary'
);

phase2_assert_contains($legacyJs, 'notice notice-', 'Legacy admin notices use WP notice-* classes');
phase2_assert_contains($legacyJs, 'notice-dismiss', 'Legacy admin notices use notice-dismiss');
phase2_assert_not_contains($legacyJs, 'sidebar-jlg-toast--success', 'Legacy admin notices no longer use custom toast tones');

phase2_assert_not_contains($appSource, 'TabPanel', 'React app no longer mounts a competing TabPanel chrome');
phase2_assert_contains($appSource, 'OnboardingModal', 'React app still hosts the onboarding modal');

phase2_assert_contains($block, '"apiVersion": 3', 'Search block declares apiVersion 3');
phase2_assert_contains($publicJs, 'SIDEBAR_JLG_IS_EDITOR', 'Front JS reads the editor canvas flag');
phase2_assert_contains($publicJs, 'block-editor-iframe__body', 'Front JS detects the iframed editor body class');
phase2_assert_contains($searchSource, 'enqueue_block_assets', 'Search block injects the editor canvas guard');
phase2_assert_contains($searchSource, 'SIDEBAR_JLG_IS_EDITOR', 'Search block sets SIDEBAR_JLG_IS_EDITOR');
phase2_assert_contains($rendererPhp, 'isEditorCanvasRequest', 'Frontend renderer skips the iframed editor');

$GLOBALS['test_is_admin'] = true;

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return !empty($GLOBALS['test_is_admin']);
    }
}

$registeredHooks = [];
$previousAddAction = $GLOBALS['wp_test_function_overrides']['add_action'] ?? null;
$GLOBALS['wp_test_function_overrides']['add_action'] = static function ($hook, $callback, $priority = 10, $accepted_args = 1) use (&$registeredHooks): void {
    $registeredHooks[] = (string) $hook;
};

$defaults = new DefaultSettings();
$iconLibrary = new IconLibrary($pluginFile);
$sanitizer = new SettingsSanitizer($defaults, $iconLibrary);
$settings = new SettingsRepository($defaults, $iconLibrary, $sanitizer);
$blockInstance = new SearchBlock($settings, $pluginFile, 'test-version');
$blockInstance->registerHooks();

phase2_assert(in_array('init', $registeredHooks, true), 'Search block still registers on init');
phase2_assert(in_array('enqueue_block_assets', $registeredHooks, true), 'Search block registers enqueue_block_assets');
phase2_assert(in_array('enqueue_block_editor_assets', $registeredHooks, true), 'Search block registers enqueue_block_editor_assets');

if ($previousAddAction !== null) {
    $GLOBALS['wp_test_function_overrides']['add_action'] = $previousAddAction;
} else {
    unset($GLOBALS['wp_test_function_overrides']['add_action']);
}

$inlineScripts = [];
$enqueuedScripts = [];
$previousInline = $GLOBALS['wp_test_function_overrides']['wp_add_inline_script'] ?? null;
$previousEnqueue = $GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] ?? null;
$GLOBALS['wp_test_function_overrides']['wp_add_inline_script'] = static function ($handle, $data, $position = 'after') use (&$inlineScripts): void {
    $inlineScripts[] = (string) $data;
};
$GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] = static function (...$args) use (&$enqueuedScripts): void {
    $handle = $args[0] ?? null;
    if ($handle !== null) {
        $enqueuedScripts[] = $handle;
    }
};

$blockInstance->enqueueEditorCanvasGuard();
$guardMarkup = implode("\n", $inlineScripts);
phase2_assert_contains($guardMarkup, 'window.SIDEBAR_JLG_IS_EDITOR = true;', 'Editor canvas guard sets the JS flag in admin');
phase2_assert(in_array('sidebar-jlg-editor-canvas-guard', $enqueuedScripts, true), 'Editor canvas guard script is enqueued in admin');

$GLOBALS['test_is_admin'] = false;
$inlineScripts = [];
$enqueuedScripts = [];
$blockInstance->enqueueEditorCanvasGuard();
phase2_assert($inlineScripts === [] && $enqueuedScripts === [], 'Editor canvas guard stays off on the front');

if ($previousInline !== null) {
    $GLOBALS['wp_test_function_overrides']['wp_add_inline_script'] = $previousInline;
} else {
    unset($GLOBALS['wp_test_function_overrides']['wp_add_inline_script']);
}

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$options = $settingsRepository->getDefaultSettings();
$options['enable_sidebar'] = true;
$settingsRepository->saveOptions($options);

$frontScripts = [];
$GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] = static function (...$args) use (&$frontScripts): void {
    $handle = $args[0] ?? null;
    if ($handle !== null) {
        $frontScripts[] = $handle;
    }
};

$renderer->enqueueAssets();
phase2_assert(in_array('sidebar-jlg-public-js', $frontScripts, true), 'Public script still enqueues on the front');

$frontScripts = [];
$_GET['canvas'] = 'edit';
$renderer->enqueueAssets();
phase2_assert(!in_array('sidebar-jlg-public-js', $frontScripts, true), 'Public script is skipped when canvas=edit');

$baseline = ['baseline-class'];
$editorClasses = $renderer->addBodyClasses($baseline);
phase2_assert($editorClasses === $baseline, 'Body classes stay unchanged in the iframed editor');

unset($_GET['canvas']);

if ($previousEnqueue !== null) {
    $GLOBALS['wp_test_function_overrides']['wp_enqueue_script'] = $previousEnqueue;
} else {
    unset($GLOBALS['wp_test_function_overrides']['wp_enqueue_script']);
}

if ($testsPassed) {
    echo "Phase 2 admin charter / WP 7.1 tests passed\n";
    exit(0);
}

echo "Phase 2 admin charter / WP 7.1 tests failed\n";
exit(1);
