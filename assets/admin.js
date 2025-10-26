(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle manual sync button
        $('button[name="ffp_sync_now"]').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            
            $button.prop('disabled', true).addClass('is-loading');
            $spinner.show();
            
            $.ajax({
                url: ffpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_sync_now',
                    nonce: ffpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Sync started! This may take a moment. Check the status panel below.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('is-loading');
                    $spinner.hide();
                }
            });
        });
    });
})(jQuery);

