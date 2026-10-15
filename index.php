<?php
/**
 * Main Index Fallback Template
 */
get_header();
?>

<main class="wrap" style="padding-top: 60px; padding-bottom: 90px; min-height: 70vh;">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <header class="entry-header">
        <h1 style="font: 500 36px var(--serif); color: var(--ink);"><?php the_title(); ?></h1>
      </header>
      <div class="entry-content" style="color: var(--muted); font-size: 15px; line-height: 1.8;">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>
