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
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php _e('This plugin enhances UIPress Pro\'s Google Analytics integration with improved authentication and data retrieval.', 'uipress-analytics-bridge'); ?></p>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('UIPress Pro Status', 'uipress-analytics-bridge'); ?></h2>
        
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
        <?php endif; ?>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Google Analytics API Connection', 'uipress-analytics-bridge'); ?></h2>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('uipress_analytics_bridge_settings');
            do_settings_sections('uipress_analytics_bridge_settings');
            submit_button(__('Save API Settings', 'uipress-analytics-bridge'));
            ?>
        </form>
        
        <hr>
        
        <h3><?php _e('Authentication Status', 'uipress-analytics-bridge'); ?></h3>
        
        <?php if ($has_credentials): ?>
            <?php if ($is_authenticated): ?>
                <div class="uip-analytics-bridge-status success">
                    <p><?php _e('Successfully authenticated with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
                </div>
                
                <p>
                    <button id="uip-analytics-test-button" class="uip-analytics-bridge-button"><?php _e('Test Connection', 'uipress-analytics-bridge'); ?></button>
                    <button id="uip-analytics-revoke-button" class="uip-analytics-bridge-button secondary"><?php _e('Revoke Access', 'uipress-analytics-bridge'); ?></button>
                </p>
            <?php else: ?>
                <div class="uip-analytics-bridge-status warning">
                    <p><?php _e('API credentials are set, but not authenticated with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
                </div>
                
                <?php if (!empty($auth_url)): ?>
                    <p>
                        <a href="#" id="uip-analytics-auth-button" class="uip-analytics-bridge-button" data-auth-url="<?php echo esc_url($auth_url); ?>"><?php _e('Authenticate with Google', 'uipress-analytics-bridge'); ?></a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="uip-analytics-bridge-status info">
                <p><?php _e('Please enter your Google API credentials above to enable authentication.', 'uipress-analytics-bridge'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Integration Status', 'uipress-analytics-bridge'); ?></h2>
        
        <?php if ($uipress_detected && $has_credentials && $is_authenticated): ?>
            <div class="uip-analytics-bridge-status success">
                <p><?php _e('UIPress Analytics Bridge is properly configured and ready to enhance UIPress Pro\'s analytics.', 'uipress-analytics-bridge'); ?></p>
            </div>
            <p><?php _e('You can now use UIPress Pro\'s Google Analytics features, which will automatically use the improved authentication provided by this plugin.', 'uipress-analytics-bridge'); ?></p>
        <?php else: ?>
            <div class="uip-analytics-bridge-status warning">
                <p><?php _e('Complete the setup steps above to enable the enhanced Google Analytics integration.', 'uipress-analytics-bridge'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div> 