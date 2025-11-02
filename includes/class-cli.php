<?php
  /**
   * WP-CLI commands for Farmers Floor Plans
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  if ( ! class_exists( 'WP_CLI_Command' ) ) {
    return;
  }
  
  class FFP_CLI extends WP_CLI_Command {
    
    /**
     * Run the sync process
     *
     * ## EXAMPLES
     *
     *     wp farmers-floorplans sync
     *
     * @when after_wp_load
     */
    public function sync( $args, $assoc_args ) {
      WP_CLI::log( 'Starting Farmers Floor Plans sync...' );
      
      $sync = new FFP_Sync();
      
      // Run sync
      $sync->run_sync();
      
      // Get final stats
      $stats = FFP_Logger::get_stats();
      
      WP_CLI::success( sprintf(
        'Sync completed! Created: %d, Updated: %d, Deactivated: %d, Errors: %d',
        $stats['created'],
        $stats['updated'],
        $stats['deactivated'],
        $stats['errors']
      ) );
    }
  }
  
  // Register the command
  if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'farmers-floorplans', 'FFP_CLI' );
  }

