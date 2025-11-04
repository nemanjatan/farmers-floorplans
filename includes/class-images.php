<?php
  /**
   * Image handling and gallery management
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  class FFP_Images {
    
    /**
     * Set gallery images
     */
    public static function set_gallery( $post_id, $image_urls ) {
      $gallery_ids = [];
      
      // Download all images
      foreach ( $image_urls as $url ) {
        FFP_Logger::log( 'Downloading image: ' . substr( $url, 0, 80 ), 'info' );
        $attachment_id = self::download_image( $url, $post_id );
        if ( $attachment_id ) {
          $gallery_ids[] = $attachment_id;
        }
      }
      
      // Store gallery IDs
      if ( ! empty( $gallery_ids ) ) {
        update_post_meta( $post_id, '_ffp_gallery_ids', $gallery_ids );
        
        // Set first image as featured if none exists
        if ( ! has_post_thumbnail( $post_id ) && ! empty( $gallery_ids ) ) {
          set_post_thumbnail( $post_id, $gallery_ids[0] );
        }
      }
      
      return $gallery_ids;
    }
    
    /**
     * Download and attach image to post
     */
    public static function download_image( $url, $post_id, $featured = false ) {
      if ( empty( $url ) ) {
        return false;
      }
      
      // Check if we already have this image
//      $attachment_id = self::get_existing_attachment( $url );
//      
//      if ( $attachment_id ) {
//        // Set as featured if requested
//        if ( $featured ) {
//          set_post_thumbnail( $post_id, $attachment_id );
//        }
//        FFP_Logger::log( 'Image already exists, reused: ' . basename( $url ) . ' (ID: ' . $attachment_id . ')', 'info' );
//
//        return $attachment_id;
//      }
      
      // Download image
      require_once( ABSPATH . 'wp-admin/includes/media.php' );
      require_once( ABSPATH . 'wp-admin/includes/file.php' );
      require_once( ABSPATH . 'wp-admin/includes/image.php' );
      
      // Use a conservative timeout to avoid long stalls on slow CDNs
      $temp_file = download_url( $url, 30 );
      
      if ( is_wp_error( $temp_file ) ) {
        FFP_Logger::log( 'Failed to download image: ' . substr( $url, 0, 80 ), 'error' );
        
        return false;
      }
      
      // Generate random string (5-10 characters) and prepend to filename
      $random_string = self::generate_random_string( 5, 10 );
      $original_filename = basename( $url );
      $new_filename = $random_string . '-' . $original_filename;
      
      $file_array = [
        'name'     => $new_filename,
        'tmp_name' => $temp_file,
      ];
      
      $attachment_id = media_handle_sideload( $file_array, $post_id );
      
      if ( is_wp_error( $attachment_id ) ) {
        @unlink( $temp_file );
        FFP_Logger::log( 'Failed to import image: ' . $attachment_id->get_error_message() . ' | URL: ' . substr( $url, 0, 60 ), 'error' );
        
        return false;
      }
      
      // Store the source URL as metadata so we can identify this exact image later
      update_post_meta( $attachment_id, '_ffp_source_url', $url );
      
      // Log successful download
      FFP_Logger::log( 'Downloaded image: ' . basename( $url ) . ' (Attachment ID: ' . $attachment_id . ')', 'info' );
      
      // Generate WebP version for better performance (if supported)
      // self::maybe_generate_webp_for_attachment( $attachment_id );
      
      // Set as featured if requested
      if ( $featured ) {
        set_post_thumbnail( $post_id, $attachment_id );
      }
      
      return $attachment_id;
    }
    
    /**
     * Generate a random string of specified length
     * 
     * @param int $min_length Minimum length (default 5)
     * @param int $max_length Maximum length (default 10)
     * @return string Random alphanumeric string
     */
    private static function generate_random_string( $min_length = 5, $max_length = 10 ) {
      $length = rand( $min_length, $max_length );
      $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      $random_string = '';
      for ( $i = 0; $i < $length; $i++ ) {
        $random_string .= $characters[rand( 0, strlen( $characters ) - 1 )];
      }
      return $random_string;
    }
    
    /**
     * Check if image already exists by URL
     */
    private static function get_existing_attachment( $url ) {
      global $wpdb;
      
      // Check by source URL - this is the most reliable way to know if we've
      // already downloaded this exact image from this exact URL
      // This prevents filename collisions from preventing downloads
      $attachment = $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_ffp_source_url'
             AND p.post_type = 'attachment'
             AND pm.meta_value = %s
             LIMIT 1",
        $url
      ) );
      
      if ( $attachment ) {
        return intval( $attachment );
      }
      
      // If no exact URL match, return false to download the image
      return false;
    }
    
    /**
     * Generate a WebP copy of the original image and store its URL on the attachment meta
     * Does nothing if WebP isn't supported by the server or if already generated.
     */
    private static function maybe_generate_webp_for_attachment( $attachment_id ) {
      // If already generated, skip
      $existing = get_post_meta( $attachment_id, '_ffp_webp_url', true );
      if ( ! empty( $existing ) ) {
        return;
      }
      
      $path = get_attached_file( $attachment_id );
      if ( ! $path || ! file_exists( $path ) ) {
        return;
      }
      
      // Skip WebP for very large originals to avoid timeouts/memory issues
      $filesize = @filesize( $path );
      if ( $filesize && $filesize > 6 * 1024 * 1024 ) { // >6MB
        return;
      }
      
      // Create image editor
      $editor = wp_get_image_editor( $path );
      if ( is_wp_error( $editor ) ) {
        return;
      }
      
      // Derive WebP file path
      $uploads  = wp_upload_dir();
      $base_dir = trailingslashit( $uploads['basedir'] );
      $base_url = trailingslashit( $uploads['baseurl'] );
      
      $info      = pathinfo( $path );
      // Generate a unique random filename for WebP to avoid collisions
      // when multiple images have the same original filename (e.g., "large.jpg")
      // Using uniqid with more entropy and prefix for extra uniqueness
      $random_string = uniqid( 'ffp_', true ) . '_' . wp_generate_password( 8, false );
      $webp_filename = $random_string . '.webp';
      $webp_path = $info['dirname'] . '/' . $webp_filename;
      
      // Save as WebP (quality 85)
      $saved = $editor->save( $webp_path, 'image/webp' );
      if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
        return;
      }
      
      // Build URL and store on attachment meta
      $webp_url = str_replace( $base_dir, $base_url, $webp_path );
      update_post_meta( $attachment_id, '_ffp_webp_url', esc_url_raw( $webp_url ) );
    }
    
    /**
     * Get gallery images
     */
    public static function get_gallery( $post_id ) {
      $gallery_ids = get_post_meta( $post_id, '_ffp_gallery_ids', true );
      
      if ( empty( $gallery_ids ) || ! is_array( $gallery_ids ) ) {
        return [];
      }
      
      $images = [];
      foreach ( $gallery_ids as $id ) {
        $img = wp_get_attachment_image_src( $id, 'large' );
        if ( $img ) {
          // Prefer WebP URL if we've generated one
          $webp_url = get_post_meta( $id, '_ffp_webp_url', true );
          $images[] = [
            'id'     => $id,
            'url'    => $webp_url ? $webp_url : $img[0],
            'width'  => $img[1],
            'height' => $img[2],
          ];
        }
      }
      
      return $images;
    }
  }
