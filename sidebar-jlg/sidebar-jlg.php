<?php
/**
 * Plugin Name:       Sidebar - JLG
 * Description:       Une sidebar professionnelle, animée et entièrement personnalisable pour votre site WordPress.
 * Version:           4.10.0
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
    define( 'SIDEBAR_JLG_VERSION', '4.10.0' );
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

        if (function_exists('set_transient')) {
            $expiration = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;

            $payload = [
                'code' => 'uploads_access_error',
                'details' => is_string($errorMessage) ? $errorMessage : '',
            ];

            set_transient('sidebar_jlg_activation_error', $payload, $expiration);
        }

        return;
    }

    $iconsDir = trailingslashit($baseDir) . 'sidebar-jlg/icons/';
    if (!is_dir($iconsDir)) {
        $created = wp_mkdir_p($iconsDir);

        if (!$created) {
            if (function_exists('error_log')) {
                error_log('[Sidebar JLG] Activation failed: unable to create icons directory.');
            }

            if (!function_exists('set_transient')) {
                return;
            }

            $expiration = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
            $payload = [
                'code' => 'icons_directory_creation_failed',
                'details' => '',
            ];
            set_transient('sidebar_jlg_activation_error', $payload, $expiration);

            return;
        }
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
