<?php
declare(strict_types=1);

use JLG\Sidebar\Icons\IconLibrary;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/autoload.php';

$testsPassed = true;

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

function assertTrue($condition, string $message): void
{
    if ($condition) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Condition evaluated to false.\n";
}

function assertNull($value, string $message): void
{
    if ($value === null) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Expected null, got `" . var_export($value, true) . "`.\n";
}

function assertNotContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        echo "[PASS] {$message}\n";

        return;
    }

    global $testsPassed;
    $testsPassed = false;
    echo "[FAIL] {$message}. Found disallowed substring `{$needle}` in `{$haystack}`.\n";
}

$originalWpKsesOverride = $GLOBALS['wp_test_function_overrides']['wp_kses'] ?? null;

$GLOBALS['wp_test_function_overrides']['wp_kses'] = static function (string $string, array $allowedHtml) {
    $sanitized = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $string);

    return is_string($sanitized) ? $sanitized : '';
};

$iconLibrary = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');

$safeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" /></svg>';
$failure = null;
$safeResult = $iconLibrary->sanitizeSvgMarkup($safeSvg, null, $failure);

assertTrue(is_array($safeResult), 'sanitizeSvgMarkup returns an array for safe SVG markup');
assertSame(null, $failure, 'sanitizeSvgMarkup leaves failure context empty for safe markup');

if (is_array($safeResult)) {
    assertNotContains('<script', $safeResult['svg'], 'sanitizeSvgMarkup keeps safe markup unchanged');
}

$svgWithScript = '<svg xmlns="http://www.w3.org/2000/svg"><title>Unsafe</title><script>alert(1)</script><circle cx="0" cy="0" r="1" /></svg>';
$failure = null;
$rejectedResult = $iconLibrary->sanitizeSvgMarkup($svgWithScript, null, $failure);

assertNull($rejectedResult, 'sanitizeSvgMarkup rejects SVG markup that requires stripping disallowed elements');
assertTrue(is_array($failure), 'sanitizeSvgMarkup returns failure details for rejected markup');

if (is_array($failure)) {
    assertSame('mismatched_sanitization', $failure['reason'] ?? '', 'sanitizeSvgMarkup reports mismatched sanitization when markup is altered');
}

$reflection = new ReflectionClass($iconLibrary);
$createUploadsContext = $reflection->getMethod('createUploadsContext');
$createUploadsContext->setAccessible(true);

$uploadsContext = $createUploadsContext->invoke($iconLibrary, '/var/www/example.com/wp-content/uploads', 'http://example.com/wp-content/uploads');

assertTrue(is_array($uploadsContext), 'createUploadsContext provides a usable uploads context for validation');

$unsafeUseMarkup = '<svg xmlns="http://www.w3.org/2000/svg"><use href="http://malicious.test/icons/icon.svg#shape" /></svg>';
$failure = null;
$unsafeResult = $iconLibrary->sanitizeSvgMarkup($unsafeUseMarkup, $uploadsContext, $failure);

assertNull($unsafeResult, 'sanitizeSvgMarkup rejects unsafe external <use> references');
assertTrue(is_array($failure), 'sanitizeSvgMarkup provides failure context for rejected markup');

if (is_array($failure)) {
    $reason = $failure['reason'] ?? '';
    $detail = $failure['context']['detail'] ?? [];
    $detailCode = is_array($detail) ? ($detail['code'] ?? '') : '';
    $detailInfo = is_array($detail) ? ($detail['info'] ?? null) : null;
    $infoCode = is_array($detailInfo) ? ($detailInfo['code'] ?? '') : (is_string($detailInfo) ? $detailInfo : '');

    assertSame('validation_failed', $reason, 'sanitizeSvgMarkup signals validation failure for unsafe <use> references');
    assertSame('unsafe_use_reference', $detailCode, 'sanitizeSvgMarkup reports unsafe reference detail code');
    assertSame('host_mismatch', $infoCode, 'sanitizeSvgMarkup flags host mismatch for external references');
}

if ($originalWpKsesOverride === null) {
    unset($GLOBALS['wp_test_function_overrides']['wp_kses']);
} else {
    $GLOBALS['wp_test_function_overrides']['wp_kses'] = $originalWpKsesOverride;
}

exit($testsPassed ? 0 : 1);
