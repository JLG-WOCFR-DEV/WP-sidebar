<?php
declare(strict_types=1);

namespace {
    require __DIR__ . '/bootstrap.php';
}

namespace JLG\Sidebar\Frontend {
    if (!function_exists(__NAMESPACE__ . '\\ob_start')) {
        function ob_start(...$args): bool
        {
            $handled = false;
            $result = \wp_test_call_override(__NAMESPACE__ . '\\ob_start', $args, $handled);
            if ($handled) {
                return (bool) $result;
            }

            return \ob_start(...$args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\ob_get_clean')) {
        function ob_get_clean()
        {
            $handled = false;
            $result = \wp_test_call_override(__NAMESPACE__ . '\\ob_get_clean', [], $handled);
            if ($handled) {
                return $result;
            }

            return \ob_get_clean();
        }
    }
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

    $setTransientCalls = [];
    $GLOBALS['wp_test_function_overrides']['set_transient'] = static function ($key, $value, $expiration = 0) use (&$setTransientCalls) {
        $setTransientCalls[] = ['key' => $key, 'value' => $value, 'expiration' => $expiration];

        return true;
    };

    $GLOBALS['wp_test_function_overrides']['JLG\\Sidebar\\Frontend\\ob_get_clean'] = static function () {
        return false;
    };

    ob_start();
    $renderer->render();
    $output = ob_get_clean();

    unset($GLOBALS['wp_test_function_overrides']['JLG\\Sidebar\\Frontend\\ob_get_clean']);
    unset($GLOBALS['wp_test_function_overrides']['set_transient']);

    $testsPassed = true;

    $assertTrue = static function ($condition, string $message) use (&$testsPassed): void {
        if ($condition) {
            echo "[PASS] {$message}\n";

            return;
        }

        $testsPassed = false;
        echo "[FAIL] {$message}\n";
    };

    $assertTrue($output === '', 'No sidebar output emitted when buffer capture fails');
    $assertTrue($setTransientCalls === [], 'Cache is not written when buffer capture fails');

    if ($testsPassed) {
        echo "Render sidebar buffer failure tests passed.\n";
        exit(0);
    }

    echo "Render sidebar buffer failure tests failed.\n";
    exit(1);
}
