<?php
/**
 * The template for displaying the homepage.
 *
 * This page template will display any functions hooked into the `homepage` action.
 * By default this includes a variety of product displays and the page content itself. To change the order or toggle these components
 * use the Homepage Control plugin.
 * https://wordpress.org/plugins/homepage-control/
 *
 * Template name: Homepage
 *
 * @package storefront
 */

get_header(); ?>

<main id="site-content" class="site-content">
<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
  <nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'deliz-short' ); ?>">
    <?php yoast_breadcrumb(); ?>
  </nav>
<?php endif; ?>
  <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

      <div class="entry-content">        
  
        <div class="main-content">
            <div class="right-column">
                <div class="right-column-inner">

                    <div class="menu-block">
                        <?php echo do_shortcode('[ed_menu_sidebar]'); ?>  
                    </div>
                </div>
            </div>
            <div class="center-column">
              <div class="center-column-inner">
                  <?php echo do_shortcode('[ed_main_slider]'); ?>   
                   <?php echo do_shortcode('[ed_products_box]'); ?>  
              </div>            
            </div>
            <div class="left-column">
                <?php echo do_shortcode('[ed_floating_cart]'); ?>  
            </div>            
        </div>
      </div>
    </article>

  <?php endwhile; ?>
  <?php echo do_shortcode('[ed_basket_bar]'); ?>  
</main>

<?php get_footer(); ?>
