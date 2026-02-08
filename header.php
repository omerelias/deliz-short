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
<?php endif; ?>
<?php
  global $current_user;
  $icon_color = sanitize_hex_color( get_field('main_header_txt_color', 'option') ) ?: '#000';
  $header_place = (get_field('logo_position', 'option') === 'center') ? '' : 'right-logo';
?>
<header class="site-header <?php echo $header_place; ?>">
  <div class="container">  
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

      <div class="header-left">
        <div class="header-user" <?php if(get_field("hm_ico_user", 'option')) echo 'style="background-image:url('.get_field("hm_ico_user", 'option').');"'; ?>>
          <div class="header-user-inner">
            <svg xmlns="http://www.w3.org/2000/svg" width="31.23" height="28.5" viewBox="0 0 31.23 28.5"><g id="Account" transform="translate(-0.609 -0.65)"><g id="Layer_1" data-name="Layer 1" transform="translate(1.653 1.65)"><g id="Layer_1-2" data-name="Layer 1-2" transform="translate(0)"><g id="elements"><path id="Path_688" data-name="Path 688" d="M2.973,50.385H29.5a1.329,1.329,0,0,0,1.341-1.306h0a1.183,1.183,0,0,0-.043-.347C29.217,42.816,23.3,38.37,16.239,38.37S3.257,42.812,1.7,48.733a1.321,1.321,0,0,0,.947,1.609h0a1.578,1.578,0,0,0,.331.043Z" transform="translate(-1.653 -23.885)" fill="none" stroke="<?php echo $icon_color; ?>" stroke-linecap="round" stroke-miterlimit="10" stroke-width="2"></path><path id="Path_689" data-name="Path 689" d="M29.256,1.65a6.106,6.106,0,1,0,6.106,6.106h0A6.114,6.114,0,0,0,29.256,1.65Z" transform="translate(-14.67 -1.65)" fill="none" stroke="<?php echo $icon_color; ?>" stroke-linecap="round" stroke-miterlimit="10" stroke-width="2"></path></g></g></g></g></svg> 
            <?php if(is_user_logged_in()): ?>          
              <span><?php echo __('Hi', 'deliz-short'); ?> </span> <a href="<?php echo get_home_url(); ?>/my-account" aria-label="מעבר לחשבון שלי "><?php echo $current_user->first_name; ?></a>
            <?php else: ?>
              <span><?php echo __('Hi,', 'deliz-short'); ?> </span> <a href="<?php echo get_home_url(); ?>/my-account" aria-label="מעבר לחשבון שלי "><?php echo __('Login', 'deliz-short'); ?></a>
            <?php endif; ?>
          </div>
        </div>    
      </div>    
  </div>
</header>
