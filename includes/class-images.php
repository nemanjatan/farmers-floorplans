<?php
/**
 * Image handling and gallery management
 */

if (!defined('ABSPATH')) {
    exit;
}

class FFP_Images {
    
    /**
     * Download and attach image to post
     */
    public static function download_image($url, $post_id, $featured = false) {
        if (empty($url)) {
            return false;
        }
        
        // Check if we already have this image
        $attachment_id = self::get_existing_attachment($url);
        
        if ($attachment_id) {
            // Set as featured if requested
            if ($featured) {
                set_post_thumbnail($post_id, $attachment_id);
            }
            return $attachment_id;
        }
        
        // Download image
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $temp_file = download_url($url, 300);
        
        if (is_wp_error($temp_file)) {
            FFP_Logger::log('Failed to download image: ' . $url, 'error');
            return false;
        }
        
        $file_array = [
            'name' => basename($url),
            'tmp_name' => $temp_file,
        ];
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            FFP_Logger::log('Failed to import image: ' . $attachment_id->get_error_message(), 'error');
            return false;
        }
        
        // Set as featured if requested
        if ($featured) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        return $attachment_id;
    }
    
    /**
     * Check if image already exists by URL
     */
    private static function get_existing_attachment($url) {
        global $wpdb;
        
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);
        
        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s LIMIT 1",
            $filename_no_ext
        ));
        
        return $attachment ? intval($attachment) : false;
    }
    
    /**
     * Set gallery images
     */
    public static function set_gallery($post_id, $image_urls) {
        $gallery_ids = [];
        
        // Download all images
        foreach ($image_urls as $url) {
            $attachment_id = self::download_image($url, $post_id);
            if ($attachment_id) {
                $gallery_ids[] = $attachment_id;
            }
        }
        
        // Store gallery IDs
        if (!empty($gallery_ids)) {
            update_post_meta($post_id, '_ffp_gallery_ids', $gallery_ids);
            
            // Set first image as featured if none exists
            if (!has_post_thumbnail($post_id) && !empty($gallery_ids)) {
                set_post_thumbnail($post_id, $gallery_ids[0]);
            }
        }
        
        return $gallery_ids;
    }
    
    /**
     * Get gallery images
     */
    public static function get_gallery($post_id) {
        $gallery_ids = get_post_meta($post_id, '_ffp_gallery_ids', true);
        
        if (empty($gallery_ids) || !is_array($gallery_ids)) {
            return [];
        }
        
        $images = [];
        foreach ($gallery_ids as $id) {
            $img = wp_get_attachment_image_src($id, 'large');
            if ($img) {
                $images[] = [
                    'id' => $id,
                    'url' => $img[0],
                    'width' => $img[1],
                    'height' => $img[2],
                ];
            }
        }
        
        return $images;
    }
}

