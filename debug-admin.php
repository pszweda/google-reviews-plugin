<?php
// Simple debug admin page
add_action('admin_menu', function() {
    add_options_page(
        'GBR Debug',
        'GBR Debug',
        'manage_options',
        'gbr-debug',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Debug Google Business Reviews</h1>';
            
            if (isset($_POST['submit'])) {
                echo '<div class="notice notice-success"><p>Form submitted successfully!</p></div>';
                echo '<pre>POST data: ' . print_r($_POST, true) . '</pre>';
                
                if (isset($_POST['client_id'])) {
                    update_option('gbr_debug_client_id', sanitize_text_field($_POST['client_id']));
                    echo '<p>Client ID saved: ' . get_option('gbr_debug_client_id') . '</p>';
                }
            }
            
            echo '<form method="post">';
            wp_nonce_field('gbr_debug');
            echo '<table class="form-table">';
            echo '<tr><th>Client ID</th><td><input type="text" name="client_id" value="' . esc_attr(get_option('gbr_debug_client_id', '')) . '" /></td></tr>';
            echo '</table>';
            submit_button();
            echo '</form>';
            echo '</div>';
        }
    );
});
?>