<?php
declare(strict_types=1);

use JLG\Sidebar\Plugin as SidebarPlugin;
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

function renderSidebarHtml(): string {
    global $renderer;

    $html = $renderer->render();
    assertTrue(is_string($html), 'Sidebar renderer returned HTML during locale cache test');

    return (string) $html;
}

function cachedLocaleExists(array $entries, string $locale, ?string $suffix): bool {
    foreach ($entries as $entry) {
        if (is_array($entry)) {
            $entryLocale = isset($entry['locale']) ? (string) $entry['locale'] : '';
            $entrySuffix = $entry['suffix'] ?? null;
            if ($entrySuffix !== null && !is_string($entrySuffix)) {
                $entrySuffix = null;
            }
        } elseif (is_string($entry)) {
            $entryLocale = $entry;
            $entrySuffix = null;
        } else {
            continue;
        }

        if ($entryLocale === $locale) {
            $normalizedSuffix = $suffix ?? null;
            $entryNormalized = is_string($entrySuffix) ? $entrySuffix : null;

            if ($entryNormalized === $normalizedSuffix) {
                return true;
            }
        }
    }

    return false;
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
$sanitized_settings['content_margin'] = 'calc(10px + 5%)';

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
$GLOBALS['wp_test_inline_styles'] = [];
$renderer->enqueueAssets();
$french_inline_styles = wp_test_get_inline_styles('sidebar-jlg-public-css');
$french_html = renderSidebarHtml();

assertContains('Ouvrir le menu', $french_html, 'French menu label rendered');
assertContains('href="http://example.com/post/789"', $french_html, 'Post menu item links to the correct article');
assertContains('href="http://example.com/category/321"', $french_html, 'Category menu item links to the correct term');
assertContains('calc(var(--sidebar-width-desktop) + 10px + 5%)', $french_inline_styles, 'Content margin calc expression flattened');
assertNotContains('calc(calc', $french_inline_styles, 'Content margin does not contain nested calc');
assertNotContains('Open menu', $french_html, 'English menu label absent in French cache');
assertTrue(isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR_default']), 'French transient stored');

switch_to_locale('en_US');
$english_html = renderSidebarHtml();

assertContains('Open menu', $english_html, 'English menu label rendered after locale switch');
assertNotContains('Ouvrir le menu', $english_html, 'French label absent in English cache');
assertTrue(isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_default']), 'English transient stored');

switch_to_locale('fr_FR');
$french_cached_html = renderSidebarHtml();

assertContains('Ouvrir le menu', $french_cached_html, 'French cache reused correctly');

$cached_locales_option = get_option('sidebar_jlg_cached_locales', []);
assertTrue(cachedLocaleExists($cached_locales_option, 'fr_FR', 'default'), 'French locale tracked');
assertTrue(cachedLocaleExists($cached_locales_option, 'en_US', 'default'), 'English locale tracked');

$menuCache->clear();

assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR_default']), 'French transient cleared');
assertTrue(!isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_en_US_default']), 'English transient cleared');
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
$first_dynamic_html = renderSidebarHtml();

$dynamic_transient_key = 'sidebar_jlg_full_html_en_US_default';
assertTrue(!isset($GLOBALS['wp_test_transients'][$dynamic_transient_key]), 'Dynamic sidebar render skips transient storage');
assertContains('#1', $first_dynamic_html, 'Dynamic render includes first shortcode marker');

$second_dynamic_html = renderSidebarHtml();

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
$search_disabled_first_html = renderSidebarHtml();

$search_disabled_transient_key = 'sidebar_jlg_full_html_en_US_default';
assertTrue(isset($GLOBALS['wp_test_transients'][$search_disabled_transient_key]), 'Sidebar cache stored when search disabled despite shortcode method');

$search_disabled_second_html = renderSidebarHtml();

assertTrue($search_disabled_first_html === $search_disabled_second_html, 'Cached HTML reused when search disabled despite shortcode method');
assertTrue(($GLOBALS['wp_test_shortcode_calls'] ?? 0) === 0, 'Shortcode not executed when search disabled');

$cached_locales_option = get_option('sidebar_jlg_cached_locales', []);
assertTrue(cachedLocaleExists($cached_locales_option, 'en_US', 'default'), 'Locale cached when search disabled with shortcode method');

$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];
delete_option('sidebar_jlg_cached_locales');
$GLOBALS['wp_test_default_search_calls'] = 0;
$GLOBALS['wp_test_function_overrides']['get_search_form'] = static function () {
    $GLOBALS['wp_test_default_search_calls'] = ($GLOBALS['wp_test_default_search_calls'] ?? 0) + 1;
    echo 'DEFAULT_SEARCH #' . $GLOBALS['wp_test_default_search_calls'];

    return '';
};

$default_search_settings = $settingsRepository->getDefaultSettings();
$default_search_settings['social_icons'] = [];
$default_search_settings['enable_sidebar'] = true;
$default_search_settings['enable_search'] = true;
$default_search_settings['search_method'] = 'default';

update_option('sidebar_jlg_settings', $default_search_settings);

$default_search_transient_key = 'sidebar_jlg_full_html_en_US_default';
switch_to_locale('en_US');
$default_search_first_html = renderSidebarHtml();

assertContains('DEFAULT_SEARCH #1', $default_search_first_html, 'Default search render outputs first marker');
assertTrue(!isset($GLOBALS['wp_test_transients'][$default_search_transient_key]), 'Default search render skips transient storage on first render');

$default_search_second_html = renderSidebarHtml();

assertContains('DEFAULT_SEARCH #2', $default_search_second_html, 'Default search render outputs second marker');

assertTrue(!isset($GLOBALS['wp_test_transients'][$default_search_transient_key]), 'Default search render skips transient storage on subsequent render');
assertTrue(($GLOBALS['wp_test_default_search_calls'] ?? 0) === 2, 'Default search callback executed on each render');
assertTrue(empty(get_option('sidebar_jlg_cached_locales', [])), 'Cached locales not tracked when default search keeps cache disabled');

unset($GLOBALS['wp_test_function_overrides']['get_search_form']);
unset($GLOBALS['wp_test_default_search_calls']);

$menuCache->clear();
$GLOBALS['wp_test_transients'] = [];
unset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']);

$originalAddActionOverride = $GLOBALS['wp_test_function_overrides']['add_action'] ?? null;
$originalUpdateOptionOverride = $GLOBALS['wp_test_function_overrides']['update_option'] ?? null;
$registeredHooks = [];

$GLOBALS['wp_test_function_overrides']['add_action'] = static function ($hook, $callback, $priority = 10, $accepted_args = 1) use (&$registeredHooks): void {
    $registeredHooks[$hook][] = [
        'callback' => $callback,
        'accepted_args' => (int) $accepted_args,
    ];
};

$GLOBALS['wp_test_function_overrides']['update_option'] = static function ($name, $value, $autoload = null) use (&$registeredHooks): bool {
    $GLOBALS['wp_test_options'][$name] = $value;

    $hook = 'update_option_' . $name;
    if (isset($registeredHooks[$hook])) {
        foreach ($registeredHooks[$hook] as $listener) {
            $callback = $listener['callback'];
            $acceptedArgs = $listener['accepted_args'];

            if ($acceptedArgs > 0) {
                $args = array_slice([$value], 0, $acceptedArgs);
                call_user_func_array($callback, $args);
            } else {
                call_user_func($callback);
            }
        }
    }

    return true;
};

$GLOBALS['wp_test_options']['sidebar_jlg_plugin_version'] = SIDEBAR_JLG_VERSION;
$GLOBALS['wp_test_options']['sidebar_jlg_cached_locales'] = [
    ['locale' => 'fr_FR', 'suffix' => 'default'],
];
$GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR_default'] = '<div>cached</div>';
$GLOBALS['wp_test_options']['sidebar_jlg_settings'] = [
    'menu_items' => [
        [
            'label' => 'Corrupted item',
            'type' => 'custom',
            'icon_type' => 'svg_inline',
            'icon' => 'custom_missing',
        ],
    ],
];

$revalidatingPlugin = new SidebarPlugin(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php', SIDEBAR_JLG_VERSION);
$revalidatingPlugin->register();

assertTrue(
    !isset($GLOBALS['wp_test_transients']['sidebar_jlg_full_html_fr_FR_default']),
    'Cache transient cleared when revalidation updates corrupted options'
);
assertTrue(
    !isset($GLOBALS['wp_test_options']['sidebar_jlg_cached_locales']),
    'Cached locales option removed when cache cleared via revalidation'
);

if ($originalAddActionOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['add_action']);
} else {
    $GLOBALS['wp_test_function_overrides']['add_action'] = $originalAddActionOverride;
}

if ($originalUpdateOptionOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['update_option']);
} else {
    $GLOBALS['wp_test_function_overrides']['update_option'] = $originalUpdateOptionOverride;
}

unset($registeredHooks);

if ($testsPassed) {
    echo "Sidebar locale cache tests passed.\n";
    exit(0);
}

echo "Sidebar locale cache tests failed.\n";
exit(1);
