<?php
declare(strict_types=1);

namespace {
    require __DIR__ . '/bootstrap.php';
}

namespace {
    use function JLG\Sidebar\plugin;

    require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

    $plugin = plugin();
    $renderer = $plugin->getSidebarRenderer();
    $settingsRepository = $plugin->getSettingsRepository();
    $menuCache = $plugin->getMenuCache();

    $defaultSettings = $settingsRepository->getDefaultSettings();
    $defaultSettings['enable_sidebar'] = '1';
    update_option('sidebar_jlg_settings', $defaultSettings);

    $menuCache->clear();
    $GLOBALS['wp_test_transients'] = [];

    $tempDir = sys_get_temp_dir();
    $themeTemplate = tempnam($tempDir, 'sidebar-theme');
    $filterTemplate = tempnam($tempDir, 'sidebar-filter');

    file_put_contents($themeTemplate, "<?php echo '<div class=\\"theme-template\\">Theme override</div>';\n");
    file_put_contents($filterTemplate, "<?php echo '<div class=\\"filter-template\\">Filter override</div>';\n");

    $GLOBALS['wp_test_function_overrides']['locate_template'] = static function ($templates, $load = false) use ($themeTemplate) {
        return $themeTemplate;
    };

    $testsPassed = true;

    $assertContains = static function (string $needle, string $haystack, string $message) use (&$testsPassed): void {
        if (strpos($haystack, $needle) !== false) {
            echo "[PASS] {$message}\n";

            return;
        }

        $testsPassed = false;
        echo "[FAIL] {$message}\n";
    };

    $menuCache->clear();
    $GLOBALS['wp_test_transients'] = [];
    $htmlFromTheme = $renderer->render();

    if (!is_string($htmlFromTheme)) {
        echo "[FAIL] Failed to render sidebar with theme override.\n";
        $testsPassed = false;
    } else {
        $assertContains('theme-template', $htmlFromTheme, 'Theme template override is used when located by locate_template');
    }

    $GLOBALS['wp_test_function_overrides']['apply_filters'] = static function ($hook, $value) use ($filterTemplate) {
        if ($hook === 'sidebar_jlg_template_path') {
            return $filterTemplate;
        }

        return $value;
    };

    $menuCache->clear();
    $GLOBALS['wp_test_transients'] = [];
    $htmlFromFilter = $renderer->render();

    if (!is_string($htmlFromFilter)) {
        echo "[FAIL] Failed to render sidebar with filter override.\n";
        $testsPassed = false;
    } else {
        $assertContains('filter-template', $htmlFromFilter, 'Filter-based template override has priority over theme override');
    }

    unset($GLOBALS['wp_test_function_overrides']['locate_template']);
    unset($GLOBALS['wp_test_function_overrides']['apply_filters']);

    @unlink($themeTemplate);
    @unlink($filterTemplate);

    if ($testsPassed) {
        echo "Render sidebar template override tests passed.\n";
        exit(0);
    }

    echo "Render sidebar template override tests failed.\n";
    exit(1);
}
