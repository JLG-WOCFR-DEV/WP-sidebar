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

$flatInput = [];
foreach ($expectedKeys as $index => $key) {
    $flatInput[$key] = $index % 2 === 0 ? '1' : '';
}

$flatResult = $sanitizer->sanitize_accessibility_checklist($flatInput);
$flatExpected = [
    Checklist::DEFAULT_CONTEXT_KEY => [],
];
foreach ($expectedKeys as $index => $key) {
    $flatExpected[Checklist::DEFAULT_CONTEXT_KEY][$key] = $index % 2 === 0;
}

assertSameChecklist($flatExpected, $flatResult, 'Flat checklist is wrapped into the default context with boolean values.');

$profileContext = Checklist::getContextKeyForProfile('marketing-team');
$anotherContext = Checklist::getContextKeyForProfile('support');

$nestedInput = [
    Checklist::DEFAULT_CONTEXT_KEY => [
        $expectedKeys[0] => true,
        $expectedKeys[1] => false,
    ],
    'marketing team' => [
        $expectedKeys[0] => '1',
        $expectedKeys[2] => '1',
    ],
    'profile__support' => [
        $expectedKeys[1] => true,
    ],
    'invalid' => 'not an array',
];

$nestedResult = $sanitizer->sanitize_accessibility_checklist($nestedInput);

$nestedExpected = [
    Checklist::DEFAULT_CONTEXT_KEY => array_fill_keys($expectedKeys, false),
    $profileContext => array_fill_keys($expectedKeys, false),
    $anotherContext => array_fill_keys($expectedKeys, false),
];
$nestedExpected[Checklist::DEFAULT_CONTEXT_KEY][$expectedKeys[0]] = true;
$nestedExpected[Checklist::DEFAULT_CONTEXT_KEY][$expectedKeys[1]] = false;
$nestedExpected[$profileContext][$expectedKeys[0]] = true;
$nestedExpected[$profileContext][$expectedKeys[2]] = true;
$nestedExpected[$anotherContext][$expectedKeys[1]] = true;

assertSameChecklist($nestedExpected, $nestedResult, 'Nested contexts are normalized and additional contexts added.');

$invalidResult = $sanitizer->sanitize_accessibility_checklist('not an array');
$expectedDefaults = [
    Checklist::DEFAULT_CONTEXT_KEY => array_fill_keys($expectedKeys, false),
];

assertSameChecklist($expectedDefaults, $invalidResult, 'Non-array values reset to default checklist state.');

if (!$testsPassed) {
    exit(1);
}
