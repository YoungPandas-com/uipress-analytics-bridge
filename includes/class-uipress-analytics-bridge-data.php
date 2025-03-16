<?php
/**
 * Handles Google Analytics data retrieval and formatting.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Data {

    /**
     * The API data instance.
     *
     * @var UIPress_Analytics_Bridge_API_Data
     */
    private $api_data;

    /**
     * The auth instance.
     *
     * @var UIPress_Analytics_Bridge_Auth
     */
    private $auth;

    /**
     * Initialize the class.
     */
    public function __construct() {
        // We'll initialize these only when needed to avoid loading issues
    }

    /**
     * Get the API data instance.
     *
     * @return UIPress_Analytics_Bridge_API_Data
     */
    private function get_api_data() {
        if (!isset($this->api_data)) {
            $this->api_data = new UIPress_Analytics_Bridge_API_Data();
        }
        return $this->api_data;
    }

    /**
     * Get the auth instance.
     *
     * @return UIPress_Analytics_Bridge_Auth
     */
    private function get_auth() {
        if (!isset($this->auth)) {
            $this->auth = new UIPress_Analytics_Bridge_Auth();
        }
        return $this->auth;
    }

    /**
     * Intercept the build query AJAX request from UIPress Pro.
     * 
     * @return void
     */
    public function intercept_build_query() {
        // Verify nonce first for security
        if (!check_ajax_referer('uipress-lite-security-nonce', 'security', false)) {
            wp_send_json_error(__('Security check failed', 'uipress-analytics-bridge'));
        }

        // Get save to user flag
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        // Get analytics data
        $analytics_data = $this->get_auth()->get_analytics_data($save_to_user);
        
        // Get the API credentials for measurement ID if available
        $api_credentials = get_option('uip_analytics_bridge_google_api', array());
        if (!empty($api_credentials['measurement_id'])) {
            $analytics_data['measurement_id'] = $api_credentials['measurement_id'];
            
            // If view isn't set but we have a measurement ID, use that as the view
            if (empty($analytics_data['view'])) {
                $analytics_data['view'] = $api_credentials['measurement_id'];
            }
        }
        
        // Debug logging if enabled
        $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
        if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
            error_log('UIPress Analytics Bridge: Building GA query with data: ' . json_encode($analytics_data));
        }
        
        // Check if we have the license data (required by UIPress)
        $uip_pro_data = get_option('uip_pro', array());
        
        if (!$uip_pro_data || !isset($uip_pro_data['key'])) {
            $response = array(
                'error' => true,
                'message' => __('You need a licence key to use analytics blocks', 'uipress-analytics-bridge'),
                'error_type' => 'no_licence',
                'url' => false
            );
            wp_send_json($response);
        }
        
        // Check if we have analytics data
        if (!$analytics_data || (!isset($analytics_data['view']) && !isset($analytics_data['measurement_id']))) {
            $response = array(
                'error' => true,
                'message' => __('You need to connect a google analytics account to display data', 'uipress-analytics-bridge'),
                'error_type' => 'no_google',
                'url' => false
            );
            wp_send_json($response);
        }
        
        // Extract data
        $key = $uip_pro_data['key'];
        $instance = isset($uip_pro_data['instance']) ? $uip_pro_data['instance'] : '';
        $code = isset($analytics_data['code']) ? $analytics_data['code'] : '';
        
        // Get view - prefer GA4 measurement ID if available
        $view = '';
        if (!empty($analytics_data['measurement_id'])) {
            $view = $analytics_data['measurement_id'];
        } elseif (!empty($analytics_data['view'])) {
            $view = $analytics_data['view'];
        }
        
        $domain = get_home_url();
        
        // Validate key components
        if ($key == '' || $code == '' || $view == '') {
            $response = array(
                'error' => true,
                'message' => __('You need to connect a google analytics account to display data', 'uipress-analytics-bridge'),
                'error_type' => 'no_google',
                'url' => false
            );
            wp_send_json($response);
        }
        
        // Get token if available
        $token = '';
        if (isset($analytics_data['token']) && $analytics_data['token'] != '') {
            $token = $analytics_data['token'];
        }
        
        // Build the query URL with our auth approach, but keep the format compatible with UIPress Pro
        $query_url = sprintf(
            "https://analytics.uipress.co/view.php?code=%s&view=%s&key=%s&instance=%s&uip3=1&gafour=true&d=%s&uip_token=%s&bridge=1",
            urlencode($code),
            urlencode($view),
            urlencode($key),
            urlencode($instance),
            urlencode($domain),
            urlencode($token)
        );
        
        // Return the URL for UIPress Pro to use
        $response = array(
            'success' => true,
            'url' => $query_url,
            'connected' => true,
            'oauth' => true,
            'measurement_id' => isset($analytics_data['measurement_id']) ? $analytics_data['measurement_id'] : ''
        );
        
        wp_send_json($response);
    }

    /**
     * Format analytics data from API to be compatible with UIPress Pro.
     * 
     * @param array $data The incoming data from the API
     * @return array The formatted data
     */
    public function format_analytics_data($data) {
        // If we received no data, or if it's already an error, just return it
        if (empty($data) || (isset($data['error']) && $data['error'])) {
            // Return a sensible default structure to avoid error
            return array(
                'success' => true,
                'connected' => false,
                'data' => array(),
                'totalStats' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0,
                    'change' => array(
                        'users' => 0,
                        'pageviews' => 0,
                        'sessions' => 0
                    )
                ),
                'topContent' => array(),
                'topSources' => array(),
                'gafour' => true,
                'message' => 'No data available'
            );
        }

        // Format the data to be compatible with UIPress Pro
        $formatted_data = $data;
        
        // Make sure we have the correct authentication data
        $auth_data = $this->get_auth()->get_analytics_data();
        if (is_array($auth_data)) {
            // Add the auth data to the formatted data
            $formatted_data['google_account'] = $auth_data;
            
            // Ensure the connected flag is set
            $formatted_data['connected'] = true;
            
            // Add required GA4 fields
            if (isset($auth_data['view'])) {
                $formatted_data['property'] = $auth_data['view'];
            }
            
            // Add required code if available
            if (isset($auth_data['code'])) {
                $formatted_data['code'] = $auth_data['code'];
            }
            
            // Get measurement ID from settings if available
            $api_credentials = get_option('uip_analytics_bridge_google_api', array());
            if (!empty($api_credentials['measurement_id'])) {
                $formatted_data['measurement_id'] = $api_credentials['measurement_id'];
            }
        }
        
        // Ensure success flag is set
        $formatted_data['success'] = true;
        
        // Add a flag to indicate our plugin is handling the data
        $formatted_data['uip_analytics_bridge_processed'] = true;
        
        // Ensure these flags are set for compatibility
        $formatted_data['gafour'] = true;
        
        // Add default datasets if they don't exist
        if (!isset($formatted_data['data']) || !is_array($formatted_data['data'])) {
            $formatted_data['data'] = array();
        }
        
        if (!isset($formatted_data['totalStats']) || !is_array($formatted_data['totalStats'])) {
            $formatted_data['totalStats'] = array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'change' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0
                )
            );
        }
        
        // Ensure we have top content data (required by some UIPress blocks)
        if (!isset($formatted_data['topContent']) || !is_array($formatted_data['topContent'])) {
            $formatted_data['topContent'] = array();
        }
        
        // Ensure we have top sources data (required by some UIPress blocks)
        if (!isset($formatted_data['topSources']) || !is_array($formatted_data['topSources'])) {
            $formatted_data['topSources'] = array();
        }
        
        // Log the processing if debug mode is enabled
        $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
        if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
            error_log('UIPress Analytics Bridge: Formatting analytics data for UIPress Pro compatibility');
            error_log('UIPress Analytics Bridge: Formatted data structure: ' . json_encode(array_keys($formatted_data)));
        }
        
        return $formatted_data;
    }

    /**
     * Get analytics data directly through our API.
     * 
     * @param array $params Additional parameters for the request
     * @return array|false Analytics data or false on failure
     */
    public function get_direct_analytics_data($params = array()) {
        // Get the analytics account data
        $analytics_data = $this->get_auth()->get_analytics_data();
        
        if (!$analytics_data || !isset($analytics_data['view']) || !isset($analytics_data['code'])) {
            return false;
        }
        
        // Get the data through our API
        return $this->get_api_data()->fetch_analytics_data($analytics_data, $params);
    }

    /**
     * Refresh analytics data.
     * 
     * @return array|false Refreshed data or false on failure
     */
    public function refresh_analytics_data() {
        // Clear any cached data
        $this->get_api_data()->clear_cache();
        
        // Get fresh data
        return $this->get_direct_analytics_data(array('refresh' => true));
    }
} 