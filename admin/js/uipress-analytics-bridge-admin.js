/**
 * Admin JavaScript for UIPress Analytics Bridge
 */
(function($) {
    'use strict';

    /**
     * Initialize the admin functionality
     */
    function init() {
        bindEvents();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Handle authentication button click
        $('#uip-analytics-auth-button').on('click', handleAuthClick);
        
        // Handle revoke button click
        $('#uip-analytics-revoke-button').on('click', handleRevokeClick);
        
        // Handle test connection button click
        $('#uip-analytics-test-button').on('click', handleTestClick);
    }

    /**
     * Handle authentication button click
     */
    function handleAuthClick(e) {
        // Prevent default if it's a link
        e.preventDefault();
        
        // Get the auth URL from the data attribute
        const authUrl = $(this).data('auth-url');
        
        if (authUrl) {
            // Open the auth URL in a new window
            window.open(authUrl, 'uip_analytics_auth', 'width=600,height=700');
        }
    }

    /**
     * Handle revoke button click
     */
    function handleRevokeClick(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to revoke the Google Analytics authentication?')) {
            // Send AJAX request to revoke
            $.ajax({
                url: uipAnalyticsBridge.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'uip_analytics_bridge_revoke',
                    nonce: uipAnalyticsBridge.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to update status
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Could not revoke authentication.'));
                    }
                },
                error: function() {
                    alert('An error occurred while trying to revoke authentication.');
                }
            });
        }
    }

    /**
     * Handle test connection button click
     */
    function handleTestClick(e) {
        e.preventDefault();
        
        // Change button text and disable
        const $button = $(this);
        const originalText = $button.text();
        $button.text('Testing...').prop('disabled', true);
        
        // Send AJAX request to test connection
        $.ajax({
            url: uipAnalyticsBridge.ajaxUrl,
            type: 'POST',
            data: {
                action: 'uip_analytics_bridge_test_connection',
                nonce: uipAnalyticsBridge.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: Connection to Google Analytics is working properly.');
                } else {
                    alert('Error: ' + (response.data || 'Could not connect to Google Analytics.'));
                }
                
                // Restore button
                $button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert('An error occurred while testing the connection.');
                $button.text(originalText).prop('disabled', false);
            }
        });
    }

    // Initialize when the document is ready
    $(document).ready(init);

})(jQuery); 