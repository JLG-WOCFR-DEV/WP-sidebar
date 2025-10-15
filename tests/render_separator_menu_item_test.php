<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

$settings = $settingsRepository->getDefaultSettings();
$settings['menu_items'] = [
    [
        'type' => 'separator',
        'label' => 'Raccourcis',
    ],
    [
        'type' => 'custom',
        'label' => 'Accueil',
        'value' => 'https://example.com/',
    ],
];
$settings['social_icons'] = [];

update_option('sidebar_jlg_settings', $settings);

$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['REQUEST_URI'] = '/';
unset($_SERVER['HTTPS']);

$menuCache->clear();

$resetResolver = \Closure::bind(
    static function (): void {
        if (self::$sharedRequestContextResolver !== null) {
            self::$sharedRequestContextResolver->resetCachedContext();
        }
    },
    null,
    \JLG\Sidebar\Frontend\SidebarRenderer::class
);

if (is_callable($resetResolver)) {
    $resetResolver();
}

$html = $renderer->render();
if (!is_string($html) || $html === '') {
    echo "Sidebar renderer did not return HTML for separator scenario.\n";
    exit(1);
}

if (strpos($html, 'menu-item menu-item-static menu-separator') === false) {
    echo "Expected rendered HTML to contain a menu separator list item.\n";
    exit(1);
}

if (strpos($html, 'menu-separator__label') === false || strpos($html, 'Raccourcis') === false) {
    echo "Expected separator label to be rendered in markup.\n";
    exit(1);
}

if (substr_count($html, 'menu-separator__line') < 2) {
    echo "Separator should render decorative lines around the label.\n";
    exit(1);
}

echo "Separator rendering test passed.\n";
exit(0);
