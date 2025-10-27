<?php
// Check image attachments for floor plans
require_once('wp-load.php');

echo "=== Floor Plans Image Attachment Check ===\n\n";

$args = [
    'post_type' => 'floor_plan',
    'posts_per_page' => 10,
    'post_status' => 'any',
];

$query = new WP_Query($args);

echo "Checking {$query->found_posts} floor plans...\n\n";

if ($query->have_posts()) {
    $count = 0;
    while ($query->have_posts() && $count < 5) {
        $query->the_post();
        $post_id = get_the_ID();
        $count++;
        
        $has_thumbnail = has_post_thumbnail($post_id);
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $gallery_ids = get_post_meta($post_id, '_ffp_gallery_ids', true);
        
        echo sprintf(
            "Floor Plan #%d: %s\n",
            $post_id,
            get_the_title()
        );
        echo sprintf(
            "  Featured Image: %s (ID: %s)\n",
            $has_thumbnail ? 'YES' : 'NO',
            $thumbnail_id ?: 'NONE'
        );
        
        if (!empty($gallery_ids) && is_array($gallery_ids)) {
            echo sprintf(
                "  Gallery Images: %d images (IDs: %s)\n",
                count($gallery_ids),
                implode(', ', $gallery_ids)
            );
            
            // Check each gallery image
            foreach ($gallery_ids as $gallery_id) {
                $url = wp_get_attachment_url($gallery_id);
                echo sprintf(
                    "    - ID: %d → %s\n",
                    $gallery_id,
                    $url ? basename($url) : 'NOT FOUND'
                );
            }
        } else {
            echo "  Gallery Images: NONE\n";
        }
        echo "\n";
    }
    
    wp_reset_postdata();
} else {
    echo "No floor plans found.\n";
}

echo "\nDone! Check if images are attached to the floor plans.\n";

