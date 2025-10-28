<?php
/**
 * Custom Post Type Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class FFP_CPT {
    
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('after_setup_theme', [$this, 'add_image_sizes']);
        
        // Add custom columns to admin list
        add_filter('manage_floor_plan_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_floor_plan_posts_custom_column', [$this, 'render_custom_column'], 10, 2);
        add_filter('manage_edit-floor_plan_sortable_columns', [$this, 'make_columns_sortable']);
    }
    
    public function register_post_type() {
        $labels = [
            'name' => 'Floor Plans',
            'singular_name' => 'Floor Plan',
            'menu_name' => 'Floor Plans',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Floor Plan',
            'edit_item' => 'Edit Floor Plan',
            'new_item' => 'New Floor Plan',
            'view_item' => 'View Floor Plan',
            'search_items' => 'Search Floor Plans',
            'not_found' => 'No floor plans found',
            'not_found_in_trash' => 'No floor plans found in trash',
        ];
        
        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'floor-plans'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-building',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'show_in_rest' => true,
        ];
        
        register_post_type('floor_plan', $args);
    }
    
    public function register_taxonomy() {
        $labels = [
            'name' => 'Bedrooms',
            'singular_name' => 'Bedroom',
            'search_items' => 'Search Bedrooms',
            'all_items' => 'All Bedrooms',
            'edit_item' => 'Edit Bedroom',
            'update_item' => 'Update Bedroom',
            'add_new_item' => 'Add New Bedroom',
            'new_item_name' => 'New Bedroom Name',
            'menu_name' => 'Bedrooms',
        ];
        
        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'bedrooms'],
            'show_in_rest' => true,
        ];
        
        register_taxonomy('bedrooms', ['floor_plan'], $args);
    }
    
    public function add_image_sizes() {
        add_image_size('ffp_card', 600, 400, true);
        add_image_size('ffp_featured', 800, 600, true);
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'ffp_details',
            'Floor Plan Details',
            [$this, 'render_meta_box'],
            'floor_plan',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('ffp_meta_box', 'ffp_meta_box_nonce');
        
        $source_id = get_post_meta($post->ID, '_ffp_source_id', true);
        $building = get_post_meta($post->ID, '_ffp_building', true);
        $address = get_post_meta($post->ID, '_ffp_address', true);
        $price = get_post_meta($post->ID, '_ffp_price', true);
        $bedrooms = get_post_meta($post->ID, '_ffp_bedrooms', true);
        $bathrooms = get_post_meta($post->ID, '_ffp_bathrooms', true);
        $sqft = get_post_meta($post->ID, '_ffp_sqft', true);
        $available = get_post_meta($post->ID, '_ffp_available', true);
        $active = get_post_meta($post->ID, '_ffp_active', true);
        $source_url = get_post_meta($post->ID, '_ffp_source_url', true);
        $featured = get_post_meta($post->ID, '_ffp_featured', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="ffp_source_id">Source ID</label></th>
                <td><input type="text" id="ffp_source_id" name="ffp_source_id" value="<?php echo esc_attr($source_id); ?>" class="regular-text" readonly /></td>
            </tr>
            <tr>
                <th><label for="ffp_building">Building</label></th>
                <td><input type="text" id="ffp_building" name="ffp_building" value="<?php echo esc_attr($building); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_address">Address</label></th>
                <td><input type="text" id="ffp_address" name="ffp_address" value="<?php echo esc_attr($address); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_price">Price</label></th>
                <td><input type="number" id="ffp_price" name="ffp_price" value="<?php echo esc_attr($price); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_bedrooms">Bedrooms</label></th>
                <td><input type="text" id="ffp_bedrooms" name="ffp_bedrooms" value="<?php echo esc_attr($bedrooms); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_bathrooms">Bathrooms</label></th>
                <td><input type="text" id="ffp_bathrooms" name="ffp_bathrooms" value="<?php echo esc_attr($bathrooms); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_sqft">Square Feet</label></th>
                <td><input type="number" id="ffp_sqft" name="ffp_sqft" value="<?php echo esc_attr($sqft); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_available">Available</label></th>
                <td><input type="text" id="ffp_available" name="ffp_available" value="<?php echo esc_attr($available); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ffp_active">Active</label></th>
                <td>
                    <input type="checkbox" id="ffp_active" name="ffp_active" value="1" <?php checked($active, '1'); ?> />
                    <label for="ffp_active">Show in listings</label>
                </td>
            </tr>
            <tr>
                <th><label for="ffp_featured">Featured</label></th>
                <td>
                    <input type="checkbox" id="ffp_featured" name="ffp_featured" value="1" <?php checked($featured, '1'); ?> />
                    <label for="ffp_featured">Feature on homepage</label>
                </td>
            </tr>
            <tr>
                <th><label for="ffp_source_url">Source URL</label></th>
                <td><input type="url" id="ffp_source_url" name="ffp_source_url" value="<?php echo esc_attr($source_url); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }
    
    public function save_meta_box($post_id) {
        if (!isset($_POST['ffp_meta_box_nonce']) || !wp_verify_nonce($_POST['ffp_meta_box_nonce'], 'ffp_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'floor_plan') {
            return;
        }
        
        $fields = [
            'ffp_source_id' => '_ffp_source_id',
            'ffp_building' => '_ffp_building',
            'ffp_address' => '_ffp_address',
            'ffp_price' => '_ffp_price',
            'ffp_bedrooms' => '_ffp_bedrooms',
            'ffp_bathrooms' => '_ffp_bathrooms',
            'ffp_sqft' => '_ffp_sqft',
            'ffp_available' => '_ffp_available',
            'ffp_source_url' => '_ffp_source_url',
        ];
        
        foreach ($fields as $input => $meta_key) {
            if (isset($_POST[$input])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$input]));
            }
        }
        
        // Handle checkboxes
        update_post_meta($post_id, '_ffp_active', isset($_POST['ffp_active']) ? '1' : '0');
        update_post_meta($post_id, '_ffp_featured', isset($_POST['ffp_featured']) ? '1' : '0');
    }
    
    /**
     * Add custom columns to admin list
     */
    public function add_custom_columns($columns) {
        // Remove default date column (we'll add it back later)
        unset($columns['date']);
        
        // Add custom columns after title
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['ffp_bedrooms'] = 'Bedrooms';
        $new_columns['ffp_bathrooms'] = 'Bathrooms';
        $new_columns['ffp_sqft'] = 'Sq Ft';
        $new_columns['ffp_price'] = 'Price';
        $new_columns['ffp_address'] = 'Address';
        $new_columns['ffp_available'] = 'Available';
        $new_columns['ffp_active'] = 'Active';
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Render custom column content
     */
    public function render_custom_column($column, $post_id) {
        switch ($column) {
            case 'ffp_bedrooms':
                $bedrooms = get_post_meta($post_id, '_ffp_bedrooms', true);
                echo $bedrooms ? esc_html($bedrooms) : '—';
                break;
                
            case 'ffp_bathrooms':
                $bathrooms = get_post_meta($post_id, '_ffp_bathrooms', true);
                echo $bathrooms ? esc_html($bathrooms) : '—';
                break;
                
            case 'ffp_sqft':
                $sqft = get_post_meta($post_id, '_ffp_sqft', true);
                echo $sqft ? esc_html(number_format($sqft)) : '—';
                break;
                
            case 'ffp_price':
                $price = get_post_meta($post_id, '_ffp_price', true);
                if ($price) {
                    echo '$' . esc_html(number_format($price));
                } else {
                    echo '—';
                }
                break;
                
            case 'ffp_address':
                $address = get_post_meta($post_id, '_ffp_address', true);
                echo $address ? esc_html($address) : '—';
                break;
                
            case 'ffp_available':
                $available = get_post_meta($post_id, '_ffp_available', true);
                if ($available) {
                    $status_class = strpos(strtolower($available), 'available') !== false ? 'status-available' : 'status-coming-soon';
                    echo '<span class="' . esc_attr($status_class) . '">' . esc_html($available) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'ffp_active':
                $active = get_post_meta($post_id, '_ffp_active', true);
                $icon = ($active === '1') ? '✓' : '✗';
                $class = ($active === '1') ? 'status-active' : 'status-inactive';
                echo '<span class="' . esc_attr($class) . '">' . $icon . '</span>';
                break;
        }
    }
    
    /**
     * Make columns sortable
     */
    public function make_columns_sortable($columns) {
        $columns['ffp_bedrooms'] = 'ffp_bedrooms';
        $columns['ffp_bathrooms'] = 'ffp_bathrooms';
        $columns['ffp_sqft'] = 'ffp_sqft';
        $columns['ffp_price'] = 'ffp_price';
        $columns['ffp_active'] = 'ffp_active';
        return $columns;
    }
}

