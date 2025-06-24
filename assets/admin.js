jQuery(document).ready(function($) {
    const $status = $('#gbr-status');
    
    function showMessage(message, type = 'info') {
        $status.html('<div class="notice notice-' + type + '"><p>' + message + '</p></div>');
        setTimeout(() => {
            $status.find('.notice').fadeOut();
        }, 5000);
    }
    
    $('#gbr-test-connection').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: gbrAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'gbr_test_connection',
                nonce: gbrAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Connection error', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    $('#gbr-download-reviews').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text(gbrAjax.strings.downloading);
        showMessage(gbrAjax.strings.downloading, 'info');
        
        $.ajax({
            url: gbrAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'gbr_download_reviews',
                nonce: gbrAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Reload page to show updated reviews
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(gbrAjax.strings.error + ' ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(gbrAjax.strings.error + ' Connection error', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});