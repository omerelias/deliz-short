<?php
/**
 * ED Helpers & Debugging Tools
 */

// 1. פונקציית Debug חכמה - תצוגה לפי הרשאות או פרמטר ב-URL
if ( ! function_exists('debug') ) {
    function debug($data, $admin_only = true) {
        $is_admin = current_user_can('manage_options');
        $is_debug_url = isset($_GET['ed_debug']);

        // בדיקה אם להציג: חייב להיות אדמין (אם הוגדר) או שקיים הפרמטר ב-URL
        if ( ($admin_only && ! $is_admin) && ! $is_debug_url ) {
            return;
        }

        echo '<pre style="direction: ltr; text-align: left; background: #1a1a1a; color: #00ff00; padding: 20px; border: 2px solid #333; border-left: 5px solid #ff00ff; border-radius: 5px; overflow: auto; max-height: 600px; font-family: monospace; font-size: 13px; line-height: 1.5; position: relative; z-index: 999999;">';
        echo '<div style="background: #333; color: #fff; padding: 5px 10px; margin: -20px -20px 15px -20px; font-weight: bold; font-size: 11px; text-transform: uppercase;">ED Debug Mode ' . ($is_debug_url ? '(via URL)' : '(Admin)') . '</div>';

        if ( is_array($data) || is_object($data) ) {
            print_r($data);
        } elseif ( is_bool($data) ) {
            var_dump($data);
        } else {
            echo htmlspecialchars($data);
        }

        echo '</pre>';
    }
}

// 2. בודק מהיר: האם המשתמש הוא מפתח/אדמין
if ( ! function_exists('is_dev') ) {
    function is_dev() {
        return current_user_can('manage_options') || isset($_GET['ed_debug']);
    }
}

// 3. לוג לשרת - מעולה לבדיקת API ו-AJAX (נכתב ל-debug.log)
if ( ! function_exists('ed_log') ) {
    function ed_log($message, $title = 'LOG') {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            $formatted_message = is_array($message) || is_object($message) ? print_r($message, true) : $message;
            error_log("[ED_$title] " . $formatted_message);
        }
    }
}

// 4. שליפת מחיר מעוצב של מוצר בקלות (לפי ID)
if ( ! function_exists('ed_get_price_html') ) {
    function ed_get_price_html($product_id) {
        $product = wc_get_product($product_id);
        return $product ? $product->get_price_html() : '';
    }
}

// 5. שליפת שדה ACF עם ערך ברירת מחדל (מונע שגיאות אם השדה ריק)
if ( ! function_exists('ed_get_field') ) {
    function ed_get_field($field_name, $post_id = 'options', $default = '') {
        $value = get_field($field_name, $post_id);
        return ! empty($value) ? $value : $default;
    }
}

// 6. ניקוי קאש של WP-Rocket בעת שמירת הגדרות ACF (אופציונלי)
add_action('acf/save_post', function($post_id) {
    // בודק אם שמרנו בדף הגדרות (options) ואם WP-Rocket מותקן
    if ( $post_id === 'options' && function_exists('rocket_clean_domain') ) {
        rocket_clean_domain();
        ed_log('WP-Rocket Cache Cleared after ACF Save', 'CACHE');
    }
}, 20);

// 7. הזרקת הגדרות בסיסיות ל-JS (כדי שלא תצטרך לכתוב URLs קבועים ב-JS)
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('jquery', 'ED_DATA', [ // מחבר את זה ל-jquery כי הוא תמיד נטען
        'ajax_url'  => admin_url('admin-ajax.php'),
        'rest_url'  => esc_url_raw(rest_url('ed/v1/')),
        'is_mobile' => wp_is_mobile(),
        'nonce'     => wp_create_nonce('wp_rest')
    ]);
}, 1);