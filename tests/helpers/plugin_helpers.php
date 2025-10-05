<?php
declare(strict_types=1);

use JLG\Sidebar\Cache\MenuCache;
use JLG\Sidebar\Frontend\ProfileSelector;
use JLG\Sidebar\Frontend\RequestContextResolver;
use JLG\Sidebar\Frontend\SidebarRenderer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;
use JLG\Sidebar\Settings\SettingsRepository;

require_once __DIR__ . '/../../sidebar-jlg/sidebar-jlg.php';

function sidebar_jlg_create_test_sidebar_renderer(): SidebarRenderer
{
    $pluginFile = __DIR__ . '/../../sidebar-jlg/sidebar-jlg.php';
    $version = 'test';

    $defaults = new DefaultSettings();
    $icons = new IconLibrary($pluginFile);
    $settings = new SettingsRepository($defaults, $icons);
    $cache = new MenuCache();
    $contextResolver = new RequestContextResolver();
    $profileSelector = new ProfileSelector($settings, $contextResolver);

    return new SidebarRenderer($settings, $icons, $cache, $profileSelector, $contextResolver, $pluginFile, $version);
}
