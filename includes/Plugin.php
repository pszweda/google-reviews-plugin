<?php

namespace GoogleBusinessReviews;

class Plugin
{
    private static $instance = null;
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        $this->init();
    }
    
    private function init()
    {
        // Initialize admin
        if (is_admin()) {
            new Admin();
        }
        
        // Initialize AJAX handlers
        new AjaxHandler();
        
        // Initialize CLI command
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('google-reviews', new CliCommand());
        }
        
        // Add settings link
        add_filter('plugin_action_links_' . plugin_basename(GBR_PLUGIN_DIR . 'google-business-reviews.php'), [$this, 'addSettingsLink']);
    }
    
    public function addSettingsLink($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=google-business-reviews') . '">' . __('Settings', 'google-business-reviews') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public static function activate()
    {
        // Create default options
        add_option('gbr_client_id', '');
        add_option('gbr_client_secret', '');
        add_option('gbr_access_token', '');
        add_option('gbr_refresh_token', '');
        add_option('gbr_token_expires_at', 0);
        add_option('gbr_account_id', '');
        add_option('gbr_location_id', '');
        add_option('gbr_reviews', []);
        add_option('gbr_last_update', '');
        
        // Schedule cron job for automatic updates
        if (!wp_next_scheduled('gbr_update_reviews')) {
            wp_schedule_event(time(), 'daily', 'gbr_update_reviews');
        }
    }
    
    public static function deactivate()
    {
        // Clear scheduled cron job
        wp_clear_scheduled_hook('gbr_update_reviews');
    }
    
    public static function uninstall()
    {
        // Remove options
        delete_option('gbr_client_id');
        delete_option('gbr_client_secret');
        delete_option('gbr_access_token');
        delete_option('gbr_refresh_token');
        delete_option('gbr_token_expires_at');
        delete_option('gbr_account_id');
        delete_option('gbr_location_id');
        delete_option('gbr_reviews');
        delete_option('gbr_last_update');
        
        // Clear scheduled cron job
        wp_clear_scheduled_hook('gbr_update_reviews');
    }
}