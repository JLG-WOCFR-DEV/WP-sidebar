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
    $defaultSettings['social_icons'] = [];
    $defaultSettings['menu_items'] = [
        [
            'type' => 'nav_menu',
            'label' => 'Primary navigation',
            'value' => 123,
            'icon_type' => 'svg_inline',
            'icon' => '',
            'nav_menu_max_depth' => 0,
            'nav_menu_filter' => 'all',
        ],
    ];

    update_option('sidebar_jlg_settings', $defaultSettings);

    $menuCache->clear();
    $GLOBALS['wp_test_transients'] = [];

    $GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_items'] = static function ($menuId) {
        $parentOne = (object) [
            'ID' => 10,
            'menu_item_parent' => '0',
            'title' => 'Parent One',
            'url' => 'http://example.com/parent-one',
            'type' => 'custom',
            'object' => 'custom',
            'object_id' => 0,
            'classes' => [],
        ];

        $childOne = (object) [
            'ID' => 11,
            'menu_item_parent' => '10',
            'title' => 'Child One',
            'url' => 'http://example.com/parent-one/child',
            'type' => 'custom',
            'object' => 'custom',
            'object_id' => 0,
            'classes' => [],
        ];

        $parentTwo = (object) [
            'ID' => 20,
            'menu_item_parent' => '0',
            'title' => 'Parent Two',
            'url' => 'http://example.com/parent-two',
            'type' => 'custom',
            'object' => 'custom',
            'object_id' => 0,
            'classes' => [],
        ];

        $childTwo = (object) [
            'ID' => 21,
            'menu_item_parent' => '20',
            'title' => 'Child Two',
            'url' => 'http://example.com/parent-two/child',
            'type' => 'custom',
            'object' => 'custom',
            'object_id' => 0,
            'classes' => [],
        ];

        return [$parentOne, $childOne, $parentTwo, $childTwo];
    };

    $extractSubmenuIds = static function (string $html): array {
        if ($html === '') {
            return [];
        }

        if (!preg_match_all('/id="sidebar-submenu-(\d+)"/', $html, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    };

    $firstRender = $renderer->renderSidebarToHtml($defaultSettings);
    $secondRender = $renderer->renderSidebarToHtml($defaultSettings);

    $testsPassed = true;

    $assertTrue = static function (bool $condition, string $message) use (&$testsPassed): void {
        if ($condition) {
            echo "[PASS] {$message}\n";

            return;
        }

        $testsPassed = false;
        echo "[FAIL] {$message}\n";
    };

    $submenuIdsFirst = is_string($firstRender) ? $extractSubmenuIds($firstRender) : [];
    $submenuIdsSecond = is_string($secondRender) ? $extractSubmenuIds($secondRender) : [];

    $assertTrue($submenuIdsFirst === [1, 2], 'First render generates sequential submenu IDs');
    $assertTrue($submenuIdsSecond === [1, 2], 'Second render resets submenu IDs to the initial sequence');

    unset($GLOBALS['wp_test_function_overrides']['wp_get_nav_menu_items']);

    if ($testsPassed) {
        echo "Sidebar submenu index reset tests passed.\n";
        exit(0);
    }

    echo "Sidebar submenu index reset tests failed.\n";
    exit(1);
}
