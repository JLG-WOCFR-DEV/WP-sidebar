<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\RequestContextResolver;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$testsPassed = true;

function request_context_assert(bool $condition, string $message): void
{
    global $testsPassed;

    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    echo "[FAIL] {$message}\n";
    $testsPassed = false;
}

$originalDetermineOverride = $GLOBALS['wp_test_function_overrides']['determine_locale'] ?? null;
$determineCalls = 0;
$GLOBALS['wp_test_function_overrides']['determine_locale'] = static function () use (&$determineCalls) {
    $determineCalls++;

    return 'fr_FR';
};

$resolver = new RequestContextResolver();

$context = $resolver->resolve();
request_context_assert(is_array($context), 'Resolver returns an array');

$resolver->resolve();
request_context_assert($determineCalls === 1, 'Cached context prevents duplicate locale resolution');

$resolver->resetCachedContext();
$GLOBALS['wp_test_function_overrides']['determine_locale'] = static function () use (&$determineCalls) {
    $determineCalls++;

    return 'en_US';
};

$contextAfterReset = $resolver->resolve();
request_context_assert($determineCalls === 2, 'Resetting the cache triggers a fresh resolution');
request_context_assert(($contextAfterReset['language'] ?? null) === 'en_us', 'Language reflects the refreshed locale');

if ($originalDetermineOverride !== null) {
    $GLOBALS['wp_test_function_overrides']['determine_locale'] = $originalDetermineOverride;
} else {
    unset($GLOBALS['wp_test_function_overrides']['determine_locale']);
}

if ($testsPassed) {
    echo "Request context caching tests passed.\n";
    exit(0);
}

echo "Request context caching tests failed.\n";
exit(1);
