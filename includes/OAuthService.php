<?php

namespace GoogleBusinessReviews;

class OAuthService
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scope = 'https://www.googleapis.com/auth/business.manage';
    
    public function __construct()
    {
        $this->clientId = get_option('gbr_client_id', '');
        $this->clientSecret = get_option('gbr_client_secret', '');
        $this->redirectUri = admin_url('options-general.php?page=google-business-reviews&oauth_callback=1');
		//https://prestige.ddev.site/
    }
    
    public function getAuthorizationUrl()
    {
        if (empty($this->clientId)) {
            throw new \Exception(__('Client ID not configured', 'google-business-reviews'));
        }
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->scope,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('gbr_oauth_state')
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    public function handleCallback($code, $state)
    {
        // Verify state parameter
        if (!wp_verify_nonce($state, 'gbr_oauth_state')) {
            throw new \Exception(__('Invalid state parameter', 'google-business-reviews'));
        }
        
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception(__('OAuth credentials not configured', 'google-business-reviews'));
        }
        
        // Exchange authorization code for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('OAuth Error: ' . $data['error_description']);
        }
        
        // Store tokens and token expiry time
        update_option('gbr_access_token', $data['access_token']);
        if (isset($data['refresh_token'])) {
            update_option('gbr_refresh_token', $data['refresh_token']);
        }
        
        // Store token expiry time (default to 1 hour if not provided)
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        update_option('gbr_token_expires_at', time() + $expiresIn);
        
        error_log('OAuth tokens stored successfully. Access token: ' . substr($data['access_token'], 0, 20) . '...');
        
        // Get account information (but don't fail if this doesn't work)
        $accounts = [];
        try {
            // Wait a moment for token to propagate
            sleep(1);
            
            $service = new GoogleBusinessService();
            $accounts = $service->getAccounts();
            
            error_log('Found ' . count($accounts) . ' accounts');
            
            if (!empty($accounts)) {
                // Auto-select first account
                $firstAccount = $accounts[0];
                $accountId = $this->extractIdFromName($firstAccount['name']);
                update_option('gbr_account_id', $accountId);
                
                error_log('Set account ID: ' . $accountId);
                
                // Try to get locations for this account (optional)
                try {
                    $locations = $service->getLocations($accountId);
                    if (!empty($locations)) {
                        // Auto-select first location
                        $firstLocation = $locations[0];
                        $locationId = $this->extractIdFromName($firstLocation['name']);
                        update_option('gbr_location_id', $locationId);
                        
                        error_log('Set location ID: ' . $locationId);
                    }
                } catch (\Exception $e) {
                    error_log('Could not fetch locations: ' . $e->getMessage());
                    // Location fetch failed, but that's ok
                }
            }
        } catch (\Exception $e) {
            error_log('Could not fetch accounts immediately after OAuth: ' . $e->getMessage());
            // Account fetch failed, but we have the token - this is OK
        }
        
        return [
            'access_token' => $data['access_token'],
            'accounts' => $accounts
        ];
    }
    
    public function refreshToken()
    {
        $refreshToken = get_option('gbr_refresh_token', '');
        
        if (empty($refreshToken) || empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception(__('Refresh token or OAuth credentials not available', 'google-business-reviews'));
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token'
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('Token Refresh Error: ' . $data['error_description']);
        }
        
        // Update access token and expiry time
        update_option('gbr_access_token', $data['access_token']);
        
        // Update token expiry time (default to 1 hour if not provided)
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        update_option('gbr_token_expires_at', time() + $expiresIn);
        
        return $data['access_token'];
    }
    
    public function revokeToken()
    {
        $accessToken = get_option('gbr_access_token', '');
        
        if (empty($accessToken)) {
            return true; // Already revoked
        }
        
        wp_remote_post('https://oauth2.googleapis.com/revoke', [
            'body' => [
                'token' => $accessToken
            ]
        ]);
        
        // Clear stored tokens
        delete_option('gbr_access_token');
        delete_option('gbr_refresh_token');
        delete_option('gbr_account_id');
        delete_option('gbr_location_id');
        
        return true;
    }
    
    public function isAuthorized()
    {
        $accessToken = get_option('gbr_access_token', '');
        return !empty($accessToken);
    }
    
    public function isTokenExpired()
    {
        $expiresAt = get_option('gbr_token_expires_at', 0);
        return time() >= $expiresAt;
    }
    
    public function ensureValidToken()
    {
        if (!$this->isAuthorized()) {
            throw new \Exception(__('Not authorized', 'google-business-reviews'));
        }
        
        if ($this->isTokenExpired()) {
            error_log('Token expired, attempting refresh...');
            try {
                $this->refreshToken();
                error_log('Token refreshed successfully');
            } catch (\Exception $e) {
                error_log('Token refresh failed: ' . $e->getMessage());
                throw new \Exception(__('Token expired and refresh failed. Please re-authorize.', 'google-business-reviews'));
            }
        }
        
        return true;
    }
    
    private function extractIdFromName($name)
    {
        // Extract ID from resource name like "accounts/123" or "accounts/123/locations/456"
        $parts = explode('/', $name);
        return end($parts);
    }
    
    public function setCredentials($clientId, $clientSecret)
    {
        update_option('gbr_client_id', $clientId);
        update_option('gbr_client_secret', $clientSecret);
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }
}