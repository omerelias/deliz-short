<?php get_header(); ?>

<main id="site-content" class="site-content">
  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <?php get_template_part('template-parts/content'); ?>
    <?php endwhile; ?>

    <nav class="pagination">
      <?php the_posts_pagination(); ?>
    </nav>

  <?php else : ?>
    <p><?php esc_html_e('No posts found.', 'deliz-short'); ?></p>
  <?php endif; ?>
</main>

<?php get_footer(); ?>
