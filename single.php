<?php get_header(); ?>

<main id="site-content" class="site-content">
  <?php while ( have_posts() ) : the_post(); ?>
    <?php get_template_part('template-parts/content'); ?>

    <?php if ( comments_open() || get_comments_number() ) : ?>
      <?php comments_template(); ?>
    <?php endif; ?>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
