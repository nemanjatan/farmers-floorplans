<?php
/**
 * Logging functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class FFP_Logger {
    
    private static $log_size = 50; // Keep last 50 log entries
    
    public static function log($message, $type = 'info') {
        $logs = get_option('ffp_logs', []);
        
        $log_entry = [
            'time' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
        ];
        
        array_unshift($logs, $log_entry);
        
        // Keep only last N entries
        $logs = array_slice($logs, 0, self::$log_size);
        
        update_option('ffp_logs', $logs);
    }
    
    public static function get_logs($limit = 20) {
        $logs = get_option('ffp_logs', []);
        return array_slice($logs, 0, $limit);
    }
    
    public static function clear_logs() {
        delete_option('ffp_logs');
    }
    
    public static function update_stats($stats) {
        update_option('ffp_stats', array_merge(get_option('ffp_stats', []), $stats));
    }
    
    public static function get_stats() {
        return get_option('ffp_stats', [
            'last_run' => null,
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'errors' => 0,
        ]);
    }
}

