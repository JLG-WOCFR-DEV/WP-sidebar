<?php
/**
 * Plugin Name:       Sidebar - JLG
 * Description:       Une sidebar professionnelle, animée et entièrement personnalisable pour votre site WordPress.
 * Version:           4.9.2
 * Author:            Jérôme Le Gousse
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidebar-jlg
 * Domain Path:       /languages
 */

namespace JLG\Sidebar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SIDEBAR_JLG_VERSION' ) ) {
    define( 'SIDEBAR_JLG_VERSION', '4.9.2' );
}

require_once __DIR__ . '/autoload.php';

register_activation_hook(__FILE__, static function () {
    $uploadDir = wp_upload_dir();

    $baseDir = '';
    $errorValue = null;

    if (is_array($uploadDir)) {
        $baseDir = $uploadDir['basedir'] ?? '';
        $errorValue = $uploadDir['error'] ?? null;
    }

    $hasError = false;
    $errorMessage = '';

    if ($errorValue !== null) {
        if (is_wp_error($errorValue)) {
            $errorMessage = (string) $errorValue->get_error_message();
            $hasError = $errorMessage !== '';
        } elseif (is_string($errorValue) && $errorValue !== '') {
            $errorMessage = $errorValue;
            $hasError = true;
        }
    }

    if (!is_array($uploadDir) || !is_string($baseDir) || $baseDir === '' || $hasError) {
        if ($errorMessage !== '' && function_exists('error_log')) {
            error_log(sprintf('[Sidebar JLG] Activation skipped: %s', $errorMessage));
        }

        return;
    }

    $iconsDir = trailingslashit($baseDir) . 'sidebar-jlg/icons/';
    if (!is_dir($iconsDir)) {
        wp_mkdir_p($iconsDir);
    }

    update_option('sidebar_jlg_plugin_version', SIDEBAR_JLG_VERSION);

    plugin()->getMenuCache()->clear();
});

function plugin(): Plugin
{
    static $instance = null;

    if ($instance === null) {
        $instance = new Plugin(__FILE__, SIDEBAR_JLG_VERSION);
        $instance->register();
    }

    return $instance;
}

if ( ! defined( 'SIDEBAR_JLG_SKIP_BOOTSTRAP' ) ) {
    plugin();
}
