<?php if ( ! defined('ABSPATH') ) exit; ?>
<aside class="site-sidebar">
  <?php if ( is_active_sidebar('sidebar-1') ) : ?>
    <?php dynamic_sidebar('sidebar-1'); ?>
  <?php endif; ?>
</aside>
