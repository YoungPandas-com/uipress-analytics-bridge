<?php
/**
 * Auth callback template
 *
 * @since 1.0.0
 */
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><span class="dashicons dashicons-google"></span> <?php _e('Google Analytics Authentication', 'uipress-analytics-bridge'); ?></h1>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Authentication Process', 'uipress-analytics-bridge'); ?></h2>
        
        <div style="text-align: center; padding: 30px 20px; max-width: 600px; margin: 0 auto;">
            <img src="<?php echo UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/images/google-auth.png'; ?>" alt="Google Authentication" style="max-width: 250px; margin-bottom: 30px;">
            
            <div class="uip-analytics-bridge-status info">
                <p><?php _e('You will be redirected to Google to authenticate with your Google Analytics account.', 'uipress-analytics-bridge'); ?></p>
            </div>
            
            <p style="margin-top: 20px;"><?php _e('Please authorize access to your Google Analytics account to enable the integration with UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
            
            <div style="margin: 30px 0;">
                <div class="uip-analytics-bridge-loader" style="display: inline-block; border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; width: 30px; height: 30px; animation: uip-analytics-spin 2s linear infinite;"></div>
                <p><?php _e('Redirecting to Google...', 'uipress-analytics-bridge'); ?></p>
            </div>
            
            <p><?php _e('If you are not redirected automatically, please click the button below:', 'uipress-analytics-bridge'); ?></p>
            
            <p>
                <a href="<?php echo esc_url($api_auth->get_authorization_url()); ?>" class="uip-analytics-bridge-google-button" style="padding: 10px 20px; font-size: 16px;">
                    <img src="<?php echo UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/images/google-logo.svg'; ?>" alt="Google" width="20" height="20">
                    <?php _e('Sign in with Google', 'uipress-analytics-bridge'); ?>
                </a>
            </p>
            
            <style>
                @keyframes uip-analytics-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </div>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('What happens next?', 'uipress-analytics-bridge'); ?></h2>
        
        <ol style="margin-left: 20px; line-height: 1.8;">
            <li><?php _e('You\'ll be redirected to Google\'s authentication page.', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Sign in with your Google account that has access to Google Analytics.', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Grant permission to access your Analytics data.', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('You\'ll be returned to your WordPress site automatically.', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('The connection will be established with UIPress Pro.', 'uipress-analytics-bridge'); ?></li>
        </ol>
        
        <div class="uip-analytics-bridge-status info" style="margin-top: 20px;">
            <p><?php _e('Note: Your analytics data never leaves your site. This plugin only establishes a secure connection between WordPress and Google Analytics.', 'uipress-analytics-bridge'); ?></p>
        </div>
    </div>
    
    <p style="text-align: center; margin-top: 20px;">
        <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="button button-secondary">
            <span class="dashicons dashicons-arrow-left-alt" style="margin-top: 3px;"></span>
            <?php _e('Back to Settings', 'uipress-analytics-bridge'); ?>
        </a>
    </p>
</div>

<script>
// Redirect to Google Auth URL after a short delay
setTimeout(function() {
    window.location.href = "<?php echo esc_url($api_auth->get_authorization_url()); ?>";
}, 2000);
</script>