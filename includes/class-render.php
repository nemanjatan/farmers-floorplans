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
        'limit'          => 12,
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
        $args['meta_query'][] = [ 'key'     => '_ffp_bedrooms',
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
          $args['meta_query'][] = [ 'key'     => '_ffp_price',
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
  }

