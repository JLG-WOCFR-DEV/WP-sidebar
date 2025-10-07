<?php
declare(strict_types=1);

use JLG\Sidebar\Accessibility\Checklist;
use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$items = Checklist::getItems();
$expectedKeys = [];
foreach ($items as $item) {
    $id = isset($item['id']) ? (string) $item['id'] : '';
    if ($id === '') {
        continue;
    }
    $expectedKeys[] = $id;
}

$testsPassed = true;

function assertSameChecklist($expected, $actual, string $message): void
{
    global $testsPassed;
    if ($expected !== $actual) {
        $testsPassed = false;
        echo "Assertion failed: {$message}.\n";
    }
}

$input = [];
foreach ($expectedKeys as $index => $key) {
    $input[$key] = $index % 2 === 0 ? '1' : '';
}

$result = $sanitizer->sanitize_accessibility_checklist($input);
$expected = [];
foreach ($expectedKeys as $index => $key) {
    $expected[$key] = $index % 2 === 0;
}

assertSameChecklist($expected, $result, 'Sanitizer converts checklist entries to booleans and preserves keys');

$invalidResult = $sanitizer->sanitize_accessibility_checklist(['unknown' => true]);
$expectedFalse = [];
foreach ($expectedKeys as $key) {
    $expectedFalse[$key] = false;
}

assertSameChecklist($expectedFalse, $invalidResult, 'Sanitizer ignores unknown keys and defaults to false');

if (!$testsPassed) {
    exit(1);
}
