<?php
/**
 * Frontend rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class FFP_Render {
    
    public function __construct() {
        add_shortcode('farmers_floor_plans', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('template_include', [$this, 'template_include']);
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('ffp-front', FFP_PLUGIN_URL . 'assets/front.css', [], FFP_VERSION);
        wp_enqueue_script('ffp-front', FFP_PLUGIN_URL . 'assets/front.js', ['jquery'], FFP_VERSION, true);
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'featured' => '',
            'limit' => 12,
            'beds' => '',
            'min_price' => '',
            'max_price' => '',
            'orderby' => 'date',
        ], $atts);
        
        // Cache key
        $cache_key = 'ffp_' . md5(serialize($atts));
        $output = wp_cache_get($cache_key);
        
        if ($output !== false) {
            return $output;
        }
        
        // Build query args
        $args = [
            'post_type' => 'floor_plan',
            'posts_per_page' => intval($atts['limit']),
            'post_status' => 'publish',
            'orderby' => $atts['orderby'],
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_ffp_active',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ];
        
        // Featured filter
        if (!empty($atts['featured'])) {
            $args['meta_query'][] = [
                'key' => '_ffp_featured',
                'value' => '1',
                'compare' => '=',
            ];
        }
        
        // Bedrooms filter
        if (!empty($atts['beds'])) {
            $args['meta_query'][] = [
                'key' => '_ffp_bedrooms',
                'value' => $atts['beds'],
                'compare' => '=',
            ];
        }
        
        // Price filters
        if (!empty($atts['min_price']) || !empty($atts['max_price'])) {
            $price_query = [
                'key' => '_ffp_price',
                'type' => 'NUMERIC',
            ];
            
            if (!empty($atts['min_price'])) {
                $price_query['value'] = intval($atts['min_price']);
                $price_query['compare'] = '>=';
            }
            
            if (!empty($atts['max_price'])) {
                $price_query['value'] = intval($atts['max_price']);
                $price_query['compare'] = '<=';
            }
            
            $args['meta_query'][] = $price_query;
        }
        
        // Execute query
        $query = new WP_Query($args);
        
        ob_start();
        
        if ($query->have_posts()) {
            echo '<div class="ffp-grid">';
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_card();
            }
            echo '</div>';
        } else {
            echo '<p>No floor plans found.</p>';
        }
        
        wp_reset_postdata();
        
        $output = ob_get_clean();
        
        // Cache for 10 minutes
        wp_cache_set($cache_key, $output, '', 600);
        
        return $output;
    }
    
    private function render_card() {
        $template = FFP_PLUGIN_DIR . 'templates/parts/card-floor-plan.php';
        
        if (file_exists($template)) {
            include $template;
        } else {
            // Fallback inline template
            ?>
            <div class="ffp-card">
                <?php if (has_post_thumbnail()): ?>
                    <div class="ffp-card-image">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail('ffp_card'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <div class="ffp-card-content">
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <div class="ffp-card-meta">
                        <?php if ($price = get_post_meta(get_the_ID(), '_ffp_price', true)): ?>
                            <span class="ffp-price">$<?php echo number_format($price); ?></span>
                        <?php endif; ?>
                        <?php if ($beds = get_post_meta(get_the_ID(), '_ffp_bedrooms', true)): ?>
                            <span class="ffp-beds"><?php echo esc_html($beds); ?> bed<?php echo $beds != 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ($baths = get_post_meta(get_the_ID(), '_ffp_bathrooms', true)): ?>
                            <span class="ffp-baths"><?php echo esc_html($baths); ?> bath<?php echo $baths != 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ($sqft = get_post_meta(get_the_ID(), '_ffp_sqft', true)): ?>
                            <span class="ffp-sqft"><?php echo number_format($sqft); ?> sq ft</span>
                        <?php endif; ?>
                    </div>
                    <a href="<?php the_permalink(); ?>" class="ffp-view-details">View Details</a>
                </div>
            </div>
            <?php
        }
    }
    
    public function template_include($template) {
        if (is_post_type_archive('floor_plan')) {
            $template_file = FFP_PLUGIN_DIR . 'templates/archive-floor_plan.php';
            if (file_exists($template_file)) {
                return $template_file;
            }
        }
        
        if (is_singular('floor_plan')) {
            $template_file = FFP_PLUGIN_DIR . 'templates/single-floor_plan.php';
            if (file_exists($template_file)) {
                return $template_file;
            }
        }
        
        return $template;
    }
}

