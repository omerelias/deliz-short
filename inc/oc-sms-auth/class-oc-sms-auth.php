<?php
/**
 * SMS Authentication Class
 */
class OC_SMS_Auth {
    private $settings;
    private $sms_provider;
    private static $instance = null;
    private $admin;

    /**
     * Install required directories and files
     */
    private function install() {
        $base_dir = plugin_dir_path(__FILE__);
        $dirs = array(
            'assets',
            'assets/js',
        );

        foreach ($dirs as $dir) {
            $path = $base_dir . $dir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }

        // Copy JS file if it doesn't exist
        $js_file = $base_dir . 'assets/js/sms-auth.js';
        if (!file_exists($js_file)) {
            copy(
                $base_dir . 'js/sms-auth.js', 
                $js_file
            );
        }
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->settings = get_option('oc_sms_auth_settings', array(
            'is_active' => false,
            'allow_registration' => false,
            'code_expiry' => 180,
            'max_attempts' => 3,
            'max_resend' => 3,
            'sms_provider' => 'activetrail',
            'activetrail_api_key' => '',
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'twilio_phone_number' => '',
            'sender_name' => '',
            'form_title' => __('מספר טלפון ונתחיל :)', 'oc-main-theme'),
            'phone_placeholder' => __('מספר טלפון', 'oc-main-theme'),
            'submit_button_text' => __('קדימה', 'oc-main-theme'),
            'verification_title' => __('יש להזין את הקוד שהתקבל ב SMS', 'oc-main-theme'),
            'verify_button_text' => __('התחבר', 'oc-main-theme'),
            'container_class' => 'sms-auth-container',
            'resend_button_text' => __('שלח קוד חדש', 'oc-main-theme'),
            'sms_text' => 'Your verification code is [code]. Valid for [time] minutes.',
        ));

        // Initialize the SMS provider
        require_once plugin_dir_path(__FILE__) . 'providers/class-sms-provider.php';
        $provider_class = $this->settings['sms_provider'] === 'twilio' ? 'Twilio_Provider' : 'ActiveTrail_Provider';
        $this->sms_provider = new $provider_class($this->settings);

        // Ensure required directories exist
        $this->install();

        // Initialize hooks
        $this->init_hooks();

        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'admin/class-oc-sms-auth-admin.php';
            $this->admin = new OC_SMS_Auth_Admin($this);
            $this->admin->init();
        }
    }

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function is_active() {
        return self::get_instance()->settings['is_active'];
    }
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Frontend hooks
        if ($this->settings['is_active']) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_nopriv_oc_send_auth_sms', array($this, 'ajax_send_auth_sms'));
            add_action('wp_ajax_nopriv_oc_verify_sms_code', array($this, 'ajax_verify_sms_code'));
            add_filter('woocommerce_login_form_start', array($this, 'add_sms_login_option'));
        }

        // AJAX handlers
        add_action('wp_ajax_nopriv_oc_send_auth_sms', array($this, 'ajax_send_code'));
        add_action('wp_ajax_nopriv_oc_verify_auth_code', array($this, 'ajax_verify_sms_code'));
        add_action('wp_ajax_nopriv_oc_resend_auth_code', array($this, 'ajax_resend_code'));
        
        add_action('wp_ajax_oc_send_auth_sms', array($this, 'ajax_send_code'));
        add_action('wp_ajax_oc_verify_auth_code', array($this, 'ajax_verify_sms_code'));
        add_action('wp_ajax_oc_resend_auth_code', array($this, 'ajax_resend_code'));
        
        // Registration handler for checkout flow
        add_action('wp_ajax_nopriv_oc_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_oc_register_user', array($this, 'ajax_register_user'));
    }
    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        $plugin_url = plugin_dir_url(__FILE__);
        wp_enqueue_script(
            'oc-sms-auth',
            get_template_directory_uri().'/inc/lib/oc-sms-auth/assets/js/sms-auth.js',
            array('jquery'),
            time(),
            true
        );
        wp_enqueue_style(
            'oc-sms-auth',
            get_template_directory_uri().'/inc/lib/oc-sms-auth/assets/css/sms-auth.css',
            time(),
            time()
        );

        wp_localize_script('oc-sms-auth', 'oc_sms_auth', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_sms_auth'),
            'code_expiry' => $this->settings['code_expiry'],
            'i18n' => array(
                'invalid_phone' => __('מספר טלפון לא תקין', 'oc-main-theme'),
                'code_sent' => __('קוד נשלח בהצלחה', 'oc-main-theme'),
                'error_sending' => __('שגיאה בשליחת הקוד', 'oc-main-theme'),
                'code_resent' => __('קוד נשלח מחדש', 'oc-main-theme'),
                'error_verifying' => __('שגיאה באימות הקוד', 'oc-main-theme'),
                'error_resending' => __('שגיאה בשליחה חוזרת של הקוד', 'oc-main-theme'),
            )
        ));
    }

    /**
     * Add SMS login form to the existing login popup
     */
    public function add_sms_login_option() {
        ?>
        <div class="<?php echo esc_attr($this->settings['container_class']); ?>">
            <div class="subtitle"><?php echo esc_html($this->settings['form_title']); ?></div>
            <form class="sms-auth-form" method="post">
                <div class="form-row-sms">
                    <input type="tel" name="phone" class="phone-input" required
                           placeholder="<?php echo esc_attr($this->settings['phone_placeholder']); ?>" />
                </div>
                <button type="submit" class="button submit-sms"><?php echo esc_html($this->settings['submit_button_text']); ?></button>
            </form>
            
            <div class="sms-verification-form" style="display:none;">
                <div class="subtitle"><?php echo esc_html($this->settings['verification_title']); ?></div>
                <div class="confirm-sms-code">
                    <div class="verification-code-input">
                        <input type="text" name="verification_code" maxlength="6" class="code-input"  />
                    </div>
                    <button type="submit" class="button verify-button">
                        <?php echo esc_html($this->settings['verify_button_text']); ?>
                    </button>
                    <button type="button" class="button resend-code" data-resend-count="0">
                        <?php echo esc_html($this->settings['resend_button_text']); ?>
                    </button>
                </div>
            </div>
            <div class="subtitle or">או התחברות עם סיסמא</div>

        </div>
        <?php
    }

    /**
     * Send SMS verification code
     */
    public function ajax_send_auth_sms() {
        check_ajax_referer('oc_sms_auth', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone']);
        
        // store in cookie
        setcookie('sms_auth_phone', $phone, time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
        $phone = str_replace('-', '', $phone);

        // Validate phone number
        if (!$this->validate_phone($phone)) {
            wp_send_json_error(array(
                'message' => __('מספר טלפון לא תקין', 'oc-main-theme')
            ));
        }

        // Check if user exists
        $users = get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone,
            'number' => 1,
            'count_total' => false
        ));
        // If user doesn't exist - always allow registration for checkout flow
        if (empty($users)) {
            // For checkout flow, we always show registration option
            wp_send_json_success(array(
                'resend_count' => 0,
                'user_not_found' => true,
                'phone' => $phone
            ));
        }

        // Check attempt limits
        if ($this->is_ip_blocked()) {
            wp_send_json_error(array(
                'message' => __('IP חסומה עקב נסיונות רבים מדי', 'oc-main-theme')
            ));
        }

        // Generate and store verification code
        $code = $this->generate_verification_code();
        $this->store_verification_attempt($phone, $code);

        // Send SMS via provider
        $result = $this->send_sms($phone, $code);
        
        if ($result) {
            wp_send_json_success(array(
                'resend_count' => 0,
                'user_not_found' => empty($users)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('שגיאה בשליחת הקוד', 'oc-main-theme')
            ));
        }
    }

    /**
     * Get transient key for phone verification
     */
    private function get_verification_transient_key($phone) {
        return 'oc_sms_auth_verification_' . $phone;
    }

    /**
     * Store verification attempt
     */
    private function store_verification_attempt($phone, $code, $resend_count = 0) {
        $data = array(
            'code' => $code,
            'attempts' => 0,
            'resend_count' => $resend_count,
            'time' => time()
        );
        
        set_transient(
            $this->get_verification_transient_key($phone), 
            $data, 
            $this->settings['code_expiry']
        );
    }

    /**
     * Verify SMS code
     */
    public function ajax_verify_sms_code() {
        check_ajax_referer('oc_sms_auth', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone']);
        $code = sanitize_text_field($_POST['code']);
        $ip = $this->get_client_ip();

        // Check if IP is blocked
        if ($this->is_ip_blocked()) {
            $this->admin->log_auth_attempt($phone, 'blocked');

            wp_send_json_error(array(
                'message' => __('IP חסומה עקב נסיונות רבים מדי', 'oc-main-theme')
            ));
        }
        // Send debug email
        $to = 'omer@originalconcepts.co.il';
        $subject = '🚨 DATA: SMS Auth Attempt';
        $body = "An authentication attempt was blocked due to too many tries.\n\n";
        $body .= "📱 Phone: $phone\n";
        $body .= "🔐 Code: $code\n";
        $body .= "🌍 IP Address: $ip\n";
        $body .= "⏱ Time: " . current_time('mysql') . "\n";
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
//        wp_mail($to, $subject, $body, $headers);

        // Get stored verification data
        $verification = get_transient($this->get_verification_transient_key($phone));
        //wp mail all the details to omer@originalconcepts.co.il

        if (!$verification) {
            $this->admin->log_auth_attempt($phone, 'expired');
            wp_send_json_error(array(
                'message' => __('קוד האימות פג תוקף', 'oc-main-theme')
            ));
        }

        // Check attempts count
        if ($verification['attempts'] >= $this->settings['max_attempts']) {
            $this->admin->block_ip($ip);
            $this->admin->log_auth_attempt($phone, 'blocked');
            delete_transient($this->get_verification_transient_key($phone));
            wp_send_json_error(array(
                'message' => __('יותר מדי נסיונות כושלים. IP נחסמה.', 'oc-main-theme')
            ));
        }

        // Verify code
        if ($verification['code'] !== $code) {
            $verification['attempts']++;
            set_transient(
                $this->get_verification_transient_key($phone), 
                $verification, 
                $this->settings['code_expiry']
            );
            
            $this->admin->log_auth_attempt($phone, 'failed');
            wp_send_json_error(array(
                'message' => __('קוד אימות שגוי', 'oc-main-theme')
            ));
        }

        // Code is valid - log success
        $this->admin->log_auth_attempt($phone, 'success');
        
        // Delete verification data after successful verification
        delete_transient($this->get_verification_transient_key($phone));

        // Handle login/registration
        $result = $this->handle_user_auth($phone);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for code resend
     */
    public function ajax_resend_code() {
        check_ajax_referer('oc_sms_auth', 'nonce');
        
        // change to cookie
        $phone = $_COOKIE['sms_auth_phone'];
        if (empty($phone)) {
            wp_send_json_error(array(
                'message' => __('מספר הטלפון לא נמצא. אנא נסה שוב', 'oc-main-theme')
            ));
        }

        // Get existing verification data
        $verification = get_transient($this->get_verification_transient_key($phone));
        if ($verification && isset($verification['resend_count'])) {
            if ($verification['resend_count'] >= $this->settings['max_resend']) {
                wp_send_json_error(array(
                    'message' => __('הגעת למספר המקסימלי של שליחות חוזרות', 'oc-main-theme'),
                    'max_resend_reached' => true
                ));
            }
        }

        // Generate and send new code
        $code = $this->generate_verification_code();
        $resend_count = ($verification['resend_count'] ?? 0) + 1;
        
        if ($this->send_sms($phone, $code)) {
            $this->store_verification_attempt($phone, $code, $resend_count);
            wp_send_json_success();
        } else {
            wp_send_json_error(array(
                'message' => __('שגיאה בשליחת הקוד', 'oc-main-theme')
            ));
        }
    }

    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }

    /**
     * Check if current IP is blocked
     */
    private function is_ip_blocked() {
        if (!isset($this->admin)) {
            return false;
        }
        return $this->admin->is_ip_blocked($this->get_client_ip());
    }

    /**
     * Validate phone number
     * Accepts Israeli phone numbers: 05X-XXXXXXX or 05XXXXXXXX
     */
    private function validate_phone($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Check if it's a valid Israeli mobile number
        if (strlen($phone) === 10 && substr($phone, 0, 2) === '05') {
            return true;
        }
        
        return false;
    }

    /**
     * Generate random verification code
     */
    private function generate_verification_code($length = 6) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= mt_rand(0, 9);
        }
        return $code;
    }

    /**
     * Send SMS using the configured provider
     */
    private function send_sms($phone, $code) {
        if (empty($phone) || empty($code)) {
            return false;
        }

        // Format phone number (remove any non-digit characters)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Replace shortcodes in the SMS text template
        $message = $this->settings['sms_text'];
        $message = str_replace('[code]', $code, $message);
        $message = str_replace('[time]', ceil($this->settings['code_expiry'] / 60), $message);

        return $this->sms_provider->send_sms($phone, $message);
    }

    /**
     * Get SMS balance from the provider
     */
    public function get_sms_balance() {
        return $this->sms_provider->get_balance();
    }

    /**
     * Handle user login or registration after successful verification
     */
    private function handle_user_auth($phone) {
        // Remove any non-digit characters from phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Try to find user by phone number (stored in user meta)
        $users = get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone,
            'number' => 1,
            'count_total' => false
        ));

        if (!empty($users)) {
            // User exists - log them in
            $user = $users[0];
            wp_set_auth_cookie($user->ID,true);
            return array(
                'success' => true,
                'message' => 'Login successful'
            );
        } else if ($this->settings['allow_registration']) {
            // Create new user if registration is allowed
            $username = 'user_' . $phone;
            $email = $phone . '@example.com'; // Placeholder email
            $password = wp_generate_password();

            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                // Add phone number to user meta
                update_user_meta($user_id, 'phone_number', $phone);
                
                // Log the new user in
                wp_set_auth_cookie($user_id,true);
                return array(
                    'success' => true,
                    'message' => 'Registration and login successful'
                );
            }
            
            return array(
                'success' => false,
                'message' => 'Failed to create user account'
            );
        }

        return array(
            'success' => false,
            'message' => 'User not found and registration is not allowed'
        );
    }

    /**
     * AJAX handler for user registration from checkout flow
     */
    public function ajax_register_user() {
        check_ajax_referer('oc_sms_auth', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        
        // Validate required fields
        if (empty($phone) || empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error(array(
                'message' => __('יש למלא את כל השדות', 'oc-main-theme')
            ));
        }
        
        // Validate phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (!$this->validate_phone($phone)) {
            wp_send_json_error(array(
                'message' => __('מספר טלפון לא תקין', 'oc-main-theme')
            ));
        }
        
        // Check if user already exists
        $users = get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone,
            'number' => 1,
            'count_total' => false
        ));
        
        if (!empty($users)) {
            // User exists - log them in
            $user = $users[0];
            wp_set_auth_cookie($user->ID, true);
            wp_send_json_success(array('message' => 'Login successful'));
        }
        
        // Check if email already exists
        if (email_exists($email)) {
            wp_send_json_error(array(
                'message' => __('כתובת האימייל כבר קיימת במערכת', 'oc-main-theme')
            ));
        }
        
        // Create new user
        $username = 'user_' . $phone;
        $password = wp_generate_password(12, false);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array(
                'message' => $user_id->get_error_message()
            ));
        }
        
        // Update user meta
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', $last_name);
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        
        // Log the new user in
        wp_set_auth_cookie($user_id, true);
        
        wp_send_json_success(array(
            'message' => __('הרשמה הושלמה בהצלחה', 'oc-main-theme')
        ));
    }

    /**
     * Format phone number for display
     */
    private function format_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            return substr($phone, 0, 3) . '-' . substr($phone, 3);
        }
        return $phone;
    }
}

// Initialize
OC_SMS_Auth::get_instance(); 