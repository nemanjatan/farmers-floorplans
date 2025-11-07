<?php
  /**
   * Frontend rendering
   */
  
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }
  
  class FFP_Render {
    
    public function __construct() {
      add_shortcode( 'farmers_floor_plans', [ $this, 'render_shortcode' ] );
      add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
      // AJAX filtering endpoints
      add_action( 'wp_ajax_ffp_filter', [ $this, 'ajax_filter' ] );
      add_action( 'wp_ajax_nopriv_ffp_filter', [ $this, 'ajax_filter' ] );
      // AJAX contact form endpoint
      add_action( 'wp_ajax_ffp_contact_form', [ $this, 'ajax_contact_form' ] );
      add_action( 'wp_ajax_nopriv_ffp_contact_form', [ $this, 'ajax_contact_form' ] );
      // Add contact modal to footer
      add_action( 'wp_footer', [ $this, 'render_contact_modal' ] );
      // Use high priority to ensure our template loads before theme templates
      add_filter( 'template_include', [ $this, 'template_include' ], 99 );
      // Also hook into single template hierarchy for better compatibility
      add_filter( 'single_template', [ $this, 'single_template' ], 99 );
    }
    
    public function enqueue_scripts() {
      wp_enqueue_style( 'ffp-front', FFP_PLUGIN_URL . 'assets/front.css', [], FFP_VERSION );
      wp_enqueue_script( 'ffp-front', FFP_PLUGIN_URL . 'assets/front.js', [ 'jquery' ], FFP_VERSION, true );
      wp_localize_script( 'ffp-front', 'ffpFront', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      ] );
    }
    
    public function render_shortcode( $atts ) {
      // Get parameters from URL
      if ( isset( $_GET['sort'] ) ) {
        $atts['orderby'] = sanitize_text_field( $_GET['sort'] );
      }
      if ( isset( $_GET['beds'] ) ) {
        $atts['beds'] = sanitize_text_field( $_GET['beds'] );
      }
      if ( isset( $_GET['min_price'] ) ) {
        $atts['min_price'] = sanitize_text_field( $_GET['min_price'] );
      }
      if ( isset( $_GET['max_price'] ) ) {
        $atts['max_price'] = sanitize_text_field( $_GET['max_price'] );
      }
      if ( isset( $_GET['available_only'] ) ) {
        $atts['available_only'] = sanitize_text_field( $_GET['available_only'] );
      }
      
      // Note: unit_type[] is read directly from $_GET in the query
      $atts['unit_type'] = isset( $_GET['unit_type'] ) ? $_GET['unit_type'] : [];
      
      $atts = shortcode_atts( [
        'featured'       => '',
        'limit'          => - 1, // -1 means show all posts (no limit)
        'beds'           => '',
        'min_price'      => '',
        'max_price'      => '',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'show_sort'      => 'yes',
        'show_filter'    => 'yes',
        'available_only' => '',
      ], $atts );
      
      // Parse orderby for sorting
      $order    = 'DESC';
      $meta_key = null;
      
      switch ( $atts['orderby'] ) {
        case 'price_asc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_price';
          $order    = 'ASC';
          break;
        case 'price_desc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_price';
          $order    = 'DESC';
          break;
        case 'sqft_asc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_sqft';
          $order    = 'ASC';
          break;
        case 'sqft_desc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_sqft';
          $order    = 'DESC';
          break;
        case 'date':
        default:
          $orderby = 'date';
          $order   = 'DESC';
          break;
      }
      
      // Cache key
      $cache_key = 'ffp_' . md5( serialize( $atts ) );
      $output    = wp_cache_get( $cache_key );
      
      if ( $output !== false ) {
        return $output;
      }
      
      // Build query args
      $args = [
        'post_type'      => 'floor_plan',
        'posts_per_page' => intval( $atts['limit'] ),
        'post_status'    => 'publish',
        'orderby'        => $orderby,
        'order'          => $order,
        'meta_query'     => [
          [
            'key'     => '_ffp_active',
            'value'   => '1',
            'compare' => '=',
          ],
        ],
      ];
      
      // Add meta_key if sorting by meta value
      if ( $meta_key ) {
        $args['meta_key'] = $meta_key;
      }
      
      // Featured filter
      if ( ! empty( $atts['featured'] ) ) {
        $args['meta_query'][] = [
          'key'     => '_ffp_featured',
          'value'   => '1',
          'compare' => '=',
        ];
      }
      
      // Bedrooms filter - handle both single beds and unit_type array
      if ( isset( $_GET['unit_type'] ) && is_array( $_GET['unit_type'] ) && ! empty( $_GET['unit_type'] ) ) {
        $args['meta_query'][] = [
          'key'     => '_ffp_bedrooms',
          'value'   => array_map( 'intval', $_GET['unit_type'] ),
          'compare' => 'IN',
        ];
      } elseif ( ! empty( $atts['beds'] ) ) {
        $args['meta_query'][] = [
          'key'     => '_ffp_bedrooms',
          'value'   => $atts['beds'],
          'compare' => '=',
        ];
      }
      
      // Price filters - support BETWEEN, >=, <= correctly
      if ( $atts['min_price'] !== '' || $atts['max_price'] !== '' ) {
        $min = $atts['min_price'] !== '' ? intval( $atts['min_price'] ) : null;
        $max = $atts['max_price'] !== '' ? intval( $atts['max_price'] ) : null;
        
        if ( $min !== null && $max !== null ) {
          if ( $min > $max ) {
            // Swap if provided in reverse
            $tmp = $min;
            $min = $max;
            $max = $tmp;
          }
          $args['meta_query'][] = [
            'key'     => '_ffp_price',
            'value'   => [ $min, $max ],
            'compare' => 'BETWEEN',
            'type'    => 'NUMERIC',
          ];
        } elseif ( $min !== null ) {
          $args['meta_query'][] = [
            'key'     => '_ffp_price',
            'value'   => $min,
            'compare' => '>=',
            'type'    => 'NUMERIC',
          ];
        } elseif ( $max !== null ) {
          $args['meta_query'][] = [
            'key'     => '_ffp_price',
            'value'   => $max,
            'compare' => '<=',
            'type'    => 'NUMERIC',
          ];
        }
      }
      
      // Available only filter - check if listing is actually available now (has "NOW" in availability)
      if ( ! empty( $atts['available_only'] ) ) {
        $args['meta_query'][] = [
          'key'     => '_ffp_available',
          'value'   => 'NOW',
          'compare' => 'LIKE',
        ];
      }
      
      // Execute query
      $query = new WP_Query( $args );
      
      ob_start();
      
      // Start main wrapper for filters and content
      echo '<div class="ffp-main-wrapper">';
      
      // Add filter sidebar if enabled
      if ( $atts['show_filter'] === 'yes' ) {
        $this->render_filters( $atts );
      }
      
      // Start content area
      echo '<div class="ffp-content-area">';
      
      // Add sorting dropdown if enabled
      if ( $atts['show_sort'] === 'yes' ) {
        echo '<div class="ffp-sort-container">';
        echo '<div class="ffp-sort-wrapper">';
        echo '<select id="ffp-sort-' . uniqid() . '" class="ffp-sort-select">';
        echo '<option value="date" ' . selected( $atts['orderby'], 'date', false ) . '>Most Recent</option>';
        echo '<option value="price_asc" ' . selected( $atts['orderby'], 'price_asc', false ) . '>Rent - Low to High</option>';
        echo '<option value="price_desc" ' . selected( $atts['orderby'], 'price_desc', false ) . '>Rent - High to Low</option>';
        echo '<option value="sqft_asc" ' . selected( $atts['orderby'], 'sqft_asc', false ) . '>Square Feet - Low to High</option>';
        echo '<option value="sqft_desc" ' . selected( $atts['orderby'], 'sqft_desc', false ) . '>Square Feet - High to Low</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
      }
      
      echo '<div class="ffp-grid" data-sort="' . esc_attr( $atts['orderby'] ) . '">';
      
      if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
          $query->the_post();
          $this->render_card();
        }
      } else {
        echo '<p>No floor plans found.</p>';
      }
      
      echo '</div>'; // Close .ffp-grid
      echo '</div>'; // Close .ffp-content-area
      echo '</div>'; // Close .ffp-main-wrapper
      
      wp_reset_postdata();
      
      $output = ob_get_clean();
      
      // Cache for 10 minutes
      wp_cache_set( $cache_key, $output, '', 600 );
      
      return $output;
    }
    
    private function render_filters( $atts ) {
      $available_checked = ! empty( $atts['available_only'] ) ? 'checked' : '';
      $unit_types        = isset( $_GET['unit_type'] ) && is_array( $_GET['unit_type'] ) ? $_GET['unit_type'] : [];
      $min_price         = isset( $_GET['min_price'] ) ? sanitize_text_field( $_GET['min_price'] ) : '';
      $max_price         = isset( $_GET['max_price'] ) ? sanitize_text_field( $_GET['max_price'] ) : '';
      ?>
        <div class="ffp-filters-sidebar">
            <div class="ffp-filters-inner">
                <div class="ffp-filter-section">
                    <h3>Show Available Now</h3>
                    <label class="ffp-checkbox-label">
                        <input type="checkbox" class="ffp-filter-checkbox"
                               name="available_only" <?php echo $available_checked; ?>>
                        <span>Show Available Now Only</span>
                    </label>
                </div>

                <div class="ffp-filter-section">
                    <h3>Unit Type:</h3>
                    <div class="ffp-checkbox-grid">
                        <label class="ffp-checkbox-label">
                            <input type="checkbox" class="ffp-filter-checkbox" name="unit_type[]"
                                   value="0" <?php echo( in_array( '0', $unit_types ) ? 'checked' : '' ); ?>>
                            <span>Studio</span>
                        </label>
                        <label class="ffp-checkbox-label">
                            <input type="checkbox" class="ffp-filter-checkbox" name="unit_type[]"
                                   value="1" <?php echo( in_array( '1', $unit_types ) ? 'checked' : '' ); ?>>
                            <span>1 Bedroom</span>
                        </label>
                        <label class="ffp-checkbox-label">
                            <input type="checkbox" class="ffp-filter-checkbox" name="unit_type[]"
                                   value="2" <?php echo( in_array( '2', $unit_types ) ? 'checked' : '' ); ?>>
                            <span>2 Bedrooms</span>
                        </label>
                        <label class="ffp-checkbox-label">
                            <input type="checkbox" class="ffp-filter-checkbox" name="unit_type[]"
                                   value="3" <?php echo( in_array( '3', $unit_types ) ? 'checked' : '' ); ?>>
                            <span>3 Bedrooms</span>
                        </label>
                        <label class="ffp-checkbox-label">
                            <input type="checkbox" class="ffp-filter-checkbox" name="unit_type[]"
                                   value="4" <?php echo( in_array( '4', $unit_types ) ? 'checked' : '' ); ?>>
                            <span>4 Bedrooms</span>
                        </label>
                        <label class="ffp-checkbox-label">
                            <input type="checkbox" class="ffp-filter-checkbox" name="unit_type[]"
                                   value="5" <?php echo( in_array( '5', $unit_types ) ? 'checked' : '' ); ?>>
                            <span>5 Bedrooms</span>
                        </label>
                    </div>
                </div>

                <div class="ffp-filter-section">
                    <h3>Price:</h3>
                    <div class="ffp-price-slider">
                        <div class="ffp-slider-values">
                            <span class="ffp-price-min">$<span
                                        id="ffp-min-display"><?php echo $min_price ?: '0'; ?></span></span>
                            <span class="ffp-price-max">$<span
                                        id="ffp-max-display"><?php echo $max_price ?: '10000'; ?></span></span>
                        </div>
                        <input type="hidden" id="ffp-min-price" value="<?php echo $min_price ?: '0'; ?>">
                        <input type="hidden" id="ffp-max-price" value="<?php echo $max_price ?: '10000'; ?>">
                        <div id="ffp-price-range" class="ffp-range-slider"></div>
                    </div>
                </div>

                <button type="button" class="ffp-reset-btn"
                        onclick="window.location.href='<?php echo esc_url( remove_query_arg( [
                          'beds',
                          'min_price',
                          'max_price',
                          'available_only'
                        ] ) ); ?>'">RESET
                </button>
            </div>
        </div>
      <?php
    }
    
    private function render_card() {
      $template = FFP_PLUGIN_DIR . 'templates/parts/card-floor-plan.php';
      
      if ( file_exists( $template ) ) {
        include $template;
      } else {
        // Fallback inline template
        ?>
          <div class="ffp-card">
            <?php if ( has_post_thumbnail() ): ?>
                <div class="ffp-card-image">
                    <a href="<?php the_permalink(); ?>">
                      <?php the_post_thumbnail( 'ffp_card' ); ?>
                    </a>
                </div>
            <?php endif; ?>
              <div class="ffp-card-content">
                  <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                  <div class="ffp-card-meta">
                    <?php if ( $price = get_post_meta( get_the_ID(), '_ffp_price', true ) ): ?>
                        <span class="ffp-price">$<?php echo number_format( $price ); ?></span>
                    <?php endif; ?>
                    <?php if ( $beds = get_post_meta( get_the_ID(), '_ffp_bedrooms', true ) ): ?>
                        <span class="ffp-beds"><?php echo esc_html( $beds ); ?> bed<?php echo $beds != 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ( $baths = get_post_meta( get_the_ID(), '_ffp_bathrooms', true ) ): ?>
                        <span class="ffp-baths"><?php echo esc_html( $baths ); ?> bath<?php echo $baths != 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ( $sqft = get_post_meta( get_the_ID(), '_ffp_sqft', true ) ): ?>
                        <span class="ffp-sqft"><?php echo number_format( $sqft ); ?> sq ft</span>
                    <?php endif; ?>
                  </div>
                  <a href="<?php the_permalink(); ?>" class="ffp-view-details">View Details</a>
              </div>
          </div>
        <?php
      }
    }
    
    /**
     * AJAX handler to return just the grid results
     */
    public function ajax_filter() {
      // Collect params from POST
      $atts              = [
        'featured'       => '',
        'limit'          => 12,
        'beds'           => isset( $_POST['beds'] ) ? sanitize_text_field( $_POST['beds'] ) : '',
        'min_price'      => isset( $_POST['min_price'] ) ? sanitize_text_field( $_POST['min_price'] ) : '',
        'max_price'      => isset( $_POST['max_price'] ) ? sanitize_text_field( $_POST['max_price'] ) : '',
        'orderby'        => isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'date',
        'order'          => 'DESC',
        'available_only' => isset( $_POST['available_only'] ) ? sanitize_text_field( $_POST['available_only'] ) : '',
        'show_sort'      => 'no',
        'show_filter'    => 'no',
      ];
      $_GET['unit_type'] = isset( $_POST['unit_type'] ) ? (array) $_POST['unit_type'] : [];
      
      // Reuse render logic but only output the grid contents
      // Build the same query args as render_shortcode up to WP_Query
      // We'll inline a minimal duplication for clarity
      // Sorting
      $order    = 'DESC';
      $meta_key = null;
      switch ( $atts['orderby'] ) {
        case 'price_asc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_price';
          $order    = 'ASC';
          break;
        case 'price_desc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_price';
          $order    = 'DESC';
          break;
        case 'sqft_asc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_sqft';
          $order    = 'ASC';
          break;
        case 'sqft_desc':
          $orderby  = 'meta_value_num';
          $meta_key = '_ffp_sqft';
          $order    = 'DESC';
          break;
        default:
          $orderby = 'date';
          $order   = 'DESC';
      }
      $args = [
        'post_type'      => 'floor_plan',
        'posts_per_page' => intval( $atts['limit'] ),
        'post_status'    => 'publish',
        'orderby'        => $orderby,
        'order'          => $order,
        'meta_query'     => [
          [ 'key' => '_ffp_active', 'value' => '1', 'compare' => '=' ],
        ],
      ];
      if ( $meta_key ) {
        $args['meta_key'] = $meta_key;
      }
      // Bedrooms filter
      if ( ! empty( $_GET['unit_type'] ) ) {
        $args['meta_query'][] = [
          'key'     => '_ffp_bedrooms',
          'value'   => array_map( 'intval', $_GET['unit_type'] ),
          'compare' => 'IN'
        ];
      } elseif ( ! empty( $atts['beds'] ) ) {
        $args['meta_query'][] = [ 'key' => '_ffp_bedrooms', 'value' => $atts['beds'], 'compare' => '=' ];
      }
      // Price filters
      if ( $atts['min_price'] !== '' || $atts['max_price'] !== '' ) {
        $min = $atts['min_price'] !== '' ? intval( $atts['min_price'] ) : null;
        $max = $atts['max_price'] !== '' ? intval( $atts['max_price'] ) : null;
        if ( $min !== null && $max !== null ) {
          if ( $min > $max ) {
            $t   = $min;
            $min = $max;
            $max = $t;
          }
          $args['meta_query'][] = [
            'key'     => '_ffp_price',
            'value'   => [ $min, $max ],
            'compare' => 'BETWEEN',
            'type'    => 'NUMERIC'
          ];
        } elseif ( $min !== null ) {
          $args['meta_query'][] = [ 'key' => '_ffp_price', 'value' => $min, 'compare' => '>=', 'type' => 'NUMERIC' ];
        } else {
          $args['meta_query'][] = [ 'key' => '_ffp_price', 'value' => $max, 'compare' => '<=', 'type' => 'NUMERIC' ];
        }
      }
      if ( ! empty( $atts['available_only'] ) ) {
        $args['meta_query'][] = [ 'key' => '_ffp_available', 'value' => 'NOW', 'compare' => 'LIKE' ];
      }
      
      $query = new WP_Query( $args );
      ob_start();
      if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
          $query->the_post();
          $this->render_card();
        }
      } else {
        echo '<p>No floor plans found.</p>';
      }
      wp_reset_postdata();
      $html = ob_get_clean();
      wp_send_json_success( [ 'html' => $html ] );
    }
    
    public function template_include( $template ) {
      if ( is_post_type_archive( 'floor_plan' ) ) {
        $template_file = FFP_PLUGIN_DIR . 'templates/archive-floor_plan.php';
        if ( file_exists( $template_file ) ) {
          return $template_file;
        }
      }
      
      if ( is_singular( 'floor_plan' ) ) {
        $template_file = FFP_PLUGIN_DIR . 'templates/single-floor_plan.php';
        if ( file_exists( $template_file ) ) {
          return $template_file;
        }
      }
      
      return $template;
    }
    
    /**
     * Alternative hook for single template hierarchy
     * This provides better compatibility with themes
     */
    public function single_template( $template ) {
      global $post;
      
      if ( ! $post || $post->post_type !== 'floor_plan' ) {
        return $template;
      }
      
      $template_file = FFP_PLUGIN_DIR . 'templates/single-floor_plan.php';
      if ( file_exists( $template_file ) ) {
        return $template_file;
      }
      
      return $template;
    }
    
    /**
     * Render contact form modal in footer
     */
    public function render_contact_modal() {
      // Only load on floor plan pages or pages with the shortcode
      $post          = get_post();
      $has_shortcode = false;
      
      if ( $post instanceof WP_Post ) {
        $has_shortcode = has_shortcode( $post->post_content, 'farmers_floor_plans' );
      }
      
      if ( ! is_singular( 'floor_plan' ) && ! is_post_type_archive( 'floor_plan' ) && ! $has_shortcode ) {
        return;
      }
      ?>
        <!-- Contact Us Modal -->
        <div id="ffp-contact-modal" class="ffp-modal" style="display: none;">
            <div class="ffp-modal-overlay"></div>
            <div class="ffp-modal-content">
                <button type="button" class="ffp-modal-close">&times;</button>
                <h2 class="ffp-modal-title">Contact Us</h2>
                <p class="ffp-modal-subtitle">Interested in <strong id="ffp-modal-listing-title"></strong>? Let us know!
                </p>

                <form id="ffp-contact-form" class="ffp-contact-form">
                    <input type="hidden" name="listing_id" id="ffp-listing-id">
                    <input type="hidden" name="listable_uid" id="ffp-listable-uid">
                    <input type="hidden" name="source_id" id="ffp-source-id">
                    <input type="hidden" name="listing_url" id="ffp-listing-url">
                    <input type="hidden" name="action" value="ffp_contact_form">
                  <?php wp_nonce_field( 'ffp_contact_form', 'ffp_contact_nonce' ); ?>

                    <!-- Required Fields -->
                    <div class="ffp-form-row">
                        <label for="ffp-move-in">Move In Date <span class="required">*</span></label>
                        <input type="date" id="ffp-move-in" name="move_in" required>
                    </div>

                    <div class="ffp-form-grid">
                        <div class="ffp-form-col">
                            <label for="ffp-first-name">First Name <span class="required">*</span></label>
                            <input type="text" id="ffp-first-name" name="first_name" required>
                        </div>
                        <div class="ffp-form-col">
                            <label for="ffp-last-name">Last Name <span class="required">*</span></label>
                            <input type="text" id="ffp-last-name" name="last_name" required>
                        </div>
                    </div>

                    <div class="ffp-form-row">
                        <label for="ffp-email">Email <span class="required">*</span></label>
                        <input type="email" id="ffp-email" name="email" required>
                    </div>

                    <div class="ffp-form-row">
                        <label for="ffp-phone">Phone</label>
                        <input type="tel" id="ffp-phone" name="phone">
                    </div>

                    <div class="ffp-form-row">
                        <label for="ffp-message">Message</label>
                        <textarea id="ffp-message" name="message" rows="4"></textarea>
                    </div>

                    <!-- Optional Information Toggle -->
                    <div class="ffp-form-row">
                        <a href="#" class="ffp-optional-toggle">+ Enter Optional Information</a>
                    </div>

                    <!-- Optional Fields (Hidden by default) -->
                    <div id="ffp-optional-fields" style="display: none;">
                        <p class="ffp-optional-info">Optionally, you may provide additional information about what you
                            are looking for so we can find the best rental to meet your needs.</p>

                        <div class="ffp-form-grid">
                            <div class="ffp-form-col">
                                <label for="ffp-monthly-income">Monthly Income</label>
                                <div class="ffp-input-addon">
                                    <span class="ffp-addon-prepend">$</span>
                                    <input type="number" id="ffp-monthly-income" name="monthly_income" step="0.01">
                                </div>
                            </div>
                            <div class="ffp-form-col">
                                <label for="ffp-credit-score">Credit Score</label>
                                <input type="text" id="ffp-credit-score" name="credit_score">
                            </div>
                        </div>

                        <div class="ffp-form-grid">
                            <div class="ffp-form-col">
                                <label for="ffp-max-rent">Maximum Rent</label>
                                <div class="ffp-input-addon">
                                    <span class="ffp-addon-prepend">$</span>
                                    <input type="number" id="ffp-max-rent" name="max_rent" step="0.01">
                                </div>
                            </div>
                            <div class="ffp-form-col">
                                <label for="ffp-additional-occupants">Additional Occupants</label>
                                <select id="ffp-additional-occupants" name="additional_occupants">
                                    <option value="">Select...</option>
                                  <?php for ( $i = 1; $i <= 9; $i ++ ): ?>
                                      <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                  <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="ffp-form-grid">
                            <div class="ffp-form-col">
                                <label for="ffp-desired-bedrooms">Desired Bedrooms</label>
                                <select id="ffp-desired-bedrooms" name="desired_bedrooms">
                                    <option value="">Select...</option>
                                  <?php for ( $i = 0; $i <= 10; $i ++ ): ?>
                                      <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                  <?php endfor; ?>
                                </select>
                            </div>
                            <div class="ffp-form-col">
                                <label for="ffp-desired-bathrooms">Desired Bathrooms</label>
                                <select id="ffp-desired-bathrooms" name="desired_bathrooms">
                                    <option value="">Select...</option>
                                  <?php for ( $i = 0; $i <= 9.5; $i += 0.5 ): ?>
                                      <option value="<?php echo number_format( $i, 1 ); ?>"><?php echo number_format( $i, 1 ); ?></option>
                                  <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="ffp-form-row">
                            <label class="ffp-form-label-bold">Pets</label>
                            <div class="ffp-checkbox-group">
                                <label class="ffp-checkbox-inline">
                                    <input type="checkbox" name="has_cats" value="1"> Cat
                                </label>
                                <label class="ffp-checkbox-inline">
                                    <input type="checkbox" name="has_dogs" value="1"> Dog
                                </label>
                                <label class="ffp-checkbox-inline">
                                    <input type="checkbox" name="has_other_pet" value="1"> Other
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="ffp-form-actions">
                        <button type="submit" class="ffp-btn ffp-btn-primary">
                            <span class="ffp-btn-text">Send Message</span>
                            <span class="ffp-btn-loading" style="display: none;">Sending...</span>
                        </button>
                    </div>

                    <div class="ffp-form-notice">
                        <p>By submitting my contact information, I agree to receive communication related to my interest
                            in available properties or unit(s). All information provided will be treated in accordance
                            with our Privacy Policy and Terms of Service.</p>
                    </div>

                    <div class="ffp-form-message" style="display: none;"></div>
                </form>
            </div>
        </div>
      <?php
    }
    
    /**
     * Handle contact form submission via AJAX
     */
    public function ajax_contact_form() {
      // Verify nonce
      if ( ! isset( $_POST['ffp_contact_nonce'] ) || ! wp_verify_nonce( $_POST['ffp_contact_nonce'], 'ffp_contact_form' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed. Please refresh the page and try again.' ] );
      }
      
      // Sanitize and validate required fields
      $listing_id   = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
      $listable_uid = isset( $_POST['listable_uid'] ) ? sanitize_text_field( $_POST['listable_uid'] ) : '';
      $source_id    = isset( $_POST['source_id'] ) ? sanitize_text_field( $_POST['source_id'] ) : '';
      $move_in      = isset( $_POST['move_in'] ) ? sanitize_text_field( $_POST['move_in'] ) : '';
      $first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
      $last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';
      $email        = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
      $phone        = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
      $message      = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
      
      // Validate required fields
      if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $move_in ) ) {
        wp_send_json_error( [ 'message' => 'Please fill in all required fields.' ] );
      }
      
      if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please provide a valid email address.' ] );
      }
      
      if ( empty( $listable_uid ) && ! empty( $source_id ) ) {
        $listable_uid = $source_id;
      }
      
      if ( empty( $listable_uid ) ) {
        wp_send_json_error( [ 'message' => 'Unable to determine the AppFolio listing ID. Please try again later.' ] );
      }
      
      // Get listing details
      $listing_title     = $listing_id ? get_the_title( $listing_id ) : 'Floor Plan Inquiry';
      $local_listing_url = isset( $_POST['listing_url'] ) ? esc_url_raw( $_POST['listing_url'] ) : '';
      
      if ( empty( $local_listing_url ) && $listing_id ) {
        $local_listing_url = get_permalink( $listing_id );
      }
      
      $appfolio_detail_url = $listing_id ? get_post_meta( $listing_id, '_ffp_source_url', true ) : '';
      
      if ( empty( $appfolio_detail_url ) && ! empty( $listable_uid ) ) {
        $appfolio_detail_url = 'https://cityblockprop.appfolio.com/listings/detail/' . $listable_uid;
      }
      
      // Optional fields
      $monthly_income       = isset( $_POST['monthly_income'] ) ? $this->sanitize_currency_field( $_POST['monthly_income'] ) : '';
      $credit_score         = isset( $_POST['credit_score'] ) ? sanitize_text_field( $_POST['credit_score'] ) : '';
      $desired_bedrooms     = ( isset( $_POST['desired_bedrooms'] ) && $_POST['desired_bedrooms'] !== '' ) ? sanitize_text_field( $_POST['desired_bedrooms'] ) : '';
      $desired_bathrooms    = ( isset( $_POST['desired_bathrooms'] ) && $_POST['desired_bathrooms'] !== '' ) ? sanitize_text_field( $_POST['desired_bathrooms'] ) : '';
      $max_rent             = isset( $_POST['max_rent'] ) ? $this->sanitize_currency_field( $_POST['max_rent'] ) : '';
      $additional_occupants = ( isset( $_POST['additional_occupants'] ) && $_POST['additional_occupants'] !== '' ) ? sanitize_text_field( $_POST['additional_occupants'] ) : '';
      $has_cats             = isset( $_POST['has_cats'] ) ? '1' : '0';
      $has_dogs             = isset( $_POST['has_dogs'] ) ? '1' : '0';
      $has_other_pet        = isset( $_POST['has_other_pet'] ) ? '1' : '0';
      
      $formatted_move_in = $this->format_move_in_date( $move_in );
      
      if ( empty( $formatted_move_in ) ) {
        wp_send_json_error( [ 'message' => 'Please provide a valid move-in date.' ] );
      }
      
      // Build payload for AppFolio
      // Note: No authenticity token or cookies needed - the terminal cURL command proved this works without them
      $appfolio_payload = [
        'listable_uid'                                                    => $listable_uid,
        'contact_us_form[guest_card][move_in]'                            => $formatted_move_in,
        'contact_us_form[guest_card][prospect_attributes][first_name]'    => $first_name,
        'contact_us_form[guest_card][prospect_attributes][last_name]'     => $last_name,
        'contact_us_form[guest_card][prospect_attributes][email_address]' => $email,
        'contact_us_form[guest_card][prospect_attributes][phone_number]'  => $phone,
        'contact_us_form[guest_card][comments]'                           => $message,
        'contact_us_form[guest_card][monthly_income]'                     => $monthly_income,
        'contact_us_form[guest_card][credit_score]'                       => $credit_score,
        'contact_us_form[guest_card][bedrooms]'                           => $desired_bedrooms,
        'contact_us_form[guest_card][bathrooms]'                          => $desired_bathrooms,
        'contact_us_form[guest_card][max_rent]'                           => $max_rent,
        'contact_us_form[guest_card][additional_occupants]'               => $additional_occupants,
        'contact_us_form[guest_card][has_cats]'                           => $has_cats,
        'contact_us_form[guest_card][has_dogs]'                           => $has_dogs,
        'contact_us_form[guest_card][has_other_pet]'                      => $has_other_pet,
        'contact_us_form[guest_card][source]'                             => '',
        'commit'                                                          => 'Contact Us',
      ];
      
      // Use native PHP cURL (bypasses WordPress's HTTP client which is blocked by AppFolio's WAF)
      $result = $this->post_with_native_curl(
        'https://cityblockprop.appfolio.com/listings/guest_cards',
        $appfolio_payload
      );
      
      if ( $result['error'] ) {
        FFP_Logger::log( 'Contact form submission failed: ' . $result['error'], 'error' );
        wp_send_json_error( [ 'message' => 'Unable to reach leasing team right now. Please try again shortly or call (470) 622-2072.' ] );
      }
      
      $status_code = $result['status'];
      $body        = $result['body'];
      
      if ( $status_code < 200 || $status_code >= 300 ) {
        FFP_Logger::log( 'Contact form submission failed (HTTP ' . $status_code . '): ' . substr( $body, 0, 500 ), 'error' );
        wp_send_json_error( [ 'message' => 'There was an issue submitting your message. Please try again or call (470) 622-2072.' ] );
      }
      
      $confirmation_message = $this->extract_confirmation_message( $body );
      
      FFP_Logger::log( "Contact form submitted to AppFolio for listing #{$listing_id} ({$listable_uid}) by {$first_name} {$last_name} ({$email})", 'info' );
      
      wp_send_json_success( [
        'message'       => $confirmation_message,
        'listing_title' => $listing_title,
        'listing_url'   => $local_listing_url,
      ] );
    }
    
    /**
     * Remove non-numeric characters from currency-esque inputs
     */
    private function sanitize_currency_field( $value ) {
      $value = is_string( $value ) ? trim( $value ) : '';
      if ( $value === '' ) {
        return '';
      }
      
      $clean = preg_replace( '/[^0-9.]/', '', $value );
      
      return $clean;
    }
    
    /**
     * Format move-in date to mm/dd/YYYY
     */
    private function format_move_in_date( $date ) {
      if ( empty( $date ) ) {
        return '';
      }
      
      try {
        $date_obj = new DateTime( $date );
      } catch ( Exception $e ) {
        return '';
      }
      
      return $date_obj->format( 'm/d/Y' );
    }
    
    /**
     * POST using native PHP cURL with options that mimic the terminal cURL command
     */
    private function post_with_native_curl( $url, $data ) {
      if ( ! function_exists( 'curl_init' ) ) {
        return [
          'error'  => 'cURL extension is not available on this server',
          'status' => 0,
          'body'   => '',
        ];
      }
      
      $ch = curl_init( $url );
      
      if ( ! $ch ) {
        return [
          'error'  => 'Failed to initialize cURL',
          'status' => 0,
          'body'   => '',
        ];
      }
      
      // Build the POST data string
      $post_data = http_build_query( $data );
      
      // Set options to mimic the exact cURL command that works from terminal
      curl_setopt_array( $ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
          'Content-Type: application/x-www-form-urlencoded',
        ],
        // Don't set a custom User-Agent - use cURL's default
        // This is key: terminal cURL uses "curl/X.X.X" as UA
        CURLOPT_USERAGENT      => '', // Empty = use cURL default
        // SSL verification (keep enabled for security)
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
      ] );
      
      $response_body = curl_exec( $ch );
      $status_code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
      $error         = curl_error( $ch );
      
      curl_close( $ch );
      
      if ( $error ) {
        return [
          'error'  => 'cURL error: ' . $error,
          'status' => $status_code,
          'body'   => '',
        ];
      }
      
      return [
        'error'  => null,
        'status' => $status_code,
        'body'   => $response_body,
      ];
    }
    
    /**
     * Extract a human-friendly confirmation message from AppFolio response
     */
    private function extract_confirmation_message( $response_body ) {
      $default_message = 'Thank you! We will contact you shortly. If you have any questions, please call (470) 622-2072.';
      
      if ( empty( $response_body ) ) {
        return $default_message;
      }
      
      if ( preg_match( '/modal_title\.text\(\s*\'(.*?)\'\s*\)/', $response_body, $title_match ) ) {
        $title = strip_tags( stripslashes( $title_match[1] ) );
        if ( ! empty( $title ) ) {
          $default_message = esc_html( $title );
        }
      }
      
      if ( preg_match( '/modal_body\.html\("(.*)"\);/sU', $response_body, $body_match ) ) {
        $html = stripslashes( $body_match[1] );
        $html = str_replace( [ '\n', '\t' ], ' ', $html );
        $text = wp_strip_all_tags( $html );
        $text = preg_replace( '/\s+/', ' ', $text );
        if ( ! empty( $text ) ) {
          $default_message = trim( $text );
        }
      }
      
      return $default_message;
    }
  }

