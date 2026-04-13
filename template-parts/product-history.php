<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('wc_get_orders')) {
  echo '<p>' . esc_html__( 'WooCommerce לא זמין.', 'deliz-short' ) . '</p>';
  return;
}

if (!is_user_logged_in()) {
  echo '<p>' . esc_html__( 'יש להתחבר כדי לצפות בהיסטוריית רכישה.', 'deliz-short' ) . '</p>';
  return;
}

$current_user_id = get_current_user_id();

// 1) קטגוריות ראשיות דינאמי (parent=0) לפי סדר תצוגה (menu_order אם קיים)
$top_terms = get_terms([
  'taxonomy'   => 'product_cat',
  'parent'     => 0,
  'hide_empty' => false,
  'orderby'    => 'menu_order', // אם לא נתמך אצלך זה עדיין יחזיר משהו
  'order'      => 'ASC',
]);

if (is_wp_error($top_terms)) $top_terms = [];

$top_ids_in_order = [];
$top_by_id = [];
foreach ($top_terms as $t) {
  $top_ids_in_order[] = (int) $t->term_id;
  $top_by_id[(int)$t->term_id] = $t;
}

// helper: מחזיר ID של קטגוריה ראשית עבור מוצר, לפי סדר הקטגוריות הראשיות באתר
$pick_top_category_id = function(int $product_id) use ($top_ids_in_order) {
  $terms = wp_get_post_terms($product_id, 'product_cat');
  if (empty($terms) || is_wp_error($terms)) return 0;

  // עבור כל קטגוריה של המוצר -> מטפסים עד root ומקבלים top_id
  $product_top_ids = [];
  foreach ($terms as $term) {
    $top = $term;
    while ($top && (int)$top->parent !== 0) {
      $top = get_term((int)$top->parent, 'product_cat');
      if (!$top || is_wp_error($top)) break;
    }
    if ($top && !is_wp_error($top)) {
      $product_top_ids[(int)$top->term_id] = true;
    }
  }

  if (!$product_top_ids) return 0;

  // בוחרים את הראשון לפי סדר הקטגוריות הראשיות באתר
  foreach ($top_ids_in_order as $top_id) {
    if (isset($product_top_ids[$top_id])) return (int)$top_id;
  }

  // fallback: כל top_id שמצאנו
  $keys = array_keys($product_top_ids);
  return (int) ($keys[0] ?? 0);
};

// 2) מביאים הזמנות (כולל סטטוסים נפוצים) מהחדשה לישנה
$statuses = ['processing', 'completed', 'on-hold'];
$orders = wc_get_orders([
  'customer_id' => $current_user_id,
  'status'      => $statuses,
  'orderby'     => 'date',
  'order'       => 'DESC',
  'limit'       => 100,
  'return'      => 'objects',
]);
// fallback לפי billing_email (אם הזמנות נעשו כאורח)
if (empty($orders)) {
  $u = wp_get_current_user();
  $email = $u ? $u->user_email : '';
  if ($email) {
    $orders = wc_get_orders([
      'status'     => $statuses,
      'orderby'    => 'date',
      'order'      => 'DESC',
      'limit'      => 100,
      'meta_key'   => '_billing_email',
      'meta_value' => $email,
      'return'     => 'objects',
    ]);
  }
}

// 3) אוספים מוצרים ללא כפילויות ומקבצים לפי קטגוריה ראשית
$products_by_top = [];          // [top_id => [product_id => true]]
$displayed_products = [];       // set: [product_id => true]

if (!empty($orders)) {
  foreach ($orders as $order) {
    if (!$order instanceof \WC_Order) {
      continue;
    }
    foreach ($order->get_items('line_item') as $item) {
      if (!$item instanceof \WC_Order_Item_Product) {
        continue;
      }
      // מזהה מוצר ראשי בלבד: ב־WC בשורת וריאציה get_product_id() מחזיר את ההורה — לא משתמשים ב־variation_id כדי שלא יופיע אותו מוצר מספר פעמים.
      $product_id = (int) $item->get_product_id();  
      if (!$product_id) {
        continue;
      }

      // בלי כפילויות
      if (isset($displayed_products[$product_id])) {
        continue; 
      }

      $top_id = $pick_top_category_id($product_id);
      if (!$top_id) continue;

      $displayed_products[$product_id] = true;

      if (!isset($products_by_top[$top_id])) $products_by_top[$top_id] = [];
      $products_by_top[$top_id][$product_id] = true; // שומר סדר הכנסה (מהזמנות האחרונות)
    }
  }
}
// ---------- OUTPUT ----------
?>
    <header class="woocommerce-products-header">
      <div class="archive-tax-content">
        <?php do_action('oc_woo_archive_before_title'); ?>
        <p class="sub"><?php echo esc_html__( 'סידרנו לכם את כל המוצרים שכבר אהבתם והזמנתם במקום אחד.', 'deliz-short' ); ?><br/><?php echo esc_html__( 'להזמנה חוזרת, קלה ומהירה.', 'deliz-short' ); ?></p>
      </div>
    </header>

    <?php do_action('woocommerce_before_shop_loop'); ?>

    <?php if (!empty($products_by_top)): ?>

      <?php
      // מדפיסים לפי סדר הקטגוריות הראשיות באתר
      foreach ($top_ids_in_order as $top_id) {
        if (empty($products_by_top[$top_id])) continue;

        $top_term = $top_by_id[$top_id] ?? get_term($top_id, 'product_cat');
        if (!$top_term || is_wp_error($top_term)) continue;

        echo '<h3>' . esc_html($top_term->name) . '</h3>';
        echo '<div class="woocommerce">';
        echo '<ul class="products columns-4">';

        foreach (array_keys($products_by_top[$top_id]) as $pid) {
          $post_object = get_post($pid);
          if (!$post_object) continue;

          setup_postdata($GLOBALS['post'] = $post_object);
          wc_get_template_part('content', 'product');
          wp_reset_postdata();
        }

        echo '</ul>';
        echo '</div>';
      }
      ?>

    <?php else: ?>
      <p><?php echo esc_html__( 'לא נמצאו מוצרים בהיסטוריית הרכישה.', 'deliz-short' ); ?></p>
    <?php endif; ?>

