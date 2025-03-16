<?php
/**
 * Diagnostics page template
 *
 * @since 1.0.0
 */
?>

<div class="wrap uip-analytics-bridge-admin">
    <div class="uip-analytics-bridge-header">
        <h1><span class="dashicons dashicons-search"></span> <?php _e('UIPress Analytics Bridge Diagnostics', 'uipress-analytics-bridge'); ?></h1>
        <p><?php _e('This page provides diagnostic information about your Google Analytics connection and plugin integration.', 'uipress-analytics-bridge'); ?></p>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-admin-plugins"></span> <?php _e('Plugin Information', 'uipress-analytics-bridge'); ?></h2>
        
        <table class="widefat" style="margin-bottom: 20px;">
            <tbody>
                <tr>
                    <td><strong><?php _e('Plugin Version', 'uipress-analytics-bridge'); ?></strong></td>
                    <td><?php echo esc_html(UIPRESS_ANALYTICS_BRIDGE_VERSION); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('WordPress Version', 'uipress-analytics-bridge'); ?></strong></td>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('PHP Version', 'uipress-analytics-bridge'); ?></strong></td>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('UIPress Pro Detected', 'uipress-analytics-bridge'); ?></strong></td>
                    <td>
                        <?php if ($detector->is_uipress_pro_active()): ?>
                            <span style="color: #46b450;"><?php _e('Yes', 'uipress-analytics-bridge'); ?></span>
                        <?php else: ?>
                            <span style="color: #dc3232;"><?php _e('No', 'uipress-analytics-bridge'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($detector->is_uipress_pro_active()): ?>
                <tr>
                    <td><strong><?php _e('UIPress Pro Path', 'uipress-analytics-bridge'); ?></strong></td>
                    <td><?php echo esc_html($detector->get_uipress_pro_path()); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Analytics Functionality', 'uipress-analytics-bridge'); ?></strong></td>
                    <td>
                        <?php if ($detector->has_analytics_functionality()): ?>
                            <span style="color: #46b450;"><?php _e('Detected', 'uipress-analytics-bridge'); ?></span>
                        <?php else: ?>
                            <span style="color: #dc3232;"><?php _e('Not Detected', 'uipress-analytics-bridge'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-admin-network"></span> <?php _e('Google Analytics Authentication', 'uipress-analytics-bridge'); ?></h2>
        
        <?php if ($api_auth->is_authenticated()): ?>
            <div class="uip-analytics-bridge-status success">
                <p><?php _e('Successfully authenticated with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
            </div>
            
            <h3><?php _e('Token Information', 'uipress-analytics-bridge'); ?></h3>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Token Status', 'uipress-analytics-bridge'); ?></strong></td>
                        <td>
                            <?php 
                            if (isset($token_data['created'], $token_data['expires_in'])) {
                                $expiry_time = $token_data['created'] + $token_data['expires_in'];
                                $now = time();
                                
                                if ($now > $expiry_time) {
                                    echo '<span style="color: #dc3232;">' . __('Expired (will refresh automatically on next use)', 'uipress-analytics-bridge') . '</span>';
                                } else {
                                    $time_left = $expiry_time - $now;
                                    $minutes_left = floor($time_left / 60);
                                    echo '<span style="color: #46b450;">' . sprintf(__('Valid (expires in %d minutes)', 'uipress-analytics-bridge'), $minutes_left) . '</span>';
                                }
                            } else {
                                echo '<span style="color: #dc3232;">' . __('Unknown status', 'uipress-analytics-bridge') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Token Created', 'uipress-analytics-bridge'); ?></strong></td>
                        <td>
                            <?php 
                            if (isset($token_data['created'])) {
                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $token_data['created']));
                            } else {
                                echo __('Unknown', 'uipress-analytics-bridge');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Refresh Token', 'uipress-analytics-bridge'); ?></strong></td>
                        <td>
                            <?php 
                            if (isset($token_data['refresh_token']) && !empty($token_data['refresh_token'])) {
                                echo '<span style="color: #46b450;">' . __('Available', 'uipress-analytics-bridge') . '</span>';
                            } else {
                                echo '<span style="color: #dc3232;">' . __('Not Available', 'uipress-analytics-bridge') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php if (isset($token_data['scope'])): ?>
                    <tr>
                        <td><strong><?php _e('Scopes', 'uipress-analytics-bridge'); ?></strong></td>
                        <td>
                            <?php 
                            $scopes = explode(' ', $token_data['scope']);
                            echo '<ul style="margin: 0; padding-left: 20px;">';
                            foreach ($scopes as $scope) {
                                echo '<li>' . esc_html($scope) . '</li>';
                            }
                            echo '</ul>';
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (!empty($user_info)): ?>
            <h3><?php _e('Google Account Information', 'uipress-analytics-bridge'); ?></h3>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <?php if (isset($user_info['name'])): ?>
                    <tr>
                        <td><strong><?php _e('Name', 'uipress-analytics-bridge'); ?></strong></td>
                        <td><?php echo esc_html($user_info['name']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (isset($user_info['email'])): ?>
                    <tr>
                        <td><strong><?php _e('Email', 'uipress-analytics-bridge'); ?></strong></td>
                        <td><?php echo esc_html($user_info['email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (isset($user_info['picture']) && !empty($user_info['picture'])): ?>
                    <tr>
                        <td><strong><?php _e('Profile Picture', 'uipress-analytics-bridge'); ?></strong></td>
                        <td><img src="<?php echo esc_url($user_info['picture']); ?>" alt="<?php _e('Profile Picture', 'uipress-analytics-bridge'); ?>" style="width: 50px; height: 50px; border-radius: 50%;" /></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php else: ?>
            <div class="uip-analytics-bridge-status error">
                <p><?php _e('Not authenticated with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-chart-bar"></span> <?php _e('UIPress Analytics Integration', 'uipress-analytics-bridge'); ?></h2>
        
        <?php if (!empty($analytics_data)): ?>
            <div class="uip-analytics-bridge-status success">
                <p><?php _e('Integration with UIPress Pro is active.', 'uipress-analytics-bridge'); ?></p>
            </div>
            
            <h3><?php _e('Analytics Configuration', 'uipress-analytics-bridge'); ?></h3>
            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <?php foreach ($analytics_data as $key => $value): ?>
                        <?php if (in_array($key, array('token', 'refresh_token'))) continue; // Skip sensitive data ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></strong></td>
                            <td><?php echo esc_html($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="uip-analytics-bridge-status warning">
                <p><?php _e('No UIPress analytics data found.', 'uipress-analytics-bridge'); ?></p>
            </div>
        <?php endif; ?>
        
        <h3><?php _e('Intercepted Hooks', 'uipress-analytics-bridge'); ?></h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Hook Name', 'uipress-analytics-bridge'); ?></th>
                    <th><?php _e('Status', 'uipress-analytics-bridge'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>wp_ajax_uip_build_google_analytics_query</code></td>
                    <td>
                        <?php 
                        if (has_action('wp_ajax_uip_build_google_analytics_query')) {
                            echo '<span style="color: #46b450;">' . __('Intercepted', 'uipress-analytics-bridge') . '</span>';
                        } else {
                            echo '<span style="color: #dc3232;">' . __('Not Intercepted', 'uipress-analytics-bridge') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><code>wp_ajax_uip_save_google_analytics</code></td>
                    <td>
                        <?php 
                        if (has_action('wp_ajax_uip_save_google_analytics')) {
                            echo '<span style="color: #46b450;">' . __('Intercepted', 'uipress-analytics-bridge') . '</span>';
                        } else {
                            echo '<span style="color: #dc3232;">' . __('Not Intercepted', 'uipress-analytics-bridge') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><code>wp_ajax_uip_save_access_token</code></td>
                    <td>
                        <?php 
                        if (has_action('wp_ajax_uip_save_access_token')) {
                            echo '<span style="color: #46b450;">' . __('Intercepted', 'uipress-analytics-bridge') . '</span>';
                        } else {
                            echo '<span style="color: #dc3232;">' . __('Not Intercepted', 'uipress-analytics-bridge') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><code>wp_ajax_uip_google_auth_check</code></td>
                    <td>
                        <?php 
                        if (has_action('wp_ajax_uip_google_auth_check')) {
                            echo '<span style="color: #46b450;">' . __('Intercepted', 'uipress-analytics-bridge') . '</span>';
                        } else {
                            echo '<span style="color: #dc3232;">' . __('Not Intercepted', 'uipress-analytics-bridge') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><code>uip_filter_google_analytics_data</code></td>
                    <td>
                        <?php 
                        if (has_filter('uip_filter_google_analytics_data')) {
                            echo '<span style="color: #46b450;">' . __('Filtered', 'uipress-analytics-bridge') . '</span>';
                        } else {
                            echo '<span style="color: #dc3232;">' . __('Not Filtered', 'uipress-analytics-bridge') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="uip-analytics-bridge-section">
        <h2><span class="dashicons dashicons-admin-tools"></span> <?php _e('Tools', 'uipress-analytics-bridge'); ?></h2>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <h3><?php _e('Connection Testing', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('Test your Google Analytics connection to ensure it\'s working properly.', 'uipress-analytics-bridge'); ?></p>
                <button id="uip-analytics-test-button" class="uip-analytics-bridge-button">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php _e('Test Connection', 'uipress-analytics-bridge'); ?>
                </button>
            </div>
            
            <div style="flex: 1; min-width: 250px;">
                <h3><?php _e('Reset Connection', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('Revoke the current Google Analytics connection and start over.', 'uipress-analytics-bridge'); ?></p>
                <button id="uip-analytics-revoke-button" class="uip-analytics-bridge-button error">
                    <span class="dashicons dashicons-no-alt"></span>
                    <?php _e('Revoke Connection', 'uipress-analytics-bridge'); ?>
                </button>
            </div>
            
            <div style="flex: 1; min-width: 250px;">
                <h3><?php _e('Clear Cache', 'uipress-analytics-bridge'); ?></h3>
                <p><?php _e('Clear the analytics data cache to fetch fresh data.', 'uipress-analytics-bridge'); ?></p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=uipress-analytics-bridge-diagnostics&action=clear_cache'), 'uipress_analytics_bridge_clear_cache')); ?>" class="uip-analytics-bridge-button">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Clear Cache', 'uipress-analytics-bridge'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <p style="text-align: center; margin-top: 20px;">
        <a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="button button-secondary">
            <span class="dashicons dashicons-arrow-left-alt" style="margin-top: 3px;"></span>
            <?php _e('Back to Settings', 'uipress-analytics-bridge'); ?>
        </a>
    </p>
</div>