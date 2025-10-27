<?php
/**
 * Single floor plan template
 */

get_header();
?>

<div class="site-content">
    <?php while (have_posts()): the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>
            
            <div class="entry-content">
                <?php if (has_post_thumbnail()): ?>
                    <div class="floor-plan-featured-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="floor-plan-details">
                    <?php if ($price = get_post_meta(get_the_ID(), '_ffp_price', true)): ?>
                        <div class="detail-item">
                            <strong>Price</strong>
                            <span>$<?php echo number_format($price); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($beds = get_post_meta(get_the_ID(), '_ffp_bedrooms', true)): ?>
                        <div class="detail-item">
                            <strong>Bedrooms</strong>
                            <span><?php echo esc_html($beds); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($baths = get_post_meta(get_the_ID(), '_ffp_bathrooms', true)): ?>
                        <div class="detail-item">
                            <strong>Bathrooms</strong>
                            <span><?php echo esc_html($baths); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($sqft = get_post_meta(get_the_ID(), '_ffp_sqft', true)): ?>
                        <div class="detail-item">
                            <strong>Square Feet</strong>
                            <span><?php echo number_format($sqft); ?> sq ft</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($address = get_post_meta(get_the_ID(), '_ffp_address', true)): ?>
                        <div class="detail-item">
                            <strong>Address</strong>
                            <span><?php echo esc_html($address); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($available = get_post_meta(get_the_ID(), '_ffp_available', true)): ?>
                        <div class="detail-item">
                            <strong>Available</strong>
                            <span><?php echo esc_html($available); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php the_content(); ?>
                
                <?php
                $gallery = FFP_Images::get_gallery(get_the_ID());
                if (!empty($gallery)):
                ?>
                    <div class="floor-plan-gallery">
                        <h2>Gallery</h2>
                        <div class="gallery-grid">
                            <?php foreach ($gallery as $image): ?>
                                <a href="<?php echo esc_url($image['url']); ?>" class="gallery-item">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="" loading="lazy" />
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endwhile; ?>
</div>

<?php
get_footer();

