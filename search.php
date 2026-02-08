<?php get_header(); ?>

<main id="site-content" class="site-content">
  <header class="page-header">
    <h1 class="page-title">
      <?php
        printf(
          esc_html__('Search results for: %s', 'deliz-short'),
          '<span>' . esc_html(get_search_query()) . '</span>'
        );
      ?>
    </h1>
  </header>

  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <?php get_template_part('template-parts/content'); ?>
    <?php endwhile; ?>

    <nav class="pagination">
      <?php the_posts_pagination(); ?>
    </nav>
  <?php else : ?>
    <p><?php esc_html_e('No results found.', 'deliz-short'); ?></p>
    <?php get_search_form(); ?>
  <?php endif; ?>
</main>

<?php get_footer(); ?>
