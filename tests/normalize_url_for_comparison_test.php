<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\RequestContextResolver;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$resolver = new RequestContextResolver();
$testsPassed = true;

$resetServer = static function (): void {
    $_SERVER['HTTP_HOST'] = 'example.com';
    unset($_SERVER['HTTPS']);
};

$assertNormalized = static function (string $input, string $expected, string $message) use (&$testsPassed, $resolver): void {
    $actual = $resolver->normalizeUrlForComparison($input);

    if ($actual === $expected) {
        echo "[PASS] {$message}\n";

        return;
    }

    echo "[FAIL] {$message} (expected {$expected}, got {$actual})\n";
    $testsPassed = false;
};

$resetServer();
$assertNormalized('MAILTO:user@example.com', 'mailto:user@example.com', 'Mailto URIs keep their payload without home URL rewriting');

$resetServer();
$assertNormalized('tel:+33 1 23 45 67', 'tel:+33 1 23 45 67', 'Telephone URIs preserve their original number');

$resetServer();
$assertNormalized('javascript: void(0)', 'javascript:void(0)', 'JavaScript URIs are trimmed after the scheme');

$resetServer();
$assertNormalized('//cdn.example.com/assets/logo.svg?ver=1', 'http://cdn.example.com/assets/logo.svg?ver=1', 'Protocol-relative URLs inherit the current HTTP scheme');

$_SERVER['HTTPS'] = 'on';
$assertNormalized('//cdn.example.com/assets/logo.svg', 'https://cdn.example.com/assets/logo.svg', 'Protocol-relative URLs inherit HTTPS when the request is secure');

$resetServer();

if ($testsPassed) {
    echo "Normalize URL comparison tests passed.\n";
    exit(0);
}

echo "Normalize URL comparison tests failed.\n";
exit(1);
