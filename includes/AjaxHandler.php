<?php

namespace GoogleBusinessReviews;

class AjaxHandler
{
    public function __construct()
    {
        add_action('wp_ajax_gbr_download_reviews', [$this, 'downloadReviews']);
        add_action('wp_ajax_gbr_test_connection', [$this, 'testConnection']);
    }
    
    public function downloadReviews()
    {
        check_ajax_referer('gbr_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'google-business-reviews'));
        }
        
        try {
            $service = new GoogleBusinessService();
            $reviews = $service->downloadAndSaveReviews();
            
            wp_send_json_success([
                'message' => sprintf(__('Downloaded %d reviews successfully!', 'google-business-reviews'), count($reviews)),
                'count' => count($reviews),
                'last_update' => $service->getLastUpdateTime()
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function testConnection()
    {
        check_ajax_referer('gbr_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'google-business-reviews'));
        }
        
        $service = new GoogleBusinessService();
        
        if ($service->testConnection()) {
            wp_send_json_success([
                'message' => __('Connection successful!', 'google-business-reviews')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Connection failed. Please check your credentials.', 'google-business-reviews')
            ]);
        }
    }
}