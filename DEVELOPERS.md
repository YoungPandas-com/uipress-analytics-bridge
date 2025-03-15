# Developer Documentation for UIPress Analytics Bridge

This document provides technical information for developers who want to extend or modify the UIPress Analytics Bridge plugin.

## Plugin Architecture

The plugin follows a modular architecture with these key components:

1. **Detector Class** (`class-uipress-analytics-bridge-detector.php`): Responsible for detecting UIPress Pro installation.
2. **Auth Class** (`class-uipress-analytics-bridge-auth.php`): Handles authentication with UIPress Pro and coordinates with the API Auth class.
3. **Data Class** (`class-uipress-analytics-bridge-data.php`): Manages data retrieval and formatting for UIPress Pro compatibility.
4. **API Classes**:
   - `class-uipress-analytics-bridge-api-auth.php`: Handles direct OAuth authentication with Google.
   - `class-uipress-analytics-bridge-api-data.php`: Handles direct data retrieval from Google Analytics API.
5. **Admin Class** (`class-uipress-analytics-bridge-admin.php`): Manages the admin interface.

## Interception Pattern

The plugin uses WordPress action and filter hooks to intercept requests from UIPress Pro to Google Analytics. The key hook points are:

```php
// Interception hooks
add_action('wp_ajax_uip_build_google_analytics_query', $data, 'intercept_build_query', 9);
add_action('wp_ajax_uip_save_google_analytics', $auth, 'intercept_save_account', 9);
add_action('wp_ajax_uip_save_access_token', $auth, 'intercept_save_access_token', 9);
add_action('wp_ajax_uip_google_auth_check', $auth, 'intercept_auth_check', 9);
```

We use priority `9` to ensure our handlers run before UIPress Pro's handlers (which use the default priority of `10`).

## Available Filters

You can use these filters to modify the plugin's behavior:

1. **`uipress_analytics_bridge_api_scopes`**: Modify the Google API scopes requested during authentication.
   ```php
   add_filter('uipress_analytics_bridge_api_scopes', function($scopes) {
       $scopes[] = 'https://www.googleapis.com/auth/analytics.manage.users';
       return $scopes;
   });
   ```

2. **`uipress_analytics_bridge_cache_expiration`**: Change the cache duration for analytics data.
   ```php
   add_filter('uipress_analytics_bridge_cache_expiration', function($expiration) {
       return 60 * 30; // 30 minutes
   });
   ```

3. **`uipress_analytics_bridge_analytics_data`**: Modify analytics data before it's returned to UIPress Pro.
   ```php
   add_filter('uipress_analytics_bridge_analytics_data', function($data) {
       // Modify data here
       return $data;
   });
   ```

## Adding Support for Additional Analytics Providers

To add support for another analytics provider:

1. Create new API auth and data classes in the `includes/api` directory.
2. Implement the provider-specific authentication and data retrieval methods.
3. Extend the detector class to detect the new provider.
4. Hook into the appropriate interception points.

## Debugging

Enable debug logging by adding this to your `wp-config.php`:

```php
define('UIPRESS_ANALYTICS_BRIDGE_DEBUG', true);
```

This will log detailed information about API calls and data processing to the WordPress debug log.

## Known Issues and Limitations

1. The plugin currently supports Google Analytics 4 (GA4) with limited backward compatibility for Universal Analytics (UA).
2. UIPress Pro must be active for the plugin to function.
3. The plugin does not modify UIPress Pro's visualization components - it only replaces the authentication and data retrieval mechanisms.

## Build and Deployment

When developing or deploying this plugin:

1. Make sure all PHP files have the proper file headers and documentation.
2. Test thoroughly with different versions of UIPress Pro to ensure compatibility.
3. Use the provided test suite to verify API connectivity and data formatting.
4. Update version numbers in both the main plugin file and the README.

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository.
2. Create a feature branch.
3. Make your changes.
4. Submit a pull request with a clear description of the changes.

Make sure your code follows WordPress coding standards and includes appropriate documentation. 