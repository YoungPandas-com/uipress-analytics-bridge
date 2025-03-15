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
    
    /**
     * Google API scopes needed for Analytics
     */
    const GOOGLE_SCOPES = array(
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/analytics'
    );
    
    /**
     * WordPress option name for storing credentials
     */
    const OPTION_NAME = 'uip_analytics_bridge_oauth';
    
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
     * Initialize the class.
     */
    public function __construct() {
        // Get credentials from options
        $credentials = get_option('uip_analytics_bridge_google_api', array());
        
        $this->client_id = isset($credentials['client_id']) ? $credentials['client_id'] : '';
        $this->client_secret = isset($credentials['client_secret']) ? $credentials['client_secret'] : '';
        $this->redirect_uri = admin_url('admin.php?page=uipress-analytics-bridge-auth');
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
            'scope' => implode(' ', self::GOOGLE_SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
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
        
        // Store token data
        $this->store_token_data($body);
        
        // Also store in a convenient format for UIPress compatibility
        $this->update_uipress_compatible_token_data($body);
        
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
        
        return $body;
    }

    /**
     * Store token data.
     * 
     * @param array $token_data The token data
     * @return void
     */
    private function store_token_data($token_data) {
        $token_data['created'] = time();
        update_option(self::OPTION_NAME, $token_data);
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
        
        // Delete stored token data
        delete_option(self::OPTION_NAME);
        
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
     * @return void
     */
    private function update_uipress_compatible_token_data($token_data) {
        // Get the auth instance to store in UIPress format
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        // Store minimal token data in UIPress format
        $uip_data = array(
            'token' => isset($token_data['access_token']) ? $token_data['access_token'] : '',
            // Add other fields that UIPress might expect
        );
        
        // Save in both user and global scopes for maximum compatibility
        $auth->save_analytics_data($uip_data, 'false'); // Global
        $auth->save_analytics_data($uip_data, 'true');  // User
    }
} 