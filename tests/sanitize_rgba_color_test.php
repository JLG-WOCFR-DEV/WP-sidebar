<?php
declare(strict_types=1);

use JLG\Sidebar\Sidebar_JLG;

define('ABSPATH', true);
define('SIDEBAR_JLG_SKIP_BOOTSTRAP', true);

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void {
        // No-op for tests.
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color): string {
        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return strtolower($color);
        }

        return '';
    }
}

require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$reflection = new ReflectionClass(Sidebar_JLG::class);
$instance = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('sanitize_rgba_color');
$method->setAccessible(true);

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
    $result = $method->invoke($instance, $test['input']);
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
    echo "All sanitize_rgba_color tests passed.\n";
    exit(0);
}

echo "sanitize_rgba_color tests failed.\n";
exit(1);
