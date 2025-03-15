<?php
/**
 * Plugin Name: UIPress Analytics Bridge
 * Plugin URI: https://yourwebsite.com/uipress-analytics-bridge
 * Description: Enhances UIPress Pro's Google Analytics integration with improved authentication and data retrieval.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: uipress-analytics-bridge
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UIPRESS_ANALYTICS_BRIDGE_VERSION', '1.0.0');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_uipress_analytics_bridge() {
    // Activation code if needed
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_uipress_analytics_bridge() {
    // Deactivation code if needed
}

register_activation_hook(__FILE__, 'activate_uipress_analytics_bridge');
register_deactivation_hook(__FILE__, 'deactivate_uipress_analytics_bridge');

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
if (!function_exists('run_uipress_analytics_bridge')) {
    function run_uipress_analytics_bridge() {
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge.php';
        $plugin = new UIPress_Analytics_Bridge();
        $plugin->run();
    }
}

// Initialize the plugin when WordPress loads
add_action('plugins_loaded', 'run_uipress_analytics_bridge'); 