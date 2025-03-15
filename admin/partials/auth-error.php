<?php
/**
 * Auth error template
 *
 * @since 1.0.0
 */
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><?php _e('Google Analytics Authentication Error', 'uipress-analytics-bridge'); ?></h1>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><?php _e('Authentication Failed', 'uipress-analytics-bridge'); ?></h2>
        
        <div class="uip-analytics-bridge-status error">
            <p><?php printf(__('An error occurred during authentication: %s', 'uipress-analytics-bridge'), esc_html($error)); ?></p>
        </div>
        
        <p><?php _e('Please try again or check your API credentials.', 'uipress-analytics-bridge'); ?></p>
        
        <p>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="uip-analytics-bridge-button"><?php _e('Return to Settings', 'uipress-analytics-bridge'); ?></a>
        </p>
    </div>
</div> 