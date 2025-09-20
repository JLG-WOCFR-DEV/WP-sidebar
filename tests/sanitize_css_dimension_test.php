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

$reflection = new ReflectionClass(SettingsSanitizer::class);
$method = $reflection->getMethod('sanitize_css_dimension');
$method->setAccessible(true);

$tests = [
    'valid_px' => [
        'input'    => '24px',
        'fallback' => '2.5rem',
        'expected' => '24px',
    ],
    'valid_rem' => [
        'input'    => '1.5rem',
        'fallback' => '2.5rem',
        'expected' => '1.5rem',
    ],
    'valid_vh' => [
        'input'    => '50vh',
        'fallback' => '2.5rem',
        'expected' => '50vh',
    ],
    'negative_value' => [
        'input'    => '-10px',
        'fallback' => '2.5rem',
        'expected' => '-10px',
    ],
    'zero_without_unit' => [
        'input'    => '0',
        'fallback' => '2.5rem',
        'expected' => '0',
    ],
    'zero_decimal' => [
        'input'    => '0.0',
        'fallback' => '2.5rem',
        'expected' => '0',
    ],
    'valid_calc_expression' => [
        'input'    => 'calc(100% - 20px)',
        'fallback' => '2.5rem',
        'expected' => 'calc(100% - 20px)',
    ],
    'invalid_calc_disallowed_unit' => [
        'input'    => 'calc(100% - 20pt)',
        'fallback' => '2.5rem',
        'expected' => '2.5rem',
    ],
    'empty_input_uses_fallback' => [
        'input'    => '',
        'fallback' => '2.5rem',
        'expected' => '2.5rem',
    ],
    'invalid_input_uses_fallback' => [
        'input'    => 'auto',
        'fallback' => '2.5rem',
        'expected' => '2.5rem',
    ],
];

$allPassed = true;
foreach ($tests as $name => $test) {
    $result = $method->invoke($sanitizer, $test['input'], $test['fallback']);
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
    echo "All sanitize_css_dimension tests passed.\n";
    exit(0);
}

echo "sanitize_css_dimension tests failed.\n";
exit(1);
