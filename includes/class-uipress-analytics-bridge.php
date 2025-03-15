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
     * Define the core functionality of the plugin.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Check dependencies before loading
        if (!$this->check_dependencies()) {
            return;
        }
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->intercept_uipress_hooks();
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
        // Only continue if we haven't loaded the detector class yet
        if (!class_exists('UIPress_Analytics_Bridge_Detector')) {
            // Load the detector class directly
            require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'includes/class-uipress-analytics-bridge-detector.php';
            $detector = new UIPress_Analytics_Bridge_Detector();
            
            if (!$detector->is_uipress_pro_active()) {
                $class = 'notice notice-error';
                $message = __('UIPress Analytics Bridge requires UIPress Pro to be installed and activated.', 'uipress-analytics-bridge');
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            }
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
        
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_options_page');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_action('admin_notices', $plugin_admin, 'display_admin_notices');
    }

    /**
     * Intercept UIPress Pro Google Analytics hooks.
     *
     * @since  1.0.0
     * @access private
     */
    private function intercept_uipress_hooks() {
        $detector = new UIPress_Analytics_Bridge_Detector();
        
        // Only setup interception if UIPress Pro is detected
        if ($detector->is_uipress_pro_active()) {
            $auth = new UIPress_Analytics_Bridge_Auth();
            $data = new UIPress_Analytics_Bridge_Data();
            
            // Intercept authentication hooks - use priority 9 to execute before UIPress Pro's handlers (default is 10)
            $this->loader->add_action('wp_ajax_uip_build_google_analytics_query', $data, 'intercept_build_query', 9);
            $this->loader->add_action('wp_ajax_uip_save_google_analytics', $auth, 'intercept_save_account', 9);
            $this->loader->add_action('wp_ajax_uip_save_access_token', $auth, 'intercept_save_access_token', 9);
            $this->loader->add_action('wp_ajax_uip_google_auth_check', $auth, 'intercept_auth_check', 9);
            
            // Add filter to provide GA data format for UIPress
            $this->loader->add_filter('uip_filter_google_analytics_data', $data, 'format_analytics_data', 10, 1);
        }
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