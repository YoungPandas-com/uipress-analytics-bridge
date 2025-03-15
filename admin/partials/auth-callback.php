<?php
/**
 * Auth callback template
 *
 * @since 1.0.0
 */
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><?php _e('Google Analytics Authentication', 'uipress-analytics-bridge'); ?></h1>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Authentication Process', 'uipress-analytics-bridge'); ?></h2>
        
        <div class="uip-analytics-bridge-status info">
            <p><?php _e('You will be redirected to Google to authenticate with your Google Analytics account.', 'uipress-analytics-bridge'); ?></p>
        </div>
        
        <p><?php _e('If you are not redirected automatically, please click the button below:', 'uipress-analytics-bridge'); ?></p>
        
        <p>
            <a href="<?php echo esc_url($api_auth->get_authorization_url()); ?>" class="uip-analytics-bridge-button"><?php _e('Authenticate with Google', 'uipress-analytics-bridge'); ?></a>
        </p>
    </div>
</div>

<script>
// Redirect to Google Auth URL after a short delay
setTimeout(function() {
    window.location.href = "<?php echo esc_url($api_auth->get_authorization_url()); ?>";
}, 1000);
</script> 