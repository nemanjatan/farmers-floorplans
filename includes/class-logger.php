<?php
  /**
   * Logging functionality
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  class FFP_Logger {
    
    private static $log_size = 50; // Keep last 50 log entries
    
    public static function log( $message, $type = 'info' ) {
      // Store in database
      $logs = get_option( 'ffp_logs', [] );
      
      $log_entry = [
        'time'    => current_time( 'mysql' ),
        'type'    => $type,
        'message' => $message,
      ];
      
      array_unshift( $logs, $log_entry );
      
      // Keep only last N entries
      $logs = array_slice( $logs, 0, self::$log_size );
      
      update_option( 'ffp_logs', $logs );
      
      // Also write to file
      self::write_to_file( $message, $type );
    }
    
    /**
     * Write to log file
     */
    private static function write_to_file( $message, $type = 'info' ) {
      $log_file = self::get_log_file_path();
      
      $timestamp  = current_time( 'Y-m-d H:i:s' );
      $type_upper = strtoupper( $type );
      $log_line   = "[{$timestamp}] [{$type_upper}] {$message}\n";
      
      // Append to file
      @file_put_contents( $log_file, $log_line, FILE_APPEND | LOCK_EX );
    }
    
    /**
     * Get log file path
     */
    private static function get_log_file_path() {
      $upload_dir = wp_upload_dir();
      $log_dir    = $upload_dir['basedir'] . '/ffp-logs';
      
      // Create directory if it doesn't exist
      if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
      }
      
      // Create .htaccess to protect logs if on Apache
      $htaccess_file = $log_dir . '/.htaccess';
      if ( ! file_exists( $htaccess_file ) ) {
        file_put_contents( $htaccess_file, "deny from all\n" );
      }
      
      return $log_dir . '/sync-' . date( 'Y-m-d' ) . '.log';
    }
    
    public static function get_logs( $limit = 20 ) {
      $logs = get_option( 'ffp_logs', [] );
      
      return array_slice( $logs, 0, $limit );
    }
    
    public static function clear_logs() {
      delete_option( 'ffp_logs' );
    }
    
    /**
     * Get recent log file contents
     */
    public static function get_log_file_contents( $lines = 100 ) {
      $log_file = self::get_log_file_path();
      
      if ( ! file_exists( $log_file ) ) {
        return 'Log file does not exist yet.';
      }
      
      $content   = file_get_contents( $log_file );
      $log_lines = explode( "\n", $content );
      
      // Get last N lines
      $log_lines = array_slice( $log_lines, - $lines );
      
      return implode( "\n", $log_lines );
    }
    
    /**
     * Get log file path for display
     */
    public static function get_log_file_url() {
      $upload_dir = wp_upload_dir();
      $log_dir    = $upload_dir['baseurl'] . '/ffp-logs';
      
      return $log_dir . '/sync-' . date( 'Y-m-d' ) . '.log';
    }
    
    public static function update_stats( $stats ) {
      update_option( 'ffp_stats', array_merge( get_option( 'ffp_stats', [] ), $stats ) );
    }
    
    public static function get_stats() {
      return get_option( 'ffp_stats', [
        'last_run'    => null,
        'created'     => 0,
        'updated'     => 0,
        'deactivated' => 0,
        'errors'      => 0,
      ] );
    }
  }

