<?php
// Check if floor plans have images
require_once('wp-load.php');

echo "=== Floor Plans Image Check ===\n\n";

$args = [
    'post_type' => 'floor_plan',
    'posts_per_page' => 10,
    'post_status' => 'any',
];

$query = new WP_Query($args);

echo "Total floor plans: {$query->found_posts}\n\n";

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        
        $image_url = get_post_meta($post_id, '_ffp_source_url', true);
        $has_thumbnail = has_post_thumbnail($post_id);
        $thumbnail_url = $has_thumbnail ? wp_get_attachment_url(get_post_thumbnail_id($post_id)) : 'NONE';
        
        echo sprintf(
            "ID: %d | Title: %s\n   Has thumbnail: %s\n   Thumbnail URL: %s\n   Source URL: %s\n\n",
            $post_id,
            get_the_title(),
            $has_thumbnail ? 'YES' : 'NO',
            $thumbnail_url,
            $image_url ?: 'NONE'
        );
    }
    
    wp_reset_postdata();
} else {
    echo "No floor plans found.\n";
}

echo "\nDone!\n";

