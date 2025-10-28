(function($) {
    'use strict';
    
    var progressInterval = null;
    var pollingStartTime = null;
    
    $(document).ready(function() {
        // Handle manual sync button click
        $('#ffp_sync_now, button[name="ffp_sync_now"]').on('click', function(e) {
            e.preventDefault();
            
            console.log('Sync button clicked');
            
            var $button = $(this);
            
            handleSyncClick($button);
        });
        
        function handleSyncClick($button) {
            // Check if jQuery and ffpAdmin are loaded
            if (typeof ffpAdmin === 'undefined') {
                alert('Error: Admin script not loaded properly. Please refresh the page.');
                console.error('ffpAdmin is not defined');
                return;
            }
            
            var $spinner = $button.next('.spinner');
            var $progressContainer = $('#ffp-progress-container');
            
            // Create or show progress container
            if (!$progressContainer.length) {
                // Create progress container
                $progressContainer = $('<div id="ffp-progress-container" class="ffp-progress-wrapper">' +
                    '<div class="ffp-progress-header">' +
                    '<h3>Sync Progress</h3>' +
                    '</div>' +
                    '<div class="ffp-progress-bar-container">' +
                    '<div class="ffp-progress-bar">' +
                    '<div class="ffp-progress-bar-fill" style="width: 0%"></div>' +
                    '</div>' +
                    '<div class="ffp-progress-text">0%</div>' +
                    '</div>' +
                    '<div class="ffp-progress-status">Starting...</div>' +
                    '</div>');
                
                // Insert after Sync Controls heading
                $('h2').filter(function() {
                    return $(this).text() === 'Sync Controls';
                }).after($progressContainer);
                
                console.log('Progress container created');
            }
            
            // Force show with inline style to override CSS
            $progressContainer.css('display', 'block');
            console.log('Progress container shown', $progressContainer.length, 'containers found');
            console.log('Progress container HTML:', $progressContainer[0]);
            
            $button.prop('disabled', true).addClass('is-loading');
            $spinner.show();
            
            console.log('Starting sync with AJAX...');
            
            $.ajax({
                url: ffpAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_sync_now',
                    nonce: ffpAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Start polling for progress
                        startProgressPolling();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).removeClass('is-loading');
                        $spinner.hide();
                        if ($progressContainer.length) {
                            $progressContainer.hide();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    alert('An error occurred: ' + error + '. Please check the browser console for details.');
                    $button.prop('disabled', false).removeClass('is-loading');
                    $spinner.hide();
                    if ($progressContainer.length) {
                        $progressContainer.hide();
                    }
                }
            });
        }
        
        // Function to start polling for progress
        function startProgressPolling() {
            console.log('Progress polling function called');
            pollingStartTime = Date.now();
            
            if (progressInterval) {
                clearInterval(progressInterval);
            }
            
            progressInterval = setInterval(function() {
                console.log('Polling for progress...');
                $.ajax({
                    url: ffpAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ffp_get_progress',
                        nonce: ffpAdmin.nonce
                    },
                    success: function(response) {
                        console.log('Progress poll response:', response);
                        if (response.success && response.data) {
                            console.log('Progress data:', response.data.percentage + '% - ' + response.data.status);
                            updateProgressDisplay(response.data);
                            
                            // Check for timeout (5 minutes)
                            // var elapsed = Date.now() - pollingStartTime;
                            // if (elapsed > 300000) { // 5 minutes
                            //     clearInterval(progressInterval);
                            //     console.error('Sync timeout after 5 minutes');
                            //     alert('Sync timed out after 5 minutes. Please try again.');
                            //     return;
                            // }
                            
                            // If sync is complete successfully, stop polling and reload
                            if (response.data.percentage >= 100 && !response.data.in_progress) {
                                clearInterval(progressInterval);
                                console.log('Sync complete, reloading in 2 seconds...');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                            // If sync had an error (0% and not in progress)
                            else if (response.data.percentage === 0 && !response.data.in_progress && response.data.status.toLowerCase().includes('error')) {
                                clearInterval(progressInterval);
                                console.error('Sync failed:', response.data.status);
                            }
                        }
                    },
                    error: function() {
                        console.error('Error fetching progress');
                    }
                });
            }, 1000); // Poll every second
        }
        
        // Function to update progress display
        function updateProgressDisplay(data) {
            console.log('Updating progress display with data:', data);
            var $progressContainer = $('#ffp-progress-container');
            
            if (!$progressContainer.length) {
                console.warn('Progress container not found, cannot update');
                return;
            }
            
            // Update progress bar
            $progressContainer.find('.ffp-progress-bar-fill').css('width', data.percentage + '%');
            $progressContainer.find('.ffp-progress-text').text(Math.round(data.percentage) + '%');
            $progressContainer.find('.ffp-progress-status').text(data.status);
            
            // Show/hide based on progress
            if (data.percentage > 0 || data.in_progress) {
                $progressContainer.show();
            }
        }
    });
})(jQuery);

