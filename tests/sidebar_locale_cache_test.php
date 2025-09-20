<?php
declare(strict_types=1);

use function JLG\Sidebar\plugin;

require __DIR__ . '/bootstrap.php';

$GLOBALS['wp_test_translations'] = [
    'fr_FR' => [
        'Navigation principale' => 'Navigation principale',
        'Ouvrir le menu'        => 'Ouvrir le menu',
        'Fermer le menu'        => 'Fermer le menu',
    ],
    'en_US' => [
        'Navigation principale' => 'Main navigation',
        'Ouvrir le menu'        => 'Open menu',
        'Fermer le menu'        => 'Close menu',
    ],
];

$GLOBALS['wp_test_function_overrides']['do_shortcode'] = static function ($content) {
    $GLOBALS['wp_test_shortcode_calls'] = ($GLOBALS['wp_test_shortcode_calls'] ?? 0) + 1;

    return $content . ' #' . $GLOBALS['wp_test_shortcode_calls'];
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$plugin = plugin();
$settingsRepository = $plugin->getSettingsRepository();
$sanitizer = $plugin->getSanitizer();
$renderer = $plugin->getSidebarRenderer();
$menuCache = $plugin->getMenuCache();

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

function assertContains(string $needle, string $haystack, string $message): void {
    assertTrue(strpos($haystack, $needle) !== false, $message);
}

function assertNotContains(string $needle, string $haystack, string $message): void {
    assertTrue(strpos($haystack, $needle) === false, $message);
}

$default_settings = $settingsRepository->getDefaultSettings();
$default_settings['social_icons'] = [];
update_option('sidebar_jlg_settings', $default_settings);

$input_settings = [
    'menu_items' => [
        [
            'label'     => 'Article SVG',
            'type'      => 'post',
            'icon_type' => 'svg_url',
            'icon'      => 'https://example.com/icon.svg',
            'value'     => '789',
        ],
        [
            'label'     => 'Catégorie liens',
            'type'      => 'category',
            'icon_type' => 'svg_inline',
            'icon'      => 'folder',
            'value'     => '321',
        ],
    ],
];

$sanitized_settings = $sanitizer->sanitize_settings($input_settings);
$sanitized_settings['enable_sidebar'] = true;

assertTrue(
    isset($sanitized_settings['menu_items'][0]['value']) && $sanitized_settings['menu_items'][0]['value'] === 789,
    'Post ID sanitized with absint even when icon type is svg_url'
);
assertTrue(
    isset($sanitized_settings['menu_items'][1]['value']) && $sanitized_settings['menu_items'][1]['value'] === 321,
    'Category ID preserved after sanitization'
);

update_option('sidebar_jlg_settings', $sanitized_settings);

$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];

switch_to_locale('fr_FR');
ob_start();
$renderer->render();
$french_html = ob_get_clean();

assertContains('Ouvrir le menu', $french_html, 'French menu label rendered');
assertContains('href="http://example.com/post/789"', $french_html, 'Post menu item links to the correct article');
assertContains('href="http://example.com/category/321"', $french_html, 'Category menu item links to the correct term');
assertNotContains('Open menu', $french_html, 'English menu label absent in French cache');
assertTrue(isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']), 'French transient stored');

switch_to_locale('en_US');
ob_start();
$renderer->render();
$english_html = ob_get_clean();

assertContains('Open menu', $english_html, 'English menu label rendered after locale switch');
assertNotContains('Ouvrir le menu', $english_html, 'French label absent in English cache');
assertTrue(isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US']), 'English transient stored');

switch_to_locale('fr_FR');
ob_start();
$renderer->render();
$french_cached_html = ob_get_clean();

assertContains('Ouvrir le menu', $french_cached_html, 'French cache reused correctly');

$cached_locales_option = get_option('sidebar_jlg_cached_locales', []);
assertTrue(in_array('fr_FR', $cached_locales_option, true), 'French locale tracked');
assertTrue(in_array('en_US', $cached_locales_option, true), 'English locale tracked');

$menuCache->clear();

assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR']), 'French transient cleared');
assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US']), 'English transient cleared');
assertTrue(!isset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']), 'Cached locales option cleared');

$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['wp_test_shortcode_calls'] = 0;

$dynamic_settings = $settingsRepository->getDefaultSettings();
$dynamic_settings['social_icons'] = [];
$dynamic_settings['enable_search'] = true;
$dynamic_settings['search_method'] = 'shortcode';
$dynamic_settings['search_shortcode'] = '[dynamic]';

update_option('sidebar_jlg_settings', $dynamic_settings);

switch_to_locale('en_US');
ob_start();
$renderer->render();
$first_dynamic_html = ob_get_clean();

$dynamic_transient_key = 'sidebar_jlg_full_html_en_US';
assertTrue(!isset($GLOBALS['wp_test_transients'][$dynamic_transient_key]), 'Dynamic sidebar render skips transient storage');
assertContains('#1', $first_dynamic_html, 'Dynamic render includes first shortcode marker');

ob_start();
$renderer->render();
$second_dynamic_html = ob_get_clean();

assertContains('#2', $second_dynamic_html, 'Dynamic render increments shortcode marker on subsequent render');
assertTrue($first_dynamic_html !== $second_dynamic_html, 'Dynamic HTML regenerated for each render when cache disabled');
assertTrue(!isset($GLOBALS['wp_test_transients'][$dynamic_transient_key]), 'Dynamic sidebar never stores persistent transients');
assertTrue(empty(get_option('sidebar_jlg_cached_locales', [])), 'Cached locales not tracked when cache is disabled');

$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];
$GLOBALS['wp_test_shortcode_calls'] = 0;

$search_disabled_settings = $settingsRepository->getDefaultSettings();
$search_disabled_settings['social_icons'] = [];
$search_disabled_settings['enable_sidebar'] = true;
$search_disabled_settings['enable_search'] = false;
$search_disabled_settings['search_method'] = 'shortcode';
$search_disabled_settings['search_shortcode'] = '[disabled]';

update_option('sidebar_jlg_settings', $search_disabled_settings);

switch_to_locale('en_US');
ob_start();
$renderer->render();
$search_disabled_first_html = ob_get_clean();

$search_disabled_transient_key = 'sidebar_jlg_full_html_en_US';
assertTrue(isset($GLOBALS['wp_test_transients'][$search_disabled_transient_key]), 'Sidebar cache stored when search disabled despite shortcode method');

ob_start();
$renderer->render();
$search_disabled_second_html = ob_get_clean();

assertTrue($search_disabled_first_html === $search_disabled_second_html, 'Cached HTML reused when search disabled despite shortcode method');
assertTrue(($GLOBALS['wp_test_shortcode_calls'] ?? 0) === 0, 'Shortcode not executed when search disabled');

$cached_locales_option = get_option('sidebar_jlg_cached_locales', []);
assertTrue(in_array('en_US', $cached_locales_option, true), 'Locale cached when search disabled with shortcode method');

if ($testsPassed) {
    echo "Sidebar locale cache tests passed.\n";
    exit(0);
}

echo "Sidebar locale cache tests failed.\n";
exit(1);
