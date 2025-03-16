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
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        
        // Only load on UIPress admin pages or our plugin pages
        if ((isset($screen->id) && strpos($screen->id, 'uipress') !== false) || 
            (isset($_GET['page']) && strpos($_GET['page'], 'uipress-analytics-bridge') !== false)) {
            
            wp_enqueue_script(
                'uipress-analytics-bridge-admin',
                plugin_dir_url(__FILE__) . 'js/uipress-analytics-bridge-admin.js',
                array('jquery'),
                UIPRESS_ANALYTICS_BRIDGE_VERSION,
                false
            );
            
            // Add localization for the script
            wp_localize_script(
                'uipress-analytics-bridge-admin',
                'uip_analytics_bridge',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'security_nonce' => wp_create_nonce('uipress-lite-security-nonce'),
                )
            );
            
            // Add our auth override script
            $this->inject_auth_override_script();
        }
    }
    
    /**
     * Inject a script to override UIPress authentication check
     * 
     * @since    1.0.0
     * @return void
     */
    private function inject_auth_override_script() {
        // Get our authentication status
        $auth = new UIPress_Analytics_Bridge_Auth();
        $ga_data = $auth->get_analytics_data();
        
        // Get the callback URL for our OAuth flow
        $callback_url = admin_url('admin.php?page=uipress-analytics-bridge-auth');
        
        // Get our API credentials
        $api_credentials = get_option('uip_analytics_bridge_google_api', array());
        $measurement_id = !empty($api_credentials['measurement_id']) ? $api_credentials['measurement_id'] : '';
        
        // Only inject if we have valid authentication or credentials
        if (is_array($ga_data) || !empty($measurement_id)) {
            $script = "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Override UIPress Google Analytics authentication check
                function patchUipGoogleAnalytics() {
                    if (window.uip && window.uip.appData && window.uip.appData.options) {
                        // Force Google Analytics to be recognized as connected
                        if (!window.uip.appData.options.google_analytics) {
                            window.uip.appData.options.google_analytics = " . json_encode(is_array($ga_data) ? $ga_data : array()) . ";
                            window.uip.appData.options.google_analytics.connected = true;
                            window.uip.appData.options.google_analytics.oauth = true;
                            " . (!empty($measurement_id) ? "window.uip.appData.options.google_analytics.measurement_id = '{$measurement_id}';" : "") . "
                            console.log('UIPress Analytics Bridge: Injected Google Analytics authentication');
                        }
                    } else {
                        // Try again in 100ms if UIPress is not loaded yet
                        setTimeout(patchUipGoogleAnalytics, 100);
                    }
                }
                
                // Replace the 'Switch account' functionality
                function replaceSwitchAccountButton() {
                    // Find all 'Switch account' buttons or links
                    const switchButtons = Array.from(document.querySelectorAll('.uip-link, .uip-button')).filter(el => {
                        return el.textContent.trim().toLowerCase().includes('switch account');
                    });
                    
                    if (switchButtons.length > 0) {
                        switchButtons.forEach(button => {
                            // Change the button text to make it clear this uses our plugin
                            button.textContent = '" . __('Switch with Analytics Bridge', 'uipress-analytics-bridge') . "';
                            
                            // Remove old click handlers by cloning and replacing the element
                            const newButton = button.cloneNode(true);
                            button.parentNode.replaceChild(newButton, button);
                            
                            // Add our click handler
                            newButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                // Open our auth flow instead
                                window.open('" . esc_js($callback_url) . "', 'uip_analytics_auth', 'width=600,height=700');
                                
                                // Force the parent menu/dropdown to close if exists
                                const dropdowns = document.querySelectorAll('.uip-dropdown-content');
                                dropdowns.forEach(dropdown => {
                                    if (dropdown.contains(newButton)) {
                                        // Try to find close button or click outside to close
                                        const closeButtons = dropdown.querySelectorAll('.uip-link-muted');
                                        closeButtons.forEach(closeBtn => closeBtn.click());
                                    }
                                });
                            });
                        });
                        
                        console.log('UIPress Analytics Bridge: Replaced switch account buttons: ' + switchButtons.length);
                    }
                    
                    // Keep watching for new buttons that might be dynamically added
                    setTimeout(replaceSwitchAccountButton, 2000);
                }
                
                // Also hook into any AJAX calls to check authentication status
                if (typeof jQuery !== 'undefined') {
                    jQuery(document).ajaxSend(function(event, xhr, settings) {
                        if (settings.data && typeof settings.data === 'string') {
                            // Intercept Google Auth check
                            if (settings.data.indexOf('action=uip_google_auth_check') !== -1) {
                                console.log('UIPress Analytics Bridge: Intercepting Google auth check');
                            }
                            // Intercept build query
                            else if (settings.data.indexOf('action=uip_build_google_analytics_query') !== -1) {
                                console.log('UIPress Analytics Bridge: Intercepting Google query build');
                            }
                        }
                    });
                }
                
                // Start patching after a short delay to ensure UIPress has loaded
                setTimeout(patchUipGoogleAnalytics, 500);
                setTimeout(replaceSwitchAccountButton, 1000);
            });
            </script>
            ";
            
            // Output the script directly
            echo $script;
        }
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
     * Display admin notices about the plugin status
     * 
     * @since 1.0.0
     * @return void
     */
    public function display_admin_notices() {
        // Only show notices to administrators and on relevant screens
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if we're on a UIPress dashboard page with Google Analytics components
        $screen = get_current_screen();
        $is_uipress_dashboard = isset($screen->id) && (
            $screen->id === 'admin_page_uip-admin-dashboard' || 
            $screen->id === 'uipress_page_uip-admin-dashboard' ||
            (isset($_GET['page']) && $_GET['page'] === 'uip-admin-dashboard')
        );
        
        if ($is_uipress_dashboard) {
            // Check if we have valid authentication but UIPress Pro doesn't recognize it
            $auth = new UIPress_Analytics_Bridge_Auth();
            $ga_data = $auth->get_analytics_data();
            
            // Only show notice if we have valid authentication
            if (is_array($ga_data) && isset($ga_data['view']) && isset($ga_data['code'])) {
                // Add a button to force synchronization
                echo '<div class="notice notice-info is-dismissible uip-analytics-bridge-notice">';
                echo '<p><strong>' . __('UIPress Analytics Bridge is properly configured', 'uipress-analytics-bridge') . '</strong></p>';
                echo '<p>' . __('If Google Analytics components still show "Sign in with Google", please refresh this page or try the button below.', 'uipress-analytics-bridge') . '</p>';
                echo '<p><button id="uip-analytics-bridge-sync" class="button button-primary">' . __('Force Sync with UIPress', 'uipress-analytics-bridge') . '</button></p>';
                echo '</div>';
                
                // Add the script to handle the button click
                echo '<script>
                jQuery(document).ready(function($) {
                    $("#uip-analytics-bridge-sync").on("click", function(e) {
                        e.preventDefault();
                        $(this).prop("disabled", true).text("' . __('Syncing...', 'uipress-analytics-bridge') . '");
                        
                        // Force reload the UIPress app data
                        if (window.uip && window.uip.appData && window.uip.appData.options) {
                            window.uip.appData.options.google_analytics = ' . json_encode($ga_data) . ';
                            window.uip.appData.options.google_analytics.connected = true;
                            
                            // Notify user
                            alert("' . __('Authentication data synchronized. Please reload any Google Analytics blocks or refresh the page.', 'uipress-analytics-bridge') . '");
                            $(this).prop("disabled", false).text("' . __('Force Sync with UIPress', 'uipress-analytics-bridge') . '");
                        } else {
                            alert("' . __('UIPress not detected. Please refresh the page and try again.', 'uipress-analytics-bridge') . '");
                            $(this).prop("disabled", false).text("' . __('Force Sync with UIPress', 'uipress-analytics-bridge') . '");
                        }
                    });
                });
                </script>';
            }
        }
        
        // Display other notices from the plugin settings
        $notices = get_transient('uipress_analytics_bridge_admin_notices');
        if ($notices && is_array($notices)) {
            foreach ($notices as $notice) {
                if (isset($notice['message']) && isset($notice['type'])) {
                    echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
                    echo '<p>' . wp_kses_post($notice['message']) . '</p>';
                    echo '</div>';
                }
            }
            // Clear the notices after displaying them
            delete_transient('uipress_analytics_bridge_admin_notices');
        }
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