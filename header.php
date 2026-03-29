<?php if ( ! defined('ABSPATH') ) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
if ( have_rows('top_header_msg', 'option') ) : ?>
  <div class="top-header">
    <div class="top-header-container">
        <div class="header-top-right">
          <?php if ( have_rows('top_header_right', 'option') ) : ?>
            <ul class="top-header-right-list">
              <?php while ( have_rows('top_header_right', 'option') ) : the_row(); 
                $link = get_sub_field('link');
                if ( ! $link ) {
                  continue;
                }
              ?>
                <li class="top-header-right-item">
                  <a href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>">
                    <?php echo esc_html( $link['title'] ); ?>
                  </a>
                </li>
              <?php endwhile; ?>
            </ul>
          <?php endif; ?>       
        </div>
        <div class="top-header-inner slick-slider">
          <?php while ( have_rows('top_header_msg', 'option') ) : the_row();

            $text = trim((string) get_sub_field('text'));
            $link = get_sub_field('link');

            if ( $text === '' ) continue;

            $url    = '';
            $target = '';

            if ( is_array($link) ) {
              $url    = !empty($link['url']) ? $link['url'] : '';
              $target = !empty($link['target']) ? $link['target'] : '';
            } else {
              $url = is_string($link) ? trim($link) : '';
            }

            $text_esc = esc_html($text);      
              echo '<div class="item">';
                if ( $url ) {
                  printf(
                    '<a class="top-header-msg__item" href="%s"%s>%s</a>',
                    esc_url($url),
                    $target ? ' target="' . esc_attr($target) . '" rel="noopener noreferrer"' : '',
                    $text_esc
                  );
                } else {
                  printf('<span class="top-header-msg__item">%s</span>', $text_esc);
                }
              echo '</div>';
          endwhile; ?>
      </div>
      </div>
  </div>
<?php endif; ?>
<?php
  global $current_user;
  $icon_color = sanitize_hex_color( get_field('main_header_txt_color', 'option') ) ?: '#000';
  $header_place = (get_field('logo_position', 'option') === 'center') ? '' : 'right-logo';
?>
<header class="site-header <?php echo $header_place; ?>">
  <div class="container">  
    <div class="container-header-inner">
     <nav class="site-nav" aria-label="<?php esc_attr_e('Primary', 'deliz-short'); ?>">
      <?php
        wp_nav_menu([
          'theme_location' => 'primary',
          'container'      => false,
          'fallback_cb'    => '__return_false',
        ]);
      ?>
    </nav>

      <div class="header-logo">
        <?php
          if ( function_exists('the_custom_logo') && has_custom_logo() ) {
            the_custom_logo();
          } else {
            bloginfo('name');
          }
        ?>
      </div>
      <div class="search ed-ajax-search">
        <div class="search ed-ajax-search-inner">
          <label class="sr-only" for="search_q"><?php esc_html_e( 'חיפוש מוצרים בחנות', 'deliz-short' ); ?></label>
          <input type="text" placeholder="<?php echo esc_attr__( 'מה בא לך?', 'deliz-short' ); ?>" id="search_q" class="input">

          <button type="button" class="ed-search-btn" aria-label="<?php esc_attr_e( 'חפש', 'deliz-short' ); ?>">
              <!-- אייקון זכוכית -->
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                  <path d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm11 3-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>

            <button type="button" class="ed-search-clear" aria-label="<?php esc_attr_e( 'נקה חיפוש', 'deliz-short' ); ?>" style="display:none;">
              <!-- אייקון X -->
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                  <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
          </div>
      </div>
      <div class="header-left">
        <div class="header-user" <?php if(get_field("hm_ico_user", 'option')) echo 'style="background-image:url('.get_field("hm_ico_user", 'option').');"'; ?>>
          <div class="header-user-inner">
            <svg width="16" height="16" viewBox="0 0 11 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2.49941 3C2.49941 2.20435 2.81548 1.44129 3.37809 0.87868C3.9407 0.316071 4.70376 0 5.49941 0C6.29506 0 7.05812 0.316071 7.62073 0.87868C8.18334 1.44129 8.49941 2.20435 8.49941 3C8.49941 3.79565 8.18334 4.55871 7.62073 5.12132C7.05812 5.68393 6.29506 6 5.49941 6C4.70376 6 3.9407 5.68393 3.37809 5.12132C2.81548 4.55871 2.49941 3.79565 2.49941 3ZM7.96023e-05 12.4033C0.0225598 10.9597 0.61184 9.58271 1.64072 8.56972C2.6696 7.55674 4.05555 6.98897 5.49941 6.98897C6.94327 6.98897 8.32923 7.55674 9.35811 8.56972C10.387 9.58271 10.9763 10.9597 10.9987 12.4033C11.0005 12.5005 10.9738 12.5961 10.9221 12.6784C10.8704 12.7607 10.7958 12.8261 10.7074 12.8667C9.07353 13.6158 7.29685 14.0024 5.49941 14C3.64208 14 1.87741 13.5947 0.291413 12.8667C0.20307 12.8261 0.128463 12.7607 0.076721 12.6784C0.0249789 12.5961 -0.00165456 12.5005 7.96023e-05 12.4033Z" fill="<?php echo $icon_color; ?>"/>
            </svg>
            <?php if(is_user_logged_in()): ?>          
              <span><?php echo esc_html( __( 'Hi', 'deliz-short' ) ); ?> </span> <a href="<?php echo esc_url( trailingslashit( get_home_url() ) . 'my-account' ); ?>" aria-label="<?php esc_attr_e( 'מעבר לחשבון שלי', 'deliz-short' ); ?>"><?php echo esc_html( $current_user->first_name ); ?></a>
            <?php else: ?>
              <a href="<?php echo esc_url( trailingslashit( get_home_url() ) . 'my-account' ); ?>" aria-label="<?php esc_attr_e( 'מעבר לחשבון שלי', 'deliz-short' ); ?>"><?php echo esc_html( __( 'Login', 'deliz-short' ) ); ?></a>
            <?php endif; ?>
          </div>
        </div>   
        </div>  
      </div>    
  </div>
</header>
