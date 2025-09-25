<?php
declare(strict_types=1);

use JLG\Sidebar\Frontend\Templating;

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

$allIcons = [
    'facebook_white'    => '<svg class="facebook"></svg>',
    'custom_my_brand'   => '<svg class="custom"></svg>',
];

$resultWithStandardIcon = Templating::renderSocialIcons([
    [
        'icon'  => 'facebook_white',
        'url'   => 'https://example.com/facebook',
        'label' => '',
    ],
], $allIcons, 'horizontal');

$expectedStandardMarkup = '<div class="social-icons horizontal"><a href="https://example.com/facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook White"><svg class="facebook"></svg></a></div>';
assertSame($expectedStandardMarkup, $resultWithStandardIcon, 'renders markup for valid social icon with humanized label');

$resultWithCustomLabel = Templating::renderSocialIcons([
    [
        'icon'  => 'custom_my_brand',
        'url'   => 'https://example.com/custom',
        'label' => '  Mon Label Personnalisé  ',
    ],
], $allIcons, '');

$expectedCustomMarkup = '<div class="social-icons"><a href="https://example.com/custom" target="_blank" rel="noopener noreferrer" aria-label="Mon Label Personnalisé"><svg class="custom"></svg></a></div>';
assertSame($expectedCustomMarkup, $resultWithCustomLabel, 'uses trimmed custom label when provided');

$resultWithCustomFallback = Templating::renderSocialIcons([
    [
        'icon'  => 'custom_my_brand',
        'url'   => 'https://example.com/custom',
        'label' => '',
    ],
], $allIcons, 'vertical');

$expectedCustomFallbackMarkup = '<div class="social-icons vertical"><a href="https://example.com/custom" target="_blank" rel="noopener noreferrer" aria-label="My Brand"><svg class="custom"></svg></a></div>';
assertSame($expectedCustomFallbackMarkup, $resultWithCustomFallback, 'falls back to humanized label for custom icon');

$resultWithoutRenderableIcons = Templating::renderSocialIcons([
    [
        'icon'  => 'unknown_icon',
        'url'   => 'https://example.com/unknown',
        'label' => '',
    ],
], $allIcons, 'horizontal');

assertSame('', $resultWithoutRenderableIcons, 'returns empty string when no valid icons are rendered');

exit($testsPassed ? 0 : 1);
