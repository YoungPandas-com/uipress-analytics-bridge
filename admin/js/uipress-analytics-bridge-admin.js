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
        setupTooltips();
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
        
        // Handle manual setup link
        $('a[href="#manual-ga4-setup"]').on('click', function(e) {
            e.preventDefault();
            scrollToElement('#uip_analytics_bridge_measurement_id');
        });
    }

    /**
     * Set up tooltips
     */
    function setupTooltips() {
        $('.uip-analytics-bridge-tooltip').each(function() {
            const $tooltip = $(this);
            const $icon = $tooltip.find('.dashicons');
            
            // Mobile-friendly tooltip toggle
            $icon.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $tooltipText = $tooltip.find('.uip-analytics-bridge-tooltip-text');
                $('.uip-analytics-bridge-tooltip-text').not($tooltipText).css('visibility', 'hidden');
                
                if ($tooltipText.css('visibility') === 'visible') {
                    $tooltipText.css('visibility', 'hidden');
                } else {
                    $tooltipText.css('visibility', 'visible');
                }
            });
            
            // Hide tooltips when clicking elsewhere
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.uip-analytics-bridge-tooltip').length) {
                    $('.uip-analytics-bridge-tooltip-text').css('visibility', 'hidden');
                }
            });
        });
    }

    /**
     * Handle authentication button click
     */
    function handleAuthClick(e) {
        e.preventDefault();
        
        // Get the auth URL from the data attribute
        const authUrl = $(this).data('auth-url');
        
        if (authUrl) {
            // Show loading state
            const $button = $(this);
            const originalHtml = $button.html();
            $button.html('<span class="spinner" style="visibility:visible;float:none;margin:0 8px 0 0;"></span> ' + uipAnalyticsBridge.connecting);
            $button.prop('disabled', true);
            
            // Open the auth window
            const authWindow = window.open(authUrl, 'uip_analytics_auth', 'width=600,height=700');
            
            // Check if window was blocked
            if (!authWindow || authWindow.closed || typeof authWindow.closed === 'undefined') {
                // Popup blocked
                $button.html(originalHtml);
                $button.prop('disabled', false);
                alert(uipAnalyticsBridge.popupBlocked);
                return;
            }
            
            // Poll to check if the auth window has been closed
            const pollTimer = window.setInterval(function() {
                if (authWindow.closed) {
                    window.clearInterval(pollTimer);
                    // Reload the page to check authentication status
                    window.location.reload();
                }
            }, 500);
        }
    }

    /**
     * Handle revoke button click
     */
    function handleRevokeClick(e) {
        e.preventDefault();
        
        if (confirm(uipAnalyticsBridge.confirmRevoke)) {
            // Show loading state
            const $button = $(this);
            const originalHtml = $button.html();
            $button.html('<span class="spinner" style="visibility:visible;float:none;margin:0 8px 0 0;"></span> ' + uipAnalyticsBridge.revoking);
            $button.prop('disabled', true);
            
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
                        // Show success message and reload
                        showNotice('success', response.data);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error and restore button
                        showNotice('error', response.data || uipAnalyticsBridge.errorRevoke);
                        $button.html(originalHtml);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    // Show error and restore button
                    showNotice('error', uipAnalyticsBridge.errorAjax);
                    $button.html(originalHtml);
                    $button.prop('disabled', false);
                }
            });
        }
    }

    /**
     * Handle test connection button click
     */
    function handleTestClick(e) {
        e.preventDefault();
        
        // Show loading state
        const $button = $(this);
        const originalHtml = $button.html();
        $button.html('<span class="spinner" style="visibility:visible;float:none;margin:0 8px 0 0;"></span> ' + uipAnalyticsBridge.testing);
        $button.prop('disabled', true);
        
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
                    // Show success message
                    showNotice('success', response.data);
                } else {
                    // Show error message
                    showNotice('error', response.data || uipAnalyticsBridge.errorTest);
                }
                
                // Restore button
                $button.html(originalHtml);
                $button.prop('disabled', false);
            },
            error: function() {
                // Show error and restore button
                showNotice('error', uipAnalyticsBridge.errorAjax);
                $button.html(originalHtml);
                $button.prop('disabled', false);
            }
        });
    }

    /**
     * Show notice message
     * 
     * @param {string} type The notice type (success, error, warning, info)
     * @param {string} message The message to display
     */
    function showNotice(type, message) {
        // Remove any existing notice
        $('.uip-analytics-bridge-notice').remove();
        
        // Create notice element
        const $notice = $('<div>', {
            'class': 'notice notice-' + type + ' is-dismissible uip-analytics-bridge-notice',
            'style': 'position: relative; padding-right: 38px;'
        }).append($('<p>').text(message));
        
        // Add dismiss button
        const $dismissButton = $('<button>', {
            'type': 'button',
            'class': 'notice-dismiss',
            'aria-label': uipAnalyticsBridge.dismiss
        });
        
        $dismissButton.on('click', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        $notice.append($dismissButton);
        
        // Insert notice at the top of the page
        $('.wrap.uip-analytics-bridge-admin').prepend($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            if ($notice.length) {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        }, 5000);
    }

    /**
     * Scroll to element
     * 
     * @param {string} selector Element selector
     */
    function scrollToElement(selector) {
        const $element = $(selector);
        if ($element.length) {
            $('html, body').animate({
                scrollTop: $element.offset().top - 100
            }, 500);
            
            // Highlight the field
            $element.focus().css({
                'background-color': '#fffbcc'
            }).delay(1000).queue(function(next) {
                $(this).css({
                    'background-color': ''
                });
                next();
            });
        }
    }

    // Initialize when the document is ready
    $(document).ready(init);

})(jQuery);