<?php

namespace GoogleBusinessReviews;

class CliCommand
{
    public function __invoke($args, $assoc_args)
    {
        try {
            $service = new GoogleBusinessService();
            
            // Update configuration if provided
            if (isset($assoc_args['access-token']) && isset($assoc_args['account-id']) && isset($assoc_args['location-id'])) {
                $service->setConfiguration($assoc_args['access-token'], $assoc_args['account-id'], $assoc_args['location-id']);
                \WP_CLI::success('Configuration updated.');
            }
            
            // Test connection
            \WP_CLI::log('Testing API connection...');
            if (!$service->testConnection()) {
                \WP_CLI::error('Connection failed. Please check your configuration.');
            }
            \WP_CLI::success('API connection successful.');
            
            // Download reviews
            \WP_CLI::log('Downloading reviews...');
            $reviews = $service->downloadAndSaveReviews();
            
            \WP_CLI::success(sprintf('Downloaded and saved %d reviews.', count($reviews)));
            \WP_CLI::log('Last update: ' . $service->getLastUpdateTime());
            
            // Display summary
            $this->displaySummary($reviews);
            
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }
    
    private function displaySummary($reviews)
    {
        if (empty($reviews)) {
            \WP_CLI::log('No reviews found.');
            return;
        }
        
        $ratings = array_column($reviews, 'rating');
        $averageRating = array_sum($ratings) / count($ratings);
        
        \WP_CLI::log('');
        \WP_CLI::log('--- Reviews Summary ---');
        \WP_CLI::log('Total reviews: ' . count($reviews));
        \WP_CLI::log('Average rating: ' . number_format($averageRating, 2) . '/5');
        
        // Show recent reviews
        $recentReviews = array_slice(
            array_reverse(array_sort($reviews, function($review) { return $review['time']; })),
            0,
            3
        );
        
        \WP_CLI::log('');
        \WP_CLI::log('--- Recent Reviews ---');
        foreach ($recentReviews as $review) {
            \WP_CLI::log(sprintf(
                '★%d - %s (%s)',
                $review['rating'],
                $review['author_name'],
                $review['relative_time_description']
            ));
            \WP_CLI::log('  ' . substr($review['text'], 0, 100) . (strlen($review['text']) > 100 ? '...' : ''));
            \WP_CLI::log('');
        }
    }
}