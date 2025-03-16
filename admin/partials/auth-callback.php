<?php
/**
 * Auth callback template
 *
 * @since 1.0.0
 */
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e('Google Analytics Authentication', 'uipress-analytics-bridge'); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.5;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
        }
        p {
            margin-bottom: 20px;
            font-size: 15px;
        }
        .btn {
            display: inline-block;
            background-color: #4285f4;
            color: #fff;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            border: none;
            cursor: pointer;
            text-align: center;
        }
        .btn:hover {
            background-color: #3367d6;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
        }
        .btn-google img {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        .info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f1f8e9;
            border-left: 4px solid #8bc34a;
            border-radius: 2px;
        }
        .note {
            font-size: 13px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php _e('Connect to Google Analytics', 'uipress-analytics-bridge'); ?></h1>
        
        <p><?php _e('UIPress Analytics Bridge needs your permission to access your Google Analytics data. Click the button below to connect your account.', 'uipress-analytics-bridge'); ?></p>

        <p><?php _e('You will be redirected to Google\'s authentication page where you can select which Google account to use.', 'uipress-analytics-bridge'); ?></p>
        
        <div class="info">
            <p><strong><?php _e('Important', 'uipress-analytics-bridge'); ?>:</strong> <?php _e('After authorizing, make sure to select your Google Analytics account, property and view to link with UIPress.', 'uipress-analytics-bridge'); ?></p>
        </div>

        <?php 
        // Generate the auth URL
        $auth_url = $api_auth->generate_auth_url();
        ?>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="<?php echo esc_url($auth_url); ?>" class="btn btn-google">
                <img src="<?php echo UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL; ?>admin/images/google-logo.svg" alt="Google">
                <?php _e('Sign in with Google', 'uipress-analytics-bridge'); ?>
            </a>
        </p>
        
        <p class="note"><?php _e('Note: UIPress Analytics Bridge only requests read-only access to your Google Analytics data.', 'uipress-analytics-bridge'); ?></p>
    </div>

    <script>
        // Auto-click the auth button for a better user experience
        document.addEventListener('DOMContentLoaded', function() {
            // Give the user time to read the instructions first
            setTimeout(function() {
                document.querySelector('.btn-google').click();
            }, 500);
        });
    </script>
</body>
</html>