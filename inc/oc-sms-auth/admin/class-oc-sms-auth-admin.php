<?php
/**
 * SMS Authentication Admin Class
 */
class OC_SMS_Auth_Admin {
    private $settings;
    private $parent;

    public function __construct($parent) {
        $this->parent = $parent;
        $this->settings = $parent->get_settings();
        
        // Create required tables
        $this->create_tables();
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('oc-main-theme', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_oc_sms_auth_unblock_ip', array($this, 'ajax_unblock_ip'));
        add_action('wp_ajax_oc_sms_auth_clear_logs', array($this, 'ajax_clear_logs'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {

        // Custom admin styles
        wp_enqueue_style(
            'oc-sms-auth-admin',
            get_template_directory_uri().'/inc/lib/oc-sms-auth/admin/css/admin.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'css/admin.css')
        );
        // Custom admin scripts
        wp_enqueue_script(
            'oc-sms-auth-admin',
            get_template_directory_uri().'/inc/lib/oc-sms-auth/admin/js/admin.js',
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'js/admin.js'),
            true
        );

        wp_localize_script('oc-sms-auth-admin', 'ocSmsAuthAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_sms_auth_admin'),
            'i18n' => array(
                'confirm_unblock' => __('האם אתה בטוח שברצונך לשחרר חסימה זו?', 'oc-main-theme'),
                'confirm_clear_logs' => __('האם אתה בטוח שברצונך למחוק את כל הלוגים?', 'oc-main-theme'),
                'no_logs' => __('אין לוגים.', 'oc-main-theme'),
                'no_blocked_ips' => __('אין כתובות IP חסומות.', 'oc-main-theme')
            )
        ));
    }

    /**
     * Render blocked IPs table
     */
    public function render_blocked_ips_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'oc_sms_auth_blocked_ips';

        // Get fresh data from database 
        $blocked_ips = $wpdb->get_results("
            SELECT * FROM {$table_name} 
            WHERE blocked_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            ORDER BY blocked_time DESC
        ");


        ?>
        <div class="blocked-ips-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('IP Address', 'oc-main-theme'); ?></th>
                        <th><?php _e('Failed Attempts', 'oc-main-theme'); ?></th>
                        <th><?php _e('Blocked Time', 'oc-main-theme'); ?></th>
                        <th><?php _e('Actions', 'oc-main-theme'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blocked_ips)): ?>
                        <tr>
                            <td colspan="4"><?php _e('No blocked IP addresses found.', 'oc-main-theme'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($blocked_ips as $ip): ?>
                            <tr>
                                <td><?php echo esc_html($ip->ip_address); ?></td>
                                <td><?php echo esc_html($ip->attempts); ?></td>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($ip->blocked_time))); ?></td>
                                <td>
                                    <button 
                                        class="button button-small unblock-ip" 
                                        data-ip="<?php echo esc_attr($ip->ip_address); ?>"
                                    >
                                        <?php _e('Unblock', 'oc-main-theme'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render authentication logs table
     */
    public function render_auth_logs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'oc_sms_auth_logs';
        $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY attempt_time DESC LIMIT 100");
        ?>
        <div class="auth-logs-table-wrap">
            <div class="tablenav top">
                <button class="button clear-logs">
                    <?php _e('Clear Logs', 'oc-main-theme'); ?>
                </button>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'oc-main-theme'); ?></th>
                        <th><?php _e('Phone', 'oc-main-theme'); ?></th>
                        <th><?php _e('IP Address', 'oc-main-theme'); ?></th>
                        <th><?php _e('Status', 'oc-main-theme'); ?></th>
                        <th><?php _e('Device', 'oc-main-theme'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5"><?php _e('No authentication logs found.', 'oc-main-theme'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->attempt_time))); ?></td>
                                <td><?php echo esc_html($log->phone); ?></td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td><?php echo esc_html($this->get_status_label($log->status)); ?></td>
                                <td><?php echo esc_html($log->user_agent); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'success' => __('Success', 'oc-main-theme'),
            'failed' => __('Failed', 'oc-main-theme'),
            'blocked' => __('Blocked', 'oc-main-theme'),
            'expired' => __('Expired', 'oc-main-theme')
        );

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce', // This should be the slug of the parent menu
            __('SMS Authentication', 'oc-main-theme'), // Page title with translation
            __('SMS Authentication', 'oc-main-theme'), // Menu title with translation
            'manage_options', // Capability required to see this option
            'oc_sms_auth_options', // Menu slug for this option
            array($this, 'render_admin_page') // Callback function to render the admin page
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('oc_sms_auth_options', 'oc_sms_auth_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // Single Settings Section for all settings
        add_settings_section(
            'oc_sms_auth_settings',
            __('SMS Authentication Settings', 'oc-main-theme'),
            null,
            'oc_sms_auth_options'
        );

        // General Settings
        add_settings_field(
            'is_active',
            __('Enable SMS Authentication', 'oc-main-theme'),
            array($this, 'render_checkbox_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'is_active',
                'name' => 'is_active',
                'description' => __('Enable or disable SMS authentication functionality', 'oc-main-theme')
            )
        );
//
//        add_settings_field(
//            'allow_registration',
//            __('Allow Registration via SMS', 'oc-main-theme'),
//            array($this, 'render_checkbox_field'),
//            'oc_sms_auth_options',
//            'oc_sms_auth_settings',
//            array(
//                'label_for' => 'allow_registration',
//                'name' => 'allow_registration',
//                'description' => __('Allow new users to register using SMS verification', 'oc-main-theme')
//            )
//        );

        // SMS Provider Settings
        add_settings_field(
            'sms_provider',
            __('SMS Provider', 'oc-main-theme'),
            array($this, 'render_select_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'sms_provider',
                'name' => 'sms_provider',
                'options' => array(
                    'activetrail' => 'ActiveTrail',
                    'twilio' => 'Twilio'
                ),
                'default' => 'activetrail',
                'description' => __('Select your SMS service provider', 'oc-main-theme')
            )
        );

        // ActiveTrail Settings
        add_settings_field(
            'activetrail_api_key',
            __('ActiveTrail API Key', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'activetrail_api_key',
                'name' => 'activetrail_api_key',
                'description' => __('Enter your ActiveTrail API key', 'oc-main-theme'),
                'class' => 'activetrail-setting'
            )
        );

        // Twilio Settings
        add_settings_field(
            'twilio_account_sid',
            __('Twilio Account SID', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'twilio_account_sid',
                'name' => 'twilio_account_sid',
                'description' => __('Enter your Twilio Account SID', 'oc-main-theme'),
                'class' => 'twilio-setting'
            )
        );

        add_settings_field(
            'twilio_auth_token',
            __('Twilio Auth Token', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'twilio_auth_token',
                'name' => 'twilio_auth_token',
                'description' => __('Enter your Twilio Auth Token', 'oc-main-theme'),
                'class' => 'twilio-setting'
            )
        );

        add_settings_field(
            'twilio_phone_number',
            __('Twilio Phone Number', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'twilio_phone_number',
                'name' => 'twilio_phone_number',
                'description' => __('Enter your Twilio Phone Number', 'oc-main-theme'),
                'class' => 'twilio-setting'
            )
        );

        // Move SMS text setting right after sender_name
        add_settings_field(
            'sender_name',
            __('Sender Name', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'sender_name',
                'name' => 'sender_name',
                'description' => __('SMS sender name (as registered with ActiveTrail)', 'oc-main-theme')
            )
        );

        add_settings_field(
            'sms_text',
            __('SMS Message Template', 'oc-main-theme'),
            array($this, 'render_textarea_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'sms_text',
                'name' => 'sms_text',
                'default' => __('קוד האימות שלך הוא: [code]. הקוד תקף ל-[time] דקות.', 'oc-main-theme'),
                'description' => __('Use [code] for verification code and [time] for expiry time in minutes', 'oc-main-theme')
            )
        );

        // Security Settings
        add_settings_field(
            'code_expiry',
            __('Code Expiry (seconds)', 'oc-main-theme'),
            array($this, 'render_number_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'code_expiry',
                'name' => 'code_expiry',
                'min' => 60,
                'max' => 600,
                'default' => 180,
                'description' => __('How long the verification code remains valid (60-600 seconds)', 'oc-main-theme')
            )
        );

        add_settings_field(
            'max_attempts',
            __('Max Login Attempts', 'oc-main-theme'),
            array($this, 'render_number_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'max_attempts',
                'name' => 'max_attempts',
                'min' => 1,
                'max' => 10,
                'default' => 3,
                'description' => __('Maximum number of failed attempts before blocking', 'oc-main-theme')
            )
        );

        // Add max resend attempts setting
        add_settings_field(
            'max_resend',
            __('Max Resend Attempts', 'oc-main-theme'),
            array($this, 'render_number_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'max_resend',
                'name' => 'max_resend',
                'min' => 1,
                'max' => 5,
                'default' => 3,
                'description' => __('Maximum number of times a user can request a new code (1-5)', 'oc-main-theme')
            )
        );

        add_settings_field(
            'resend_button_text',
            __('Resend Code Button Text', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'resend_button_text',
                'name' => 'resend_button_text',
                'default' => __('שלח קוד חדש', 'oc-main-theme'),
                'description' => __('Text for the resend code button', 'oc-main-theme')
            )
        );


        // Form Text Settings
        add_settings_field(
            'form_title',
            __('Form Title', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'form_title',
                'name' => 'form_title',
                'default' => __('מספר טלפון ונתחיל :)', 'oc-main-theme'),
                'description' => __('Main form title text', 'oc-main-theme')
            )
        );

        add_settings_field(
            'phone_placeholder',
            __('Phone Input Placeholder', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'phone_placeholder',
                'name' => 'phone_placeholder',
                'default' => __('מספר טלפון', 'oc-main-theme'),
                'description' => __('Placeholder text for phone input', 'oc-main-theme')
            )
        );

        add_settings_field(
            'submit_button_text',
            __('Submit Button Text', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'submit_button_text',
                'name' => 'submit_button_text',
                'default' => __('קדימה', 'oc-main-theme'),
                'description' => __('Text for the submit button', 'oc-main-theme')
            )
        );

        add_settings_field(
            'verification_title',
            __('Verification Form Title', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'verification_title',
                'name' => 'verification_title',
                'default' => __('יש להזין את הקוד שהתקבל ב SMS', 'oc-main-theme'),
                'description' => __('Title for verification code form', 'oc-main-theme')
            )
        );

        add_settings_field(
            'verify_button_text',
            __('Verify Button Text', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'verify_button_text',
                'name' => 'verify_button_text',
                'default' => __('התחבר', 'oc-main-theme'),
                'description' => __('Text for the verification button', 'oc-main-theme')
            )
        );


        add_settings_field(
            'container_class',
            __('Container Class', 'oc-main-theme'),
            array($this, 'render_text_field'),
            'oc_sms_auth_options',
            'oc_sms_auth_settings',
            array(
                'label_for' => 'container_class',
                'name' => 'container_class',
                'default' => 'sms-auth-container',
                'description' => __('CSS class for the main container', 'oc-main-theme')
            )
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap settings-page-oc-sms-auth">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            // Get and display SMS balance
            $sms_balance = $this->parent->get_sms_balance();
            if ($sms_balance !== false) {
                echo '<div class="sms-balance-notice">';
                echo '<p>' . sprintf(
                    __('יתרת SMS: %s', 'oc-main-theme'),
                    '<strong>' . esc_html($sms_balance) . '</strong>'
                ) . '</p>';
                echo '</div>';
            }
            ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('oc_sms_auth_options');
                do_settings_sections('oc_sms_auth_options');
                submit_button();
                ?>
            </form>

            <div class="blocked-ips-section">
                <h2><?php _e('Blocked IP Addresses', 'oc-main-theme'); ?></h2>
                <?php $this->render_blocked_ips_table(); ?>
            </div>

            <div class="auth-logs-section">
                <h2><?php _e('Authentication Logs', 'oc-main-theme'); ?></h2>
                <?php $this->render_auth_logs_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     */
    public  function render_checkbox_field($args) {
        $name = $args['name'];
        $value = isset($this->settings[$name]) ? $this->settings[$name] : false;
        ?>
        <label>
            <input type="checkbox"
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="oc_sms_auth_settings[<?php echo esc_attr($name); ?>]"
                   value="1"
                   <?php checked($value, true); ?>>
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field($args) {
        $name = $args['name'];
        $value = isset($this->settings[$name]) ? $this->settings[$name] : '';
        ?>
        <input type="text"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="oc_sms_auth_settings[<?php echo esc_attr($name); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    /**
     * Render number field
     */
    public function render_number_field($args) {
        $name = $args['name'];
        $value = isset($this->settings[$name]) ? $this->settings[$name] : $args['default'];
        ?>
        <input type="number" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="oc_sms_auth_settings[<?php echo esc_attr($name); ?>]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>"
               max="<?php echo esc_attr($args['max']); ?>"
               class="small-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($args) {
        $value = isset($this->settings[$args['name']]) 
            ? $this->settings[$args['name']] 
            : $args['default'];
        ?>
        <textarea 
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="oc_sms_auth_settings[<?php echo esc_attr($args['name']); ?>]"
            rows="3"
            cols="50"
        ><?php echo esc_textarea($value); ?></textarea>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    /**
     * Render select field
     */
    public function render_select_field($args) {
        $name = $args['name'];
        $options = $args['options'];
        $default = $args['default'] ?? '';
        $value = $this->settings[$name] ?? $default;
        ?>
        <select 
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="oc_sms_auth_settings[<?php echo esc_attr($name); ?>]"
        >
            <?php foreach ($options as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Basic settings
        $sanitized['is_active'] = isset($input['is_active']);
        $sanitized['allow_registration'] = isset($input['allow_registration']);
        
        // Provider settings
        $sanitized['sms_provider'] = sanitize_text_field($input['sms_provider']);
        
        // ActiveTrail settings
        $sanitized['activetrail_api_key'] = sanitize_text_field($input['activetrail_api_key']);
        
        // Twilio settings
        $sanitized['twilio_account_sid'] = sanitize_text_field($input['twilio_account_sid']);
        $sanitized['twilio_auth_token'] = sanitize_text_field($input['twilio_auth_token']);
        $sanitized['twilio_phone_number'] = sanitize_text_field($input['twilio_phone_number']);
        
        // Common settings
        $sanitized['sender_name'] = sanitize_text_field($input['sender_name']);
        $sanitized['code_expiry'] = min(max(intval($input['code_expiry']), 60), 600);
        $sanitized['max_attempts'] = min(max(intval($input['max_attempts']), 1), 10);
        $sanitized['max_resend'] = min(max(intval($input['max_resend']), 1), 5);
        
        // Text fields
        $text_fields = array(
            'form_title',
            'phone_placeholder',
            'submit_button_text',
            'verification_title',
            'verify_button_text',
            'container_class',
            'resend_button_text'
        );

        foreach ($text_fields as $field) {
            $sanitized[$field] = sanitize_text_field($input[$field] ?? '');
        }
        
        // SMS text template
        $sanitized['sms_text'] = wp_kses(
            $input['sms_text'],
            array()  // No HTML allowed
        );
        
        return $sanitized;
    }

    /**
     * Create required database tables
     */
    private function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Blocked IPs table
        $table_name = $wpdb->prefix . 'oc_sms_auth_blocked_ips';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            blocked_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ip_address (ip_address)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Auth logs table
        $table_name = $wpdb->prefix . 'oc_sms_auth_logs';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            status varchar(20) NOT NULL,
            user_agent text,
            attempt_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Block IP address
     */
    public function block_ip($ip_address) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'oc_sms_auth_blocked_ips';
        
        // First check if IP is already blocked
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE ip_address = %s",
            $ip_address
        ));

        if ($existing) {
            // Update existing record
            return $wpdb->update(
                $table_name,
                array(
                    'attempts' => $this->settings['max_attempts'],
                    'blocked_time' => current_time('mysql')
                ),
                array('ip_address' => $ip_address),
                array('%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            return $wpdb->insert(
                $table_name,
                array(
                    'ip_address' => $ip_address,
                    'attempts' => $this->settings['max_attempts'],
                    'blocked_time' => current_time('mysql')
                ),
                array('%s', '%d', '%s')
            );
        }
    }

    /**
     * Unblock an IP address
     */
    public function unblock_ip($ip_address) {
        global $wpdb;
        
        // Delete from blocked IPs table
        $blocked_table = $wpdb->prefix . 'oc_sms_auth_blocked_ips';
        $wpdb->delete(
            $blocked_table,
            array('ip_address' => $ip_address),
            array('%s')
        );

        // Reset failed attempts in auth logs
        $logs_table = $wpdb->prefix . 'oc_sms_auth_logs';
        $wpdb->query($wpdb->prepare(
            "UPDATE $logs_table 
            SET status = 'reset' 
            WHERE ip_address = %s 
            AND status = 'failed' 
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $ip_address
        ));

        return true;
    }

    /**
     * Check if an IP is blocked
     */
    public function is_ip_blocked($ip_address) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'oc_sms_auth_blocked_ips';
        
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE ip_address = %s",
            $ip_address
        ));

        return $blocked > 0;
    }

    /**
     * Log authentication attempt
     */
    public function log_auth_attempt($phone, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'oc_sms_auth_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'phone' => $phone,
                'ip_address' => $this->get_client_ip(),
                'status' => $status,
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ),
            array('%s', '%s', '%s', '%s')
        );
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
     * Clear auth logs
     */
    public function clear_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'oc_sms_auth_logs';
        
        return $wpdb->query("TRUNCATE TABLE $table_name");
    }

    /**
     * AJAX handler for unblocking IP
     */
    public function ajax_unblock_ip() {
        check_ajax_referer('oc_sms_auth_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'oc-main-theme'));
        }

        $ip = sanitize_text_field($_POST['ip']);
        if ($this->unblock_ip($ip)) {
            wp_send_json_success(__('IP unblocked successfully', 'oc-main-theme'));
        } else {
            wp_send_json_error(__('Failed to unblock IP', 'oc-main-theme'));
        }
    }

    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('oc_sms_auth_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'oc-main-theme'));
        }

        if ($this->clear_logs()) {
            wp_send_json_success(__('Logs cleared successfully', 'oc-main-theme'));
        } else {
            wp_send_json_error(__('Failed to clear logs', 'oc-main-theme'));
        }
    }
} 