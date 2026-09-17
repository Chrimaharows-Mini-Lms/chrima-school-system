<?php (!defined('BASEPATH')) and exit('No direct script access allowed');

class Custom_sms
{
    /*
     * ============================================
     * GEEZ SMS CONFIGURATION
     * ============================================
     */

    // GeezSMS API URL
    private $apiURL = 'https://api.geezsms.com/api/v1/sms/send';

    // Your GeezSMS API token
    private $token = 'YOUR_GEEZ_SMS_TOKEN';

    // Your GeezSMS shortcode ID (optional)
    // Leave empty if you want to use GeezSMS default shortcode
    private $shortcodeID = '';

    // Optional webhook/callback URL
    // Leave empty if you don't need callbacks
    private $callbackURL = '';


    /**
     * Constructor
     */
    public function __construct()
    {
        // No database configuration is required.
    }


    /**
     * Send SMS
     *
     * @param string|array $numbers
     * @param string $message
     * @param string $dlt_template_id
     *
     * @return bool
     */
    public function send($numbers, $message, $dlt_template_id = '')
    {
        /*
         * If multiple phone numbers are provided,
         * send the SMS separately to each number.
         */
        if (is_array($numbers)) {

            $success = true;

            foreach ($numbers as $number) {

                if (!$this->sendSingle($number, $message)) {
                    $success = false;
                }
            }

            return $success;
        }

        return $this->sendSingle($numbers, $message);
    }


    /**
     * Send SMS to one phone number
     */
    private function sendSingle($number, $message)
    {
        /*
         * Clean phone number
         */
        $number = trim($number);

        /*
         * Convert:
         *
         * 0912345678
         * +251912345678
         *
         * into:
         *
         * 251912345678
         */
        if (strpos($number, '+251') === 0) {
            $number = substr($number, 1);
        }

        if (strpos($number, '0') === 0) {
            $number = '251' . substr($number, 1);
        }

        /*
         * GeezSMS requires Ethiopian mobile numbers
         * to start with 2519.
         */
        if (strpos($number, '2519') !== 0) {

            log_message(
                'error',
                'GeezSMS: Invalid phone number: ' . $number
            );

            return false;
        }

        /*
         * GeezSMS message limit:
         * Less than 335 characters.
         */
        if (mb_strlen($message, 'UTF-8') >= 335) {

            log_message(
                'error',
                'GeezSMS: Message is too long.'
            );

            return false;
        }

        /*
         * Build multipart/form-data request.
         */
        $postFields = array(
            'token' => $this->token,
            'phone' => $number,
            'msg'   => $message,
        );

        /*
         * Add shortcode if configured.
         */
        if (!empty($this->shortcodeID)) {
            $postFields['shortcode_id'] = $this->shortcodeID;
        }

        /*
         * Add callback if configured.
         */
        if (!empty($this->callbackURL)) {
            $postFields['callback'] = $this->callbackURL;
        }

        /*
         * Initialize cURL.
         */
        $curl = curl_init();

        curl_setopt_array($curl, array(

            CURLOPT_URL => $this->apiURL,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => $postFields,

            /*
             * SSL verification
             */
            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_SSL_VERIFYPEER => true,

            /*
             * Timeout settings
             */
            CURLOPT_CONNECTTIMEOUT => 15,

            CURLOPT_TIMEOUT => 30,

            /*
             * Accept JSON response.
             *
             * Do NOT manually set Content-Type.
             * cURL will automatically generate
             * the multipart/form-data boundary.
             */
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json'
            ),
        ));

        /*
         * Send request.
         */
        $response = curl_exec($curl);

        /*
         * Check cURL error.
         */
        if ($response === false) {

            $error = curl_error($curl);

            log_message(
                'error',
                'GeezSMS cURL Error: ' . $error
            );

            curl_close($curl);

            return false;
        }

        /*
         * Get HTTP status code.
         */
        $httpCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        /*
         * Decode response.
         */
        $result = json_decode($response, true);

        /*
         * Log response.
         */
        log_message(
            'info',
            'GeezSMS Response: HTTP ' .
            $httpCode .
            ' - ' .
            $response
        );

        /*
         * Check JSON response.
         */
        if (!is_array($result)) {

            log_message(
                'error',
                'GeezSMS returned invalid JSON: ' .
                $response
            );

            return false;
        }

        /*
         * Successful response example:
         *
         * {
         *     "message_status": "success",
         *     "log": "async ...",
         *     "phone": "251...",
         *     "message": "Test message",
         *     "api_log_id": 6569829
         * }
         */
        if (
            isset($result['message_status']) &&
            strtolower($result['message_status']) === 'success'
        ) {
            return true;
        }

        /*
         * SMS failed.
         */
        log_message(
            'error',
            'GeezSMS API Error: ' .
            $response
        );

        return false;
    }
}