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
  <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

      <div class="entry-content">        
  
        <div class="main-content">
            <div class="right-column">
                <div class="right-column-inner">
                  <div class="search ed-ajax-search">
                    <label class="sr-only" for="search_q">חיפוש מוצרים בחנות</label>
                    <input type="text" placeholder="מה בא לך?" id="search_q" class="input">

                    <button type="button" class="ed-search-btn" aria-label="חפש">
                      <!-- אייקון זכוכית -->
                      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                        <path d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm11 3-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </button>

                    <button type="button" class="ed-search-clear" aria-label="נקה חיפוש" style="display:none;">
                      <!-- אייקון X -->
                      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </button>
                  </div>

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
