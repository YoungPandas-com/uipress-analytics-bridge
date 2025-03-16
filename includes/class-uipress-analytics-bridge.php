<?php
/**
 * The main plugin class
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since  1.0.0
     * @access protected
     * @var    UIPress_Analytics_Bridge_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The detector instance.
     *
     * @since  1.0.0
     * @access protected
     * @var    UIPress_Analytics_Bridge_Detector $detector UIPress Pro detector instance.
     */
    protected $detector;

    /**
     * Define the core functionality of the plugin.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize detector
        $this->detector = new UIPress_Analytics_Bridge_Detector();
        
        // Check dependencies before loading
        if (!$this->check_dependencies()) {
            return;
        }
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->intercept_uipress_hooks();
        
        // Add any upgrade/migration code
        $this->maybe_migrate_from_previous_version();
    }

    /**
     * Check if all dependencies are met
     *
     * @since 1.0.0
     * @return bool Whether dependencies are met
     */
    private function check_dependencies() {
        // Add admin notice if dependencies are not met
        add_action('admin_notices', array($this, 'dependency_notice'));
        
        // Check for function_exists to avoid fatal errors
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // For now, we'll continue loading even if UIPress Pro isn't active
        // The detector class will handle appropriate notices
        return true;
    }
    
    /**
     * Display dependency notice if UIPress Pro is not active
     *
     * @since 1.0.0
     */
    public function dependency_notice() {
        // Only continue if we need to show a notice
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Don't show notices on our own plugin pages
        $screen = get_current_screen();
        if (isset($screen->id) && (
            $screen->id === 'settings_page_uipress-analytics-bridge' ||
            $screen->id === 'admin_page_uipress-analytics-bridge-auth' ||
            $screen->id === 'admin_page_uipress-analytics-bridge-diagnostics'
        )) {
            return;
        }
        
        // Check if UIPress Pro is detected
        if (!$this->detector->is_uipress_pro_active()) {
            $class = 'notice notice-error';
            $message = sprintf(
                __('UIPress Analytics Bridge requires UIPress Pro to be installed and activated. <a href="%s">Settings</a>', 'uipress-analytics-bridge'),
                admin_url('options-general.php?page=uipress-analytics-bridge')
            );
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses($message, array('a' => array('href' => array()))));
        }
    }

    /**
     * Load required dependencies for this plugin.
     *
     * @since  1.0.0
     * @access private
     */
    private function load_dependencies() {
        // The class responsible for orchestrating the actions and filters of the core plugin
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge-loader.php';
        
        // The class responsible for defining all actions that occur in the admin area
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/class-uipress-analytics-bridge-admin.php';
        
        // The class responsible for UIPress Pro detection
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge-detector.php';
        
        // The class responsible for Google Analytics authentication
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge-auth.php';
        
        // The class responsible for data retrieval and formatting
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge-data.php';
        
        // API classes 
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/api/class-uipress-analytics-bridge-api-auth.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/api/class-uipress-analytics-bridge-api-data.php';
        
        $this->loader = new UIPress_Analytics_Bridge_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since  1.0.0
     * @access private
     */
    private function define_admin_hooks() {
        $plugin_admin = new UIPress_Analytics_Bridge_Admin();
        
        // Admin assets and menu pages
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_options_page');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_action('admin_notices', $plugin_admin, 'display_admin_notices');
        
        // Add action links on the plugins page
        $this->loader->add_filter('plugin_action_links_' . UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME, $this, 'add_plugin_action_links');
        
        // Handle cache clearing from diagnostics page
        $this->loader->add_action('admin_init', $this, 'handle_admin_actions');
    }

    /**
     * Intercept UIPress Pro Google Analytics hooks.
     *
     * @since  1.0.0
     * @access private
     */
    private function intercept_uipress_hooks() {
        // Only setup interception if UIPress Pro is detected
        if ($this->detector->is_uipress_pro_active()) {
            $auth = new UIPress_Analytics_Bridge_Auth();
            $data = new UIPress_Analytics_Bridge_Data();
            
            // Get advanced settings
            $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
            $override_method = isset($advanced_settings['override_method']) ? $advanced_settings['override_method'] : 'hook';
            
            // Always register the filter for maximum compatibility
            $this->loader->add_filter('uip_filter_google_analytics_data', $data, 'format_analytics_data', 10, 1);
            
            // Based on settings, intercept hooks and/or use filters
            if ($override_method === 'hook' || $override_method === 'both') {
                // Intercept authentication hooks - use priority 9 to execute before UIPress Pro's handlers (default is 10)
                $this->loader->add_action('wp_ajax_uip_build_google_analytics_query', $data, 'intercept_build_query', 9);
                $this->loader->add_action('wp_ajax_uip_save_google_analytics', $auth, 'intercept_save_account', 9);
                $this->loader->add_action('wp_ajax_uip_save_access_token', $auth, 'intercept_save_access_token', 9);
                $this->loader->add_action('wp_ajax_uip_google_auth_check', $auth, 'intercept_auth_check', 9);
                
                // Log interception for diagnostics if debug mode is enabled
                if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
                    add_action('admin_init', function() {
                        error_log('UIPress Analytics Bridge: Intercepting AJAX hooks with priority 9');
                    });
                }
            }
        }
    }

    /**
     * Add plugin action links.
     *
     * @since 1.0.0
     * @param array $links The existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=uipress-analytics-bridge') . '">' . __('Settings', 'uipress-analytics-bridge') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Handle admin actions like cache clearing.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_admin_actions() {
        // Handle cache clearing
        if (isset($_GET['page']) && $_GET['page'] === 'uipress-analytics-bridge-diagnostics' && 
            isset($_GET['action']) && $_GET['action'] === 'clear_cache' && 
            isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'uipress_analytics_bridge_clear_cache')) {
            
            // Clear the cache
            $api_data = new UIPress_Analytics_Bridge_API_Data();
            $api_data->clear_cache();
            
            // Redirect to diagnostics page with a success message
            wp_redirect(add_query_arg('cache_cleared', '1', admin_url('admin.php?page=uipress-analytics-bridge-diagnostics')));
            exit;
        }
    }
    
    /**
     * Run migration/upgrade tasks if needed.
     *
     * @since 1.0.0
     * @return void
     */
    private function maybe_migrate_from_previous_version() {
        $current_version = get_option('uipress_analytics_bridge_version', '0.0.0');
        
        // If this is a new installation or same version, no need to run migrations
        if ($current_version === '0.0.0' || $current_version === UIPRESS_ANALYTICS_BRIDGE_VERSION) {
            // Just update the version for new installations
            if ($current_version === '0.0.0') {
                update_option('uipress_analytics_bridge_version', UIPRESS_ANALYTICS_BRIDGE_VERSION);
            }
            return;
        }
        
        // Versioned migrations can be added here
        if (version_compare($current_version, '1.0.0', '<')) {
            // Migration from pre-1.0.0 to 1.0.0
            $this->migrate_to_1_0_0();
        }
        
        // Update the stored version
        update_option('uipress_analytics_bridge_version', UIPRESS_ANALYTICS_BRIDGE_VERSION);
    }
    
    /**
     * Migration to version 1.0.0.
     *
     * @since 1.0.0
     * @return void
     */
    private function migrate_to_1_0_0() {
        // Example migration task - create default advanced settings
        if (!get_option('uip_analytics_bridge_advanced')) {
            update_option('uip_analytics_bridge_advanced', array(
                'debug_mode' => false,
                'cache_expiration' => 3600,
                'override_method' => 'hook'
            ));
        }
        
        // Clear any old caches
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_uip_analytics_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_timeout_uip_analytics_%'
            )
        );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since 1.0.0
     */
    public function run() {
        $this->loader->run();
    }
}