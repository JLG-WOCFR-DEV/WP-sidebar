<?php
declare(strict_types=1);

use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$testsPassed = true;

function assertTrue($condition, string $message): void
{
    global $testsPassed;
    if (!$condition) {
        $testsPassed = false;
        echo "Assertion failed: {$message}.\n";
    }
}

$defaults = new DefaultSettings();
$options = $defaults->all();

unset($options['social_position']);

$iconLibrary = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$allIcons = $iconLibrary->getAllIcons();

$capturedNotices = [];
set_error_handler(static function (int $severity, string $message) use (&$capturedNotices): bool {
    if (in_array($severity, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true)) {
        $capturedNotices[] = $message;
        return true;
    }

    return false;
});

ob_start();
include __DIR__ . '/../sidebar-jlg/includes/sidebar-template.php';
$html = ob_get_clean();
restore_error_handler();

assertTrue($capturedNotices === [], 'Rendering template without social_position should not trigger notices');
assertTrue(is_string($html) && $html !== '', 'Template rendered non-empty markup');

if (!$testsPassed) {
    exit(1);
}

echo "Render sidebar missing social position tests passed.\n";
