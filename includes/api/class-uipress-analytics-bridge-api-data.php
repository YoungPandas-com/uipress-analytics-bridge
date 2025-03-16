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
    const GA4_ADMIN_URL = 'https://analyticsadmin.googleapis.com/v1alpha/';
    
    /**
     * Cache group
     */
    const CACHE_GROUP = 'uip_analytics_bridge';
    
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
     * Test the connection to Google Analytics.
     * 
     * @return true|WP_Error True if successful, WP_Error on failure
     */
    public function test_connection() {
        // Get access token
        $access_token = $this->api_auth->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Make a simple metadata request to test connection
        $auth = new UIPress_Analytics_Bridge_Auth();
        $analytics_data = $auth->get_analytics_data();
        
        if (empty($analytics_data) || empty($analytics_data['view'])) {
            return new WP_Error(
                'missing_property', 
                __('No Google Analytics property ID found. Please connect to Google Analytics in the settings.', 'uipress-analytics-bridge')
            );
        }
        
        $property_id = $analytics_data['view'];
        $request_url = self::GA4_API_URL . "properties/{$property_id}/metadata";
        
        $request_args = array(
            'headers' => array(
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        );
        
        $response = wp_remote_get($request_url, $request_args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'api_response_error',
                sprintf(
                    __('Google Analytics API error (HTTP %s)', 'uipress-analytics-bridge'),
                    $response_code
                )
            );
        }
        
        return true;
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
        if (!isset($analytics_data['view']) && !isset($analytics_data['measurement_id'])) {
            return new WP_Error('missing_property', __('Google Analytics property ID is missing', 'uipress-analytics-bridge'));
        }
        
        // Get property ID from either view or measurement_id
        $property_id = isset($analytics_data['view']) ? $analytics_data['view'] : $analytics_data['measurement_id'];
        $property_id = $this->sanitize_property_id($property_id);
        
        // If refresh is not requested, try to get from cache first
        $refresh = isset($params['refresh']) && $params['refresh'];
        if (!$refresh) {
            $cached_data = $this->get_cached_data($property_id);
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
                    __('Authentication error: %s. Please re-authenticate with Google Analytics.', 'uipress-analytics-bridge'),
                    $access_token->get_error_message()
                )
            );
        }
        
        // Prepare date range
        $days = isset($params['days']) ? intval($params['days']) : 30;
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get comparison data for previous period
        $prev_end_date = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $prev_start_date = date('Y-m-d', strtotime($prev_end_date . " -{$days} days"));
        
        // Build request to GA4 API
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
                        'endDate' => $end_date,
                        'name' => 'current'
                    ),
                    array(
                        'startDate' => $prev_start_date,
                        'endDate' => $prev_end_date,
                        'name' => 'previous'
                    )
                ),
                'dimensions' => array(
                    array('name' => 'date')
                ),
                'metrics' => array(
                    array('name' => 'activeUsers'),
                    array('name' => 'screenPageViews'),
                    array('name' => 'sessions'),
                    array('name' => 'engagementRate'),
                    array('name' => 'totalUsers'),
                    array('name' => 'newUsers')
                ),
                'keepEmptyRows' => true,
                'limit' => 100000
            )),
            'timeout' => 20 // Increase timeout for larger datasets
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
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['error']['message']) 
                ? $body['error']['message'] 
                : wp_remote_retrieve_response_message($response);
            
            return new WP_Error(
                'api_response_error',
                sprintf(
                    __('Google Analytics API error (HTTP %s): %s', 'uipress-analytics-bridge'),
                    $response_code,
                    $error_message
                ),
                $body['error'] ?? null
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
        
        // Debug log for successful API call
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('UIPress Analytics Bridge: Successful GA4 API call for property ' . $property_id);
        }
        
        // Process the response into a format compatible with UIPress
        $processed_data = $this->process_api_response($body, $days);
        
        // Get top content data if needed
        if (isset($params['include_content']) && $params['include_content']) {
            $content_data = $this->get_top_content_data($property_id, $access_token, $start_date, $end_date);
            if (!is_wp_error($content_data)) {
                $processed_data['content'] = $content_data;
            }
        }
        
        // Cache the processed data
        $this->cache_data($property_id, $processed_data);
        
        return $processed_data;
    }

    /**
     * Get top content data from Google Analytics.
     * 
     * @param string $property_id  The GA property ID
     * @param string $access_token The access token
     * @param string $start_date   The start date
     * @param string $end_date     The end date
     * @return array|WP_Error Content data or error
     */
    private function get_top_content_data($property_id, $access_token, $start_date, $end_date) {
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
                    array('name' => 'pagePath'),
                    array('name' => 'pageTitle')
                ),
                'metrics' => array(
                    array('name' => 'screenPageViews'),
                    array('name' => 'engagementRate'),
                    array('name' => 'totalUsers')
                ),
                'limit' => 20,
                'orderBys' => array(
                    array(
                        'metric' => array(
                            'metricName' => 'screenPageViews'
                        ),
                        'desc' => true
                    )
                )
            )),
            'timeout' => 15
        );
        
        $response = wp_remote_request($request_url, $request_args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'api_response_error',
                sprintf(
                    __('Content data API error (HTTP %s)', 'uipress-analytics-bridge'),
                    $response_code
                )
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Process content data
        $content_data = array();
        
        if (isset($body['rows']) && is_array($body['rows'])) {
            foreach ($body['rows'] as $row) {
                if (!isset($row['dimensionValues']) || !isset($row['metricValues'])) {
                    continue;
                }
                
                $path = $row['dimensionValues'][0]['value'];
                $title = $row['dimensionValues'][1]['value'];
                $pageviews = intval($row['metricValues'][0]['value']);
                $engagement = floatval($row['metricValues'][1]['value']) * 100;
                $users = intval($row['metricValues'][2]['value']);
                
                $content_data[] = array(
                    'path' => $path,
                    'title' => $title,
                    'pageviews' => $pageviews,
                    'engagement' => round($engagement, 2),
                    'users' => $users
                );
            }
        }
        
        return $content_data;
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
                'engagement' => 0,
                'newUsers' => 0,
                'totalUsers' => 0
            ),
            'previous' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'engagement' => 0,
                'newUsers' => 0,
                'totalUsers' => 0
            ),
            'comparison' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'engagement' => 0,
                'newUsers' => 0,
                'totalUsers' => 0
            )
        );
        
        if (!isset($response['rows']) || empty($response['rows'])) {
            return $processed_data;
        }
        
        // Get metric columns
        $metric_headers = array();
        if (isset($response['metricHeaders']) && is_array($response['metricHeaders'])) {
            foreach ($response['metricHeaders'] as $index => $header) {
                $metric_headers[$index] = $header['name'];
            }
        }
        
        // Prepare data arrays
        $current_data = array();
        $previous_data = array();
        
        // Process rows from the API response
        foreach ($response['rows'] as $row) {
            // Skip if required data is missing
            if (!isset($row['dimensionValues']) || !isset($row['metricValues'])) {
                continue;
            }
            
            $date = $row['dimensionValues'][0]['value']; // Format: YYYYMMDD
            $formatted_date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
            
            // Get metrics based on headers
            $metrics = array();
            foreach ($row['metricValues'] as $index => $value) {
                if (isset($metric_headers[$index])) {
                    $metrics[$metric_headers[$index]] = $value['value'];
                }
            }
            
            // Determine if this is current or previous period
            $period = isset($row['dateRange']) ? $row['dateRange'] : 'current';
            
            // Add data to appropriate array
            if ($period === 'previous') {
                $previous_data[$formatted_date] = $metrics;
            } else {
                $current_data[$formatted_date] = $metrics;
            }
        }
        
        // Process current period data
        foreach ($current_data as $date => $metrics) {
            $processed_data['dates'][] = $date;
            
            // Process each metric
            $users = isset($metrics['activeUsers']) ? intval($metrics['activeUsers']) : 0;
            $pageviews = isset($metrics['screenPageViews']) ? intval($metrics['screenPageViews']) : 0;
            $sessions = isset($metrics['sessions']) ? intval($metrics['sessions']) : 0;
            $engagement = isset($metrics['engagementRate']) ? floatval($metrics['engagementRate']) * 100 : 0;
            $newUsers = isset($metrics['newUsers']) ? intval($metrics['newUsers']) : 0;
            $totalUsers = isset($metrics['totalUsers']) ? intval($metrics['totalUsers']) : 0;
            
            $processed_data['users'][] = $users;
            $processed_data['pageviews'][] = $pageviews;
            $processed_data['sessions'][] = $sessions;
            $processed_data['engagement'][] = round($engagement, 2);
            
            // Add to totals
            $processed_data['totals']['users'] += $users;
            $processed_data['totals']['pageviews'] += $pageviews;
            $processed_data['totals']['sessions'] += $sessions;
            $processed_data['totals']['newUsers'] += $newUsers;
            $processed_data['totals']['totalUsers'] += $totalUsers;
        }
        
        // Calculate average engagement
        $processed_data['totals']['engagement'] = count($processed_data['engagement']) > 0 
            ? array_sum($processed_data['engagement']) / count($processed_data['engagement']) 
            : 0;
        
        // Process previous period data for totals
        foreach ($previous_data as $date => $metrics) {
            // Process each metric for previous period
            $users = isset($metrics['activeUsers']) ? intval($metrics['activeUsers']) : 0;
            $pageviews = isset($metrics['screenPageViews']) ? intval($metrics['screenPageViews']) : 0;
            $sessions = isset($metrics['sessions']) ? intval($metrics['sessions']) : 0;
            $engagement = isset($metrics['engagementRate']) ? floatval($metrics['engagementRate']) * 100 : 0;
            $newUsers = isset($metrics['newUsers']) ? intval($metrics['newUsers']) : 0;
            $totalUsers = isset($metrics['totalUsers']) ? intval($metrics['totalUsers']) : 0;
            
            // Add to previous totals
            $processed_data['previous']['users'] += $users;
            $processed_data['previous']['pageviews'] += $pageviews;
            $processed_data['previous']['sessions'] += $sessions;
            $processed_data['previous']['engagement'] += $engagement;
            $processed_data['previous']['newUsers'] += $newUsers;
            $processed_data['previous']['totalUsers'] += $totalUsers;
        }
        
        // Calculate average engagement for previous period
        $processed_data['previous']['engagement'] = count($previous_data) > 0 
            ? $processed_data['previous']['engagement'] / count($previous_data) 
            : 0;
        
        // Calculate comparison percentages
        $metrics_to_compare = array('users', 'pageviews', 'sessions', 'engagement', 'newUsers', 'totalUsers');
        
        foreach ($metrics_to_compare as $metric) {
            if ($processed_data['previous'][$metric] > 0) {
                $change = (($processed_data['totals'][$metric] - $processed_data['previous'][$metric]) / $processed_data['previous'][$metric]) * 100;
                $processed_data['comparison'][$metric] = round($change, 2);
            } else {
                $processed_data['comparison'][$metric] = 0;
            }
        }
        
        return $processed_data;
    }

    /**
     * Get analytics data.
     * 
     * @param array $params Additional parameters for the request
     * @return array|WP_Error Analytics data or error
     */
    public function get_analytics_data($params = array()) {
        // Get the analytics account data from auth class
        $auth = new UIPress_Analytics_Bridge_Auth();
        $analytics_data = $auth->get_analytics_data();
        
        if (!$analytics_data) {
            return new WP_Error('missing_data', __('No Google Analytics data found', 'uipress-analytics-bridge'));
        }
        
        // Check if we should skip cache
        $refresh = isset($params['refresh']) && $params['refresh'];
        
        // Try to get data from cache first
        if (!$refresh) {
            $property_id = isset($analytics_data['view']) ? $analytics_data['view'] : (isset($analytics_data['measurement_id']) ? $analytics_data['measurement_id'] : '');
            if (!empty($property_id)) {
                $cached_data = $this->get_cached_data($property_id);
                if ($cached_data !== false) {
                    return $cached_data;
                }
            }
        }
        
        // Fetch fresh data
        return $this->fetch_analytics_data($analytics_data, $params);
    }

    /**
     * Cache data.
     * 
     * @param string $property_id The property ID
     * @param array  $data        The data to cache
     * @return void
     */
    private function cache_data($property_id, $data) {
        // Get cache expiration from settings
        $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
        $cache_expiration = isset($advanced_settings['cache_expiration']) ? intval($advanced_settings['cache_expiration']) : 3600;
        
        // Allow filtering of cache expiration
        $cache_expiration = apply_filters('uipress_analytics_bridge_cache_expiration', $cache_expiration);
        
        $transient_name = $this->get_cache_key($property_id);
        set_transient($transient_name, $data, $cache_expiration);
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
            
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                    '_transient_timeout_uip_analytics_%'
                )
            );
        }
    }

    /**
     * Sanitize property ID to ensure it's valid.
     * 
     * @param string $property_id The property ID
     * @return string Sanitized property ID
     */
    private function sanitize_property_id($property_id) {
        // Remove any non-alphanumeric characters except hyphen
        $property_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $property_id);
        
        // Handle GA4 format property IDs
        if (strpos($property_id, 'G-') !== 0 && is_numeric($property_id)) {
            return $property_id;
        }
        
        // Return as-is for property IDs with a prefix like G-XXXXXXXX
        return $property_id;
    }
}