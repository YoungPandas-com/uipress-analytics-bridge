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
        $this->api_data = new UIPress_Analytics_Bridge_API_Data();
        $this->auth = new UIPress_Analytics_Bridge_Auth();
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
        $analytics_data = $this->auth->get_analytics_data($save_to_user);
        
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
        if (!$analytics_data || !isset($analytics_data['view']) || !isset($analytics_data['code'])) {
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
        $code = $analytics_data['code'];
        $view = $analytics_data['view'];
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
            "https://analytics.uipress.co/view.php?code=%s&view=%s&key=%s&instance=%s&uip3=1&gafour=true&d=%s&uip_token=%s",
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
            'url' => $query_url
        );
        
        wp_send_json($response);
    }

    /**
     * Format analytics data for UIPress Pro.
     * 
     * This method can be used as a filter to modify the analytics data before
     * it's used by UIPress Pro visualization components.
     * 
     * @param array $data The data to format
     * @return array Formatted data
     */
    public function format_analytics_data($data) {
        // If we have our own data from the API, format it for UIPress Pro
        $our_data = $this->api_data->get_analytics_data();
        
        if ($our_data) {
            // Merge our data with the incoming data
            // Making sure to maintain the format expected by UIPress Pro
            return $this->merge_and_format_data($data, $our_data);
        }
        
        // Otherwise, just return the original data
        return $data;
    }

    /**
     * Merge and format data for UIPress Pro compatibility.
     * 
     * @param array $uip_data  The original UIPress Pro data
     * @param array $our_data  Our API data
     * @return array Merged and formatted data
     */
    private function merge_and_format_data($uip_data, $our_data) {
        // If UIPress data is not set or not an array, initialize it
        if (!is_array($uip_data)) {
            $uip_data = array();
        }
        
        // Here we'd merge the data, ensuring the format matches what UIPress Pro expects
        // This would depend on the specific data structure UIPress Pro expects
        
        // For now, we'll simply ensure our data has all required fields
        foreach ($our_data as $key => $value) {
            $uip_data[$key] = $value;
        }
        
        return $uip_data;
    }

    /**
     * Get analytics data directly through our API.
     * 
     * @param array $params Additional parameters for the request
     * @return array|false Analytics data or false on failure
     */
    public function get_direct_analytics_data($params = array()) {
        // Get the analytics account data
        $analytics_data = $this->auth->get_analytics_data();
        
        if (!$analytics_data || !isset($analytics_data['view']) || !isset($analytics_data['code'])) {
            return false;
        }
        
        // Get the data through our API
        return $this->api_data->fetch_analytics_data($analytics_data, $params);
    }

    /**
     * Refresh analytics data.
     * 
     * @return array|false Refreshed data or false on failure
     */
    public function refresh_analytics_data() {
        // Clear any cached data
        $this->api_data->clear_cache();
        
        // Get fresh data
        return $this->get_direct_analytics_data(array('refresh' => true));
    }
} 