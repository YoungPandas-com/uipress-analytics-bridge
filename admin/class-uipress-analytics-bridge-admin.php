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
        $this->detector = new UIPress_Analytics_Bridge_Detector();
        
        // Register AJAX handlers
        add_action('wp_ajax_uip_analytics_bridge_revoke', array($this, 'ajax_revoke_token'));
        add_action('wp_ajax_uip_analytics_bridge_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @return void
     */
    public function enqueue_styles() {
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
        wp_enqueue_script(
            'uipress-analytics-bridge-admin',
            UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/js/uipress-analytics-bridge-admin.js',
            array('jquery'),
            UIPRESS_ANALYTICS_BRIDGE_VERSION,
            false
        );
        
        // Localize script with plugin data
        wp_localize_script('uipress-analytics-bridge-admin', 'uipAnalyticsBridge', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('uipress-analytics-bridge-nonce')
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
            include UIPRESS_ANALYTICS_BRIDGE_PLUGIN_DIR . 'admin/partials/auth-callback.php';
        }
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
                'sanitize_callback' => array($this, 'sanitize_api_settings')
            )
        );
        
        add_settings_section(
            'uipress_analytics_bridge_section',
            __('Google Analytics API Settings', 'uipress-analytics-bridge'),
            array($this, 'render_settings_section'),
            'uipress_analytics_bridge_settings'
        );
        
        add_settings_field(
            'uip_analytics_bridge_client_id',
            __('Client ID', 'uipress-analytics-bridge'),
            array($this, 'render_client_id_field'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_section'
        );
        
        add_settings_field(
            'uip_analytics_bridge_client_secret',
            __('Client Secret', 'uipress-analytics-bridge'),
            array($this, 'render_client_secret_field'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_section'
        );
        
        add_settings_field(
            'uip_analytics_bridge_measurement_id',
            __('Measurement ID (optional)', 'uipress-analytics-bridge'),
            array($this, 'render_measurement_id_field'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_section'
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
        
        return $sanitized;
    }

    /**
     * Render settings section.
     *
     * @return void
     */
    public function render_settings_section() {
        echo '<p>' . __('Enter your Google Analytics API credentials to enable authentication.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Render Client ID field.
     *
     * @return void
     */
    public function render_client_id_field() {
        $settings = get_option('uip_analytics_bridge_google_api', array());
        $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        
        echo '<input type="text" id="uip_analytics_bridge_client_id" name="uip_analytics_bridge_google_api[client_id]" value="' . esc_attr($client_id) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter the Google API Client ID', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Render Client Secret field.
     *
     * @return void
     */
    public function render_client_secret_field() {
        $settings = get_option('uip_analytics_bridge_google_api', array());
        $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        
        echo '<input type="password" id="uip_analytics_bridge_client_secret" name="uip_analytics_bridge_google_api[client_secret]" value="' . esc_attr($client_secret) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter the Google API Client Secret', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Render Measurement ID field.
     *
     * @return void
     */
    public function render_measurement_id_field() {
        $settings = get_option('uip_analytics_bridge_google_api', array());
        $measurement_id = isset($settings['measurement_id']) ? $settings['measurement_id'] : '';
        
        echo '<input type="text" id="uip_analytics_bridge_measurement_id" name="uip_analytics_bridge_google_api[measurement_id]" value="' . esc_attr($measurement_id) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your GA4 Measurement ID (optional, for direct measurement ID entry)', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices() {
        // Check if UIPress Pro is detected
        if (!$this->detector->is_uipress_pro_active()) {
            $this->render_uipress_not_detected_notice();
        } else {
            // Check if UIPress Pro has Google Analytics functionality
            if (!$this->detector->has_analytics_functionality()) {
                $this->render_analytics_not_detected_notice();
            }
        }
        
        // Display auth success message if applicable
        if (isset($_GET['page']) && $_GET['page'] === 'uipress-analytics-bridge' && isset($_GET['auth']) && $_GET['auth'] === 'success') {
            $this->render_auth_success_notice();
        }
    }

    /**
     * Render UIPress Pro not detected notice.
     *
     * @return void
     */
    private function render_uipress_not_detected_notice() {
        $class = 'notice notice-error';
        $message = __('UIPress Analytics Bridge requires UIPress Pro to be installed and activated.', 'uipress-analytics-bridge');
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Render Analytics not detected notice.
     *
     * @return void
     */
    private function render_analytics_not_detected_notice() {
        $class = 'notice notice-warning';
        $message = __('UIPress Analytics Bridge detected UIPress Pro, but could not find Google Analytics functionality.', 'uipress-analytics-bridge');
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Render auth success notice.
     *
     * @return void
     */
    private function render_auth_success_notice() {
        $class = 'notice notice-success is-dismissible';
        $message = __('Successfully authenticated with Google Analytics!', 'uipress-analytics-bridge');
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
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
        $result = $api_data->get_analytics_data();
        
        if (is_wp_error($result) || $result === false) {
            $error_message = is_wp_error($result) ? $result->get_error_message() : __('Could not retrieve data from Google Analytics', 'uipress-analytics-bridge');
            wp_send_json_error($error_message);
        }
        
        wp_send_json_success(__('Connection to Google Analytics is working properly', 'uipress-analytics-bridge'));
    }
} 