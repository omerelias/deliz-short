<?php
if ( post_password_required() ) return;
?>

<section id="comments" class="comments-area">
  <?php if ( have_comments() ) : ?>
    <h2 class="comments-title">
      <?php
        printf(
          esc_html(_n('%s Comment', '%s Comments', get_comments_number(), 'deliz-short')),
          number_format_i18n(get_comments_number())
        );
      ?>
    </h2>

    <ol class="comment-list">
      <?php
        wp_list_comments([
          'style'      => 'ol',
          'short_ping' => true,
        ]);
      ?>
    </ol>

    <?php the_comments_pagination(); ?>
  <?php endif; ?>

  <?php if ( comments_open() ) : ?>
    <?php comment_form(); ?>
  <?php endif; ?>
</section>
