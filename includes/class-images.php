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
      $attachment_id = self::get_existing_attachment( $url );
      
      if ( $attachment_id ) {
        // Set as featured if requested
        if ( $featured ) {
          set_post_thumbnail( $post_id, $attachment_id );
        }
        FFP_Logger::log( 'Image already exists, reused: ' . basename( $url ) . ' (ID: ' . $attachment_id . ')', 'info' );
        
        return $attachment_id;
      }
      
      // Download image
      require_once( ABSPATH . 'wp-admin/includes/media.php' );
      require_once( ABSPATH . 'wp-admin/includes/file.php' );
      require_once( ABSPATH . 'wp-admin/includes/image.php' );
      
      $temp_file = download_url( $url, 300 );
      
      if ( is_wp_error( $temp_file ) ) {
        FFP_Logger::log( 'Failed to download image: ' . substr( $url, 0, 80 ), 'error' );
        
        return false;
      }
      
      $file_array = [
        'name'     => basename( $url ),
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
      
      // Set as featured if requested
      if ( $featured ) {
        set_post_thumbnail( $post_id, $attachment_id );
      }
      
      return $attachment_id;
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
          $images[] = [
            'id'     => $id,
            'url'    => $img[0],
            'width'  => $img[1],
            'height' => $img[2],
          ];
        }
      }
      
      return $images;
    }
  }
