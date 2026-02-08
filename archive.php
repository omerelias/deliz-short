<?php get_header(); ?>

<main id="site-content" class="site-content">
  <header class="page-header">
    <h1 class="page-title"><?php the_archive_title(); ?></h1>
    <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
  </header>

  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <?php get_template_part('template-parts/content'); ?>
    <?php endwhile; ?>

    <nav class="pagination">
      <?php the_posts_pagination(); ?>
    </nav>
  <?php else : ?>
    <p><?php esc_html_e('Nothing here yet.', 'deliz-short'); ?></p>
  <?php endif; ?>
</main>

<?php get_footer(); ?>
