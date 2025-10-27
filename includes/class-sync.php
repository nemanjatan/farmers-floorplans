<?php
/**
 * Sync functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class FFP_Sync {
    
    public function __construct() {
        add_action('ffp_run_sync', [$this, 'run_sync']);
        add_action('ffp_daily_sync', [$this, 'run_sync']);
        add_action('wp_ajax_ffp_sync_now', [$this, 'handle_manual_sync']);
    }
    
    /**
     * Main sync method
     */
    public function run_sync() {
        // Check mutex to avoid overlapping syncs
        if (get_transient('ffp_sync_lock')) {
            FFP_Logger::log('Sync already in progress, skipping', 'warning');
            return;
        }
        
        // Set mutex for 15 minutes
        set_transient('ffp_sync_lock', time(), 15 * 60);
        
        try {
            $stats = [
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
                'errors' => 0,
            ];
            
            // Fetch listings
            $url = get_option('ffp_list_url', 'https://cityblockprop.appfolio.com/listings');
            $response = $this->fetch_listings($url);
            
            if (is_wp_error($response)) {
                FFP_Logger::log('Failed to fetch listings: ' . $response->get_error_message(), 'error');
                $stats['errors']++;
                FFP_Logger::update_stats($stats);
                delete_transient('ffp_sync_lock');
                return;
            }
            
            // Parse listings
            $building_filter = get_option('ffp_building_filter', 'Farmer\'s Exchange 580 E Broad St.');
            $parser = new FFP_Parser($building_filter);
            $listings = $parser->parse($response);
            
            if (empty($listings)) {
                FFP_Logger::log('No listings found after parsing. Check HTML structure or selectors.', 'warning');
            }
            
            // Get current source IDs
            $current_source_ids = $this->get_all_source_ids();
            
            // Process each listing
            foreach ($listings as $listing) {
                $result = $this->upsert_listing($listing);
                
                if ($result === 'created') {
                    $stats['created']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                } else {
                    $stats['errors']++;
                }
                
                // Remove from current IDs (those remaining will be deactivated)
                unset($current_source_ids[$listing['source_id']]);
            }
            
            // Deactivate stale listings
            foreach ($current_source_ids as $source_id => $post_id) {
                update_post_meta($post_id, '_ffp_active', '0');
                wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
                $stats['deactivated']++;
            }
            
            // Update stats
            $stats['last_run'] = current_time('mysql');
            FFP_Logger::update_stats($stats);
            FFP_Logger::log('Sync completed: ' . $stats['created'] . ' created, ' . $stats['updated'] . ' updated, ' . $stats['deactivated'] . ' deactivated', 'info');
            
        } catch (Exception $e) {
            FFP_Logger::log('Sync error: ' . $e->getMessage(), 'error');
        } finally {
            delete_transient('ffp_sync_lock');
        }
    }
    
    /**
     * Fetch listings from AppFolio
     */
    private function fetch_listings($url) {
        FFP_Logger::log("Fetching from: {$url}", 'info');
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
            'sslverify' => true,
        ]);
        
        if (is_wp_error($response)) {
            FFP_Logger::log("First fetch error: " . $response->get_error_message(), 'warning');
            // Retry once
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ],
                'sslverify' => true,
            ]);
        }
        
        if (is_wp_error($response)) {
            FFP_Logger::log("Retry fetch error: " . $response->get_error_message(), 'error');
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        FFP_Logger::log("Fetched {$code} response, body length: " . strlen($body), 'info');
        
        if ($code !== 200) {
            return new WP_Error('http_error', 'HTTP ' . $code);
        }
        
        // Check if page appears to be empty or error page
        if (strlen($body) < 1000) {
            FFP_Logger::log("Response body is suspiciously short (" . strlen($body) . " bytes)", 'warning');
        }
        
        return $body;
    }
    
    /**
     * Upsert a listing
     */
    private function upsert_listing($listing) {
        // Find existing post by source_id
        $existing = $this->find_by_source_id($listing['source_id']);
        
        $post_data = [
            'post_title' => $listing['title'] ?? 'Floor Plan',
            'post_content' => $this->generate_content($listing),
            'post_status' => 'publish',
            'post_type' => 'floor_plan',
        ];
        
        if ($existing) {
            // Update existing
            $post_data['ID'] = $existing->ID;
            $post_id = wp_update_post($post_data);
            
            if (is_wp_error($post_id)) {
                FFP_Logger::log('Failed to update post: ' . $post_id->get_error_message(), 'error');
                return false;
            }
            
            $this->update_meta($post_id, $listing);
            
            // Update images if changed
            if (!empty($listing['image_url'])) {
                FFP_Images::download_image($listing['image_url'], $post_id, true);
            }
            
            // Download gallery images if available
            if (!empty($listing['gallery_images'])) {
                FFP_Images::set_gallery($post_id, $listing['gallery_images']);
            }
            
            return 'updated';
        } else {
            // Create new
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                FFP_Logger::log('Failed to create post: ' . $post_id->get_error_message(), 'error');
                return false;
            }
            
            $this->update_meta($post_id, $listing);
            
            // Set featured image
            if (!empty($listing['image_url'])) {
                FFP_Images::download_image($listing['image_url'], $post_id, true);
            }
            
            // Download gallery images if available
            if (!empty($listing['gallery_images'])) {
                FFP_Images::set_gallery($post_id, $listing['gallery_images']);
            }
            
            return 'created';
        }
    }
    
    /**
     * Find post by source ID
     */
    private function find_by_source_id($source_id) {
        $posts = get_posts([
            'post_type' => 'floor_plan',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_ffp_source_id',
                    'value' => $source_id,
                    'compare' => '=',
                ],
            ],
        ]);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Update post meta
     */
    private function update_meta($post_id, $listing) {
        $meta_fields = [
            '_ffp_source_id' => $listing['source_id'] ?? '',
            '_ffp_building' => get_option('ffp_building_filter', ''),
            '_ffp_address' => $listing['address'] ?? '',
            '_ffp_price' => $listing['price'] ?? 0,
            '_ffp_bedrooms' => $listing['bedrooms'] ?? '',
            '_ffp_bathrooms' => $listing['bathrooms'] ?? '',
            '_ffp_sqft' => $listing['sqft'] ?? '',
            '_ffp_available' => $listing['available'] ?? '',
            '_ffp_active' => '1',
            '_ffp_source_url' => $listing['detail_url'] ?? '',
        ];
        
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }
    
    /**
     * Generate post content
     */
    private function generate_content($listing) {
        $content = '';
        
        if (!empty($listing['address'])) {
            $content .= '<p><strong>Address:</strong> ' . esc_html($listing['address']) . '</p>';
        }
        
        if (!empty($listing['available'])) {
            $content .= '<p><strong>Available:</strong> ' . esc_html($listing['available']) . '</p>';
        }
        
        return $content;
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
        foreach ($results as $row) {
            $source_ids[$row->meta_value] = $row->post_id;
        }
        
        return $source_ids;
    }
    
    /**
     * Handle manual sync via AJAX
     */
    public function handle_manual_sync() {
        check_ajax_referer('ffp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Trigger sync asynchronously
        do_action('ffp_run_sync');
        
        wp_send_json_success(['message' => 'Sync started']);
    }
}

