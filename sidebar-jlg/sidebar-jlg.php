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

use JLG\Sidebar\Analytics\AnalyticsEventQueue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SIDEBAR_JLG_VERSION' ) ) {
    define( 'SIDEBAR_JLG_VERSION', '4.10.0' );
}

require_once __DIR__ . '/autoload.php';

register_activation_hook(__FILE__, static function () {
    $handler = new Activation\ActivationHandler(
        SIDEBAR_JLG_VERSION,
        new Cache\MenuCache()
    );

    $handler->handle();

    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook(AnalyticsEventQueue::CRON_HOOK);
    }

    if (function_exists('delete_option')) {
        delete_option(AnalyticsEventQueue::OPTION_NAME);
    }
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
