<?php
  /**
   * Sync functionality
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  class FFP_Sync {
    
    public function __construct() {
      add_action( 'ffp_run_sync', [ $this, 'run_sync' ] );
      add_action( 'ffp_daily_sync', [ $this, 'run_sync' ] );
      add_action( 'ffp_run_sync_fallback', [ $this, 'run_sync_fallback' ] );
      add_action( 'wp_ajax_ffp_sync_now', [ $this, 'handle_manual_sync' ] );
      add_action( 'wp_ajax_ffp_sync_inline', [ $this, 'handle_manual_sync_inline' ] );
      add_action( 'wp_ajax_ffp_get_progress', [ $this, 'get_progress' ] );
    }
    
    /**
     * Fallback sync method if cron doesn't work
     */
    public function run_sync_fallback() {
      // Check if sync is already running or completed
      $progress = get_option( 'ffp_sync_progress', [
        'percentage'  => 0,
        'status'      => 'Not started',
        'in_progress' => false,
        'updated_at'  => 0,
      ] );
      
      $age = time() - intval( $progress['updated_at'] );
      
      // Only run if sync hasn't started, not in progress, or appears stuck > 60s
      if ( ! $progress['in_progress'] || $progress['percentage'] === 0 || $age > 60 ) {
        FFP_Logger::log( 'Running sync fallback - reason: ' . ( ! $progress['in_progress'] ? 'idle' : ( $progress['percentage'] === 0 ? 'not started' : 'stalled ' . $age . 's' ) ), 'warning' );
        $this->run_sync();
      } else {
        FFP_Logger::log( 'Fallback check: sync is already running (' . $progress['percentage'] . '%), last update ' . $age . 's ago', 'info' );
      }
    }
    
    /**
     * Main sync method
     */
    public function run_sync() {
      // Improve resilience to timeouts
      if ( function_exists( 'ignore_user_abort' ) ) {
        ignore_user_abort( true );
      }
      if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 300 );
      }
      // Check mutex to avoid overlapping syncs
      //        if (get_transient('ffp_sync_lock')) {
      //            FFP_Logger::log('Sync already in progress, skipping', 'warning');
      //            return;
      //        }
      
      // Set mutex for 15 minutes
      set_transient( 'ffp_sync_lock', time(), 15 * 60 );
      
      // Initialize progress tracking
      $this->update_progress( 0, 'Starting sync...' );
      
      try {
        $stats = [
          'created'     => 0,
          'updated'     => 0,
          'deactivated' => 0,
          'errors'      => 0,
        ];
        
        // Fetch listings
        $this->update_progress( 10, 'Fetching listings from AppFolio...' );
        $url      = get_option( 'ffp_list_url', 'https://cityblockprop.appfolio.com/listings' );
        $response = $this->fetch_listings( $url );
        
        if ( is_wp_error( $response ) ) {
          FFP_Logger::log( 'Failed to fetch listings: ' . $response->get_error_message(), 'error' );
          $stats['errors'] ++;
          FFP_Logger::update_stats( $stats );
          $this->update_progress( 0, 'Failed to fetch listings: ' . $response->get_error_message(), false );
          delete_transient( 'ffp_sync_lock' );
          
          return;
        }
        
        // Parse listings
        $this->update_progress( 20, 'Parsing listings...' );
        $building_filter = get_option( 'ffp_building_filter', 'Farmer\'s Exchange 580 E Broad St.' );
        $parser          = new FFP_Parser( $building_filter );
        $listings        = $parser->parse( $response );
        
        if ( empty( $listings ) ) {
          FFP_Logger::log( 'No listings found after parsing. Check HTML structure or selectors.', 'warning' );
        }
        
        // Get current source IDs
        $this->update_progress( 30, 'Checking existing listings...' );
        $current_source_ids = $this->get_all_source_ids();
        
        // Process each listing
        $total_listings = count( $listings );
        $processed      = 0;
        foreach ( $listings as $listing ) {
          $processed ++;
          $progress = 30 + intval( ( $processed / max( $total_listings, 1 ) ) * 50 );
          $this->update_progress( $progress, "Processing {$processed} of {$total_listings} listings..." );
          
          $result = $this->upsert_listing( $listing );
          
          if ( $result === 'created' ) {
            $stats['created'] ++;
          } elseif ( $result === 'updated' ) {
            $stats['updated'] ++;
          } else {
            $stats['errors'] ++;
          }
          
          // Remove from current IDs (those remaining will be deactivated)
          unset( $current_source_ids[ $listing['source_id'] ] );
        }
        
        // Deactivate stale listings
        $this->update_progress( 85, 'Deactivating stale listings...' );
        $total_stale       = count( $current_source_ids );
        $deactivated_count = 0;
        foreach ( $current_source_ids as $source_id => $post_id ) {
          update_post_meta( $post_id, '_ffp_active', '0' );
          wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
          $stats['deactivated'] ++;
          $deactivated_count ++;
          
          if ( $total_stale > 0 ) {
            $progress = 85 + intval( ( $deactivated_count / $total_stale ) * 10 );
            $this->update_progress( $progress, "Deactivating {$deactivated_count} of {$total_stale} stale listings..." );
          }
        }
        
        // Update stats
        $this->update_progress( 95, 'Saving results...' );
        $stats['last_run'] = current_time( 'mysql' );
        FFP_Logger::update_stats( $stats );
        FFP_Logger::log( 'Sync completed: ' . $stats['created'] . ' created, ' . $stats['updated'] . ' updated, ' . $stats['deactivated'] . ' deactivated', 'info' );
        
        $this->update_progress( 100, 'Sync completed successfully!', false );
        
      } catch ( Exception $e ) {
        FFP_Logger::log( 'Sync error: ' . $e->getMessage(), 'error' );
        $this->update_progress( 0, 'Error: ' . $e->getMessage(), false );
      } finally {
        delete_transient( 'ffp_sync_lock' );
      }
    }
    
    /**
     * Update sync progress
     */
    private function update_progress( $percentage, $status, $in_progress = true ) {
      $progress_data = [
        'percentage'  => $percentage,
        'status'      => $status,
        'in_progress' => $in_progress,
        'updated_at'  => time(),
      ];
      update_option( 'ffp_sync_progress', $progress_data );
      
      // Log progress updates for debugging
      FFP_Logger::log( "Progress: {$percentage}% - {$status}", 'info' );
    }
    
    /**
     * Fetch listings from AppFolio
     */
    private function fetch_listings( $url ) {
      FFP_Logger::log( "Fetching from: {$url}", 'info' );
      
      $response = wp_remote_get( $url, [
        'timeout'   => 30,
        'headers'   => [
          'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
          'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
          'Accept-Language' => 'en-US,en;q=0.5',
        ],
        'sslverify' => true,
      ] );
      
      if ( is_wp_error( $response ) ) {
        FFP_Logger::log( "First fetch error: " . $response->get_error_message(), 'warning' );
        // Retry once
        $response = wp_remote_get( $url, [
          'timeout'   => 30,
          'headers'   => [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
          ],
          'sslverify' => true,
        ] );
      }
      
      if ( is_wp_error( $response ) ) {
        FFP_Logger::log( "Retry fetch error: " . $response->get_error_message(), 'error' );
        
        return $response;
      }
      
      $body = wp_remote_retrieve_body( $response );
      $code = wp_remote_retrieve_response_code( $response );
      
      FFP_Logger::log( "Fetched {$code} response, body length: " . strlen( $body ), 'info' );
      
      if ( $code !== 200 ) {
        return new WP_Error( 'http_error', 'HTTP ' . $code );
      }
      
      // Check if page appears to be empty or error page
      if ( strlen( $body ) < 1000 ) {
        FFP_Logger::log( "Response body is suspiciously short (" . strlen( $body ) . " bytes)", 'warning' );
      }
      
      return $body;
    }
    
    /**
     * Get all current source IDs
     */
    private function get_all_source_ids() {
      global $wpdb;
      
      $results = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ffp_source_id'",
        OBJECT_K
      );
      
      $source_ids = [];
      foreach ( $results as $row ) {
        $source_ids[ $row->meta_value ] = $row->post_id;
      }
      
      return $source_ids;
    }
    
    /**
     * Upsert a listing
     */
    private function upsert_listing( $listing ) {
      // Find existing post by source_id
      $existing = $this->find_by_source_id( $listing['source_id'] );
      
      $post_data = [
        'post_title'   => $listing['title'] ?? 'Floor Plan',
        'post_content' => $this->generate_content( $listing ),
        'post_status'  => 'publish',
        'post_type'    => 'floor_plan',
      ];
      
      if ( $existing ) {
        // Update existing
        $post_data['ID'] = $existing->ID;
        $post_id         = wp_update_post( $post_data );
        
        if ( is_wp_error( $post_id ) ) {
          FFP_Logger::log( 'Failed to update post: ' . $post_id->get_error_message(), 'error' );
          
          return false;
        }
        
        $this->update_meta( $post_id, $listing );
        
        // Update images if changed
        if ( ! empty( $listing['image_url'] ) ) {
          FFP_Images::download_image( $listing['image_url'], $post_id, true );
        }
        
        // Download gallery images if available
        if ( ! empty( $listing['gallery_images'] ) ) {
          FFP_Images::set_gallery( $post_id, $listing['gallery_images'] );
        }
        
        return 'updated';
      } else {
        // Create new
        $post_id = wp_insert_post( $post_data );
        
        if ( is_wp_error( $post_id ) ) {
          FFP_Logger::log( 'Failed to create post: ' . $post_id->get_error_message(), 'error' );
          
          return false;
        }
        
        $this->update_meta( $post_id, $listing );
        
        // Set featured image
        if ( ! empty( $listing['image_url'] ) ) {
          FFP_Images::download_image( $listing['image_url'], $post_id, true );
        }
        
        // Download gallery images if available
        if ( ! empty( $listing['gallery_images'] ) ) {
          FFP_Images::set_gallery( $post_id, $listing['gallery_images'] );
        }
        
        return 'created';
      }
    }
    
    /**
     * Find post by source ID
     */
    private function find_by_source_id( $source_id ) {
      $posts = get_posts( [
        'post_type'      => 'floor_plan',
        'posts_per_page' => 1,
        'meta_query'     => [
          [
            'key'     => '_ffp_source_id',
            'value'   => $source_id,
            'compare' => '=',
          ],
        ],
      ] );
      
      return ! empty( $posts ) ? $posts[0] : null;
    }
    
    /**
     * Generate post content
     */
    private function generate_content( $listing ) {
      $content = '';
      
      if ( ! empty( $listing['address'] ) ) {
        $content .= '<p><strong>Address:</strong> ' . esc_html( $listing['address'] ) . '</p>';
      }
      
      if ( ! empty( $listing['available'] ) ) {
        $content .= '<p><strong>Available:</strong> ' . esc_html( $listing['available'] ) . '</p>';
      }
      
      return $content;
    }
    
    /**
     * Update post meta
     */
    private function update_meta( $post_id, $listing ) {
      $meta_fields = [
        '_ffp_source_id'  => $listing['source_id'] ?? '',
        '_ffp_building'   => get_option( 'ffp_building_filter', '' ),
        '_ffp_address'    => $listing['address'] ?? '',
        '_ffp_price'      => $listing['price'] ?? 0,
        '_ffp_bedrooms'   => $listing['bedrooms'] ?? '',
        '_ffp_bathrooms'  => $listing['bathrooms'] ?? '',
        '_ffp_sqft'       => $listing['sqft'] ?? '',
        '_ffp_available'  => $listing['available'] ?? '',
        '_ffp_active'     => '1',
        '_ffp_source_url' => $listing['detail_url'] ?? '',
      ];
      
      foreach ( $meta_fields as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
      }
    }
    
    /**
     * Debug function to check cron status
     */
    public function debug_cron_status() {
      $cron_disabled        = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
      $spawn_cron_available = function_exists( 'spawn_cron' );
      $fastcgi_available    = function_exists( 'fastcgi_finish_request' );
      
      FFP_Logger::log( "Cron Debug - Disabled: " . ( $cron_disabled ? 'YES' : 'NO' ) . ", spawn_cron: " . ( $spawn_cron_available ? 'YES' : 'NO' ) . ", fastcgi: " . ( $fastcgi_available ? 'YES' : 'NO' ), 'info' );
    }
    
    /**
     * Handle manual sync via AJAX
     */
    public function handle_manual_sync() {
      check_ajax_referer( 'ffp_admin', 'nonce' );
      
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
        
        return;
      }
      
      // Initialize progress
      update_option( 'ffp_sync_progress', [
        'percentage'  => 0,
        'status'      => 'Initializing...',
        'in_progress' => true,
      ] );
      
      // Try multiple methods to ensure sync runs
      // Method 1: Schedule event for immediate execution
      wp_schedule_single_event( time() + 1, 'ffp_run_sync' );
      
      // Method 2: Trigger spawn_cron (works on most hosting)
      if ( function_exists( 'spawn_cron' ) ) {
        spawn_cron();
      }
      
      // Method 3: Fallback - schedule another event a bit later
      wp_schedule_single_event( time() + 5, 'ffp_run_sync_fallback' );
      
      // Method 4: Direct trigger if cron seems disabled
      if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
        // If cron is disabled, we need to trigger manually
        add_action( 'shutdown', function () {
          // Run sync after response is sent
          if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
          }
          do_action( 'ffp_run_sync' );
        } );
      }
      
      // Send response immediately
      wp_send_json_success( [ 'message' => 'Sync started' ] );
    }
    
    /**
     * Handle manual sync inline in the same request (no WP-Cron dependency)
     */
    public function handle_manual_sync_inline() {
      check_ajax_referer( 'ffp_admin', 'nonce' );
      
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
        
        return;
      }
      
      // Initialize progress
      update_option( 'ffp_sync_progress', [
        'percentage'  => 0,
        'status'      => 'Initializing...',
        'in_progress' => true,
        'updated_at'  => time(),
      ] );
      
      // Respond to the browser immediately, then continue in background
      // This avoids proxy timeouts while letting polling continue
      if ( ! headers_sent() ) {
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
      }
      $payload = json_encode( [ 'success' => true, 'data' => [ 'message' => 'Sync started' ] ] );
      if ( ! headers_sent() ) {
        header( 'Connection: close' );
        header( 'Content-Length: ' . strlen( $payload ) );
      }
      echo $payload;
      @ob_flush();
      @flush();
      if ( function_exists( 'fastcgi_finish_request' ) ) {
        @fastcgi_finish_request();
      }
      
      // After sending response, continue the sync process server-side
      if ( function_exists( 'ignore_user_abort' ) ) {
        ignore_user_abort( true );
      }
      if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 900 ); // give plenty of time when running post-response
      }
      $this->run_sync();
      exit;
    }
    
    /**
     * Get current sync progress
     */
    public function get_progress() {
      check_ajax_referer( 'ffp_admin', 'nonce' );
      
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
        
        return;
      }
      
      $progress = get_option( 'ffp_sync_progress', [
        'percentage'  => 0,
        'status'      => 'Not started',
        'in_progress' => false,
      ] );
      
      wp_send_json_success( $progress );
    }
  }

