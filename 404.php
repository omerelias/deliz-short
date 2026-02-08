<?php get_header(); ?>

<main id="site-content" class="site-content">
  <section class="error-404">
    <h1><?php esc_html_e('Page not found', 'deliz-short'); ?></h1>
    <p><?php esc_html_e('Sorry, that page does not exist.', 'deliz-short'); ?></p>
    <?php get_search_form(); ?>
  </section>
</main>

<?php get_footer(); ?>
