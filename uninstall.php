<?php
/**
 * Uninstall script
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clear scheduled events
wp_clear_scheduled_hook('ffp_daily_sync');

// Delete options
delete_option('ffp_list_url');
delete_option('ffp_building_filter');
delete_option('ffp_sync_time');
delete_option('ffp_auto_create_page');
delete_option('ffp_floor_plans_page_id');
delete_option('ffp_logs');
delete_option('ffp_stats');

// Clear transients
delete_transient('ffp_sync_lock');

// Note: We don't delete posts/meta/images on uninstall
// This allows the user to keep their data even after deactivating

