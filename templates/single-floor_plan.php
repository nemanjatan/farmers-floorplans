<?php
  /**
   * Single floor plan template
   */
  
  get_header();
?>

    <div class="site-content">
      <?php while ( have_posts() ): the_post(); ?>
          <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
              <header class="entry-header">
                  <h1 class="entry-title"><?php the_title(); ?></h1>
              </header>

              <div class="entry-content">
                <?php
                  // Get all meta fields
                  $building   = get_post_meta( get_the_ID(), '_ffp_building', true );
                  $address    = get_post_meta( get_the_ID(), '_ffp_address', true );
                  $price      = get_post_meta( get_the_ID(), '_ffp_price', true );
                  $bedrooms   = get_post_meta( get_the_ID(), '_ffp_bedrooms', true );
                  $bathrooms  = get_post_meta( get_the_ID(), '_ffp_bathrooms', true );
                  $sqft       = get_post_meta( get_the_ID(), '_ffp_sqft', true );
                  $available  = get_post_meta( get_the_ID(), '_ffp_available', true );
                  $source_url = get_post_meta( get_the_ID(), '_ffp_source_url', true );
                  $gallery    = FFP_Images::get_gallery( get_the_ID() );
                  
                  // Format available field - check if it says "NOW" or contains date
                  $available_display = $available;
                  if ( ! empty( $available ) ) {
                    $available_lower = strtolower( trim( $available ) );
                    if ( strpos( $available_lower, 'now' ) !== false || strpos( $available_lower, 'available now' ) !== false ) {
                      $available_display = 'NOW';
                    }
                  }
                  
                  // Extract unit number from address
                  // Format: "580 E Broad St - 302, Athens, GA 30601" -> Unit: 302
                  // Format: "580 E Broad St - 103 BTB, Athens, GA 30601" -> Unit: 103
                  $unit_number = '';
                  if ( ! empty( $address ) ) {
                    // Look for pattern: " - " followed by digits (and optionally letters like BTB)
                    if ( preg_match( '/\s-\s([0-9]+[A-Z]*)\b/', $address, $matches ) ) {
                      $unit_number = $matches[1];
                    }
                  }
                ?>

                  <div class="floor-plan-layout">
                    <?php if ( ! empty( $gallery ) ): ?>
                        <div class="floor-plan-gallery-main">
                            <div class="gallery-main-image">
                              <?php
                                // Use first gallery image or featured image
                                $main_image = ! empty( $gallery ) ? $gallery[0] : null;
                                if ( $main_image ) {
                                  echo '<img src="' . esc_url( $main_image['url'] ) . '" alt="' . esc_attr( get_the_title() ) . '" id="ffp-main-gallery-image"/>';
                                } elseif ( has_post_thumbnail() ) {
                                  the_post_thumbnail( 'large', [ 'id' => 'ffp-main-gallery-image' ] );
                                }
                              ?>
                            </div>
                          
                          <?php if ( count( $gallery ) > 1 ): ?>
                              <div class="gallery-thumbnails">
                                <?php foreach ( $gallery as $index => $image ): ?>
                                    <a href="<?php echo esc_url( $image['url'] ); ?>"
                                       class="gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                                       data-image-index="<?php echo $index; ?>"
                                       data-image-url="<?php echo esc_url( $image['url'] ); ?>">
                                        <img src="<?php echo esc_url( $image['url'] ); ?>"
                                             alt="Thumbnail <?php echo $index + 1; ?>" loading="lazy"/>
                                    </a>
                                <?php endforeach; ?>
                              </div>
                          <?php endif; ?>
                        </div>
                    <?php elseif ( has_post_thumbnail() ): ?>
                        <div class="floor-plan-featured-image">
                          <?php the_post_thumbnail( 'large' ); ?>
                        </div>
                    <?php endif; ?>

                      <div class="floor-plan-details">
                        <?php if ( ! empty( $unit_number ) ): ?>
                            <div class="detail-item">
                                <strong>Unit Number</strong>
                                <span><?php echo esc_html( $unit_number ); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $price ) ): ?>
                            <div class="detail-item">
                                <strong>Price</strong>
                                <span class="detail-price">$<?php echo number_format( $price ); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $bedrooms ) ): ?>
                            <div class="detail-item">
                                <strong>Bedrooms</strong>
                                <span><?php echo esc_html( $bedrooms ); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $bathrooms ) ): ?>
                            <div class="detail-item">
                                <strong>Bathrooms</strong>
                                <span><?php echo esc_html( $bathrooms ); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $sqft ) ): ?>
                            <div class="detail-item">
                                <strong>Square Feet</strong>
                                <span><?php echo number_format( $sqft ); ?> sq ft</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $available ) ): ?>
                            <div class="detail-item">
                                <strong>Available</strong>
                                <span class="detail-available"><?php echo esc_html( $available_display ); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                          // Get custom button URLs from settings (with fallback to AppFolio listing URL)
                          $apply_now_url  = get_option( 'ffp_apply_now_url', '' );
                          $contact_us_url = get_option( 'ffp_contact_us_url', '' );
                          
                          // Use custom URLs if set, otherwise fall back to source URL
                          $apply_now_url  = ! empty( $apply_now_url ) ? $apply_now_url : $source_url;
                          $contact_us_url = ! empty( $contact_us_url ) ? $contact_us_url : $source_url;
                          
                          // Only show buttons if at least one URL is available
                          if ( ! empty( $apply_now_url ) || ! empty( $contact_us_url ) ):
                        ?>
                            <div class="detail-actions">
                              <?php if ( ! empty( $apply_now_url ) ): ?>
                                  <a href="<?php echo esc_url( $apply_now_url ); ?>" 
                                     class="ffp-view-details" 
                                     target="_blank" 
                                     rel="noopener noreferrer">
                                      Apply Now
                                  </a>
                              <?php endif; ?>
                              
                              <?php if ( ! empty( $contact_us_url ) ): ?>
                                  <a href="<?php echo esc_url( $contact_us_url ); ?>" 
                                     class="ffp-view-details" 
                                     target="_blank" 
                                     rel="noopener noreferrer">
                                      Contact Us
                                  </a>
                              <?php endif; ?>
                            </div>
                        <?php endif; ?>
                      </div>
                  </div>
              </div>
          </article>
      <?php endwhile; ?>
    </div>

<?php
  get_footer();

