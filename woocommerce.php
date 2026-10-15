<?php
/**
 * WooCommerce Wrapper Template
 */
get_header();
?>

<main class="wrap woocommerce-page" style="padding-top: 60px; padding-bottom: 90px; min-height: 70vh;">
  <?php woocommerce_content(); ?>
</main>

<?php get_footer(); ?>
