<?php
  /**
   * Plugin Name: Farmers Floor Plans
   * Plugin URI: https://farmersathens.com
   * Description: Scrape and display Farmer's Exchange property listings from AppFolio.
   * Version: 1.2.6
   * Author: Nemanja Tanaskovic
   * Text Domain: farmers-floorplans
   * Domain Path: /languages
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  // Define plugin constants
  define( 'FFP_VERSION', '1.0.0' );
  define( 'FFP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
  define( 'FFP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
  define( 'FFP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
  
  // Autoloader
  spl_autoload_register( function ( $class ) {
    $prefix   = 'FFP_';
    $base_dir = FFP_PLUGIN_DIR . 'includes/';
    
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
      return;
    }
    
    $relative_class = substr( $class, $len );
    $file           = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';
    
    if ( file_exists( $file ) ) {
      require $file;
    }
  } );
  
  // Initialize plugin
  add_action( 'plugins_loaded', 'ffp_init' );
  
  function ffp_init() {
    // Load text domain
    load_plugin_textdomain( 'farmers-floorplans', false, dirname( FFP_PLUGIN_BASENAME ) . '/languages' );
    
    // Initialize core classes
    new FFP_CPT();
    new FFP_Admin();
    new FFP_Sync();
    new FFP_Render();
    
    // Load WP-CLI commands if WP-CLI is running
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
      require_once FFP_PLUGIN_DIR . 'includes/class-cli.php';
    }
  }
  
  // Activation hook
  register_activation_hook( __FILE__, 'ffp_activate' );
  
  function ffp_activate() {
    // Clear permalinks
    flush_rewrite_rules();
    
    // Schedule daily sync
    if ( ! wp_next_scheduled( 'ffp_daily_sync' ) ) {
      wp_schedule_event( time(), 'daily', 'ffp_daily_sync' );
    }
    
    // Create default options
    add_option( 'ffp_list_url', 'https://cityblockprop.appfolio.com/listings' );
    add_option( 'ffp_building_filter', 'Farmer\'s Exchange 580 E Broad St.' );
    add_option( 'ffp_sync_time', '03:00' );
    add_option( 'ffp_auto_create_page', true );
    
    // Optionally create Floor Plans page
    $auto_create = get_option( 'ffp_auto_create_page', true );
    if ( $auto_create ) {
      $page = get_page_by_path( 'floor-plans' );
      if ( ! $page ) {
        $page_id = wp_insert_post( [
          'post_title'   => 'Floor Plans',
          'post_content' => '[farmers_floor_plans]',
          'post_status'  => 'publish',
          'post_type'    => 'page',
          'post_name'    => 'floor-plans'
        ] );
        update_option( 'ffp_floor_plans_page_id', $page_id );
      }
    }
  }
  
  // Deactivation hook
  register_deactivation_hook( __FILE__, 'ffp_deactivate' );
  
  function ffp_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'ffp_daily_sync' );
    
    // Clear mutex
    delete_transient( 'ffp_sync_lock' );
  }

