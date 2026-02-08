<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package storefront
 */

?>

</div><!-- .col-full -->
</div><!-- #content -->

<?php do_action( 'storefront_before_footer' ); ?>
<?php if(!Is_checkout()): ?>
    <footer id="colophon" class="site-footer" role="contentinfo">
        <?php $web_inputs_show_newsletter = get_field('web_inputs_show_newsletter', 'option'); ?>
        <?php if($web_inputs_show_newsletter){ ?>
        <div class="footer-top">
            <div class="footer-top-content">
                <div class="container">
                    <div class="row justify-content-center">
                            <div class="footer-top-right">
                                <div class="title">
                                    <h4><?php the_field("ft_title", 'option') ?></h4>
                                    <h5><?php the_field("ft_subtitle", 'option') ?></h5>
                                </div>
                                <div class="f-form">
                                    <?php echo do_shortcode("[contact-form-7 id=".get_field("ft_form", 'option')."]" ); ?>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
        <div class="fmain clearfix">
            <div class="container">
                <div class="row">
                    <div class="fcol-logo">
                        <img src="<?php the_field("f_logo", 'option'); ?>" alt="logo">
                        <?php
                        if( have_rows('fb_social', 'option') ){
                            ?><ul class="social-f"><?php
                            while( have_rows('fb_social', 'option') ){
                                the_row();
                                $fb_social_icon = get_sub_field('fb_social_icon');
                                $fb_social_link = get_sub_field('fb_social_link');
                                ?>
                                <li><a target="_blank" href="<?php echo $fb_social_link ?>"><img src="<?php echo $fb_social_icon; ?>" alt=""></a></li>
                                <?php
                            }
                            ?></ul><?php
                        }
                        ?>
                    </div>
                    <div class="fcol-contact">
                        <?php if($footer_hours = get_field('footer_hours', 'option')): ?>
                          <div class="title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path id="Path_1068" data-name="Path 1068" d="M27,969.362a10,10,0,1,0,10,10A10.014,10.014,0,0,0,27,969.362Zm0,1.818a8.182,8.182,0,1,1-8.182,8.182A8.168,8.168,0,0,1,27,971.18Zm0,1.515a.909.909,0,0,0-.909.909v5.758a.908.908,0,0,0,.265.644L30,983.652a.911.911,0,0,0,1.288-1.288l-3.381-3.381V973.6A.909.909,0,0,0,27,972.7Z" transform="translate(-17 -969.362)" fill="<?php echo get_field('fb_txt_color', 'option'); ?>"></path></svg> 
                                <span>שעות פעילות</span>
                          </div>
                          <div class="content"><?php echo $footer_hours; ?></div>
                        <?php endif; ?>
                        
                        <?php if($footer_contact = get_field('footer_contact', 'option')): ?>
                          <div class="title">
                              <svg xmlns="http://www.w3.org/2000/svg" width="20.5" height="20.5" viewBox="0 0 20.5 20.5"><g id="noun_Phone_373695" transform="translate(0.249 0.25)"><path id="noun_Phone_373695-2" data-name="noun_Phone_373695" d="M24.667,26.779a2.075,2.075,0,0,0,0-3.068l-2.473-2.473a2.065,2.065,0,0,0-1.522-.69,2.145,2.145,0,0,0-1.522.69l-1.427,1.427c-.119-.071-.238-.119-.357-.19-.166-.071-.309-.166-.452-.238a15.126,15.126,0,0,1-3.686-3.353,8.175,8.175,0,0,1-1.213-1.926c.357-.333.713-.69,1.046-1.023l.38-.38a2.227,2.227,0,0,0,.713-1.546,2.172,2.172,0,0,0-.713-1.546L12.23,11.226c-.143-.143-.285-.285-.4-.428-.262-.285-.547-.571-.832-.832A2.12,2.12,0,0,0,9.5,9.3a2.216,2.216,0,0,0-1.522.666L6.427,11.511a3.256,3.256,0,0,0-.975,2.069,8.292,8.292,0,0,0,.571,3.329,19.248,19.248,0,0,0,3.424,5.731,21.178,21.178,0,0,0,7.015,5.493A10.588,10.588,0,0,0,20.411,29.3H20.7a3.349,3.349,0,0,0,2.568-1.118l.024-.024a8.357,8.357,0,0,1,.785-.809A4.467,4.467,0,0,0,24.667,26.779Zm-1.4-.262a10.343,10.343,0,0,0-.856.9,2.13,2.13,0,0,1-1.688.713h-.214a10.559,10.559,0,0,1-3.52-1.07,19.948,19.948,0,0,1-6.611-5.184,18.476,18.476,0,0,1-3.234-5.375,6.208,6.208,0,0,1-.5-2.806,2.174,2.174,0,0,1,.618-1.332l1.522-1.522a1,1,0,0,1,.69-.333,1.006,1.006,0,0,1,.69.333c.285.262.523.523.809.809l.428.428L12.61,13.3a.892.892,0,0,1,0,1.427l-.38.38c-.38.38-.737.737-1.118,1.094l-.024.024a.918.918,0,0,0-.238,1c0,.024,0,.024.024.048a9.3,9.3,0,0,0,1.451,2.354A16.509,16.509,0,0,0,16.3,23.236c.19.119.38.214.547.309.166.071.309.166.452.238.024,0,.024.024.048.024a.969.969,0,0,0,.428.119.957.957,0,0,0,.666-.309l1.546-1.546a.856.856,0,0,1,1.356,0l2.473,2.473a.912.912,0,0,1-.024,1.427A5.557,5.557,0,0,1,23.264,26.517Z" transform="translate(-5.437 -9.3)" fill="<?php echo get_field('fb_txt_color', 'option'); ?>" stroke="<?php echo get_field('fb_txt_color', 'option'); ?>" stroke-width="0.5"></path></g></svg> 
                              <span>שירות לקוחות</span>
                          </div>
                          <div class="content"><?php echo $footer_contact; ?></div>
                        <?php endif; ?>  
                        
                        <?php if($footer_address = get_field('footer_address', 'option')): ?>
                          <div class="title">
                              <svg xmlns="http://www.w3.org/2000/svg" id="Component_21_3" data-name="Component 21 – 3" width="14.86" height="22.823" viewBox="0 0 14.86 22.823"><path id="Path_1069" data-name="Path 1069" d="M29.43,959.356a7.3,7.3,0,0,0-7.43,7.43,11.36,11.36,0,0,0,1.327,4.884l5.4,10.083a.8.8,0,0,0,1.41,0l5.4-10.083a11.361,11.361,0,0,0,1.327-4.884,7.3,7.3,0,0,0-7.43-7.43Zm0,1.592a5.606,5.606,0,0,1,5.838,5.838,10.793,10.793,0,0,1-1.136,4.13l-4.7,8.765-4.7-8.765a10.8,10.8,0,0,1-1.136-4.13A5.606,5.606,0,0,1,29.43,960.948Zm0,2.123a3.715,3.715,0,1,0,3.715,3.715A3.727,3.727,0,0,0,29.43,963.071Zm0,1.592a2.123,2.123,0,1,1-2.123,2.123A2.111,2.111,0,0,1,29.43,964.663Z" transform="translate(-22 -959.356)" fill="<?php echo get_field('fb_txt_color', 'option'); ?>"></path></svg> 
                              <span>כתובת</span>
                          </div>
                          <div class="content"><?php echo $footer_address; ?></div>
                        <?php endif; ?>  
                    </div>

                    <?php
                    if( have_rows('fb_cols', 'option') ){
                        $x = 0;
                        while( have_rows('fb_cols', 'option') ){
                            the_row();
                            $x++;
                            $fb_cols_title = get_sub_field('fb_cols_title');
                            $fb_cols_cotent = get_sub_field('fb_cols_cotent');
                            ?>
                            <div class="fcol-menu fcol-<?php echo $x; ?>">
                                <span class='title'><?php echo $fb_cols_title; ?></span>
                                <?php echo $fb_cols_cotent; ?>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-sm pay-img"><img src="<?php the_field("f_img_r", 'option'); ?>" alt=""></div>
                    <div class="col-sm text-center" style="font-size: 14px;line-height: 15px;">© <?php echo date("Y"); ?> <?php the_field("f_text_m", 'option'); ?></div>
                    <div class="col-sm text-right copyright" style="display: flex;align-items: flex-start;justify-content: flex-end;gap: 5px;"><a href="https://onlinestore.co.il/meat-and-fish-shop/" style="font-size: 14px;margin-top: 0;" target="_blank"><?php _e('בניית אתר לקצביה', 'oc_transalte'); ?></a> | <a href="https://onlinestore.co.il/" target="_blank"><img width="40" style="margin-right: 0;" src="<?php echo get_stylesheet_directory_uri() ?>/assets/images/original-concepts.svg" alt="onlinestore"></a></div>
                </div>
            </div>
        </div>
    </footer><!-- #colophon -->
<?php endif; ?>
<?php do_action( 'storefront_after_footer' ); ?>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
