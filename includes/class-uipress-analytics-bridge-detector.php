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
     * Check if UIPress Pro is active.
     *
     * @return bool
     */
    public function is_uipress_pro_active() {
        if ($this->is_uipress_pro_active !== null) {
            return $this->is_uipress_pro_active;
        }
        
        // First check standard plugin paths
        if (is_plugin_active('uipress-pro/uipress-pro.php')) {
            $this->is_uipress_pro_active = true;
            $this->uipress_pro_path = WP_PLUGIN_DIR . '/uipress-pro/';
            return true;
        }
        
        if (is_plugin_active('uipress/uipress-pro/uipress-pro.php')) {
            $this->is_uipress_pro_active = true;
            $this->uipress_pro_path = WP_PLUGIN_DIR . '/uipress/uipress-pro/';
            return true;
        }
        
        // Check for non-standard plugin paths
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
     * Deep scan for UIPress Pro in non-standard locations.
     *
     * @return bool
     */
    private function deep_scan_for_uipress_pro() {
        // Get all active plugins
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
        
        if (is_multisite()) {
            $network_plugins = get_site_option('active_sitewide_plugins');
            if ($network_plugins) {
                $network_plugins = array_keys($network_plugins);
                $active_plugins = array_merge($active_plugins, $network_plugins);
            }
        }
        
        foreach ($active_plugins as $plugin) {
            // Look for 'uipress-pro.php' in the plugin path
            if (strpos($plugin, 'uipress-pro.php') !== false) {
                $this->uipress_pro_path = WP_PLUGIN_DIR . '/' . dirname($plugin) . '/';
                return true;
            }
        }
        
        // Check if classes from UIPress Pro are available
        if (class_exists('UipressPro\\Classes\\Blocks\\GoogleAnalytics')) {
            // Try to determine the path using reflection
            $reflection = new ReflectionClass('UipressPro\\Classes\\Blocks\\GoogleAnalytics');
            $this->uipress_pro_path = dirname(dirname(dirname($reflection->getFileName()))) . '/';
            return true;
        }
        
        return false;
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
        
        // Check if the GoogleAnalytics class exists
        if (class_exists('UipressPro\\Classes\\Blocks\\GoogleAnalytics')) {
            return true;
        }
        
        // Check if the file exists in the detected path
        $analytics_file = $this->get_uipress_pro_path() . 'admin/classes/Blocks/GoogleAnalytics.php';
        
        return file_exists($analytics_file);
    }
} 