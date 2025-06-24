<?php
/**
 * Plugin Name: Google Business Reviews Downloader
 * Description: Downloads and manages Google Business Profile reviews for your WordPress site.
 * Version: 1.0.4
 * Author: Piotr Szweda
 * Text Domain: google-reviews-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GBR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GBR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GBR_PLUGIN_VERSION', '1.0.0');

// Autoload classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'GoogleBusinessReviews\\') === 0) {
        $file = GBR_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', substr($class, 21)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Initialize plugin
add_action('plugins_loaded', function() {
    GoogleBusinessReviews\Plugin::getInstance();
});

// Include debug admin for testing
if (defined('WP_DEBUG') && WP_DEBUG) {
    include_once GBR_PLUGIN_DIR . 'debug-admin.php';
}

// Activation hook
register_activation_hook(__FILE__, function() {
    GoogleBusinessReviews\Plugin::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    GoogleBusinessReviews\Plugin::deactivate();
});

// Uninstall hook
register_uninstall_hook(__FILE__, 'GoogleBusinessReviews\Plugin::uninstall');