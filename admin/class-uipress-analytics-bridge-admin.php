<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Admin {

    /**
     * The detector instance.
     *
     * @var UIPress_Analytics_Bridge_Detector
     */
    private $detector;

    /**
     * Initialize the class.
     */
    public function __construct() {
        // Initialize the detector after making sure it exists
        if (class_exists('UIPress_Analytics_Bridge_Detector')) {
            $this->detector = new UIPress_Analytics_Bridge_Detector();
        } else {
            // Handle the case where the detector class isn't available
            $this->detector = null;
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>UIPress Analytics Bridge: Required classes are missing. Please reinstall the plugin.</p></div>';
            });
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_uip_analytics_bridge_revoke', array($this, 'ajax_revoke_token'));
        add_action('wp_ajax_uip_analytics_bridge_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_uip_analytics_bridge_get_properties', array($this, 'ajax_get_properties'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @return void
     */
    public function enqueue_styles() {
        // Fixed: Removed dashicons dependency as it's not a registered stylesheet
        wp_enqueue_style(
            'uipress-analytics-bridge-admin',
            UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/css/uipress-analytics-bridge-admin.css',
            array(),
            UIPRESS_ANALYTICS_BRIDGE_VERSION,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @return void
     */
    public function enqueue_scripts() {
        // Make sure jquery is a dependency
        wp_enqueue_script(
            'uipress-analytics-bridge-admin',
            UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/js/uipress-analytics-bridge-admin.js',
            array('jquery', 'wp-i18n'),
            UIPRESS_ANALYTICS_BRIDGE_VERSION,
            true  // Changed to load in footer for better performance
        );
        
        // Localize script with plugin data and translations
        wp_localize_script('uipress-analytics-bridge-admin', 'uipAnalyticsBridge', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('uipress-analytics-bridge-nonce'),
            'connecting' => __('Connecting...', 'uipress-analytics-bridge'),
            'testing' => __('Testing Connection...', 'uipress-analytics-bridge'),
            'revoking' => __('Revoking...', 'uipress-analytics-bridge'),
            'loading' => __('Loading...', 'uipress-analytics-bridge'),
            'dismiss' => __('Dismiss this notice', 'uipress-analytics-bridge'),
            'confirmRevoke' => __('Are you sure you want to revoke access to Google Analytics? This will disconnect UIPress from your analytics data.', 'uipress-analytics-bridge'),
            'popupBlocked' => __('Your browser has blocked the authentication popup. Please allow popups for this site and try again.', 'uipress-analytics-bridge'),
            'errorRevoke' => __('Could not revoke authentication.', 'uipress-analytics-bridge'),
            'errorTest' => __('Could not connect to Google Analytics.', 'uipress-analytics-bridge'),
            'errorAjax' => __('An error occurred while processing your request.', 'uipress-analytics-bridge')
        ));
    }

    /**
     * Add options page to the WordPress admin menu.
     *
     * @return void
     */
    public function add_options_page() {
        add_options_page(
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge',
            array($this, 'render_settings_page')
        );
        
        // Add auth callback page (hidden from the menu)
        add_submenu_page(
            null, // No parent menu
            __('Google Auth', 'uipress-analytics-bridge'),
            __('Google Auth', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge-auth',
            array($this, 'render_auth_callback_page')
        );
        
        // Add diagnostics page (hidden from the menu)
        add_submenu_page(
            null, // No parent menu
            __('Analytics Diagnostics', 'uipress-analytics-bridge'),
            __('Analytics Diagnostics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge-diagnostics',
            array($this, 'render_diagnostics_page')
        );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        // Check if UIPress Pro is detected
        $uipress_detected = $this->detector->is_uipress_pro_active();
        $analytics_detected = $uipress_detected && $this->detector->has_analytics_functionality();
        
        // Create necessary directories if they don't exist
        $this->maybe_create_assets_directories();
        
        include UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    /**
     * Render the Google Auth callback page.
     *
     * @return void
     */
    public function render_auth_callback_page() {
        // Handle the OAuth callback from Google
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        
        if (!empty($code)) {
            // Exchange code for token
            $api_auth = new UIPress_Analytics_Bridge_API_Auth();
            $result = $api_auth->exchange_code_for_token($code);
            
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                // Success, redirect to settings page
                wp_redirect(admin_url('options-general.php?page=uipress-analytics-bridge&auth=success'));
                exit;
            }
        }
        
        if (!empty($error)) {
            // Handle error
            include UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/partials/auth-error.php';
        } else {
            // Show auth page
            $api_auth = new UIPress_Analytics_Bridge_API_Auth();
            include UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/partials/auth-callback.php';
        }
    }

    /**
     * Render the diagnostics page.
     *
     * @return void
     */
    public function render_diagnostics_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'uipress-analytics-bridge'));
        }
        
        // Get diagnostic data
        $api_auth = new UIPress_Analytics_Bridge_API_Auth();
        $api_data = new UIPress_Analytics_Bridge_API_Data();
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        $token_data = $api_auth->get_token_data();
        $user_info = $api_auth->get_user_info();
        $analytics_data = $auth->get_analytics_data();
        
        include UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/partials/diagnostics-page.php';
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            'uipress_analytics_bridge_settings',
            'uip_analytics_bridge_google_api',
            array(
                'sanitize_callback' => array($this, 'sanitize_api_settings'),
                'default' => array(
                    'client_id' => '',
                    'client_secret' => '',
                    'measurement_id' => ''
                )
            )
        );
        
        // Add advanced settings
        register_setting(
            'uipress_analytics_bridge_advanced_settings',
            'uip_analytics_bridge_advanced',
            array(
                'sanitize_callback' => array($this, 'sanitize_advanced_settings'),
                'default' => array(
                    'debug_mode' => false,
                    'cache_expiration' => 3600,
                    'override_method' => 'hook'
                )
            )
        );
    }

    /**
     * Sanitize API settings.
     *
     * @param array $input The input array
     * @return array Sanitized input
     */
    public function sanitize_api_settings($input) {
        $sanitized = array();
        
        if (isset($input['client_id'])) {
            $sanitized['client_id'] = sanitize_text_field($input['client_id']);
        }
        
        if (isset($input['client_secret'])) {
            $sanitized['client_secret'] = sanitize_text_field($input['client_secret']);
        }
        
        if (isset($input['measurement_id'])) {
            $sanitized['measurement_id'] = sanitize_text_field($input['measurement_id']);
        }
        
        // If credentials have changed, clear any saved tokens
        $old_settings = get_option('uip_analytics_bridge_google_api', array());
        if (
            (isset($old_settings['client_id']) && $old_settings['client_id'] !== $sanitized['client_id']) ||
            (isset($old_settings['client_secret']) && $old_settings['client_secret'] !== $sanitized['client_secret'])
        ) {
            // Clear saved tokens if credentials changed
            delete_option('uip_analytics_bridge_oauth');
            delete_option('uip_analytics_bridge_user_info');
            
            // Show notice about credentials change
            set_transient('uip_analytics_bridge_credentials_changed', true, 60);
        }
        
        return $sanitized;
    }

    /**
     * Sanitize advanced settings.
     *
     * @param array $input The input array
     * @return array Sanitized input
     */
    public function sanitize_advanced_settings($input) {
        $sanitized = array();
        
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? (bool) $input['debug_mode'] : false;
        
        if (isset($input['cache_expiration'])) {
            $cache_expiration = intval($input['cache_expiration']);
            $sanitized['cache_expiration'] = $cache_expiration > 0 ? $cache_expiration : 3600;
        } else {
            $sanitized['cache_expiration'] = 3600;
        }
        
        if (isset($input['override_method'])) {
            $sanitized['override_method'] = in_array($input['override_method'], array('hook', 'filter', 'both')) 
                ? $input['override_method'] 
                : 'hook';
        } else {
            $sanitized['override_method'] = 'hook';
        }
        
        return $sanitized;
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices() {
        // Only show notices on our settings page
        $screen = get_current_screen();
        if (!isset($screen->id) || $screen->id !== 'settings_page_uipress-analytics-bridge') {
            return;
        }
        
        // Check if UIPress Pro is detected
        if (!$this->detector->is_uipress_pro_active()) {
            $this->render_notice(
                'error',
                __('UIPress Analytics Bridge requires UIPress Pro to be installed and activated.', 'uipress-analytics-bridge'),
                'uipress-not-detected'
            );
        } else {
            // Check if UIPress Pro has Google Analytics functionality
            if (!$this->detector->has_analytics_functionality()) {
                $this->render_notice(
                    'warning',
                    __('UIPress Analytics Bridge detected UIPress Pro, but could not find Google Analytics functionality.', 'uipress-analytics-bridge'),
                    'analytics-not-detected'
                );
            }
        }
        
        // Display auth success message if applicable
        if (isset($_GET['auth']) && $_GET['auth'] === 'success') {
            $this->render_notice(
                'success',
                __('Successfully authenticated with Google Analytics!', 'uipress-analytics-bridge'),
                'auth-success'
            );
        }
        
        // Display notice about credentials changed if applicable
        if (get_transient('uip_analytics_bridge_credentials_changed')) {
            $this->render_notice(
                'warning',
                __('Your API credentials have changed. You will need to re-authenticate with Google Analytics.', 'uipress-analytics-bridge'),
                'credentials-changed'
            );
            delete_transient('uip_analytics_bridge_credentials_changed');
        }
    }

    /**
     * Render an admin notice.
     *
     * @param string $type    The notice type (success, error, warning, info)
     * @param string $message The message to display
     * @param string $id      The notice ID for dismissible notices
     * @return void
     */
    private function render_notice($type, $message, $id = '') {
        $class = 'notice notice-' . $type . ' is-dismissible';
        $id_attr = !empty($id) ? ' id="uip-analytics-bridge-notice-' . esc_attr($id) . '"' : '';
        
        printf('<div class="%1$s"%2$s><p>%3$s</p></div>', esc_attr($class), $id_attr, esc_html($message));
    }

    /**
     * AJAX handler for revoking Google authentication token.
     * 
     * @return void
     */
    public function ajax_revoke_token() {
        // Verify nonce
        if (!check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }
        
        // Revoke token
        $api_auth = new UIPress_Analytics_Bridge_API_Auth();
        $result = $api_auth->revoke_token();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Authentication revoked successfully', 'uipress-analytics-bridge'));
    }
    
    /**
     * AJAX handler for testing Google Analytics connection.
     * 
     * @return void
     */
    public function ajax_test_connection() {
        // Verify nonce
        if (!check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }
        
        // Test connection
        $api_data = new UIPress_Analytics_Bridge_API_Data();
        $result = $api_data->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Connection to Google Analytics is working properly', 'uipress-analytics-bridge'));
    }
    
    /**
     * AJAX handler for getting Google Analytics properties.
     * 
     * @return void
     */
    public function ajax_get_properties() {
        // Verify nonce
        if (!check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }
        
        // Get properties
        $api_auth = new UIPress_Analytics_Bridge_API_Auth();
        $properties = $api_auth->get_analytics_properties();
        
        if (is_wp_error($properties)) {
            wp_send_json_error($properties->get_error_message());
        }
        
        wp_send_json_success($properties);
    }
    
    /**
     * Create assets directories if they don't exist.
     * 
     * @return void
     */
    private function maybe_create_assets_directories() {
        // Create images directory if it doesn't exist
        $images_dir = UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/images';
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);
        }
        
        // Check for and maybe create Google logo SVG
        $google_logo_path = $images_dir . '/google-logo.svg';
        if (!file_exists($google_logo_path)) {
            // Simple Google logo SVG
            $google_logo = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path fill="#ffffff" d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972a6.033 6.033 0 110-12.064 5.963 5.963 0 014.123 1.576l2.9-2.9A9.864 9.864 0 0012.545 2C7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748l-9.426-.013z"/></svg>';
            
            file_put_contents($google_logo_path, $google_logo);
        }
    }
}