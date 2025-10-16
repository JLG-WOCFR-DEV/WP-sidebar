<?php
declare(strict_types=1);

namespace JLG\Sidebar\Ajax {
    if (!function_exists(__NAMESPACE__ . '\\is_uploaded_file')) {
        function is_uploaded_file($filename): bool
        {
            return is_string($filename) && $filename !== '' && file_exists($filename);
        }
    }
}

namespace {
    use JLG\Sidebar\Accessibility\AuditRunner;
    use JLG\Sidebar\Ajax\Endpoints;
    use JLG\Sidebar\Analytics\AnalyticsEventQueue;
    use JLG\Sidebar\Analytics\AnalyticsRepository;
    use JLG\Sidebar\Analytics\EventRateLimiter;
    use JLG\Sidebar\Cache\MenuCache;
    use JLG\Sidebar\Frontend\ProfileSelector;
    use JLG\Sidebar\Frontend\RequestContextResolver;
    use JLG\Sidebar\Frontend\SidebarRenderer;
    use JLG\Sidebar\Icons\IconLibrary;
    use JLG\Sidebar\Settings\DefaultSettings;
    use JLG\Sidebar\Settings\SettingsRepository;
    use JLG\Sidebar\Admin\SettingsSanitizer;

    require __DIR__ . '/bootstrap.php';

    if (!defined('SIDEBAR_JLG_SKIP_BOOTSTRAP')) {
        define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);
    }

    require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

    if (!class_exists('WP_Die_Exception')) {
        class WP_Die_Exception extends \Exception
        {
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can($capability): bool
        {
            return $GLOBALS['test_current_user_can'] ?? true;
        }
    }

    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer($action, $query_arg = false): void
        {
            $value = $query_arg !== false ? ($_POST[$query_arg] ?? null) : null;
            $GLOBALS['checked_nonces'][] = [$action, $query_arg, $value];
        }
    }

    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null): void
        {
            $GLOBALS['json_success_payloads'][] = $data;
            throw new WP_Die_Exception('success');
        }
    }

    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null): void
        {
            $GLOBALS['json_error_payloads'][] = $data;
            throw new WP_Die_Exception('error');
        }
    }

    $GLOBALS['test_current_user_can'] = true;
    $GLOBALS['checked_nonces'] = [];
    $GLOBALS['json_success_payloads'] = [];
    $GLOBALS['json_error_payloads'] = [];
    $GLOBALS['wp_test_options'] = $GLOBALS['wp_test_options'] ?? [];
    $GLOBALS['wp_test_options']['siteurl'] = 'https://example.com';

    $pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

    $defaults = new DefaultSettings();
    $iconLibrary = new IconLibrary($pluginFile);
    $sanitizer = new SettingsSanitizer($defaults, $iconLibrary);
    $settingsRepository = new SettingsRepository($defaults, $iconLibrary, $sanitizer);
    $menuCache = new MenuCache();
    $eventRateLimiter = new EventRateLimiter();
    $analyticsRepository = new AnalyticsRepository();
    $analyticsQueue = new AnalyticsEventQueue($analyticsRepository);
    $requestContextResolver = new RequestContextResolver();
    $profileSelector = new ProfileSelector($settingsRepository, $requestContextResolver);
    $sidebarRenderer = new SidebarRenderer(
        $settingsRepository,
        $iconLibrary,
        $menuCache,
        $profileSelector,
        $requestContextResolver,
        $pluginFile,
        defined('SIDEBAR_JLG_VERSION') ? SIDEBAR_JLG_VERSION : '0.0.0'
    );
    $auditRunner = new AuditRunner($pluginFile);

    $endpoints = new Endpoints(
        $settingsRepository,
        $menuCache,
        $iconLibrary,
        $sanitizer,
        $analyticsRepository,
        $analyticsQueue,
        $eventRateLimiter,
        $pluginFile,
        $sidebarRenderer,
        $auditRunner
    );

    $GLOBALS['wp_test_options']['sidebar_jlg_settings'] = $defaults->all();

    $importPayload = [
        'settings' => [
            'menu_items' => [
                [
                    'label' => '<strong>Lien externe</strong>',
                    'type' => 'custom',
                    'value' => 'https://example.com/contact',
                    'icon_type' => 'svg_url',
                    'icon' => 'https://malicious.test/uploads/sidebar-jlg/interdit.svg',
                ],
            ],
            'social_icons' => [
                [
                    'url' => 'https://example.com/social',
                    'icon' => 'facebook_white',
                    'label' => 'Suivez <em>nous</em>',
                ],
            ],
        ],
    ];

    $tmpFile = tempnam(sys_get_temp_dir(), 'sidebar-jlg-import-');
    if ($tmpFile === false) {
        echo "[FAIL] Impossible de créer un fichier temporaire pour l'import.\n";
        exit(1);
    }

    file_put_contents($tmpFile, json_encode($importPayload));

    $_FILES = [
        'settings_file' => [
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'name' => 'import.json',
            'size' => filesize($tmpFile),
            'type' => 'application/json',
        ],
    ];
    $_POST = ['nonce' => 'import-nonce'];

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

    function assertSame($expected, $actual, string $message): void
    {
        if ($expected === $actual) {
            echo "[PASS] {$message}\n";

            return;
        }

        global $testsPassed;
        $testsPassed = false;
        echo "[FAIL] {$message}. Expected `" . var_export($expected, true) . "`, got `" . var_export($actual, true) . "`.\n";
    }

    try {
        $endpoints->ajax_import_settings();
    } catch (WP_Die_Exception $exception) {
        // Expected to halt execution after JSON response.
    }

    $storedOptions = $GLOBALS['wp_test_options']['sidebar_jlg_settings'] ?? null;

    assertTrue(is_array($storedOptions), 'Les réglages importés sont enregistrés sous forme de tableau.');
    assertSame(0, count($GLOBALS['json_error_payloads']), 'L\'import ne renvoie aucune erreur.');
    assertTrue(!empty($GLOBALS['json_success_payloads']), 'L\'import renvoie une réponse de succès.');

    $firstMenuItem = $storedOptions['menu_items'][0] ?? null;
    assertTrue(is_array($firstMenuItem), 'Le premier élément de menu est présent dans les réglages importés.');

    if (is_array($firstMenuItem)) {
        assertSame('Lien externe', $firstMenuItem['label'] ?? null, 'Le label HTML du menu est assaini.');
        assertSame('', $firstMenuItem['icon'] ?? null, 'L\'icône SVG externe est supprimée.');
        assertSame('svg_inline', $firstMenuItem['icon_type'] ?? null, 'Le type d\'icône repasse en inline après rejet.');
        assertSame('https://example.com/contact', $firstMenuItem['value'] ?? null, 'L\'URL du lien personnalisé est conservée.');
    }

    $socialIcons = $storedOptions['social_icons'] ?? [];
    assertTrue(is_array($socialIcons) && count($socialIcons) === 1, 'Les icônes sociales importées sont conservées après assainissement.');

    if (is_array($socialIcons) && isset($socialIcons[0]) && is_array($socialIcons[0])) {
        $firstSocial = $socialIcons[0];
        assertSame('https://example.com/social', $firstSocial['url'] ?? null, 'L\'URL sociale reste intacte.');
        assertSame('facebook_white', $firstSocial['icon'] ?? null, 'L\'icône sociale autorisée est conservée.');
        assertSame('Suivez nous', $firstSocial['label'] ?? null, 'Le label HTML des icônes sociales est assaini.');
    }

    unlink($tmpFile);
    $_FILES = [];
    $_POST = [];

    if (!$testsPassed) {
        exit(1);
    }

    echo "All tests passed.\n";
}
