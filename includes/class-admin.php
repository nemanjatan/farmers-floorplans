<?php
  /**
   * Admin settings and UI
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  class FFP_Admin {
    
    public function __construct() {
      add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
      add_action( 'admin_init', [ $this, 'register_settings' ] );
      add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }
    
    public function add_admin_menu() {
      add_options_page(
        'Farmers Floor Plans',
        'Farmers Floor Plans',
        'manage_options',
        'farmers-floorplans',
        [ $this, 'render_settings_page' ]
      );
    }
    
    public function register_settings() {
      register_setting( 'ffp_settings', 'ffp_list_url' );
      register_setting( 'ffp_settings', 'ffp_building_filter' );
      register_setting( 'ffp_settings', 'ffp_sync_time' );
      register_setting( 'ffp_settings', 'ffp_auto_create_page' );
    }
    
    public function enqueue_scripts( $hook ) {
      if ( $hook !== 'settings_page_farmers-floorplans' ) {
        return;
      }
      
      wp_enqueue_style( 'ffp-admin', FFP_PLUGIN_URL . 'assets/admin.css', [], FFP_VERSION );
      wp_enqueue_script( 'ffp-admin', FFP_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], FFP_VERSION, true );
      
      wp_localize_script( 'ffp-admin', 'ffpAdmin', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'ffp_admin' ),
      ] );
    }
    
    public function render_settings_page() {
      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }
      
      ?>
        <div class="wrap ffp-settings">
            <h1>Farmers Floor Plans Settings</h1>
          
          <?php
            // Handle manual sync
            if ( isset( $_POST['ffp_sync_now'] ) && check_admin_referer( 'ffp_sync' ) ) {
              do_action( 'ffp_run_sync' );
              echo '<div class="notice notice-success"><p>Sync started. Check status below.</p></div>';
            }
            
            // Handle settings save
            if ( isset( $_POST['submit'] ) ) {
              settings_fields( 'ffp_settings' );
              do_settings_sections( 'ffp_settings' );
              update_option( 'ffp_list_url', sanitize_text_field( $_POST['ffp_list_url'] ) );
              update_option( 'ffp_building_filter', sanitize_text_field( $_POST['ffp_building_filter'] ) );
              update_option( 'ffp_sync_time', sanitize_text_field( $_POST['ffp_sync_time'] ) );
              update_option( 'ffp_auto_create_page', isset( $_POST['ffp_auto_create_page'] ) ? true : false );
              
              // Reschedule cron
              wp_clear_scheduled_hook( 'ffp_daily_sync' );
              $time = strtotime( $_POST['ffp_sync_time'] );
              wp_schedule_event( $time, 'daily', 'ffp_daily_sync' );
              
              echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
            }
          ?>

            <form method="post" action="">
              <?php wp_nonce_field( 'ffp_sync' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ffp_list_url">AppFolio List URL</label></th>
                        <td>
                            <input type="url" id="ffp_list_url" name="ffp_list_url"
                                   value="<?php echo esc_attr( get_option( 'ffp_list_url', 'https://cityblockprop.appfolio.com/listings' ) ); ?>"
                                   class="regular-text"/>
                            <p class="description">The URL to scrape listings from.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ffp_building_filter">Building Filter</label></th>
                        <td>
                            <input type="text" id="ffp_building_filter" name="ffp_building_filter"
                                   value="<?php echo esc_attr( get_option( 'ffp_building_filter', 'Farmer\'s Exchange 580 E Broad St.' ) ); ?>"
                                   class="regular-text"/>
                            <p class="description">Only import listings matching this text.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ffp_sync_time">Daily Sync Time</label></th>
                        <td>
                            <input type="time" id="ffp_sync_time" name="ffp_sync_time"
                                   value="<?php echo esc_attr( get_option( 'ffp_sync_time', '03:00' ) ); ?>"/>
                            <p class="description">Time to run automatic daily sync.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ffp_auto_create_page">Auto-create Page</label></th>
                        <td>
                            <input type="checkbox" id="ffp_auto_create_page" name="ffp_auto_create_page"
                                   value="1" <?php checked( get_option( 'ffp_auto_create_page', true ) ); ?> />
                            <label for="ffp_auto_create_page">Create Floor Plans page on activation</label>
                        </td>
                    </tr>
                </table>
              
              <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr/>

            <h2>Sync Controls</h2>
            <p>
                <!-- <button type="button" name="ffp_sync_now" id="ffp_sync_now" class="button button-secondary">Sync Now
                </button>
                <span class="spinner"></span>
                &nbsp;
                <button type="button" name="ffp_sync_inline" id="ffp_sync_inline" class="button button-primary">Run Inline Now
                </button>
                <span class="spinner"></span> -->
                <button type="button" name="ffp_sync_inline" id="ffp_sync_inline" class="button button-secondary">Sync
                    Now
                </button>
            </p>

            <hr/>

            <h2>Sync Status</h2>
          <?php $this->render_status_panel(); ?>

            <hr/>

            <h2>Recent Logs</h2>
          <?php $this->render_logs(); ?>
          
          <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
              <hr/>

              <h2>Debug</h2>
            <?php $this->render_debug_panel(); ?>
          <?php endif; ?>
        </div>
      <?php
    }
    
    private function render_status_panel() {
      $stats = FFP_Logger::get_stats();
      ?>
        <table class="widefat">
            <tbody>
            <tr>
                <th>Last Run</th>
                <td><?php echo esc_html( $stats['last_run'] ?: 'Never' ); ?></td>
            </tr>
            <tr>
                <th>Created</th>
                <td><?php echo esc_html( $stats['created'] ); ?></td>
            </tr>
            <tr>
                <th>Updated</th>
                <td><?php echo esc_html( $stats['updated'] ); ?></td>
            </tr>
            <tr>
                <th>Deactivated</th>
                <td><?php echo esc_html( $stats['deactivated'] ); ?></td>
            </tr>
            <tr>
                <th>Errors</th>
                <td><?php echo esc_html( $stats['errors'] ); ?></td>
            </tr>
            </tbody>
        </table>
      <?php
    }
    
    private function render_logs() {
      $logs = FFP_Logger::get_logs( 20 );
      ?>
        <table class="widefat">
            <thead>
            <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php if ( empty( $logs ) ): ?>
                <tr>
                    <td colspan="3">No logs yet.</td>
                </tr>
            <?php else: ?>
              <?php foreach ( $logs as $log ): ?>
                    <tr>
                        <td><?php echo esc_html( $log['time'] ); ?></td>
                        <td>
                            <span class="log-type log-type-<?php echo esc_attr( $log['type'] ); ?>"><?php echo esc_html( $log['type'] ); ?></span>
                        </td>
                        <td><?php echo esc_html( $log['message'] ); ?></td>
                    </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
      <?php
    }
    
    private function render_debug_panel() {
      $last_html = get_option( 'ffp_last_html', '' );
      ?>
        <div class="ffp-debug-panel">
            <h3>Last Fetched HTML</h3>
          <?php if ( empty( $last_html ) ): ?>
              <p>No HTML has been fetched yet. Run a sync to capture HTML.</p>
          <?php else: ?>
              <details>
                  <summary>Show HTML (<?php echo esc_html( strlen( $last_html ) ); ?> bytes)</summary>
                  <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-height: 400px;"><code><?php echo esc_html( $last_html ); ?></code></pre>
              </details>
          <?php endif; ?>
        </div>
      <?php
    }
  }

