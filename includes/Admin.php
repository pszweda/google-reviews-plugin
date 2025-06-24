<?php

namespace GoogleBusinessReviews;

class Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'initSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    public function addAdminMenu()
    {
        add_options_page(
            __('Google Business Reviews', 'google-business-reviews'),
            __('Google Business', 'google-business-reviews'),
            'manage_options',
            'google-business-reviews',
            [$this, 'adminPage']
        );
    }
    
    public function initSettings()
    {
        register_setting('gbr_settings', 'gbr_client_id');
        register_setting('gbr_settings', 'gbr_client_secret');
        register_setting('gbr_settings', 'gbr_location_id');
    }
    
    public function enqueueScripts($hook)
    {
        if ($hook !== 'settings_page_google-business-reviews') {
            return;
        }
        
        wp_enqueue_script(
            'gbr-admin',
            GBR_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            GBR_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('gbr-admin', 'gbrAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gbr_ajax_nonce'),
            'strings' => [
                'downloading' => __('Downloading reviews...', 'google-business-reviews'),
                'success' => __('Reviews downloaded successfully!', 'google-business-reviews'),
                'error' => __('Error downloading reviews:', 'google-business-reviews')
            ]
        ]);
    }
    
    public function adminPage()
    {
        try {
            // Handle form processing first
            $this->processFormSubmission();
            
            // Initialize services
            $service = new GoogleBusinessService();
            $oauthService = new OAuthService();
            
            // Get current options
            $clientId = get_option('gbr_client_id', '');
            $clientSecret = get_option('gbr_client_secret', '');
            $accessToken = get_option('gbr_access_token', '');
            $accountId = get_option('gbr_account_id', '');
            $locationId = get_option('gbr_location_id', '');
            $reviews = $service->getCachedReviews();
            $lastUpdate = $service->getLastUpdateTime();
            
            $accounts = [];
            $locations = [];
            $authUrl = '';
            
            // Get authorization URL if credentials are configured
            if (!empty($clientId) && !empty($clientSecret)) {
                try {
                    $authUrl = $oauthService->getAuthorizationUrl();
                } catch (\Exception $e) {
                    error_log('Auth URL Error: ' . $e->getMessage());
                }
            }
            
            // Get accounts and locations if authorized
            if (!empty($accessToken)) {
                try {
                    // Check if we should try to load data
                    $oauthService->ensureValidToken();
                    
                    $accounts = $service->getAccounts();
					if (!empty($accountId)) {
                        $locations = $service->getLocations($accountId);
                    }
                } catch (\Exception $e) {
                    error_log('Failed to load accounts/locations: ' . $e->getMessage());
                    
                    // If it's a token issue, show appropriate message
                    if (strpos($e->getMessage(), 'Token expired') !== false || 
                        strpos($e->getMessage(), 'refresh failed') !== false) {
                        add_settings_error('gbr_settings', 'token_expired', __('Token expired and refresh failed. Please re-authorize.', 'google-business-reviews'));
                    } else {
                        add_settings_error('gbr_settings', 'api_error', __('API Error: ', 'google-business-reviews') . $e->getMessage());
                    }
                }
            }
            
            include GBR_PLUGIN_DIR . 'templates/admin-page.php';
            
        } catch (\Exception $e) {
            error_log('Admin Page Error: ' . $e->getMessage());
            echo '<div class="wrap"><h1>Google Business Reviews</h1>';
            echo '<div class="notice notice-error"><p>Error loading admin page: ' . esc_html($e->getMessage()) . '</p></div>';
            echo '</div>';
        }
    }
    
    private function processFormSubmission()
    {
        // Handle OAuth callback
        if (isset($_GET['oauth_callback']) && isset($_GET['code'])) {
            try {
                $oauthService = new OAuthService();
                $result = $oauthService->handleCallback($_GET['code'], isset($_GET['state']) ? $_GET['state'] : '');
                add_settings_error('gbr_settings', 'oauth_success', __('Authorization successful! Account and location have been auto-configured.', 'google-business-reviews'), 'success');
                
                // Redirect to clean URL
                wp_redirect(admin_url('options-general.php?page=google-business-reviews&settings-updated=true'));
                exit;
            } catch (\Exception $e) {
                error_log('OAuth Error: ' . $e->getMessage());
                add_settings_error('gbr_settings', 'oauth_error', __('Authorization failed: ', 'google-business-reviews') . $e->getMessage());
            }
        }
        
        // Handle revoke authorization
        if (isset($_POST['revoke_auth']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'gbr_settings-options')) {
            try {
                $oauthService = new OAuthService();
                $oauthService->revokeToken();
                add_settings_error('gbr_settings', 'revoke_success', __('Authorization revoked successfully!', 'google-business-reviews'), 'success');
            } catch (\Exception $e) {
                error_log('Revoke Error: ' . $e->getMessage());
                add_settings_error('gbr_settings', 'revoke_error', __('Error revoking authorization: ', 'google-business-reviews') . $e->getMessage());
            }
        }
        
        // Handle load locations button
        if (isset($_POST['load_locations']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'gbr_settings-options')) {
            try {
                $service = new GoogleBusinessService();
                $accountId = get_option('gbr_account_id', '');
                if ($accountId) {
                    $locations = $service->getLocations($accountId);
                    if (!empty($locations)) {
                        add_settings_error('gbr_settings', 'locations_loaded', sprintf(__('Successfully loaded %d location(s).', 'google-business-reviews'), count($locations)), 'success');
                    } else {
                        add_settings_error('gbr_settings', 'no_locations', __('No locations found for this account.', 'google-business-reviews'), 'warning');
                    }
                } else {
                    add_settings_error('gbr_settings', 'no_account', __('No account ID found. Please re-authorize.', 'google-business-reviews'));
                }
            } catch (\Exception $e) {
                error_log('Load locations error: ' . $e->getMessage());
                add_settings_error('gbr_settings', 'load_error', __('Error loading locations: ', 'google-business-reviews') . $e->getMessage());
            }
        }
        
        // Handle refresh locations button
        if (isset($_POST['refresh_locations']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'gbr_settings-options')) {
            try {
                $service = new GoogleBusinessService();
                $accountId = get_option('gbr_account_id', '');
                if ($accountId) {
                    $locations = $service->getLocations($accountId);
                    add_settings_error('gbr_settings', 'locations_refreshed', sprintf(__('Refreshed locations: %d found.', 'google-business-reviews'), count($locations)), 'success');
                } else {
                    add_settings_error('gbr_settings', 'no_account', __('No account ID found. Please re-authorize.', 'google-business-reviews'));
                }
            } catch (\Exception $e) {
                error_log('Refresh locations error: ' . $e->getMessage());
                add_settings_error('gbr_settings', 'refresh_error', __('Error refreshing locations: ', 'google-business-reviews') . $e->getMessage());
            }
        }
        
        // Handle regular form submission
        if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'gbr_settings-options')) {
            try {
                $clientId = sanitize_text_field(isset($_POST['gbr_client_id']) ? $_POST['gbr_client_id'] : '');
                $clientSecret = sanitize_text_field(isset($_POST['gbr_client_secret']) ? $_POST['gbr_client_secret'] : '');
                $locationId = sanitize_text_field(isset($_POST['gbr_location_id']) ? $_POST['gbr_location_id'] : '');
                
                error_log('Saving settings: Client ID = ' . $clientId . ', Location ID = ' . $locationId);
                
                update_option('gbr_client_id', $clientId);
                update_option('gbr_client_secret', $clientSecret);
                update_option('gbr_location_id', $locationId);
                
                add_settings_error('gbr_settings', 'settings_saved', __('Settings saved!', 'google-business-reviews'), 'success');
            } catch (\Exception $e) {
                error_log('Form submission error: ' . $e->getMessage());
                add_settings_error('gbr_settings', 'save_error', __('Error saving settings: ', 'google-business-reviews') . $e->getMessage());
            }
        }
    }
}