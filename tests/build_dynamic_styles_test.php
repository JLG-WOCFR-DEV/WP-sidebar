<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\SidebarRenderer;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers/plugin_helpers.php';

$renderer = sidebar_jlg_create_test_sidebar_renderer();
$method = new ReflectionMethod(SidebarRenderer::class, 'buildDynamicStyles');
$method->setAccessible(true);

$testsPassed = true;

/**
 * @param array<string, mixed> $options
 * @param string[]             $expectations
 */
function runDynamicStyleScenario(
    string $label,
    SidebarRenderer $renderer,
    ReflectionMethod $method,
    array $options,
    array $expectations
): void {
    global $testsPassed;

    $styles = (string) $method->invoke($renderer, $options);

    assertStringContains(':root {', $styles, $label . ' includes :root selector');

    foreach ($expectations as $expected) {
        assertStringContains($expected, $styles, $label . ' contains `' . $expected . '`');
    }
}

function assertStringContains(string $needle, string $haystack, string $message): void
{
    global $testsPassed;

    if (strpos($haystack, $needle) !== false) {
        echo "[PASS] {$message}\n";

        return;
    }

    $testsPassed = false;
    echo "[FAIL] {$message}\n";
    echo "  Expected to find: {$needle}\n";
    echo "  Actual CSS: {$haystack}\n";
}

$scenarios = [
    'Solid background renders core variables' => [
        'options' => [
            'width_desktop' => 320,
            'bg_color_type' => 'solid',
            'bg_color' => '#101010',
            'font_size' => 18,
            'font_family' => 'arial',
            'font_weight' => '600',
            'font_color' => 'rgba(255, 0, 0, 1)',
            'nav_aria_label' => 'Menu principal',
        ],
        'expect' => [
            '--sidebar-width-desktop: 320px;',
            '--sidebar-bg-image: none;',
            '--sidebar-bg-color: #101010;',
            '--sidebar-font-size: 18px;',
            '--sidebar-font-family: Arial, Helvetica Neue, Helvetica, sans-serif;',
            '--sidebar-font-weight: 600;',
            '--sidebar-text-color: rgba(255, 0, 0, 1);',
            '--hamburger-color: rgba(255, 0, 0, 1);',
            '--sidebar-nav-label: Menu principal;',
        ],
    ],
    'Gradient colors and accessibility fallbacks' => [
        'options' => [
            'bg_color_type' => 'gradient',
            'bg_color_start' => '#123456',
            'bg_color_end' => '#654321',
            'accent_color_type' => 'gradient',
            'accent_color_start' => '#f1c40f',
            'accent_color_end' => '#e74c3c',
            'toggle_open_label' => '',
            'toggle_close_label' => '',
        ],
        'expect' => [
            '--sidebar-bg-image: linear-gradient(180deg, #123456 0%, #654321 100%);',
            '--sidebar-bg-color: #123456;',
            '--primary-accent-image: linear-gradient(90deg, #f1c40f 0%, #e74c3c 100%);',
            '--primary-accent-color: #f1c40f;',
            '--sidebar-nav-label: Navigation principale;',
            '--sidebar-toggle-open-label: Afficher le sous-menu;',
            '--sidebar-toggle-close-label: Masquer le sous-menu;',
        ],
    ],
    'Neon hover effect and content margin handling' => [
        'options' => [
            'hover_effect_desktop' => 'neon',
            'neon_blur' => 25,
            'neon_spread' => 8,
            'content_margin' => '32px',
            'animation_speed' => 250,
        ],
        'expect' => [
            '--neon-blur: 25px;',
            '--neon-spread: 8px;',
            '--content-margin: calc(var(--sidebar-width-desktop) + 32px);',
            '--transition-speed: 250ms;',
        ],
    ],
];

foreach ($scenarios as $label => $scenario) {
    runDynamicStyleScenario(
        $label,
        $renderer,
        $method,
        $scenario['options'],
        $scenario['expect']
    );
}

if ($testsPassed) {
    echo "Dynamic style rendering tests passed.\n";
    exit(0);
}

echo "Dynamic style rendering tests failed.\n";
exit(1);
