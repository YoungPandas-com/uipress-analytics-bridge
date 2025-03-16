<?php
/**
 * Responsible for detecting UIPress Pro.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Detector {

    /**
     * Cached result of UIPress Pro detection
     * 
     * @var bool|null
     */
    private $is_uipress_pro_active = null;
    
    /**
     * Cached path of UIPress Pro
     * 
     * @var string|null
     */
    private $uipress_pro_path = null;
    
    /**
     * Cached version of UIPress Pro
     * 
     * @var string|null
     */
    private $uipress_pro_version = null;
    
    /**
     * Possible plugin paths to check
     * 
     * @var array
     */
    private $possible_paths = array(
        'uipress-pro/uipress-pro.php',
        'uipress/uipress-pro/uipress-pro.php',
        'ui-press-pro/uipress-pro.php',
        'ui-press/uipress-pro/uipress-pro.php',
        'uip-pro/uipress-pro.php',
        'admin-2020-pro/admin-2020-pro.php', // Legacy name
        'admin-2020/admin-2020-pro/admin-2020-pro.php', // Legacy name
    );
    
    /**
     * Possible class namespaces to check
     * 
     * @var array
     */
    private $possible_namespaces = array(
        'UipressPro\\Classes\\Blocks\\GoogleAnalytics',
        'UipressPro\\Classes\\Analytics',
        'UipressPro\\GoogleAnalytics',
        'uip\\pro\\analytics'
    );

    /**
     * Check if UIPress Pro is active.
     *
     * @return bool
     */
    public function is_uipress_pro_active() {
        if ($this->is_uipress_pro_active !== null) {
            return $this->is_uipress_pro_active;
        }
        
        // Check standard plugin paths
        foreach ($this->possible_paths as $path) {
            if (is_plugin_active($path)) {
                $this->is_uipress_pro_active = true;
                $this->uipress_pro_path = WP_PLUGIN_DIR . '/' . dirname($path) . '/';
                $this->detect_version();
                return true;
            }
        }
        
        // Check for non-standard plugin paths with deeper scan
        $this->is_uipress_pro_active = $this->deep_scan_for_uipress_pro();
        
        return $this->is_uipress_pro_active;
    }

    /**
     * Get UIPress Pro path if detected.
     *
     * @return string|null
     */
    public function get_uipress_pro_path() {
        if ($this->is_uipress_pro_active === null) {
            $this->is_uipress_pro_active();
        }
        
        return $this->uipress_pro_path;
    }
    
    /**
     * Get UIPress Pro version if detected.
     *
     * @return string|null
     */
    public function get_uipress_pro_version() {
        if ($this->is_uipress_pro_active === null) {
            $this->is_uipress_pro_active();
        }
        
        return $this->uipress_pro_version;
    }

    /**
     * Deep scan for UIPress Pro in non-standard locations.
     *
     * @return bool
     */
    private function deep_scan_for_uipress_pro() {
        // Ensure function_exists is available
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Get all active plugins
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
        
        if (is_multisite()) {
            $network_plugins = get_site_option('active_sitewide_plugins');
            if ($network_plugins) {
                $network_plugins = array_keys($network_plugins);
                $active_plugins = array_merge($active_plugins, $network_plugins);
            }
        }
        
        // Look for any plugin containing uipress-pro.php
        foreach ($active_plugins as $plugin) {
            // Look for 'uipress-pro.php' in the plugin path
            if (strpos($plugin, 'uipress-pro.php') !== false || strpos($plugin, 'uip-pro.php') !== false) {
                $this->uipress_pro_path = WP_PLUGIN_DIR . '/' . dirname($plugin) . '/';
                $this->detect_version();
                return true;
            }
        }
        
        // Check if classes from UIPress Pro are available
        foreach ($this->possible_namespaces as $namespace) {
            if (class_exists($namespace)) {
                // Try to determine the path using reflection
                try {
                    $reflection = new ReflectionClass($namespace);
                    $this->uipress_pro_path = dirname(dirname(dirname($reflection->getFileName()))) . '/';
                    $this->detect_version();
                    return true;
                } catch (Exception $e) {
                    // Continue to the next namespace
                    continue;
                }
            }
        }
        
        // Try a more generic approach to look for UIPress functionality
        if (class_exists('UipressPro') || class_exists('uip\\pro') || class_exists('uipresslite')) {
            // Found a class but couldn't determine the path
            $this->uipress_pro_path = null;
            return true;
        }
        
        // Look for UIPress constants
        if (defined('UIP_PRO_PLUGIN_PATH') || defined('UIP_PRO_VERSION')) {
            if (defined('UIP_PRO_PLUGIN_PATH')) {
                $this->uipress_pro_path = UIP_PRO_PLUGIN_PATH;
            }
            if (defined('UIP_PRO_VERSION')) {
                $this->uipress_pro_version = UIP_PRO_VERSION;
            }
            return true;
        }
        
        // All detection methods failed
        return false;
    }

    /**
     * Detect UIPress Pro version.
     *
     * @return void
     */
    private function detect_version() {
        // If we already have the version, return
        if ($this->uipress_pro_version !== null) {
            return;
        }
        
        // Check if we have a valid path
        if (empty($this->uipress_pro_path) || !is_dir($this->uipress_pro_path)) {
            return;
        }
        
        // Check if constant is defined
        if (defined('UIP_PRO_VERSION')) {
            $this->uipress_pro_version = UIP_PRO_VERSION;
            return;
        }
        
        // Try to get version from the main plugin file
        $plugin_file = $this->uipress_pro_path . 'uipress-pro.php';
        
        if (!file_exists($plugin_file)) {
            // Try alternative main file name
            $plugin_file = $this->uipress_pro_path . 'uip-pro.php';
            
            if (!file_exists($plugin_file)) {
                // Look for any PHP file in the root directory
                $files = glob($this->uipress_pro_path . '*.php');
                if (!empty($files)) {
                    $plugin_file = $files[0];
                } else {
                    return;
                }
            }
        }
        
        // Parse the plugin file to extract the version
        $plugin_data = get_plugin_data($plugin_file);
        
        if (isset($plugin_data['Version'])) {
            $this->uipress_pro_version = $plugin_data['Version'];
        }
    }

    /**
     * Check if UIPress Pro has Google Analytics functionality.
     *
     * @return bool
     */
    public function has_analytics_functionality() {
        if (!$this->is_uipress_pro_active()) {
            return false;
        }
        
        // Check if any of the Google Analytics related classes exist
        foreach ($this->possible_namespaces as $namespace) {
            if (class_exists($namespace)) {
                return true;
            }
        }
        
        // Check for analytics files if we have a path
        if (!empty($this->uipress_pro_path)) {
            $possible_files = array(
                'admin/classes/Blocks/GoogleAnalytics.php',
                'classes/Blocks/GoogleAnalytics.php',
                'admin/classes/GoogleAnalytics.php',
                'classes/GoogleAnalytics.php',
                'includes/classes/GoogleAnalytics.php',
                'includes/analytics.php',
                'admin/analytics.php'
            );
            
            foreach ($possible_files as $file) {
                if (file_exists($this->uipress_pro_path . $file)) {
                    return true;
                }
            }
        }
        
        // Check if there are AJAX handlers for analytics
        global $wp_filter;
        
        if (isset($wp_filter['wp_ajax_uip_build_google_analytics_query']) ||
            isset($wp_filter['wp_ajax_uip_save_google_analytics']) ||
            isset($wp_filter['wp_ajax_uip_save_access_token']) ||
            isset($wp_filter['wp_ajax_uip_google_auth_check'])) {
            return true;
        }
        
        // Check if the 'google_analytics' option exists in UIPress settings
        $uip_options = get_option('uip-settings', array());
        if (isset($uip_options['google_analytics'])) {
            return true;
        }
        
        // Check user preferences
        $user_id = get_current_user_id();
        $user_preferences = get_user_meta($user_id, 'uip-prefs', true);
        if (is_array($user_preferences) && isset($user_preferences['google_analytics'])) {
            return true;
        }
        
        // No analytics functionality found
        return false;
    }
    
    /**
     * Get diagnostics data about UIPress Pro.
     *
     * @return array Diagnostics data
     */
    public function get_diagnostics() {
        $this->is_uipress_pro_active();
        
        return array(
            'active' => $this->is_uipress_pro_active,
            'path' => $this->uipress_pro_path,
            'version' => $this->uipress_pro_version,
            'has_analytics' => $this->has_analytics_functionality(),
            'plugin_paths_checked' => $this->possible_paths,
            'namespaces_checked' => $this->possible_namespaces
        );
    }
}