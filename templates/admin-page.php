<div class="wrap">
    <h1><?php echo esc_html__('Google Business Reviews', 'google-business-reviews'); ?></h1>
    
    <?php settings_errors('gbr_settings'); ?>
    
    <form method="post" action="">
        <?php settings_fields('gbr_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('Client ID', 'google-business-reviews'); ?></th>
                <td>
                    <input type="text" name="gbr_client_id" value="<?php echo esc_attr($clientId); ?>" class="regular-text" />
                    <p class="description">
                        <?php printf(
                            __('OAuth2 Client ID from <a href="%s" target="_blank">Google Cloud Console</a>. Create OAuth2 credentials, not API key.', 'google-business-reviews'),
                            'https://console.cloud.google.com/apis/credentials'
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Client Secret', 'google-business-reviews'); ?></th>
                <td>
                    <input type="text" name="gbr_client_secret" value="<?php echo esc_attr($clientSecret); ?>" class="regular-text" />
                    <p class="description">
                        <?php echo esc_html__('OAuth2 Client Secret from Google Cloud Console.', 'google-business-reviews'); ?>
                    </p>
                </td>
            </tr>
            
            <?php if (!empty($accessToken)): ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Authorization Status', 'google-business-reviews'); ?></th>
                <td>
                    <p style="color: #4CAF50; font-weight: bold;">✓ <?php echo esc_html__('Authorized', 'google-business-reviews'); ?></p>
                    <p class="description">
                        <?php if (!empty($accountId)): ?>
                            <?php printf(__('Account ID: %s', 'google-business-reviews'), esc_html($accountId)); ?>
                        <?php endif; ?>
                    </p>
                    <button type="submit" name="revoke_auth" class="button button-secondary" style="margin-top: 5px;">
                        <?php echo esc_html__('Revoke Authorization', 'google-business-reviews'); ?>
                    </button>
                </td>
            </tr>
            <?php elseif (!empty($authUrl)): ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Authorization', 'google-business-reviews'); ?></th>
                <td>
                    <a href="<?php echo esc_url($authUrl); ?>" class="button button-primary">
                        <?php echo esc_html__('Authorize with Google', 'google-business-reviews'); ?>
                    </a>
                    <p class="description">
                        <?php echo esc_html__('Click to authorize this plugin to access your Google My Business account. Your Account ID will be automatically configured.', 'google-business-reviews'); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if (!empty($accounts)): ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Available Accounts', 'google-business-reviews'); ?></th>
                <td>
                    <ul style="margin: 0;">
                        <?php foreach ($accounts as $account): ?>
                            <li>
                                <strong><?php echo esc_html($account['accountName'] ?? 'Unnamed Account'); ?></strong>
                                <code><?php echo esc_html($account['name']); ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if (!empty($accessToken)): ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Business Location', 'google-business-reviews'); ?></th>
                <td>
                    <?php if (!empty($locations)): ?>
                        <select name="gbr_location_id" class="regular-text" style="width: 400px;">
                            <option value=""><?php echo esc_html__('Select a location...', 'google-business-reviews'); ?></option>
                            <?php foreach ($locations as $location): ?>
                                <?php 
                                $locId = explode('/', $location['name']);
                                $locId = end($locId);
                                $locationName = $location['locationName'] ?? $location['name'];
                                $address = '';
                                
                                // Build address string
                                if (isset($location['address'])) {
                                    $addressParts = [];
                                    if (!empty($location['address']['addressLines'])) {
                                        $addressParts[] = implode(', ', $location['address']['addressLines']);
                                    }
                                    if (!empty($location['address']['locality'])) {
                                        $addressParts[] = $location['address']['locality'];
                                    }
                                    if (!empty($location['address']['administrativeArea'])) {
                                        $addressParts[] = $location['address']['administrativeArea'];
                                    }
                                    $address = implode(', ', $addressParts);
                                }
                                
                                $displayText = $locationName;
                                if ($address) {
                                    $displayText .= ' - ' . $address;
                                }
                                ?>
                                <option value="<?php echo esc_attr($locId); ?>" <?php selected($locationId, $locId); ?>>
                                    <?php echo esc_html($displayText); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="refresh_locations" class="button button-secondary" style="margin-left: 10px;">
                            <?php echo esc_html__('Refresh Locations', 'google-business-reviews'); ?>
                        </button>
                        <p class="description">
                            <?php printf(__('Found %d location(s). Select the business location to download reviews from.', 'google-business-reviews'), count($locations)); ?>
                        </p>
                        
                        <?php if (!empty($locationId)): ?>
                            <?php
                            $selectedLocation = null;
                            foreach ($locations as $location) {
                                $locId = explode('/', $location['name']);
                                $locId = end($locId);
                                if ($locId === $locationId) {
                                    $selectedLocation = $location;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if ($selectedLocation): ?>
                            <div style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                <strong><?php echo esc_html__('Selected Location Details:', 'google-business-reviews'); ?></strong><br>
                                <strong><?php echo esc_html($selectedLocation['locationName'] ?? 'Unnamed Location'); ?></strong><br>
                                <?php if (isset($selectedLocation['address'])): ?>
                                    <?php
                                    $address = $selectedLocation['address'];
                                    if (!empty($address['addressLines'])) {
                                        echo esc_html(implode(', ', $address['addressLines'])) . '<br>';
                                    }
                                    $cityState = [];
                                    if (!empty($address['locality'])) {
                                        $cityState[] = $address['locality'];
                                    }
                                    if (!empty($address['administrativeArea'])) {
                                        $cityState[] = $address['administrativeArea'];
                                    }
                                    if (!empty($address['postalCode'])) {
                                        $cityState[] = $address['postalCode'];
                                    }
                                    if ($cityState) {
                                        echo esc_html(implode(', ', $cityState)) . '<br>';
                                    }
                                    if (!empty($address['regionCode'])) {
                                        echo esc_html($address['regionCode']) . '<br>';
                                    }
                                    ?>
                                <?php endif; ?>
                                <small><?php printf(__('Location ID: %s', 'google-business-reviews'), esc_html($locationId)); ?></small>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div style="margin-bottom: 10px;">
                            <button type="submit" name="load_locations" class="button button-primary">
                                <?php echo esc_html__('Load Locations', 'google-business-reviews'); ?>
                            </button>
                            <span style="margin-left: 10px; color: #666;">
                                <?php echo esc_html__('Click to load available business locations from your Google My Business account.', 'google-business-reviews'); ?>
                            </span>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <label for="manual_location_id"><?php echo esc_html__('Or enter Location ID manually:', 'google-business-reviews'); ?></label><br>
                            <input type="text" id="manual_location_id" name="gbr_location_id" value="<?php echo esc_attr($locationId); ?>" class="regular-text" placeholder="<?php echo esc_attr__('Location ID', 'google-business-reviews'); ?>" />
                            <p class="description">
                                <?php echo esc_html__('Enter your business Location ID manually if auto-loading fails.', 'google-business-reviews'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php submit_button(__('Save Configuration', 'google-business-reviews')); ?>
    </form>
    
    <hr>
    
    <h2><?php echo esc_html__('Download Reviews', 'google-business-reviews'); ?></h2>
    
    <?php if (!empty($locationId) && !empty($accessToken)): ?>
        <div id="gbr-actions">
            <button type="button" id="gbr-test-connection" class="button">
                <?php echo esc_html__('Test Connection', 'google-business-reviews'); ?>
            </button>
            <button type="button" id="gbr-download-reviews" class="button button-primary">
                <?php echo esc_html__('Download Reviews Now', 'google-business-reviews'); ?>
            </button>
        </div>
        
        <div id="gbr-status" style="margin: 10px 0;"></div>
        
        <?php if ($lastUpdate): ?>
            <p><strong><?php echo esc_html__('Last Update:', 'google-business-reviews'); ?></strong> <?php echo esc_html($lastUpdate); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($reviews)): ?>
            <h3><?php printf(__('Cached Reviews (%d total)', 'google-business-reviews'), count($reviews)); ?></h3>
            
            <?php
            $ratings = array_column($reviews, 'rating');
            $averageRating = array_sum($ratings) / count($ratings);
            ?>
            
            <p><strong><?php echo esc_html__('Average Rating:', 'google-business-reviews'); ?></strong> <?php echo number_format($averageRating, 2); ?>/5</p>
            
            <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                <?php foreach (array_slice($reviews, 0, 10) as $review): ?>
                    <div style="border-bottom: 1px solid #eee; padding: 10px 0;">
                        <strong><?php echo esc_html($review['author_name']); ?></strong>
                        <span style="color: #ffa500;">
                            <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                        </span>
                        <small style="color: #666;">(<?php echo esc_html($review['relative_time_description']); ?>)</small>
                        <p><?php echo esc_html(substr($review['text'], 0, 200)) . (strlen($review['text']) > 200 ? '...' : ''); ?></p>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($reviews) > 10): ?>
                    <p><em><?php printf(__('Showing 10 of %d reviews...', 'google-business-reviews'), count($reviews)); ?></em></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <p style="color: #d63384;">
            <?php if (empty($accessToken)): ?>
                <?php echo esc_html__('Please configure OAuth credentials and authorize the application first.', 'google-business-reviews'); ?>
            <?php else: ?>
                <?php echo esc_html__('Please select a location first.', 'google-business-reviews'); ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    
    <hr>
    
    <h2><?php echo esc_html__('WP-CLI Usage', 'google-business-reviews'); ?></h2>
    <p><?php echo esc_html__('You can also download reviews using WP-CLI:', 'google-business-reviews'); ?></p>
    <code>wp google-reviews --access-token=YOUR_TOKEN --account-id=YOUR_ACCOUNT_ID --location-id=YOUR_LOCATION_ID</code>
    
    <h2><?php echo esc_html__('Setup Instructions', 'google-business-reviews'); ?></h2>
    <ol>
        <li><?php printf(__('Go to <a href="%s" target="_blank">Google Cloud Console</a>', 'google-business-reviews'), 'https://console.cloud.google.com/'); ?></li>
        <li><?php echo esc_html__('Create a new project or select an existing one', 'google-business-reviews'); ?></li>
        <li><?php echo esc_html__('Enable the <strong>My Business Account Management API</strong> and <strong>My Business Business Information API</strong>', 'google-business-reviews'); ?></li>
        <li><?php echo esc_html__('Create OAuth2 credentials (Web application type)', 'google-business-reviews'); ?></li>
        <li><?php printf(__('Add authorized redirect URI: <code>%s</code>', 'google-business-reviews'), admin_url('options-general.php?page=google-business-reviews&oauth_callback=1')); ?></li>
        <li><?php echo esc_html__('Copy Client ID and Client Secret to the fields above', 'google-business-reviews'); ?></li>
        <li><?php echo esc_html__('Save configuration and click "Authorize with Google"', 'google-business-reviews'); ?></li>
        <li><?php echo esc_html__('Select your business location from the dropdown', 'google-business-reviews'); ?></li>
    </ol>
    
    <div class="notice notice-info">
        <p><strong><?php echo esc_html__('Important:', 'google-business-reviews'); ?></strong> <?php echo esc_html__('You must be the owner or manager of the Google My Business listing to access reviews via the API.', 'google-business-reviews'); ?></p>
    </div>
</div>