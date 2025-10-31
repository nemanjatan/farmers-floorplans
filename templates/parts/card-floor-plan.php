<?php
  /**
   * Floor plan card template part
   */
  
  $price = get_post_meta( get_the_ID(), '_ffp_price', true );
  $beds  = get_post_meta( get_the_ID(), '_ffp_bedrooms', true );
  $baths = get_post_meta( get_the_ID(), '_ffp_bathrooms', true );
  $sqft  = get_post_meta( get_the_ID(), '_ffp_sqft', true );
?>

<div class="ffp-card">
  <?php
    $gallery = FFP_Images::get_gallery( get_the_ID() );
    if ( ! empty( $gallery ) ): ?>
        <div class="ffp-card-image-carousel">
            <div class="ffp-card-gallery" data-carousel>
              <?php foreach ( $gallery as $index => $image ): ?>
                  <div class="ffp-gallery-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                      <a href="<?php the_permalink(); ?>">
                          <img src="<?php echo esc_url( $image['url'] ); ?>"
                               alt="<?php echo esc_attr( get_the_title() ); ?>"
                               loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"/>
                      </a>
                  </div>
              <?php endforeach; ?>
            </div>
          
          <?php if ( count( $gallery ) > 1 ): ?>
              <div class="ffp-gallery-nav">
                  <button class="ffp-gallery-prev">‹</button>
                  <button class="ffp-gallery-next">›</button>
              </div>
              <div class="ffp-gallery-dots">
                <?php foreach ( $gallery as $index => $image ): ?>
                    <button class="ffp-gallery-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                            data-slide="<?php echo $index; ?>"></button>
                <?php endforeach; ?>
              </div>
          <?php endif; ?>
        </div>
    <?php elseif ( has_post_thumbnail() ): ?>
        <div class="ffp-card-image">
            <a href="<?php the_permalink(); ?>">
              <?php the_post_thumbnail( 'ffp_card', [ 'loading' => 'lazy' ] ); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="ffp-card-content">
        <h3 class="ffp-card-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <div class="ffp-card-meta">
          <?php if ( $price ): ?>
              <span class="ffp-price">$<?php echo number_format( $price ); ?></span>
          <?php endif; ?>
          
          <?php if ( $beds ): ?>
              <span class="ffp-beds"><?php echo esc_html( $beds ); ?> bed<?php echo $beds != 1 ? 's' : ''; ?></span>
          <?php endif; ?>
          
          <?php if ( $baths ): ?>
              <span class="ffp-baths"><?php echo esc_html( $baths ); ?> bath<?php echo $baths != 1 ? 's' : ''; ?></span>
          <?php endif; ?>
          
          <?php if ( $sqft ): ?>
              <span class="ffp-sqft"><?php echo number_format( $sqft ); ?> sq ft</span>
          <?php endif; ?>
        </div>

        <a href="<?php the_permalink(); ?>" class="ffp-view-details">View Details</a>
    </div>
</div>

