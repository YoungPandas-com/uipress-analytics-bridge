<?php
/**
 * Handles API authentication with Google Analytics.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_API_Auth {

    /**
     * Google OAuth endpoints
     */
    const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/auth';
    const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const GOOGLE_REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';
    
    /**
     * Google API scopes needed for Analytics
     */
    private $default_scopes = array(
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/analytics',
        'https://www.googleapis.com/auth/userinfo.profile',
        'https://www.googleapis.com/auth/userinfo.email'
    );
    
    /**
     * WordPress option name for storing credentials
     */
    const OPTION_NAME = 'uip_analytics_bridge_oauth';
    const USER_INFO_OPTION = 'uip_analytics_bridge_user_info';
    
    /**
     * Client ID for Google API
     * 
     * @var string
     */
    private $client_id;
    
    /**
     * Client secret for Google API
     * 
     * @var string
     */
    private $client_secret;
    
    /**
     * Redirect URI for OAuth flow
     * 
     * @var string
     */
    private $redirect_uri;
    
    /**
     * Google API scopes to request
     * 
     * @var array
     */
    private $scopes;

    /**
     * Initialize the class.
     */
    public function __construct() {
        // Get credentials from options
        $credentials = get_option('uip_analytics_bridge_google_api', array());
        
        $this->client_id = isset($credentials['client_id']) ? $credentials['client_id'] : '';
        $this->client_secret = isset($credentials['client_secret']) ? $credentials['client_secret'] : '';
        $this->redirect_uri = admin_url('admin.php?page=uipress-analytics-bridge-auth');
        
        // Allow filtering of scopes
        $this->scopes = apply_filters('uipress_analytics_bridge_api_scopes', $this->default_scopes);
    }

    /**
     * Set Google API credentials.
     * 
     * @param string $client_id     The client ID
     * @param string $client_secret The client secret
     * @return void
     */
    public function set_credentials($client_id, $client_secret) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        update_option('uip_analytics_bridge_google_api', array(
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ));
    }

    /**
     * Get OAuth authorization URL.
     * 
     * @return string The authorization URL
     */
    public function get_authorization_url() {
        if (empty($this->client_id)) {
            return '';
        }
        
        // Add state parameter for security
        $state = wp_create_nonce('uipress_analytics_bridge_auth');
        
        $args = array(
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => implode(' ', $this->scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true'
        );
        
        return add_query_arg($args, self::GOOGLE_AUTH_URL);
    }

    /**
     * Exchange authorization code for access token.
     * 
     * @param string $code The authorization code
     * @return array|WP_Error Token data or error
     */
    public function exchange_code_for_token($code) {
        if (empty($this->client_id) || empty($this->client_secret)) {
            return new WP_Error('missing_credentials', __('Client ID and Client Secret are required', 'uipress-analytics-bridge'));
        }
        
        // Verify state parameter if provided
        if (isset($_GET['state']) && !wp_verify_nonce($_GET['state'], 'uipress_analytics_bridge_auth')) {
            return new WP_Error('invalid_state', __('Invalid state parameter. Authentication request may have been tampered with.', 'uipress-analytics-bridge'));
        }
        
        $args = array(
            'body' => array(
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            )
        );
        
        $response = wp_remote_post(self::GOOGLE_TOKEN_URL, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            $error_description = isset($body['error_description']) ? $body['error_description'] : $body['error'];
            return new WP_Error('google_error', $error_description);
        }
        
        // Get user info to store with the token
        $user_info = $this->get_google_user_info($body['access_token']);
        if (!is_wp_error($user_info)) {
            update_option(self::USER_INFO_OPTION, $user_info);
        }
        
        // Store token data
        $this->store_token_data($body);
        
        // Also store in a convenient format for UIPress compatibility
        $this->update_uipress_compatible_token_data($body, $user_info);
        
        return $body;
    }

    /**
     * Get Google user information.
     * 
     * @param string $access_token The access token
     * @return array|WP_Error User info or error
     */
    private function get_google_user_info($access_token) {
        $args = array(
            'headers' => array(
                'Authorization' => "Bearer {$access_token}"
            )
        );
        
        $response = wp_remote_get(self::GOOGLE_USERINFO_URL, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('google_user_info_error', $body['error_description'] ?? $body['error']);
        }
        
        return $body;
    }

    /**
     * Refresh access token using refresh token.
     * 
     * @return array|WP_Error New token data or error
     */
    public function refresh_access_token() {
        $token_data = $this->get_token_data();
        
        if (empty($token_data) || empty($token_data['refresh_token'])) {
            return new WP_Error('missing_refresh_token', __('Refresh token is missing', 'uipress-analytics-bridge'));
        }
        
        $args = array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $token_data['refresh_token'],
                'grant_type' => 'refresh_token'
            )
        );
        
        $response = wp_remote_post(self::GOOGLE_TOKEN_URL, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('google_error', $body['error_description'] ?? $body['error']);
        }
        
        // Keep the refresh token from previous data
        $body['refresh_token'] = $token_data['refresh_token'];
        
        // Store updated token data
        $this->store_token_data($body);
        
        // Also update UIPress compatible token data
        $user_info = get_option(self::USER_INFO_OPTION, array());
        $this->update_uipress_compatible_token_data($body, $user_info);
        
        return $body;
    }

    /**
     * Store token data.
     * 
     * @param array $token_data The token data
     * @return void
     */
    private function store_token_data($token_data) {
        // Add timestamp for token creation
        $token_data['created'] = time();
        
        // Add additional metadata
        $token_data['site_url'] = home_url();
        $token_data['admin_email'] = get_option('admin_email');
        
        update_option(self::OPTION_NAME, $token_data);
        
        // Clear any cached data to ensure fresh data is retrieved
        $this->clear_cache();
    }

    /**
     * Clear any cached analytics data.
     * 
     * @return void
     */
    private function clear_cache() {
        global $wpdb;
        
        // Clear related transients
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
     * Get stored token data.
     * 
     * @return array|false Token data or false if not found
     */
    public function get_token_data() {
        return get_option(self::OPTION_NAME, false);
    }

    /**
     * Get user info.
     * 
     * @return array|false User info or false if not found
     */
    public function get_user_info() {
        return get_option(self::USER_INFO_OPTION, false);
    }

    /**
     * Get a valid access token, refreshing if necessary.
     * 
     * @return string|WP_Error Access token or error
     */
    public function get_access_token() {
        $token_data = $this->get_token_data();
        
        if (empty($token_data) || empty($token_data['access_token'])) {
            return new WP_Error('missing_access_token', __('Access token is missing', 'uipress-analytics-bridge'));
        }
        
        // Check if token is expired (or about to expire)
        if (isset($token_data['created']) && isset($token_data['expires_in'])) {
            $expiry_time = $token_data['created'] + $token_data['expires_in'] - 300; // 5 minutes buffer
            
            if (time() > $expiry_time) {
                // Token is expired or about to expire, refresh it
                $result = $this->refresh_access_token();
                
                if (is_wp_error($result)) {
                    return $result;
                }
                
                $token_data = $this->get_token_data(); // Get updated token data
            }
        }
        
        return $token_data['access_token'];
    }

    /**
     * Revoke the current token.
     * 
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function revoke_token() {
        $token_data = $this->get_token_data();
        
        if (empty($token_data) || empty($token_data['access_token'])) {
            return new WP_Error('missing_access_token', __('Access token is missing', 'uipress-analytics-bridge'));
        }
        
        $args = array(
            'body' => array(
                'token' => $token_data['access_token']
            )
        );
        
        $response = wp_remote_post(self::GOOGLE_REVOKE_URL, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Also try to revoke the refresh token if available
        if (!empty($token_data['refresh_token'])) {
            $args = array(
                'body' => array(
                    'token' => $token_data['refresh_token']
                )
            );
            
            wp_remote_post(self::GOOGLE_REVOKE_URL, $args);
        }
        
        // Delete stored token and user data
        delete_option(self::OPTION_NAME);
        delete_option(self::USER_INFO_OPTION);
        
        // Also clean up UIPress compatible data
        $this->clean_uipress_compatible_data();
        
        return true;
    }

    /**
     * Check if we have valid credentials.
     * 
     * @return bool Whether we have valid credentials
     */
    public function has_credentials() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * Check if we're authenticated with Google.
     * 
     * @return bool Whether we're authenticated
     */
    public function is_authenticated() {
        $token_data = $this->get_token_data();
        
        return !empty($token_data) && !empty($token_data['access_token']) && !empty($token_data['refresh_token']);
    }

    /**
     * Update token data in a format compatible with UIPress.
     * 
     * @param array $token_data The token data
     * @param array $user_info  The user info data
     * @return void
     */
    private function update_uipress_compatible_token_data($token_data, $user_info = array()) {
        // Get the auth instance to store in UIPress format
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        // Get any existing GA data
        $existing_data = $auth->get_analytics_data();
        if (!is_array($existing_data)) {
            $existing_data = array();
        }
        
        // Build UIPress compatible data
        $uip_data = array(
            'token' => isset($token_data['access_token']) ? $token_data['access_token'] : '',
            'refresh_token' => isset($token_data['refresh_token']) ? $token_data['refresh_token'] : '',
            'expires_in' => isset($token_data['expires_in']) ? $token_data['expires_in'] : 3600,
            'created' => isset($token_data['created']) ? $token_data['created'] : time(),
        );
        
        // Add Google Analytics property data if it exists in the current data
        if (isset($existing_data['view'])) {
            $uip_data['view'] = $existing_data['view'];
        }
        
        if (isset($existing_data['code'])) {
            $uip_data['code'] = $existing_data['code'];
        }
        
        // Add measurement ID from settings if available
        $credentials = get_option('uip_analytics_bridge_google_api', array());
        if (!empty($credentials['measurement_id'])) {
            $uip_data['measurement_id'] = $credentials['measurement_id'];
            
            // If we have a measurement ID but no view/code, generate them
            if (empty($uip_data['view'])) {
                $uip_data['view'] = $credentials['measurement_id'];
            }
            
            if (empty($uip_data['code'])) {
                // Generate a random string for code (UIPress uses this for verification)
                $uip_data['code'] = substr(md5(uniqid(mt_rand(), true)), 0, 16);
            }
        }
        
        // Add user information if available
        if (!empty($user_info)) {
            $uip_data['user_email'] = isset($user_info['email']) ? $user_info['email'] : '';
            $uip_data['user_name'] = isset($user_info['name']) ? $user_info['name'] : '';
            $uip_data['user_picture'] = isset($user_info['picture']) ? $user_info['picture'] : '';
        }
        
        // Save in both user and global scopes for maximum compatibility
        $auth->save_analytics_data($uip_data, 'false'); // Global
        $auth->save_analytics_data($uip_data, 'true');  // User
    }
    
    /**
     * Clean up UIPress compatible data when revoking access.
     * 
     * @return void
     */
    private function clean_uipress_compatible_data() {
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        // Clean data in both user and global scopes
        $auth->save_analytics_data(array(), 'false'); // Global
        $auth->save_analytics_data(array(), 'true');  // User
    }
    
    /**
     * Get Google Analytics property and view information.
     * 
     * @return array|WP_Error Property/view info or error
     */
    public function get_analytics_properties() {
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Make request to GA Admin API
        $url = 'https://analyticsadmin.googleapis.com/v1alpha/properties';
        $args = array(
            'headers' => array(
                'Authorization' => "Bearer {$access_token}"
            )
        );
        
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return new WP_Error('ga_api_error', $body['error']['message'] ?? __('Failed to retrieve GA properties', 'uipress-analytics-bridge'));
        }
        
        // Process the properties
        $properties = array();
        if (isset($body['properties']) && is_array($body['properties'])) {
            foreach ($body['properties'] as $property) {
                $property_id = isset($property['name']) ? basename($property['name']) : '';
                $display_name = isset($property['displayName']) ? $property['displayName'] : $property_id;
                
                $properties[] = array(
                    'id' => $property_id,
                    'name' => $display_name,
                    'account' => isset($property['account']) ? basename($property['account']) : '',
                    'currency' => isset($property['currencyCode']) ? $property['currencyCode'] : 'USD',
                    'timezone' => isset($property['timeZone']) ? $property['timeZone'] : 'UTC',
                    'create_time' => isset($property['createTime']) ? $property['createTime'] : '',
                );
            }
        }
        
        return $properties;
    }
}