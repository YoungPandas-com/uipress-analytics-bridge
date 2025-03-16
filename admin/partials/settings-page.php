<?php
/**
 * Settings page template
 *
 * @since 1.0.0
 */

// Get authentication status
$api_auth = new UIPress_Analytics_Bridge_API_Auth();
$has_credentials = $api_auth->has_credentials();
$is_authenticated = $api_auth->is_authenticated();
$auth_url = $api_auth->get_authorization_url();

// Get the token data for display
$token_data = $api_auth->get_token_data();

// Check if UIPress is using analytics
$auth = new UIPress_Analytics_Bridge_Auth();
$analytics_data = $auth->get_analytics_data();
$has_analytics_data = !empty($analytics_data);

// Get plugin setup completion status
$step1_completed = $has_credentials;
$step2_completed = $is_authenticated;
$step3_completed = $uipress_detected && $analytics_detected;
$step4_completed = $has_analytics_data && $uipress_detected && $is_authenticated;

// Calculate which step should be active
$active_step = 1;
if ($step1_completed && !$step2_completed) {
    $active_step = 2;
} elseif ($step1_completed && $step2_completed && !$step4_completed) {
    $active_step = 3;
} elseif ($step1_completed && $step2_completed && $step4_completed) {
    $active_step = 4;
}
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><span class="dashicons dashicons-chart-bar"></span> <?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php _e('This plugin enhances UIPress Pro\'s Google Analytics integration with improved authentication and data retrieval.', 'uipress-analytics-bridge'); ?></p>
    </div>
    
    <!-- Setup Progress Indicator -->
    <div class="uip-analytics-bridge-progress">
        <div class="uip-analytics-bridge-progress-step <?php echo ($active_step === 1) ? 'active' : ''; ?> <?php echo ($step1_completed) ? 'completed' : ''; ?>">
            <div class="uip-analytics-bridge-progress-indicator">
                <?php if ($step1_completed): ?>
                    <span class="dashicons dashicons-yes"></span>
                <?php else: ?>
                    1
                <?php endif; ?>
            </div>
            <div class="uip-analytics-bridge-progress-label"><?php _e('API Credentials', 'uipress-analytics-bridge'); ?></div>
        </div>
        <div class="uip-analytics-bridge-progress-step <?php echo ($active_step === 2) ? 'active' : ''; ?> <?php echo ($step2_completed) ? 'completed' : ''; ?>">
            <div class="uip-analytics-bridge-progress-indicator">
                <?php if ($step2_completed): ?>
                    <span class="dashicons dashicons-yes"></span>
                <?php else: ?>
                    2
                <?php endif; ?>
            </div>
            <div class="uip-analytics-bridge-progress-label"><?php _e('Authentication', 'uipress-analytics-bridge'); ?></div>
        </div>
        <div class="uip-analytics-bridge-progress-step <?php echo ($active_step === 3) ? 'active' : ''; ?> <?php echo ($step3_completed) ? 'completed' : ''; ?>">
            <div class="uip-analytics-bridge-progress-indicator">
                <?php if ($step3_completed): ?>
                    <span class="dashicons dashicons-yes"></span>
                <?php else: ?>
                    3
                <?php endif; ?>
            </div>
            <div class="uip-analytics-bridge-progress-label"><?php _e('UIPress Pro', 'uipress-analytics-bridge'); ?></div>
        </div>
        <div class="uip-analytics-bridge-progress-step <?php echo ($active_step === 4) ? 'active' : ''; ?> <?php echo ($step4_completed) ? 'completed' : ''; ?>">
            <div class="uip-analytics-bridge-progress-indicator">
                <?php if ($step4_completed): ?>
                    <span class="dashicons dashicons-yes"></span>
                <?php else: ?>
                    4
                <?php endif; ?>
            </div>
            <div class="uip-analytics-bridge-progress-label"><?php _e('Integration', 'uipress-analytics-bridge'); ?></div>
        </div>
    </div>
    
    <!-- Step 1: Google API Credentials -->
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-admin-network"></span> <?php _e('Step 1: Google API Credentials', 'uipress-analytics-bridge'); ?></h2>
        
        <p><?php _e('To connect with Google Analytics, you need to set up Google API credentials.', 'uipress-analytics-bridge'); ?></p>
        
        <div class="uip-analytics-bridge-step <?php echo ($active_step === 1) ? 'active' : ''; ?> <?php echo ($step1_completed) ? 'completed' : ''; ?>">
            <h3><?php _e('Enter your Google API Credentials', 'uipress-analytics-bridge'); ?></h3>
            <p><?php _e('These credentials allow the plugin to authenticate with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('uipress_analytics_bridge_settings');
                ?>
                
                <table class="uip-analytics-bridge-form-table">
                    <tr>
                        <th scope="row">
                            <label for="uip_analytics_bridge_client_id"><?php _e('Client ID', 'uipress-analytics-bridge'); ?></label>
                            <span class="uip-analytics-bridge-tooltip">
                                <span class="dashicons dashicons-editor-help"></span>
                                <span class="uip-analytics-bridge-tooltip-text"><?php _e('Enter the Client ID from your Google Cloud Console project.', 'uipress-analytics-bridge'); ?></span>
                            </span>
                        </th>
                        <td>
                            <?php $settings = get_option('uip_analytics_bridge_google_api', array()); ?>
                            <?php $client_id = isset($settings['client_id']) ? $settings['client_id'] : ''; ?>
                            <input type="text" id="uip_analytics_bridge_client_id" name="uip_analytics_bridge_google_api[client_id]" value="<?php echo esc_attr($client_id); ?>" class="regular-text" placeholder="123456789012-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com" />
                            <p class="description"><?php _e('Find this in your Google Cloud Console under "APIs & Services" > "Credentials".', 'uipress-analytics-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="uip_analytics_bridge_client_secret"><?php _e('Client Secret', 'uipress-analytics-bridge'); ?></label>
                            <span class="uip-analytics-bridge-tooltip">
                                <span class="dashicons dashicons-editor-help"></span>
                                <span class="uip-analytics-bridge-tooltip-text"><?php _e('Enter the Client Secret from your Google Cloud Console project.', 'uipress-analytics-bridge'); ?></span>
                            </span>
                        </th>
                        <td>
                            <?php $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : ''; ?>
                            <input type="password" id="uip_analytics_bridge_client_secret" name="uip_analytics_bridge_google_api[client_secret]" value="<?php echo esc_attr($client_secret); ?>" class="regular-text" placeholder="GOCSPX-xxxxxxxxxxxxxxxxxxxx" />
                            <p class="description"><?php _e('This value is shown when you create OAuth credentials in Google Cloud Console.', 'uipress-analytics-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="uip_analytics_bridge_measurement_id"><?php _e('GA4 Measurement ID (Optional)', 'uipress-analytics-bridge'); ?></label>
                            <span class="uip-analytics-bridge-tooltip">
                                <span class="dashicons dashicons-editor-help"></span>
                                <span class="uip-analytics-bridge-tooltip-text"><?php _e('This is optional if you prefer direct GA4 setup without OAuth.', 'uipress-analytics-bridge'); ?></span>
                            </span>
                        </th>
                        <td>
                            <?php $measurement_id = isset($settings['measurement_id']) ? $settings['measurement_id'] : ''; ?>
                            <input type="text" id="uip_analytics_bridge_measurement_id" name="uip_analytics_bridge_google_api[measurement_id]" value="<?php echo esc_attr($measurement_id); ?>" class="regular-text" placeholder="G-XXXXXXXXXX" />
                            <p class="description"><?php _e('Find this in your GA4 property admin under "Data Streams".', 'uipress-analytics-bridge'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save API Settings', 'uipress-analytics-bridge'), 'primary', 'submit', false); ?>
                
                <p class="description" style="margin-top: 20px;">
                    <strong><?php _e('Need Google Cloud credentials?', 'uipress-analytics-bridge'); ?></strong>
                    <a href="https://console.cloud.google.com/apis/dashboard" target="_blank"><?php _e('Go to Google Cloud Console', 'uipress-analytics-bridge'); ?> <span class="dashicons dashicons-external"></span></a>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Step 2: Google Authentication -->
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-lock"></span> <?php _e('Step 2: Google Authentication', 'uipress-analytics-bridge'); ?></h2>
        
        <?php if (!$has_credentials): ?>
            <div class="uip-analytics-bridge-status info">
                <p><?php _e('Please complete Step 1 first by entering your Google API credentials.', 'uipress-analytics-bridge'); ?></p>
            </div>
        <?php else: ?>
            <div class="uip-analytics-bridge-step <?php echo ($active_step === 2) ? 'active' : ''; ?> <?php echo ($step2_completed) ? 'completed' : ''; ?>">
                <h3><?php _e('Connect with Google Analytics', 'uipress-analytics-bridge'); ?></h3>
                
                <?php if ($is_authenticated): ?>
                    <div class="uip-analytics-bridge-status success">
                        <p><?php _e('Successfully authenticated with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                    
                    <?php if ($token_data): ?>
                        <div class="uip-analytics-bridge-connection-details">
                            <h4><?php _e('Connection Details', 'uipress-analytics-bridge'); ?></h4>
                            <table>
                                <tr>
                                    <th><?php _e('Status', 'uipress-analytics-bridge'); ?></th>
                                    <td><span class="dashicons dashicons-yes" style="color:#46b450;"></span> <?php _e('Connected', 'uipress-analytics-bridge'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Connected Since', 'uipress-analytics-bridge'); ?></th>
                                    <td>
                                        <?php 
                                        if (isset($token_data['created'])) {
                                            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $token_data['created']));
                                        } else {
                                            _e('Unknown', 'uipress-analytics-bridge');
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php if (isset($token_data['scope'])): ?>
                                <tr>
                                    <th><?php _e('Access Scope', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo esc_html(str_replace(' ', ', ', $token_data['scope'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px;">
                        <button id="uip-analytics-test-button" class="uip-analytics-bridge-button">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php _e('Test Connection', 'uipress-analytics-bridge'); ?>
                        </button>
                        <button id="uip-analytics-revoke-button" class="uip-analytics-bridge-button secondary">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php _e('Revoke Access', 'uipress-analytics-bridge'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <p><?php _e('Choose how you want to authenticate with Google Analytics:', 'uipress-analytics-bridge'); ?></p>
                    
                    <div class="uip-analytics-bridge-auth-methods">
                        <div class="uip-analytics-bridge-auth-method">
                            <h3><span class="dashicons dashicons-google"></span> <?php _e('OAuth Authentication', 'uipress-analytics-bridge'); ?></h3>
                            <p><?php _e('Connect using your Google account with secure OAuth authentication.', 'uipress-analytics-bridge'); ?></p>
                            <?php if (!empty($auth_url)): ?>
                                <a href="#" id="uip-analytics-auth-button" class="uip-analytics-bridge-google-button" data-auth-url="<?php echo esc_url($auth_url); ?>">
                                    <img src="<?php echo UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/images/google-logo.svg'; ?>" alt="Google" width="18" height="18">
                                    <?php _e('Sign in with Google', 'uipress-analytics-bridge'); ?>
                                </a>
                            <?php else: ?>
                                <div class="uip-analytics-bridge-status warning">
                                    <p><?php _e('Unable to generate authentication URL. Please check your API credentials.', 'uipress-analytics-bridge'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="uip-analytics-bridge-auth-method">
                            <h3><span class="dashicons dashicons-admin-generic"></span> <?php _e('Manual Configuration', 'uipress-analytics-bridge'); ?></h3>
                            <p><?php _e('If you prefer, you can manually enter your GA4 Measurement ID in Step 1.', 'uipress-analytics-bridge'); ?></p>
                            <a href="#manual-ga4-setup" class="uip-analytics-bridge-button secondary">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('Configure Manually', 'uipress-analytics-bridge'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Step 3: UIPress Pro Status -->
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-admin-plugins"></span> <?php _e('Step 3: UIPress Pro Status', 'uipress-analytics-bridge'); ?></h2>
        
        <div class="uip-analytics-bridge-step <?php echo ($active_step === 3) ? 'active' : ''; ?> <?php echo ($step3_completed) ? 'completed' : ''; ?>">
            <h3><?php _e('UIPress Pro Detection', 'uipress-analytics-bridge'); ?></h3>
            
            <?php if ($uipress_detected): ?>
                <div class="uip-analytics-bridge-status success">
                    <p><?php _e('UIPress Pro is active and detected.', 'uipress-analytics-bridge'); ?></p>
                </div>
                
                <?php if ($analytics_detected): ?>
                    <div class="uip-analytics-bridge-status success">
                        <p><?php _e('Google Analytics functionality is available in UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="uip-analytics-bridge-status warning">
                        <p><?php _e('Google Analytics functionality was not detected in UIPress Pro. The plugin might still work, but there could be compatibility issues.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="uip-analytics-bridge-status error">
                    <p><?php _e('UIPress Pro is not active or could not be detected. This plugin requires UIPress Pro to function properly.', 'uipress-analytics-bridge'); ?></p>
                </div>
                
                <p><?php _e('Please ensure UIPress Pro is installed and activated.', 'uipress-analytics-bridge'); ?></p>
                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="uip-analytics-bridge-button secondary">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Go to Plugins', 'uipress-analytics-bridge'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Step 4: Integration Status -->
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-networking"></span> <?php _e('Step 4: Integration Status', 'uipress-analytics-bridge'); ?></h2>
        
        <div class="uip-analytics-bridge-step <?php echo ($active_step === 4) ? 'active' : ''; ?> <?php echo ($step4_completed) ? 'completed' : ''; ?>">
            <h3><?php _e('UIPress Analytics Bridge Integration', 'uipress-analytics-bridge'); ?></h3>
            
            <?php if ($uipress_detected && $has_credentials && $is_authenticated): ?>
                <?php if ($has_analytics_data): ?>
                    <div class="uip-analytics-bridge-status success">
                        <p><?php _e('UIPress Analytics Bridge is properly configured and integrated with UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                    <p><?php _e('Your enhanced Google Analytics connection is now active and working with UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
                    
                    <p><strong><?php _e('Next Steps:', 'uipress-analytics-bridge'); ?></strong></p>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        <li><?php _e('Access UIPress Admin Dashboard to view your Google Analytics data', 'uipress-analytics-bridge'); ?></li>
                        <li><?php _e('Add Analytics blocks to your UIPress dashboards', 'uipress-analytics-bridge'); ?></li>
                    </ul>
                    
                    <div style="margin-top: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=uip-overview')); ?>" class="uip-analytics-bridge-button success">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php _e('View UIPress Dashboard', 'uipress-analytics-bridge'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="uip-analytics-bridge-status warning">
                        <p><?php _e('Almost there! Analytics data has not yet been retrieved from UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                    <p><?php _e('Visit your UIPress dashboard and add a Google Analytics block to complete the integration.', 'uipress-analytics-bridge'); ?></p>
                    
                    <div style="margin-top: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=uip-overview')); ?>" class="uip-analytics-bridge-button">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php _e('Go to UIPress Dashboard', 'uipress-analytics-bridge'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="uip-analytics-bridge-status info">
                    <p><?php _e('Complete the previous steps to enable the enhanced Google Analytics integration.', 'uipress-analytics-bridge'); ?></p>
                </div>
                
                <p><?php _e('Here\'s what you need to do:', 'uipress-analytics-bridge'); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <?php if (!$has_credentials): ?>
                        <li><?php _e('Step 1: Enter your Google API credentials', 'uipress-analytics-bridge'); ?></li>
                    <?php endif; ?>
                    
                    <?php if ($has_credentials && !$is_authenticated): ?>
                        <li><?php _e('Step 2: Authenticate with Google Analytics', 'uipress-analytics-bridge'); ?></li>
                    <?php endif; ?>
                    
                    <?php if (!$uipress_detected): ?>
                        <li><?php _e('Step 3: Install and activate UIPress Pro', 'uipress-analytics-bridge'); ?></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Help & Support Section -->
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-editor-help"></span> <?php _e('Help & Support', 'uipress-analytics-bridge'); ?></h2>
        
        <p><?php _e('Having trouble setting up the plugin? Here are some helpful resources:', 'uipress-analytics-bridge'); ?></p>
        
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><a href="https://uipress.co/docs/" target="_blank"><?php _e('UIPress Documentation', 'uipress-analytics-bridge'); ?> <span class="dashicons dashicons-external"></span></a></li>
            <li><a href="https://developers.google.com/analytics/devguides/reporting/data/v1" target="_blank"><?php _e('Google Analytics Data API Documentation', 'uipress-analytics-bridge'); ?> <span class="dashicons dashicons-external"></span></a></li>
            <li><a href="https://console.cloud.google.com/apis/dashboard" target="_blank"><?php _e('Google Cloud Console', 'uipress-analytics-bridge'); ?> <span class="dashicons dashicons-external"></span></a></li>
        </ul>
        
        <p><?php _e('For additional support, please contact your plugin provider.', 'uipress-analytics-bridge'); ?></p>
    </div>
</div>