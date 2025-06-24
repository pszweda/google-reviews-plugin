=== Google Business Reviews Downloader ===
Contributors: prestige-training
Tags: google, reviews, business, google-business-profile, testimonials
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download and manage Google Business Profile reviews for your WordPress site.

== Description ==

The Google Business Reviews Downloader plugin allows you to automatically download and store reviews from your Google Business Profile directly in your WordPress database.

Features:
* Download all reviews from Google Business Profile
* Store reviews in WordPress database
* Admin interface for configuration and manual downloads
* WP-CLI command support for automated downloads
* AJAX-powered admin interface
* Automatic daily updates via WordPress cron

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/google-business-reviews` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Google Business to configure the plugin
4. Enter your Google Places API Key and Place ID
5. Click "Download Reviews Now" to fetch your reviews

== Configuration ==

1. Get a Google Places API Key:
   - Go to Google Cloud Console (https://console.cloud.google.com/)
   - Create or select a project
   - Enable the Places API
   - Create an API Key
   - Restrict the key to Places API for security

2. Find your Place ID:
   - Use Google's Place ID Finder tool
   - Enter your business name and location
   - Copy the Place ID

3. Enter both values in the plugin settings

== Usage ==

= Admin Interface =
Go to Settings > Google Business to:
- Configure API credentials
- Test connection
- Download reviews manually
- View cached reviews

= WP-CLI =
Download reviews via command line:
`wp google-reviews --api-key=YOUR_API_KEY --place-id=YOUR_PLACE_ID`

= Programmatic Access =
Use the service class in your code:
```php
$service = new GoogleBusinessReviews\GoogleBusinessService();
$reviews = $service->getCachedReviews();
```

== Frequently Asked Questions ==

= How often are reviews updated? =
Reviews are automatically updated once daily via WordPress cron. You can also manually update them anytime from the admin interface.

= Is my API key secure? =
Yes, API keys are stored in WordPress options and never exposed to frontend users. Always restrict your API key to only the Places API in Google Cloud Console.

= Can I display reviews on my site? =
This plugin only downloads and stores reviews. You'll need to create custom templates or use additional plugins to display them on your frontend.

== Changelog ==

= 1.0.0 =
* Initial release
* Download Google Business Profile reviews
* Admin interface for configuration
* WP-CLI support
* Automatic daily updates