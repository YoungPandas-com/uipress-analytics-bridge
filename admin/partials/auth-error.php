<?php
/**
 * Auth error template
 *
 * @since 1.0.0
 */

// Extract error details
$is_access_denied = (isset($error) && strpos($error, 'access_denied') !== false);
$is_invalid_client = (isset($error) && strpos($error, 'invalid_client') !== false);
$is_redirect_uri_mismatch = (isset($error) && strpos($error, 'redirect_uri_mismatch') !== false);
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><span class="dashicons dashicons-warning"></span> <?php _e('Google Analytics Authentication Error', 'uipress-analytics-bridge'); ?></h1>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Authentication Failed', 'uipress-analytics-bridge'); ?></h2>
        
        <div class="uip-analytics-bridge-status error">
            <p><?php printf(__('An error occurred during authentication: %s', 'uipress-analytics-bridge'), esc_html($error)); ?></p>
        </div>
        
        <?php if ($is_access_denied): ?>
            <div style="margin-top: 20px;">
                <h3><?php _e('Access Denied', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('It looks like you declined to give permission to access your Google Analytics data.', 'uipress-analytics-bridge'); ?></p>
                <p><?php _e('To connect UIPress Pro with Google Analytics, you need to allow access when prompted by Google.', 'uipress-analytics-bridge'); ?></p>
                
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url($api_auth->get_authorization_url()); ?>" class="uip-analytics-bridge-button">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Try Again', 'uipress-analytics-bridge'); ?>
                    </a>
                </p>
            </div>
        <?php elseif ($is_invalid_client): ?>
            <div style="margin-top: 20px;">
                <h3><?php _e('Invalid Client', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('There appears to be an issue with your Google API Client credentials.', 'uipress-analytics-bridge'); ?></p>
                <p><?php _e('Please check the following:', 'uipress-analytics-bridge'); ?></p>
                
                <ul style="list-style-type: disc; margin-left: 20px; line-height: 1.6;">
                    <li><?php _e('Your Client ID and Client Secret are entered correctly', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('The Google Analytics API is enabled in your Google Cloud Console', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('The OAuth consent screen is properly configured', 'uipress-analytics-bridge'); ?></li>
                </ul>
                
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="uip-analytics-bridge-button">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Review Settings', 'uipress-analytics-bridge'); ?>
                    </a>
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="uip-analytics-bridge-button secondary">
                        <span class="dashicons dashicons-external"></span>
                        <?php _e('Google Cloud Console', 'uipress-analytics-bridge'); ?>
                    </a>
                </p>
            </div>
        <?php elseif ($is_redirect_uri_mismatch): ?>
            <div style="margin-top: 20px;">
                <h3><?php _e('Redirect URI Mismatch', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('The redirect URI in your Google Cloud Console doesn\'t match the one used by this plugin.', 'uipress-analytics-bridge'); ?></p>
                <p><?php _e('Please add the following redirect URI to your OAuth client in Google Cloud Console:', 'uipress-analytics-bridge'); ?></p>
                
                <div style="background: #f0f0f1; padding: 10px 15px; border-radius: 4px; margin: 15px 0; word-break: break-all;">
                    <code><?php echo esc_html(admin_url('admin.php?page=uipress-analytics-bridge-auth')); ?></code>
                    <button class="button button-small" onclick="copyToClipboard('<?php echo esc_js(admin_url('admin.php?page=uipress-analytics-bridge-auth')); ?>')" style="margin-left: 10px;">
                        <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
                        <?php _e('Copy', 'uipress-analytics-bridge'); ?>
                    </button>
                </div>
                
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="uip-analytics-bridge-button">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Back to Settings', 'uipress-analytics-bridge'); ?>
                    </a>
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="uip-analytics-bridge-button secondary">
                        <span class="dashicons dashicons-external"></span>
                        <?php _e('Google Cloud Console', 'uipress-analytics-bridge'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div style="margin-top: 20px;">
                <h3><?php _e('General Authentication Error', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('There was an issue with the Google authentication process.', 'uipress-analytics-bridge'); ?></p>
                <p><?php _e('You might want to try the following troubleshooting steps:', 'uipress-analytics-bridge'); ?></p>
                
                <ol style="margin-left: 20px; line-height: 1.6;">
                    <li><?php _e('Ensure your Google API credentials are correct', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Verify the Google Analytics API is enabled in your Google Cloud project', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Check that your OAuth consent screen is properly configured', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Make sure your Google account has access to Google Analytics', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Try clearing your browser cache and cookies', 'uipress-analytics-bridge'); ?></li>
                </ol>
                
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="uip-analytics-bridge-button">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Return to Settings', 'uipress-analytics-bridge'); ?>
                    </a>
                    <a href="<?php echo esc_url($api_auth->get_authorization_url()); ?>" class="uip-analytics-bridge-button secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Try Again', 'uipress-analytics-bridge'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Alternative Connection Methods', 'uipress-analytics-bridge'); ?></h2>
        
        <p><?php _e('If you continue to experience issues with Google OAuth authentication, you can try using the direct Measurement ID method instead:', 'uipress-analytics-bridge'); ?></p>
        
        <ol style="margin-left: 20px; line-height: 1.6;">
            <li><?php _e('Go to your Google Analytics 4 property', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Navigate to Admin > Data Streams > Web', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Find your Measurement ID (starts with "G-")', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Enter this ID in the "GA4 Measurement ID" field in the plugin settings', 'uipress-analytics-bridge'); ?></li>
        </ol>
        
        <p style="margin-top: 20px;">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="uip-analytics-bridge-button">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e('Configure Measurement ID', 'uipress-analytics-bridge'); ?>
            </a>
        </p>
    </div>
</div>

<script>
function copyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    alert('<?php echo esc_js(__('Redirect URI copied to clipboard', 'uipress-analytics-bridge')); ?>');
}
</script>