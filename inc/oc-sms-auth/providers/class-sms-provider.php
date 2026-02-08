<?php
abstract class SMS_Provider {
    protected $settings;

    public function __construct($settings) {
        $this->settings = $settings;
    }

    abstract public function send_sms($phone, $message);
    abstract public function get_balance();
}

class ActiveTrail_Provider extends SMS_Provider {
    private $api_url = 'https://webapi.mymarketing.co.il/api/';

    public function send_sms($phone, $message) {
        if (empty($this->settings['activetrail_api_key']) || empty($this->settings['sender_name'])) {
            return false;
        }

        // Format phone number (remove any non-digit characters)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Prepare the API request data
        $data = array(
            'details' => array(
                'unsubscribe_text' => '',
                'can_unsubscribe' => false,
                'name' => 'test',
                'from_name' => $this->settings['sender_name'],
                'content' => $message
            ),
            'scheduling' => array(
                'send_now' => true
            ),
            'mobiles' => array(
                array(
                    'phone_number' => $phone
                )
            )
        );

        $endpoint = $this->api_url . 'smscampaign/OperationalMessage';
        
        // Headers exactly as in the working request
        $headers = array(
            'Authorization: ' . $this->settings['activetrail_api_key'],
            'Content-Type: application/json'
        );

        // Make the API request 
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMS Request Data: ' . json_encode($data));
            error_log('SMS Response: ' . $response);
            error_log('SMS HTTP Code: ' . $http_code);
        }

        curl_close($ch);

        return $http_code === 200;
    }

    public function get_balance() {
        $endpoint = $this->api_url . 'account/balance';
        $headers = array(
            'Authorization: ' . $this->settings['activetrail_api_key'],
            'Content-Type: application/json'
        );

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $decodedResponse = json_decode($response);
            if (isset($decodedResponse->sms->credits)) {
                return $decodedResponse->sms->credits;
            }
        }
        
        return false;
    }
}

class Twilio_Provider extends SMS_Provider {
    public function send_sms($phone, $message) {
        $account_sid = $this->settings['twilio_account_sid'];
        $auth_token = $this->settings['twilio_auth_token'];
        $twilio_number = $this->settings['twilio_phone_number'];

        // Format phone number to E.164 format
        $phone = $this->format_phone_number($phone);

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token)
            ),
            'body' => array(
                'From' => $twilio_number,
                'To' => $phone,
                'Body' => $message
            ),
            'method' => 'POST'
        );

        $response = wp_remote_post($url, $args);
        
        // Log error for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Twilio Response: ' . print_r($response, true));
        }

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) { // Twilio returns 201 on success
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        return isset($body->sid);
    }

    /**
     * Format phone number to E.164 format
     */
    private function format_phone_number($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If number starts with 0, remove it and add Israel country code
        if (substr($phone, 0, 1) === '0') {
            $phone = '+972' . substr($phone, 1);
        }
        // If number doesn't have country code, add Israel country code
        elseif (substr($phone, 0, 1) !== '+') {
            $phone = '+972' . $phone;
        }
        
        return $phone;
    }

    public function get_balance() {
        // Twilio doesn't have a direct balance API
        return null;
    }
} 