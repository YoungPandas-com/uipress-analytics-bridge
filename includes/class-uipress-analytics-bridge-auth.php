<?php
/**
 * Handles authentication with Google Analytics.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Auth {

    /**
     * The API auth instance.
     *
     * @var UIPress_Analytics_Bridge_API_Auth
     */
    private $api_auth;

    /**
     * Initialize the class.
     */
    public function __construct() {
        // We'll initialize api_auth only when needed to avoid loading issues
    }

    /**
     * Get the API auth instance.
     *
     * @return UIPress_Analytics_Bridge_API_Auth
     */
    private function get_api_auth() {
        if (!isset($this->api_auth)) {
            $this->api_auth = new UIPress_Analytics_Bridge_API_Auth();
        }
        return $this->api_auth;
    }

    /**
     * Intercept the save account AJAX request from UIPress Pro.
     * 
     * @return void
     */
    public function intercept_save_account() {
        // Verify nonce first for security
        if (!check_ajax_referer('uipress-lite-security-nonce', 'security', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }

        // Get the analytics data and saveAccountToUser flag
        $analytics_data = isset($_POST['analytics']) ? json_decode(stripslashes($_POST['analytics'])) : null;
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        if (!is_object($analytics_data)) {
            wp_send_json_error(__('Incorrect data passed to server', 'uipress-analytics-bridge'));
        }
        
        // Process the authentication via our method instead of UIPress Pro's
        $result = $this->process_authentication($analytics_data, $save_to_user);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Format the response to match what UIPress Pro expects
        $response = array(
            'success' => true
        );
        
        // Send the JSON response and exit
        wp_send_json($response);
    }

    /**
     * Intercept the save access token AJAX request from UIPress Pro.
     * 
     * @return void
     */
    public function intercept_save_access_token() {
        // Verify nonce first for security
        if (!check_ajax_referer('uipress-lite-security-nonce', 'security', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }

        // Get the token and saveAccountToUser flag
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        if (!$token || $token == '') {
            wp_send_json_error(__('Incorrect token sent to server', 'uipress-analytics-bridge'));
        }
        
        // Process the token via our method
        $result = $this->save_access_token($token, $save_to_user);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Format the response to match what UIPress Pro expects
        $response = array(
            'success' => true
        );
        
        // Send the JSON response and exit
        wp_send_json($response);
    }

    /**
     * Intercept the auth check AJAX request from UIPress Pro.
     * 
     * @return void
     */
    public function intercept_auth_check() {
        // Verify nonce first for security
        if (!check_ajax_referer('uipress-lite-security-nonce', 'security', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }

        // Get the saveAccountToUser flag
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        // Check authentication status
        $ga_data = $this->get_analytics_data($save_to_user);
        
        // Verify we have required data
        $is_authenticated = false;
        if (is_array($ga_data) && isset($ga_data['view']) && isset($ga_data['code'])) {
            $is_authenticated = true;
        }
        
        // Format the response to match what UIPress Pro expects
        $response = array(
            'success' => true,
            'status' => $is_authenticated
        );
        
        // Send the JSON response and exit
        wp_send_json($response);
    }

    /**
     * Process the authentication data.
     * 
     * @param object $analytics_data The analytics data
     * @param string $save_to_user   Whether to save to user
     * @return true|WP_Error True on success, WP_Error on failure
     */
    private function process_authentication($analytics_data, $save_to_user) {
        // Validate the data
        if (!isset($analytics_data->view) || !isset($analytics_data->code)) {
            return new WP_Error('missing_data', __('Missing required data for authentication', 'uipress-analytics-bridge'));
        }
        
        // Get the current Google Analytics data
        $ga_data = $this->get_analytics_data($save_to_user);
        
        if (!is_array($ga_data)) {
            $ga_data = array();
        }
        
        // Store the UIPress Pro compatible data
        $ga_data['view'] = $analytics_data->view;
        $ga_data['code'] = $analytics_data->code;
        
        // Store the data
        $this->save_analytics_data($ga_data, $save_to_user);
        
        return true;
    }

    /**
     * Save access token.
     * 
     * @param string $token        The access token
     * @param string $save_to_user Whether to save to user
     * @return true|WP_Error True on success, WP_Error on failure
     */
    private function save_access_token($token, $save_to_user) {
        // Get the current Google Analytics data
        $ga_data = $this->get_analytics_data($save_to_user);
        
        if (!is_array($ga_data)) {
            $ga_data = array();
        }
        
        // Store the token
        $ga_data['token'] = $token;
        
        // Store the data
        $this->save_analytics_data($ga_data, $save_to_user);
        
        return true;
    }

    /**
     * Check authentication status.
     * 
     * @return bool Whether authenticated or not
     */
    private function check_authentication_status() {
        // Get the analytics data
        $ga_data = $this->get_analytics_data();
        
        // Check if we have the required data
        if (!is_array($ga_data) || !isset($ga_data['view']) || !isset($ga_data['code'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Get the Google Analytics data.
     * 
     * @param string $save_to_user Whether to get from user or global settings
     * @return array The Google Analytics data
     */
    public function get_analytics_data($save_to_user = 'false') {
        if ($save_to_user === 'true') {
            // Get from user meta for current user
            $user_id = get_current_user_id();
            $data = get_user_meta($user_id, 'uip_analytics_bridge_google', true);
        } else {
            // Get from options
            $data = get_option('uip_analytics_bridge_google', array());
        }
        
        return $data;
    }

    /**
     * Save the Google Analytics data.
     * 
     * @param array  $data         The data to save
     * @param string $save_to_user Whether to save to user or global settings
     * @return void
     */
    public function save_analytics_data($data, $save_to_user = 'false') {
        if ($save_to_user === 'true') {
            // Save to user meta for current user
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'uip_analytics_bridge_google', $data);
            
            // Also update the user preferences in UIPress format for compatibility
            $this->update_uipress_user_preferences($data);
        } else {
            // Save to options
            update_option('uip_analytics_bridge_google', $data);
            
            // Also update the UIPress option for compatibility
            $this->update_uipress_options($data);
        }
    }

    /**
     * Update UIPress user preferences for compatibility.
     * 
     * @param array $data The data to save
     * @return void
     */
    private function update_uipress_user_preferences($data) {
        // Only proceed if UIPress classes are available
        if (!class_exists('UipressLite\\Classes\\App\\UserPreferences')) {
            return;
        }
        
        // Format the data to match UIPress Pro's format
        $uipress_data = $this->format_uipress_compatible_data($data);
        
        // Use UIPress method to update user preferences
        $uip_user_preferences = new UipressLite\Classes\App\UserPreferences();
        $uip_user_preferences::update('google_analytics', $uipress_data);
    }

    /**
     * Update UIPress options for compatibility.
     * 
     * @param array $data The data to save
     * @return void
     */
    private function update_uipress_options($data) {
        // Only proceed if UIPress classes are available
        if (!class_exists('UipressLite\\Classes\\App\\UipOptions')) {
            return;
        }
        
        // Format the data to match UIPress Pro's format
        $uipress_data = $this->format_uipress_compatible_data($data);
        
        // Use UIPress method to update options
        $uip_options = new UipressLite\Classes\App\UipOptions();
        $uip_options::update('google_analytics', $uipress_data);
    }

    /**
     * Format data to be compatible with UIPress Pro
     *
     * @param array $data The data from our plugin
     * @return array Data formatted for UIPress
     */
    private function format_uipress_compatible_data($data) {
        // Create a copy to avoid modifying the original
        $uipress_data = $data;
        
        // Ensure all required keys are present
        $uipress_data['connected'] = true;
        
        // Get the API credentials for measurement ID if available
        $api_credentials = get_option('uip_analytics_bridge_google_api', array());
        if (!empty($api_credentials['measurement_id'])) {
            $uipress_data['measurement_id'] = $api_credentials['measurement_id'];
            
            // If view isn't set but we have a measurement ID, use that as the view
            if (empty($uipress_data['view'])) {
                $uipress_data['view'] = $api_credentials['measurement_id'];
            }
        }
        
        // Add GA4 property ID if needed
        if (!isset($uipress_data['property']) && isset($uipress_data['view'])) {
            $uipress_data['property'] = $uipress_data['view'];
        }
        
        // Add account ID if needed
        if (!isset($uipress_data['account'])) {
            $uipress_data['account'] = isset($uipress_data['code']) ? $uipress_data['code'] : '';
        }
        
        // Ensure a few other fields UIPress may expect
        $uipress_data['gafour'] = true;
        
        // Log the data if debugging is enabled
        $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
        if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
            error_log('UIPress Analytics Bridge: Formatted auth data - ' . json_encode($uipress_data));
        }
        
        return $uipress_data;
    }
} 