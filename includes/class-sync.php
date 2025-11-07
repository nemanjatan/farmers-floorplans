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
      // Improve resilience to timeouts - set longer limits for large syncs
      if ( function_exists( 'ignore_user_abort' ) ) {
        ignore_user_abort( true );
      }
      if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 3600 ); // 1 hour - plenty of time for large syncs with many images
      }
      if ( function_exists( 'ini_set' ) ) {
        @ini_set( 'max_execution_time', 3600 );
        @ini_set( 'memory_limit', '512M' ); // Increase memory limit for large image processing
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
        
        // Deduplicate listings by source_id to avoid processing the same listing multiple times
        $unique_listings = [];
        $seen_source_ids = [];
        foreach ( $listings as $listing ) {
          $source_id = $listing['source_id'] ?? '';
          if ( ! empty( $source_id ) && ! isset( $seen_source_ids[ $source_id ] ) ) {
            $unique_listings[]             = $listing;
            $seen_source_ids[ $source_id ] = true;
          } elseif ( empty( $source_id ) ) {
            // Include listings without source_id (shouldn't happen, but be safe)
            $unique_listings[] = $listing;
          }
        }
        
        $duplicate_count = count( $listings ) - count( $unique_listings );
        if ( $duplicate_count > 0 ) {
          FFP_Logger::log( "Removed {$duplicate_count} duplicate listing(s) before processing", 'info' );
        }
        $listings = $unique_listings;
        
        // Get current source IDs
        $this->update_progress( 30, 'Checking existing listings...' );
        $current_source_ids = $this->get_all_source_ids();
        
        // Process each listing
        $total_listings = count( $listings );
        $processed      = 0;
        FFP_Logger::log( "Starting to process {$total_listings} listings", 'info' );
        
        foreach ( $listings as $listing ) {
          $processed ++;
          $progress = 30 + intval( ( $processed / max( $total_listings, 1 ) ) * 50 );
          $this->update_progress( $progress, "Processing {$processed} of {$total_listings} listings..." );
          
          // Reset time limit periodically to prevent timeouts during long operations
          if ( $processed % 5 === 0 && function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 3600 );
          }
          
          // Log listing details
          $listing_title     = $listing['title'] ?? 'Unknown';
          $listing_address   = $listing['address'] ?? 'Unknown';
          $listing_source_id = $listing['source_id'] ?? 'Unknown';
          FFP_Logger::log( "[{$processed}/{$total_listings}] Processing listing: '{$listing_title}' | Address: {$listing_address} | Source ID: {$listing_source_id}", 'info' );
          
          $result = $this->upsert_listing( $listing );
          
          if ( $result === 'created' ) {
            $stats['created'] ++;
            FFP_Logger::log( "  ✓ Created new listing: '{$listing_title}'", 'info' );
          } elseif ( $result === 'updated' ) {
            $stats['updated'] ++;
            FFP_Logger::log( "  ✓ Updated existing listing: '{$listing_title}'", 'info' );
          } else {
            $stats['errors'] ++;
            FFP_Logger::log( "  ✗ Error processing listing: '{$listing_title}'", 'error' );
          }
          
          // Remove from current IDs (those remaining will be deactivated)
          unset( $current_source_ids[ $listing['source_id'] ] );
        }
        
        FFP_Logger::log( "Finished processing all listings. Summary: {$stats['created']} created, {$stats['updated']} updated, {$stats['errors']} errors", 'info' );
        
        // Deactivate stale listings
        $this->update_progress( 85, 'Deactivating stale listings...' );
        $total_stale       = count( $current_source_ids );
        $deactivated_count = 0;
        
        if ( $total_stale > 0 ) {
          FFP_Logger::log( "Deactivating {$total_stale} stale listing(s) that are no longer in the source", 'info' );
        }
        
        foreach ( $current_source_ids as $source_id => $post_id ) {
          $post_title = get_the_title( $post_id );
          $post_title = ! empty( $post_title ) ? $post_title : "Post #{$post_id}";
          
          FFP_Logger::log( "  Deactivating: '{$post_title}' (Post #{$post_id}, Source ID: {$source_id})", 'info' );
          
          update_post_meta( $post_id, '_ffp_active', '0' );
          wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
          $stats['deactivated'] ++;
          $deactivated_count ++;
          
          if ( $total_stale > 0 ) {
            $progress = 85 + intval( ( $deactivated_count / $total_stale ) * 10 );
            $this->update_progress( $progress, "Deactivating {$deactivated_count} of {$total_stale} stale listings..." );
          }
        }
        
        if ( $total_stale > 0 ) {
          FFP_Logger::log( "Deactivation complete: {$deactivated_count} listing(s) deactivated", 'info' );
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
      $listing_title = $listing['title'] ?? 'Floor Plan';
      
      // Find existing post by source_id
      $existing = $this->find_by_source_id( $listing['source_id'] );
      
      $post_data = [
        'post_title'   => $listing_title,
        'post_content' => $this->generate_content( $listing ),
        'post_status'  => 'publish',
        'post_type'    => 'floor_plan',
      ];
      
      if ( $existing ) {
        // Update existing - only update basic metadata, skip image downloads
        $post_data['ID'] = $existing->ID;
        $post_id         = wp_update_post( $post_data );
        
        if ( is_wp_error( $post_id ) ) {
          FFP_Logger::log( "    Failed to update post #{$existing->ID}: " . $post_id->get_error_message(), 'error' );
          
          return false;
        }
        
        FFP_Logger::log( "    Updating post #{$post_id} metadata (skipping images - listing already exists)", 'info' );
        $this->update_meta( $post_id, $listing );
        
        // Skip image downloads for existing listings to avoid redundant processing
        // Images are only downloaded when creating new listings
        
        return 'updated';
      } else {
        // Create new
        $post_id = wp_insert_post( $post_data );
        
        if ( is_wp_error( $post_id ) ) {
          FFP_Logger::log( "    Failed to create post: " . $post_id->get_error_message(), 'error' );
          
          return false;
        }
        
        FFP_Logger::log( "    Created new post #{$post_id}, setting metadata", 'info' );
        $this->update_meta( $post_id, $listing );
        
        // Download gallery images first (if available)
        if ( ! empty( $listing['gallery_images'] ) ) {
          $gallery_count = count( $listing['gallery_images'] );
          FFP_Logger::log( "    Downloading {$gallery_count} gallery image(s)", 'info' );
          $gallery_ids = FFP_Images::set_gallery( $post_id, $listing['gallery_images'] );
          
          // Set the first gallery image as featured image (avoid duplicate download)
          if ( ! empty( $gallery_ids[0] ) ) {
            set_post_thumbnail( $post_id, $gallery_ids[0] );
            FFP_Logger::log( "    Set first gallery image (attachment #{$gallery_ids[0]}) as featured image", 'info' );
          }
        } elseif ( ! empty( $listing['image_url'] ) ) {
          // Fallback: If no gallery images, download featured image separately
          FFP_Logger::log( "    No gallery images, downloading standalone featured image: " . basename( $listing['image_url'] ), 'info' );
          FFP_Images::download_image( $listing['image_url'], $post_id, true );
        } else {
          FFP_Logger::log( "    No featured image URL found for this listing", 'warning' );
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
      // Return empty content - all details are displayed via the template
      return '';
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
        @set_time_limit( 3600 ); // 1 hour - give plenty of time when running post-response
      }
      if ( function_exists( 'ini_set' ) ) {
        @ini_set( 'max_execution_time', 3600 );
        @ini_set( 'memory_limit', '512M' );
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

