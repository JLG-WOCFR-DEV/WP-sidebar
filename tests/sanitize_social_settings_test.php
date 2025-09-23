<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$GLOBALS['wp_test_options']['sidebar_jlg_settings'] = $defaults->all();

$input = [
    'social_icons' => [
        [
            'url' => 'https://example.com/profile',
            'icon' => 'facebook_white',
            'label' => '  Mon <b>Label</b>  ',
        ],
        [
            'url' => 'https://invalid.example.com',
            'icon' => 'not_a_real_icon',
            'label' => 'Should be dropped',
        ],
    ],
];

$sanitized = $sanitizer->sanitize_settings($input);

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

$socialIcons = $sanitized['social_icons'] ?? [];

assertSame(1, count($socialIcons), 'Only valid social icons are kept');
assertSame('https://example.com/profile', $socialIcons[0]['url'] ?? null, 'Social URL is sanitized and preserved');
assertSame('facebook_white', $socialIcons[0]['icon'] ?? null, 'Social icon key is sanitized and preserved');
assertSame('Mon Label', $socialIcons[0]['label'] ?? null, 'Social icon label is trimmed and sanitized');
assertTrue(strpos($socialIcons[0]['label'] ?? '', '<') === false, 'Social icon label contains no HTML tags');

if (!$testsPassed) {
    exit(1);
}

echo "All social settings sanitization tests passed.\n";
