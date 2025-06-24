<?php

namespace GoogleBusinessReviews;

class GoogleBusinessService
{
    private $accessToken;
    private $locationId;
    private $accountId;
    
    public function __construct()
    {
        $this->accessToken = get_option('gbr_access_token', '');
        $this->locationId = get_option('gbr_location_id', '');
        $this->accountId = get_option('gbr_account_id', '');
    }
    
    public function downloadReviews()
    {
        if (empty($this->accessToken) || empty($this->locationId) || empty($this->accountId)) {
            throw new \Exception(__('Access Token, Location ID or Account ID not configured', 'google-business-reviews'));
        }
        
        // Ensure token is valid before making API call
        $oauthService = new OAuthService();
        $oauthService->ensureValidToken();
        
        // Refresh access token in case it was updated
        $this->accessToken = get_option('gbr_access_token', '');
        
        $allReviews = [];
        $pageToken = null;
        $pageSize = 50; // Maximum allowed by API
        
        do {
            // Use Google Business Profile API for reviews
            $url = 'https://mybusiness.googleapis.com/v4/accounts/' . $this->accountId
                   . '/locations/' . $this->locationId . '/reviews';
            $params = [
                'pageSize' => $pageSize,
                "orderBy" => "updateTime desc"
            ];
            
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }
            
            $response = wp_remote_get($url . '?' . http_build_query($params), [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $responseCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Check for API errors
            if ($responseCode !== 200) {
                if (isset($data['error'])) {
                    throw new \Exception('Google My Business API Error: ' . $data['error']['message']);
                } else {
                    throw new \Exception('Google My Business API Error: HTTP ' . $responseCode);
                }
            }
            
            // Add reviews from this page
            if (isset($data['reviews'])) {
                $allReviews = array_merge($allReviews, $data['reviews']);
            }
            
            // Get next page token
            $pageToken = $data['nextPageToken'] ?? null;
            
            // Add delay between requests
            if ($pageToken) {
                sleep(1);
            }
            
        } while ($pageToken);

        return $this->formatReviews($allReviews);
    }
    
    private function formatReviews($reviews)
    {
        return array_map(function($review) {
            // Convert Google My Business API format to our format
            $createTime = isset($review['createTime']) ? strtotime($review['createTime']) : 0;
            $updateTime = isset($review['updateTime']) ? strtotime($review['updateTime']) : 0;
            
            return [
                'review_id' => $review['name'] ?? '',
                'author_name' => $review['reviewer']['displayName'] ?? '',
                'author_url' => $review['reviewer']['profilePhotoUrl'] ?? '',
                'profile_photo_url' => $review['reviewer']['profilePhotoUrl'] ?? '',
                'rating' => isset($review['starRating']) ? (int)$review['starRating'] : 0,
                'text' => $review['comment'] ?? '',
                'time' => $createTime,
                'update_time' => $updateTime,
                'formatted_time' => $createTime ? date('Y-m-d H:i:s', $createTime) : '',
                'formatted_update_time' => $updateTime ? date('Y-m-d H:i:s', $updateTime) : '',
                'relative_time_description' => $this->getRelativeTime($createTime),
                'language' => 'pl',
                'review_reply' => isset($review['reviewReply']) ? [
                    'comment' => $review['reviewReply']['comment'] ?? '',
                    'update_time' => isset($review['reviewReply']['updateTime']) ? $review['reviewReply']['updateTime'] : ''
                ] : null
            ];
        }, $reviews);
    }
    
    private function getRelativeTime($timestamp)
    {
        if (!$timestamp) return '';
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'właśnie teraz';
        if ($diff < 3600) return floor($diff / 60) . ' minut temu';
        if ($diff < 86400) return floor($diff / 3600) . ' godzin temu';
        if ($diff < 2592000) return floor($diff / 86400) . ' dni temu';
        if ($diff < 31536000) return floor($diff / 2592000) . ' miesięcy temu';
        
        return floor($diff / 31536000) . ' lat temu';
    }
    
    public function saveReviews($reviews)
    {
        update_option('gbr_reviews', $reviews);
        update_option('gbr_last_update', current_time('mysql'));
        return true;
    }
    
    public function getCachedReviews()
    {
        return get_option('gbr_reviews', []);
    }
    
    public function getLastUpdateTime()
    {
        return get_option('gbr_last_update', '');
    }
    
    public function downloadAndSaveReviews()
    {
        $reviews = $this->downloadReviews();
        $this->saveReviews($reviews);
        return $reviews;
    }
    
    public function setConfiguration($accessToken, $accountId, $locationId)
    {
        update_option('gbr_access_token', $accessToken);
        update_option('gbr_account_id', $accountId);
        update_option('gbr_location_id', $locationId);
        $this->accessToken = $accessToken;
        $this->accountId = $accountId;
        $this->locationId = $locationId;
    }
    
    public function testConnection()
    {
        try {
            $this->downloadReviews();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getAccounts()
    {
        if (empty($this->accessToken)) {
            throw new \Exception(__('Access Token not configured', 'google-business-reviews'));
        }
        
        // Ensure token is valid before making API call
        $oauthService = new OAuthService();
        $oauthService->ensureValidToken();
        
        // Refresh access token in case it was updated
        $this->accessToken = get_option('gbr_access_token', '');
        
        // Use the Business Profile API v1
        $url = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $responseCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($responseCode !== 200) {
            error_log('API Response Code: ' . $responseCode);
            error_log('API Response Body: ' . $body);
            throw new \Exception('Google Business API Error: ' . ($data['error']['message'] ?? 'HTTP ' . $responseCode));
        }
        
        return $data['accounts'] ?? [];
    }
    
    public function getLocations($accountId)
    {
        if (empty($this->accessToken) || empty($accountId)) {
            throw new \Exception(__('Access Token or Account ID not configured', 'google-business-reviews'));
        }
        
        // Ensure token is valid before making API call
        $oauthService = new OAuthService();
        $oauthService->ensureValidToken();
        
        // Refresh access token in case it was updated
        $this->accessToken = get_option('gbr_access_token', '');
        
        $url = 'https://mybusinessbusinessinformation.googleapis.com/v1/accounts/' . $accountId . '/locations?read_mask=name,title';
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $responseCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($responseCode !== 200) {
            throw new \Exception('Google My Business API Error: ' . ($data['error']['message'] ?? 'HTTP ' . $responseCode));
        }
        
        return $data['locations'] ?? [];
    }
}