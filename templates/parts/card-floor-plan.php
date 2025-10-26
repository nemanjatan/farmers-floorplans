<?php
/**
 * Floor plan card template part
 */

$price = get_post_meta(get_the_ID(), '_ffp_price', true);
$beds = get_post_meta(get_the_ID(), '_ffp_bedrooms', true);
$baths = get_post_meta(get_the_ID(), '_ffp_bathrooms', true);
$sqft = get_post_meta(get_the_ID(), '_ffp_sqft', true);
?>

<div class="ffp-card">
    <?php if (has_post_thumbnail()): ?>
        <div class="ffp-card-image">
            <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('ffp_card', ['loading' => 'lazy']); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <div class="ffp-card-content">
        <h3 class="ffp-card-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        
        <div class="ffp-card-meta">
            <?php if ($price): ?>
                <span class="ffp-price">$<?php echo number_format($price); ?></span>
            <?php endif; ?>
            
            <?php if ($beds): ?>
                <span class="ffp-beds"><?php echo esc_html($beds); ?> bed<?php echo $beds != 1 ? 's' : ''; ?></span>
            <?php endif; ?>
            
            <?php if ($baths): ?>
                <span class="ffp-baths"><?php echo esc_html($baths); ?> bath<?php echo $baths != 1 ? 's' : ''; ?></span>
            <?php endif; ?>
            
            <?php if ($sqft): ?>
                <span class="ffp-sqft"><?php echo number_format($sqft); ?> sq ft</span>
            <?php endif; ?>
        </div>
        
        <a href="<?php the_permalink(); ?>" class="ffp-view-details">View Details</a>
    </div>
</div>

