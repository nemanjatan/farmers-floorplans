<?php
/**
 * Archive template for floor plans
 */

get_header();
?>

<div class="site-content">
    <header class="page-header">
        <h1 class="page-title">Floor Plans</h1>
    </header>
    
    <div class="content-area">
        <?php echo do_shortcode('[farmers_floor_plans]'); ?>
    </div>
</div>

<?php
get_footer();

