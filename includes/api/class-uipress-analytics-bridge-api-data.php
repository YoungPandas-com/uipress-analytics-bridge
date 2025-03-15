<?php
/**
 * Handles API data retrieval from Google Analytics.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_API_Data {

    /**
     * Google Analytics API endpoints
     */
    const GA4_API_URL = 'https://analyticsdata.googleapis.com/v1beta/';
    
    /**
     * Cache group
     */
    const CACHE_GROUP = 'uip_analytics_bridge';
    
    /**
     * Cache expiration in seconds (1 hour)
     */
    const CACHE_EXPIRATION = 3600;

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
        $this->api_auth = new UIPress_Analytics_Bridge_API_Auth();
    }

    /**
     * Fetch analytics data.
     * 
     * @param array $analytics_data Account data
     * @param array $params Additional parameters
     * @return array|WP_Error Analytics data or error
     */
    public function fetch_analytics_data($analytics_data, $params = array()) {
        // Check if we have required data
        if (!isset($analytics_data['view'])) {
            return new WP_Error('missing_property', __('Google Analytics property ID is missing', 'uipress-analytics-bridge'));
        }
        
        // If refresh is not requested, try to get from cache first
        $refresh = isset($params['refresh']) && $params['refresh'];
        if (!$refresh) {
            $cached_data = $this->get_cached_data($analytics_data['view']);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Get access token
        $access_token = $this->api_auth->get_access_token();
        if (is_wp_error($access_token)) {
            return new WP_Error(
                'auth_error', 
                sprintf(
                    __('Authentication error: %s. Please re-authenticate with Google.', 'uipress-analytics-bridge'),
                    $access_token->get_error_message()
                )
            );
        }
        
        // Prepare date range
        $days = isset($params['days']) ? intval($params['days']) : 30;
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Build request to GA4 API
        $property_id = $analytics_data['view']; // In GA4, this is the property ID
        
        $request_url = self::GA4_API_URL . "properties/{$property_id}:runReport";
        
        $request_args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'dateRanges' => array(
                    array(
                        'startDate' => $start_date,
                        'endDate' => $end_date
                    )
                ),
                'dimensions' => array(
                    array('name' => 'date')
                ),
                'metrics' => array(
                    array('name' => 'activeUsers'),
                    array('name' => 'screenPageViews'),
                    array('name' => 'sessions'),
                    array('name' => 'engagementRate')
                )
            )),
            'timeout' => 15 // Increase timeout to 15 seconds for slow API responses
        );
        
        $response = wp_remote_request($request_url, $request_args);
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_error', 
                sprintf(
                    __('Failed to connect to Google Analytics API: %s', 'uipress-analytics-bridge'),
                    $response->get_error_message()
                )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            return new WP_Error(
                'api_response_error',
                sprintf(
                    __('Google Analytics API error (HTTP %s): %s', 'uipress-analytics-bridge'),
                    $response_code,
                    $error_message
                )
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error', 'uipress-analytics-bridge');
            $error_code = isset($body['error']['code']) ? $body['error']['code'] : 'unknown';
            
            return new WP_Error(
                'ga_api_error_' . $error_code,
                sprintf(
                    __('Google Analytics API error: %s', 'uipress-analytics-bridge'),
                    $error_message
                ),
                $body['error']
            );
        }
        
        // Log successful API call for debugging (in development environments)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UIPress Analytics Bridge: Successful GA4 API call for property ' . $property_id);
        }
        
        // Process the response into a format compatible with UIPress
        $processed_data = $this->process_api_response($body, $days);
        
        // Cache the processed data
        $this->cache_data($analytics_data['view'], $processed_data);
        
        return $processed_data;
    }

    /**
     * Process the API response into a format compatible with UIPress.
     * 
     * @param array $response The API response
     * @param int   $days     Number of days in the report
     * @return array Processed data
     */
    private function process_api_response($response, $days) {
        $processed_data = array(
            'success' => true,
            'dates' => array(),
            'users' => array(),
            'pageviews' => array(),
            'sessions' => array(),
            'engagement' => array(),
            'totals' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'engagement' => 0
            )
        );
        
        if (!isset($response['rows']) || empty($response['rows'])) {
            return $processed_data;
        }
        
        // Process rows from the API response
        foreach ($response['rows'] as $row) {
            $date = $row['dimensionValues'][0]['value']; // Format: YYYYMMDD
            $formatted_date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
            
            $users = intval($row['metricValues'][0]['value']);
            $pageviews = intval($row['metricValues'][1]['value']);
            $sessions = intval($row['metricValues'][2]['value']);
            $engagement = floatval($row['metricValues'][3]['value']) * 100; // Convert to percentage
            
            $processed_data['dates'][] = $formatted_date;
            $processed_data['users'][] = $users;
            $processed_data['pageviews'][] = $pageviews;
            $processed_data['sessions'][] = $sessions;
            $processed_data['engagement'][] = $engagement;
            
            // Add to totals
            $processed_data['totals']['users'] += $users;
            $processed_data['totals']['pageviews'] += $pageviews;
            $processed_data['totals']['sessions'] += $sessions;
        }
        
        // Calculate average engagement
        $processed_data['totals']['engagement'] = count($processed_data['engagement']) > 0 
            ? array_sum($processed_data['engagement']) / count($processed_data['engagement']) 
            : 0;
            
        // Add previous period data for comparison (not implemented here)
        // UIPress may expect this data for showing comparisons
        
        return $processed_data;
    }

    /**
     * Get analytics data.
     * 
     * @return array|false Analytics data or false if not available
     */
    public function get_analytics_data() {
        // This is just a simple wrapper around fetch_analytics_data
        // In a real implementation, you might want to add more logic here
        
        // Get the analytics account data from auth class
        $auth = new UIPress_Analytics_Bridge_Auth();
        $analytics_data = $auth->get_analytics_data();
        
        if (!$analytics_data) {
            return false;
        }
        
        // Try to get data from cache first
        $cached_data = $this->get_cached_data($analytics_data['view']);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Otherwise, fetch fresh data
        return $this->fetch_analytics_data($analytics_data);
    }

    /**
     * Cache data.
     * 
     * @param string $property_id The property ID
     * @param array  $data        The data to cache
     * @return void
     */
    private function cache_data($property_id, $data) {
        $transient_name = $this->get_cache_key($property_id);
        set_transient($transient_name, $data, self::CACHE_EXPIRATION);
    }

    /**
     * Get cached data.
     * 
     * @param string $property_id The property ID
     * @return array|false Cache data or false if not found
     */
    private function get_cached_data($property_id) {
        $transient_name = $this->get_cache_key($property_id);
        return get_transient($transient_name);
    }

    /**
     * Get cache key.
     * 
     * @param string $property_id The property ID
     * @return string Cache key
     */
    private function get_cache_key($property_id) {
        return 'uip_analytics_' . md5($property_id);
    }

    /**
     * Clear cache.
     * 
     * @param string $property_id The property ID (optional, if not provided, all caches will be cleared)
     * @return void
     */
    public function clear_cache($property_id = null) {
        if ($property_id) {
            $transient_name = $this->get_cache_key($property_id);
            delete_transient($transient_name);
        } else {
            // Clear all analytics caches
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                    '_transient_uip_analytics_%'
                )
            );
        }
    }

    /**
     * Determine the API version from the property ID
     * 
     * @param string $property_id The property ID
     * @return string The API version ('ga4' or 'ua')
     */
    private function determine_api_version($property_id) {
        // GA4 property IDs typically start with numbers
        if (is_numeric(substr($property_id, 0, 1))) {
            return 'ga4';
        }
        
        // UA property IDs typically start with 'UA-'
        if (stripos($property_id, 'UA-') === 0) {
            return 'ua';
        }
        
        // Default to GA4 for unknown formats
        return 'ga4';
    }
    
    /**
     * Build appropriate API request based on API version
     * 
     * @param string $api_version The API version ('ga4' or 'ua')
     * @param array  $params      Request parameters
     * @return array Request arguments
     */
    private function build_api_request($api_version, $params) {
        $request_args = [];
        
        if ($api_version === 'ga4') {
            // GA4 Data API request
            $request_args = [
                'method' => 'POST',
                'headers' => [
                    'Authorization' => "Bearer {$params['access_token']}",
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'dateRanges' => [
                        [
                            'startDate' => $params['start_date'],
                            'endDate' => $params['end_date']
                        ]
                    ],
                    'dimensions' => [
                        ['name' => 'date']
                    ],
                    'metrics' => [
                        ['name' => 'activeUsers'],
                        ['name' => 'screenPageViews'],
                        ['name' => 'sessions'],
                        ['name' => 'engagementRate']
                    ]
                ]),
                'timeout' => 15
            ];
        } else if ($api_version === 'ua') {
            // Legacy Universal Analytics API request (for backward compatibility)
            // This is a simplified example - real implementation would be more complex
            $request_args = [
                'method' => 'GET',
                'headers' => [
                    'Authorization' => "Bearer {$params['access_token']}"
                ],
                'timeout' => 15
            ];
        }
        
        return $request_args;
    }
    
    /**
     * Process API response based on API version
     * 
     * @param string $api_version The API version ('ga4' or 'ua')
     * @param array  $response    The API response
     * @param int    $days        Number of days in the report
     * @return array Processed data
     */
    private function process_version_specific_response($api_version, $response, $days) {
        if ($api_version === 'ga4') {
            return $this->process_api_response($response, $days);
        } else if ($api_version === 'ua') {
            // Process UA response (would be implemented for backward compatibility)
            // This is a placeholder - real implementation would process UA data
            return $this->process_api_response($response, $days);
        }
        
        return [];
    }
} 