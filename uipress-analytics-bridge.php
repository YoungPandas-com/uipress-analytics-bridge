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
 * Enable detailed error logging for debugging
 */
function uipress_analytics_bridge_debug_mode() {
    $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
    
    // Only enable if debug mode is specifically set in options
    if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
        // Make sure WP_DEBUG is defined
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        
        // Make sure WP_DEBUG_LOG is defined
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        
        // Make sure WP_DEBUG_DISPLAY is defined
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
        }
        
        // Make sure error reporting is set to show all errors
        error_reporting(E_ALL);
        
        // Enable special error handler for analytics errors
        set_error_handler('uipress_analytics_bridge_error_handler', E_ALL);
    }
}

/**
 * Custom error handler for plugin errors
 *
 * @param int $errno Error number
 * @param string $errstr Error string
 * @param string $errfile Error file
 * @param int $errline Error line
 * @return bool Whether the error was handled
 */
function uipress_analytics_bridge_error_handler($errno, $errstr, $errfile, $errline) {
    // Only handle errors in our plugin files
    if (strpos($errfile, 'uipress-analytics-bridge') === false) {
        return false;
    }
    
    // Create error message
    $error_message = sprintf(
        'UIPress Analytics Bridge Error [%s]: %s in %s on line %d',
        $errno,
        $errstr,
        $errfile,
        $errline
    );
    
    // Log error
    error_log($error_message);
    
    // Return false to let the default error handler run
    return false;
}

/**
 * The code that runs during plugin activation.
 */
if (!function_exists('activate_uipress_analytics_bridge')) {
    function activate_uipress_analytics_bridge() {
        // Activation code if needed
    }
}

/**
 * The code that runs during plugin deactivation.
 */
if (!function_exists('deactivate_uipress_analytics_bridge')) {
    function deactivate_uipress_analytics_bridge() {
        // Deactivation code if needed
    }
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
        // Enable debug mode if configured
        uipress_analytics_bridge_debug_mode();
        
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge.php';
        $plugin = new UIPress_Analytics_Bridge();
        $plugin->run();
    }
}

// Initialize the plugin when WordPress loads
add_action('plugins_loaded', 'run_uipress_analytics_bridge'); 