<?php
/**
 * Generic Page Template
 */
get_header();
?>

<main class="wrap" style="padding-top: 32px; padding-bottom: 70px; min-height: 70vh;">
  <?php while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <?php if (!is_checkout() && !is_cart()): ?>
        <header class="entry-header" style="margin-bottom: 24px;">
          <h1 class="entry-title" style="font: 500 clamp(36px, 5vw, 60px)/1.1 var(--serif); color: var(--ink);">
            <?php the_title(); ?>
          </h1>
        </header>
      <?php endif; ?>

      <div class="entry-content" style="color: var(--muted); font-size: 15px; line-height: 1.8;">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
