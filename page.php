<?php get_header(); ?>

<main id="site-content" class="site-content">
  <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

      <div class="entry-content">
        <?php the_content(); ?>
      </div>
    </article>

    <?php if ( comments_open() || get_comments_number() ) : ?>
      <?php comments_template(); ?>
    <?php endif; ?>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
