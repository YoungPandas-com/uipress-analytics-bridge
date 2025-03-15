# UIPress Analytics Bridge

## Description

UIPress Analytics Bridge is a WordPress plugin that enhances the Google Analytics integration in UIPress Pro. It replaces only the authentication and data retrieval components of UIPress Pro's analytics system while preserving all existing visualization components and user interface.

## Features

- **Improved Authentication**: Provides a more reliable Google Analytics authentication mechanism.
- **Seamless Integration**: Works transparently with UIPress Pro without changing its user interface.
- **Enhanced Data Reliability**: Uses robust API methods for more stable data retrieval.
- **UIPress Pro Detection**: Automatically detects UIPress Pro regardless of its installation path.
- **OAuth Support**: Connects directly to Google Analytics using secure OAuth authentication.
- **Fallback Options**: Allows manual entry of GA4 Measurement ID as a fallback.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- UIPress Pro (installed and activated)
- Google Analytics 4 property
- Google API credentials (Client ID and Client Secret)

## Installation

1. Upload the `uipress-analytics-bridge` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to Settings > UIPress Analytics to configure the plugin.

## Configuration

### Step 1: Google API Credentials

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project or select an existing one.
3. Navigate to "APIs & Services" > "Credentials".
4. Create an OAuth Client ID with the following settings:
   - Application type: Web application
   - Name: UIPress Analytics Bridge (or any name you prefer)
   - Authorized redirect URIs: Add your site's URL with the path `/wp-admin/admin.php?page=uipress-analytics-bridge-auth`
5. After creating, note the Client ID and Client Secret.

### Step 2: Plugin Configuration

1. Go to Settings > UIPress Analytics in your WordPress admin.
2. Enter your Google API Client ID and Client Secret.
3. Click "Save API Settings".
4. Click "Authenticate with Google" to connect your Google Analytics account.
5. Select the GA4 property you want to use with UIPress Pro.

## Usage

Once configured, the plugin works automatically with UIPress Pro's existing analytics features. You don't need to change any settings in UIPress Pro - it will use the enhanced authentication and data retrieval methods provided by this plugin.

## Troubleshooting

### UIPress Pro Not Detected

If the plugin cannot detect UIPress Pro:
- Ensure UIPress Pro is installed and activated.
- The plugin attempts to detect UIPress Pro in non-standard locations, but if it fails, you may need to adjust your installation.

### Authentication Issues

If you're having problems authenticating with Google:
- Verify your Client ID and Client Secret are entered correctly.
- Ensure your redirect URI is properly configured in the Google Cloud Console.
- Check that your Google Analytics account has the necessary permissions.

### Data Not Showing in UIPress Pro

If analytics data isn't displaying in UIPress Pro:
- Test the connection in the plugin settings to ensure it can access Google Analytics.
- Verify that you've selected the correct GA4 property.
- Check UIPress Pro's analytics settings to ensure they're properly configured.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

This plugin was developed to enhance the functionality of UIPress Pro, with inspiration from the authentication approaches used in popular analytics plugins like MonsterInsights. 