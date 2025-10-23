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
    'custom_accessible' => '<svg class="custom-accessible" aria-labelledby="custom-title" data-test="keep"><title id="custom-title">Mon titre</title></svg>',
];

$resultWithStandardIcon = Templating::renderSocialIcons([
    [
        'icon'  => 'facebook_white',
        'url'   => 'https://example.com/facebook',
        'label' => '',
    ],
], $allIcons, 'horizontal');

$expectedStandardMarkup = '<div class="social-icons horizontal"><a href="https://example.com/facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook – s’ouvre dans une nouvelle fenêtre"><span aria-hidden="true" focusable="false"><svg class="facebook" aria-hidden="true" focusable="false" role="presentation"></svg></span><span class="screen-reader-text">s’ouvre dans une nouvelle fenêtre</span></a></div>';
assertSame($expectedStandardMarkup, $resultWithStandardIcon, 'renders markup for valid social icon with humanized label');

$resultWithCustomLabel = Templating::renderSocialIcons([
    [
        'icon'  => 'custom_my_brand',
        'url'   => 'https://example.com/custom',
        'label' => '  Mon Label Personnalisé  ',
    ],
], $allIcons, '');

$expectedCustomMarkup = '<div class="social-icons"><a href="https://example.com/custom" target="_blank" rel="noopener noreferrer" aria-label="Mon Label Personnalisé – s’ouvre dans une nouvelle fenêtre"><span aria-hidden="true" focusable="false"><svg class="custom" aria-hidden="true" focusable="false" role="presentation"></svg></span><span class="screen-reader-text">s’ouvre dans une nouvelle fenêtre</span></a></div>';
assertSame($expectedCustomMarkup, $resultWithCustomLabel, 'uses trimmed custom label when provided');

$resultWithCustomFallback = Templating::renderSocialIcons([
    [
        'icon'  => 'custom_my_brand',
        'url'   => 'https://example.com/custom',
        'label' => '',
    ],
], $allIcons, 'vertical');

$expectedCustomFallbackMarkup = '<div class="social-icons vertical"><a href="https://example.com/custom" target="_blank" rel="noopener noreferrer" aria-label="My Brand – s’ouvre dans une nouvelle fenêtre"><span aria-hidden="true" focusable="false"><svg class="custom" aria-hidden="true" focusable="false" role="presentation"></svg></span><span class="screen-reader-text">s’ouvre dans une nouvelle fenêtre</span></a></div>';
assertSame($expectedCustomFallbackMarkup, $resultWithCustomFallback, 'falls back to humanized label for custom icon');

$resultWithAccessibleMarkup = Templating::renderSocialIcons([
    [
        'icon'  => 'custom_accessible',
        'url'   => 'https://example.com/accessible',
        'label' => '',
    ],
], $allIcons, '');

$expectedAccessibleMarkup = '<div class="social-icons"><a href="https://example.com/accessible" target="_blank" rel="noopener noreferrer" aria-label="Accessible – s’ouvre dans une nouvelle fenêtre"><span aria-hidden="true" focusable="false"><svg class="custom-accessible" aria-labelledby="custom-title" data-test="keep" aria-hidden="true" focusable="false" role="presentation"><title id="custom-title">Mon titre</title></svg></span><span class="screen-reader-text">s’ouvre dans une nouvelle fenêtre</span></a></div>';
assertSame($expectedAccessibleMarkup, $resultWithAccessibleMarkup, 'preserves custom ARIA attributes on inline SVG imports');

$resultWithMissingIconMarkup = Templating::renderSocialIcons([
    [
        'icon'  => 'unknown_icon',
        'url'   => 'https://example.com/unknown',
        'label' => '',
    ],
], $allIcons, 'horizontal');

$expectedMissingIconMarkup = '<div class="social-icons horizontal"><a href="https://example.com/unknown" class="no-icon" target="_blank" rel="noopener noreferrer" aria-label="Unknown Icon – s’ouvre dans une nouvelle fenêtre"><span class="no-icon-label">Unknown Icon</span><span class="screen-reader-text">s’ouvre dans une nouvelle fenêtre</span></a></div>';
assertSame($expectedMissingIconMarkup, $resultWithMissingIconMarkup, 'renders textual fallback when icon markup is missing');

exit($testsPassed ? 0 : 1);
