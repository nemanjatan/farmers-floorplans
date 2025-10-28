<?php
  // Simple verification script
  require_once( 'wp-load.php' );
  
  echo "=== Floor Plans Verification ===\n\n";
  
  // Get all floor plans
  $args = [
    'post_type'      => 'floor_plan',
    'posts_per_page' => - 1,
    'post_status'    => 'any',
  ];
  
  $query = new WP_Query( $args );
  
  echo "Total floor plans found: " . $query->found_posts . "\n\n";
  
  if ( $query->have_posts() ) {
    echo "First 10 floor plans:\n";
    echo str_repeat( '-', 80 ) . "\n";
    
    $count = 0;
    while ( $query->have_posts() && $count < 10 ) {
      $query->the_post();
      $count ++;
      
      $price   = get_post_meta( get_the_ID(), '_ffp_price', true );
      $address = get_post_meta( get_the_ID(), '_ffp_address', true );
      $beds    = get_post_meta( get_the_ID(), '_ffp_bedrooms', true );
      $active  = get_post_meta( get_the_ID(), '_ffp_active', true );
      
      echo sprintf(
        "#%d: %s\n   Address: %s\n   Price: $%s\n   Beds: %s | Active: %s\n   Status: %s\n\n",
        get_the_ID(),
        get_the_title(),
        $address ?: 'N/A',
        $price ? number_format( $price ) : 'N/A',
        $beds ?: 'N/A',
        $active === '1' ? 'Yes' : 'No',
        get_post_status()
      );
    }
    
    wp_reset_postdata();
  } else {
    echo "No floor plans found in database.\n";
  }
  
  echo "\n=== Meta Stats ===\n";
  global $wpdb;
  $meta_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ffp_%'" );
  echo "Total meta entries: {$meta_count}\n";
  
  echo "\nDone! Open http://localhost:10063/wp-admin/edit.php?post_type=floor_plan to view in WordPress admin.\n";

