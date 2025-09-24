<?php
declare(strict_types=1);

use JLG\Sidebar\Settings\ValueNormalizer;

require __DIR__ . '/bootstrap.php';

$GLOBALS['wp_test_function_overrides']['wp_check_filetype'] = static function ($file, $allowed = []) {
    return ['ext' => '', 'type' => ''];
};

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$tests = [
    'valid_with_spaces' => [
        'input'    => 'rgba(255, 255, 255, 0.5)',
        'expected' => 'rgba(255,255,255,0.5)',
    ],
    'valid_uppercase' => [
        'input'    => 'RGBA(12,34,56,0.25)',
        'expected' => 'rgba(12,34,56,0.25)',
    ],
    'valid_trailing_spaces' => [
        'input'    => "   rgba(0,0,0,0.3)   ",
        'expected' => 'rgba(0,0,0,0.3)',
    ],
    'alpha_without_leading_zero' => [
        'input'    => 'rgba(10,20,30,.7)',
        'expected' => 'rgba(10,20,30,0.7)',
    ],
    'alpha_trimmed_to_one' => [
        'input'    => 'rgba(10,20,30,1.0000)',
        'expected' => 'rgba(10,20,30,1)',
    ],
    'alpha_trimmed_to_zero' => [
        'input'    => 'rgba(10,20,30,0.000)',
        'expected' => 'rgba(10,20,30,0)',
    ],
    'alpha_precision_kept' => [
        'input'    => 'rgba(10,20,30,0.123456789)',
        'expected' => 'rgba(10,20,30,0.123456789)',
    ],
    'non_rgba_hex' => [
        'input'    => '#ABCDEF',
        'expected' => '#abcdef',
    ],
    'invalid_hex' => [
        'input'    => '#ZZZZZZ',
        'expected' => '',
    ],
    'invalid_structure' => [
        'input'    => 'rgba(255,255,255)',
        'expected' => '',
    ],
    'invalid_component_range' => [
        'input'    => 'rgba(300,0,0,0.5)',
        'expected' => '',
    ],
    'invalid_alpha_range' => [
        'input'    => 'rgba(0,0,0,1.2)',
        'expected' => '',
    ],
    'invalid_alpha_negative' => [
        'input'    => 'rgba(0,0,0,-0.1)',
        'expected' => '',
    ],
    'missing_components' => [
        'input'    => 'rgba(0,0,0,)',
        'expected' => '',
    ],
    'too_many_components' => [
        'input'    => 'rgba(0,0,0,0.5,1)',
        'expected' => '',
    ],
    'array_input' => [
        'input'    => ['rgba(0,0,0,0.5)'],
        'expected' => '',
    ],
];

$allPassed = true;
foreach ($tests as $name => $test) {
    $result = ValueNormalizer::normalizeColorWithExisting($test['input'], '');
    if ($result === $test['expected']) {
        echo sprintf("[PASS] %s\n", $name);
        continue;
    }

    $allPassed = false;
    echo sprintf(
        "[FAIL] %s - expected %s got %s\n",
        $name,
        var_export($test['expected'], true),
        var_export($result, true)
    );
}

if ($allPassed) {
    exit(0);
}

exit(1);
