<?php
declare(strict_types=1);

use JLG\Sidebar\Admin\SettingsSanitizer;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\DefaultSettings;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$GLOBALS['wp_test_available_languages'] = ['fr_FR', 'en_US'];

$defaults = new DefaultSettings();
$icons = new IconLibrary(__DIR__ . '/../sidebar-jlg/sidebar-jlg.php');
$sanitizer = new SettingsSanitizer($defaults, $icons);

$GLOBALS['wp_test_options']['sidebar_jlg_profiles'] = [
    [
        'id' => 'secondary',
        'conditions' => [
            'roles' => ['editor'],
            'languages' => ['en_US'],
        ],
        'settings' => [
            'layout_style' => 'floating',
            'sidebar_position' => 'right',
        ],
    ],
];

$profiles = [
    [
        'id' => 'Primary',
        'title' => 'My Profile',
        'enabled' => '1',
        'conditions' => [
            'content_types' => ['post', 'unknown'],
            'taxonomies' => ['category', 'invalid'],
            'roles' => ['administrator', 'ghost'],
            'languages' => ['fr_FR', 'xx_YY'],
        ],
        'settings' => [
            'layout_style' => 'floating',
            'sidebar_position' => 'right',
        ],
    ],
    [
        'slug' => 'secondary',
        'name' => 'Secondary Profile',
        'conditions' => [
            'content_types' => 'not-array',
        ],
        'settings' => [
            'layout_style' => 'unknown',
        ],
    ],
];

$result = $sanitizer->sanitize_profiles($profiles);

if (count($result) !== 2) {
    echo 'Expected two sanitized profiles.' . "\n";
    exit(1);
}

$primary = $result[0];
$secondary = $result[1];

if (($primary['id'] ?? '') !== 'primary') {
    echo 'Primary profile id was not normalized.' . "\n";
    exit(1);
}

if (empty($primary['enabled'])) {
    echo 'Primary profile should be enabled.' . "\n";
    exit(1);
}

$primaryConditions = $primary['conditions'] ?? [];
if (($primaryConditions['content_types'] ?? []) !== ['post']) {
    echo 'Primary content types were not sanitized as expected.' . "\n";
    exit(1);
}
if (($primaryConditions['taxonomies'] ?? []) !== ['category']) {
    echo 'Primary taxonomies were not sanitized as expected.' . "\n";
    exit(1);
}
if (($primaryConditions['roles'] ?? []) !== ['administrator']) {
    echo 'Primary roles were not sanitized as expected.' . "\n";
    exit(1);
}
if (($primaryConditions['languages'] ?? []) !== ['fr_FR']) {
    echo 'Primary languages were not sanitized as expected.' . "\n";
    exit(1);
}

$primarySettings = $primary['settings'] ?? [];
if (($primarySettings['layout_style'] ?? '') !== 'floating') {
    echo 'Primary layout style was not preserved.' . "\n";
    exit(1);
}
if (($primarySettings['sidebar_position'] ?? '') !== 'right') {
    echo 'Primary sidebar position was not preserved.' . "\n";
    exit(1);
}

if (($secondary['id'] ?? '') !== 'secondary') {
    echo 'Secondary profile id was not derived from slug.' . "\n";
    exit(1);
}

$secondaryConditions = $secondary['conditions'] ?? [];
if (($secondaryConditions['content_types'] ?? []) !== []) {
    echo 'Secondary content types should be empty when invalid.' . "\n";
    exit(1);
}
if (($secondaryConditions['roles'] ?? []) !== ['editor']) {
    echo 'Secondary roles should fall back to stored values.' . "\n";
    exit(1);
}
if (($secondaryConditions['languages'] ?? []) !== ['en_US']) {
    echo 'Secondary languages should fall back to stored values.' . "\n";
    exit(1);
}

$secondarySettings = $secondary['settings'] ?? [];
if (($secondarySettings['layout_style'] ?? '') !== 'floating') {
    echo 'Secondary layout style should fall back to existing settings.' . "\n";
    exit(1);
}
if (($secondarySettings['sidebar_position'] ?? '') !== 'right') {
    echo 'Secondary sidebar position should fall back to existing settings.' . "\n";
    exit(1);
}

exit(0);
