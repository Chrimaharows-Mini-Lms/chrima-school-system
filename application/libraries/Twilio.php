
<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter 3 Twilio Library
 *
 * Uses Twilio REST API
 * API Version: 2010-04-01
 *
 * Database fields:
 * field_one   = Twilio Account SID
 * field_two   = Twilio Auth Token
 * field_three = Twilio Phone Number
 */

class Twilio
{
    protected $_ci;
    protected $_twilio;

    protected $mode;
    protected $account_sid;
    protected $auth_token;
    protected $api_version;
    protected $number;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_ci =& get_instance();

        /*
         * Get branch ID
         */
        $branchID = null;

        if (function_exists('is_superadmin_loggedin') && is_superadmin_loggedin()) {
            $branchID = $this->_ci->input->post('branch_id');
        } elseif (function_exists('get_loggedin_branch_id')) {
            $branchID = get_loggedin_branch_id();
        }

        /*
         * Get Twilio credentials from database
         */
        $twilio = array();

        if ($branchID !== null && $branchID !== '') {

            $query = $this->_ci->db->get_where(
                'sms_credential',
                array(
                    'sms_api_id' => 1,
                    'branch_id'  => $branchID
                )
            );

            if ($query->num_rows() > 0) {
                $twilio = $query->row_array();
            }
        }

        /*
         * Twilio configuration
         */
        $this->mode        = 'prod';
        $this->api_version = '2010-04-01';

        /*
         * Database mapping
         *
         * field_one   = Account SID
         * field_two   = Auth Token
         * field_three = Twilio phone number
         */
        $this->account_sid = isset($twilio['field_one'])
            ? trim($twilio['field_one'])
            : '';

        $this->auth_token = isset($twilio['field_two'])
            ? trim($twilio['field_two'])
            : '';

        $this->number = isset($twilio['field_three'])
            ? trim($twilio['field_three'])
            : '';

        /*
         * Initialize Twilio client
         */
        $this->_twilio = new TwilioRestClient(
            $this->account_sid,
            $this->auth_token
        );
    }

    /**
     * Return Twilio configuration
     *
     * IMPORTANT:
     * Auth token is intentionally not returned.
     */
    public function get_twilio()
    {
        return array(
            'mode'       => $this->mode,
            'accountSID' => $this->account_sid,
            'version'    => $this->api_version,
            'number'     => $this->number
        );
    }

    /**
     * Send SMS
     *
     * @param string $from
     * @param string $to
     * @param string $message
     *
     * @return TwilioRestResponse
     */
    public function sms($from, $to, $message)
    {
        /*
         * Validate credentials
         */
        if (empty($this->account_sid)) {
            throw new TwilioException(
                'Twilio Account SID is missing.'
            );
        }

        if (empty($this->auth_token)) {
            throw new TwilioException(
                'Twilio Auth Token is missing.'
            );
        }

        /*
         * Use configured Twilio number if no From number
         * was supplied.
         */
        if (empty($from)) {
            $from = $this->number;
        }

        if (empty($from)) {
            throw new TwilioException(
                'Twilio From phone number is missing.'
            );
        }

        if (empty($to)) {
            throw new TwilioException(
                'Recipient phone number is missing.'
            );
        }

        if (empty($message)) {
            throw new TwilioException(
                'SMS message is empty.'
            );
        }

        /*
         * Twilio Messages API endpoint
         *
         * Correct endpoint:
         *
         * /2010-04-01/Accounts/{AccountSid}/Messages.json
         */
        $url = '/'
            . $this->api_version
            . '/Accounts/'
            . $this->account_sid
            . '/Messages.json';

        /*
         * Request data
         */
        $data = array(
            'From' => $from,
            'To'   => $to,
            'Body' => $message
        );

        /*
         * Send request
         */
        return $this->_twilio->request(
            $url,
            'POST',
            $data
        );
    }

    /**
     * Magic method to access TwilioRestClient methods
     */
    public function __call($method, $arguments)
    {
        if (!method_exists($this->_twilio, $method)) {
            throw new Exception(
                'Undefined method Twilio::' . $method . '() called'
            );
        }

        return call_user_func_array(
            array($this->_twilio, $method),
            $arguments
        );
    }
}


/**
 * ============================================================
 * Twilio REST Response
 * ============================================================
 */
class TwilioRestResponse
{
    public $ResponseText;
    public $ResponseXml;
    public $HttpStatus;
    public $Url;
    public $QueryString;
    public $IsError;
    public $ErrorMessage;

    public function __construct($url, $text, $status)
    {
        /*
         * Separate URL and query string
         */
        $parts = explode('?', $url, 2);

        $this->Url = isset($parts[0])
            ? $parts[0]
            : $url;

        $this->QueryString = isset($parts[1])
            ? $parts[1]
            : '';

        $this->ResponseText = $text;
        $this->HttpStatus = $status;

        /*
         * Try to parse XML if returned
         */
        $this->ResponseXml = null;

        if ($this->HttpStatus != 204 && !empty($text)) {
            $this->ResponseXml = @simplexml_load_string($text);
        }

        /*
         * HTTP 400+ means error
         */
        $this->IsError = ($status >= 400);

        /*
         * Get error message
         */
        $this->ErrorMessage = '';

        if ($this->IsError) {

            /*
             * Twilio XML error
             */
            if ($this->ResponseXml) {

                if (isset($this->ResponseXml->RestException->Message)) {
                    $this->ErrorMessage =
                        (string) $this->ResponseXml->RestException->Message;
                }

                /*
                 * Newer Twilio API responses can contain:
                 * <message>...</message>
                 */
                if (
                    empty($this->ErrorMessage) &&
                    isset($this->ResponseXml->message)
                ) {
                    $this->ErrorMessage =
                        (string) $this->ResponseXml->message;
                }
            }

            /*
             * If XML didn't contain an error,
             * use raw response.
             */
            if (empty($this->ErrorMessage)) {
                $this->ErrorMessage = $text;
            }
        }
    }
}


/**
 * ============================================================
 * Twilio Exception
 * ============================================================
 */
class TwilioException extends Exception
{
}


/**
 * ============================================================
 * Twilio REST Client
 * ============================================================
 */
class TwilioRestClient
{
    protected $Endpoint;
    protected $AccountSid;
    protected $AuthToken;

    /**
     * Constructor
     */
    public function __construct(
        $accountSid,
        $authToken,
        $endpoint = 'https://api.twilio.com'
    ) {
        $this->AccountSid = trim($accountSid);
        $this->AuthToken  = trim($authToken);
        $this->Endpoint   = rtrim($endpoint, '/');

        /*
         * Check cURL
         */
        if (!extension_loaded('curl')) {
            throw new TwilioException(
                'PHP cURL extension is required for Twilio.'
            );
        }
    }

    /**
     * Send REST request
     */
    public function request(
        $path,
        $method = 'GET',
        $vars = array()
    ) {
        /*
         * Validate credentials
         */
        if (empty($this->AccountSid)) {
            throw new TwilioException(
                'Twilio Account SID is empty.'
            );
        }

        if (empty($this->AuthToken)) {
            throw new TwilioException(
                'Twilio Auth Token is empty.'
            );
        }

        /*
         * Normalize path
         */
        $path = '/' . ltrim($path, '/');

        /*
         * Build URL
         */
        $url = $this->Endpoint . $path;

        /*
         * Build POST data
         */
        $encoded = '';

        if (!empty($vars)) {
            $encoded = http_build_query(
                $vars,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
        }

        /*
         * For GET requests append query parameters
         */
        if (
            strtoupper($method) === 'GET' &&
            !empty($encoded)
        ) {
            $url .= (
                strpos($url, '?') === false
                ? '?'
                : '&'
            ) . $encoded;
        }

        /*
         * Initialize cURL
         */
        $curl = curl_init($url);

        if ($curl === false) {
            throw new TwilioException(
                'Unable to initialize cURL.'
            );
        }

        /*
         * General cURL options
         */
        curl_setopt(
            $curl,
            CURLOPT_RETURNTRANSFER,
            true
        );

        /*
         * SSL verification ENABLED
         */
        curl_setopt(
            $curl,
            CURLOPT_SSL_VERIFYPEER,
            true
        );

        curl_setopt(
            $curl,
            CURLOPT_SSL_VERIFYHOST,
            2
        );

        /*
         * Follow redirects
         */
        curl_setopt(
            $curl,
            CURLOPT_FOLLOWLOCATION,
            true
        );

        /*
         * Connection timeout
         */
        curl_setopt(
            $curl,
            CURLOPT_CONNECTTIMEOUT,
            15
        );

        /*
         * Request timeout
         */
        curl_setopt(
            $curl,
            CURLOPT_TIMEOUT,
            30
        );

        /*
         * User agent
         */
        curl_setopt(
            $curl,
            CURLOPT_USERAGENT,
            'CodeIgniter Twilio Client'
        );

        /*
         * HTTP method
         */
        switch (strtoupper($method)) {

            case 'GET':

                curl_setopt(
                    $curl,
                    CURLOPT_HTTPGET,
                    true
                );

                break;

            case 'POST':

                curl_setopt(
                    $curl,
                    CURLOPT_POST,
                    true
                );

                curl_setopt(
                    $curl,
                    CURLOPT_POSTFIELDS,
                    $encoded
                );

                break;

            case 'PUT':

                curl_setopt(
                    $curl,
                    CURLOPT_CUSTOMREQUEST,
                    'PUT'
                );

                curl_setopt(
                    $curl,
                    CURLOPT_POSTFIELDS,
                    $encoded
                );

                break;

            case 'DELETE':

                curl_setopt(
                    $curl,
                    CURLOPT_CUSTOMREQUEST,
                    'DELETE'
                );

                break;

            default:

                curl_close($curl);

                throw new TwilioException(
                    'Unknown HTTP method: ' . $method
                );
        }

        /*
         * HTTP Basic Authentication
         *
         * Twilio uses:
         *
         * Account SID : Auth Token
         */
        curl_setopt(
            $curl,
            CURLOPT_USERPWD,
            $this->AccountSid . ':' . $this->AuthToken
        );

        /*
         * Execute request
         */
        $result = curl_exec($curl);

        /*
         * Check cURL error
         */
        if ($result === false) {

            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);

            curl_close($curl);

            throw new TwilioException(
                'Twilio cURL error (' .
                $curlErrno .
                '): ' .
                $curlError
            );
        }

        /*
         * Get HTTP status
         */
        $responseCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        /*
         * Get content type
         */
        $contentType = curl_getinfo(
            $curl,
            CURLINFO_CONTENT_TYPE
        );

        /*
         * Close cURL
         */
        curl_close($curl);

        /*
         * Create response object
         */
        $response = new TwilioRestResponse(
            $url,
            $result,
            $responseCode
        );

        /*
         * If Twilio returned an error, keep the
         * complete response available to the caller.
         */
        return $response;
    }
}


/**
 * ============================================================
 * TwiML Response Helpers
 * ============================================================
 */

class Verb
{
    private $tag;
    private $body;
    private $attr;
    private $children;

    protected $valid = array();
    protected $nesting = null;

    /**
     * Constructor
     */
    public function __construct(
        $body = null,
        $attr = array()
    ) {
        if (is_array($body)) {
            $attr = $body;
            $body = null;
        }

        $this->tag = get_class($this);
        $this->body = $body;
        $this->attr = array();
        $this->children = array();

        $this->addAttributes($attr);
    }

    /**
     * Add attributes
     */
    private function addAttributes($attr)
    {
        foreach ($attr as $key => $value) {

            if (in_array($key, $this->valid)) {

                $this->attr[$key] = $value;

            } else {

                throw new TwilioException(
                    $key . ', ' . $value .
                    ' is not a supported attribute pair'
                );
            }
        }
    }

    /**
     * Append child verb
     */
    public function append($verb)
    {
        if (is_null($this->nesting)) {

            throw new TwilioException(
                $this->tag .
                " doesn't support nesting"
            );

        } elseif (!is_object($verb)) {

            throw new TwilioException(
                'Verb is not an object'
            );

        } elseif (!in_array(
            get_class($verb),
            $this->nesting
        )) {

            throw new TwilioException(
                get_class($verb) .
                ' is not an allowed verb here'
            );

        } else {

            $this->children[] = $verb;

            return $verb;
        }
    }

    /**
     * Set attribute
     */
    public function set($key, $value)
    {
        $this->attr[$key] = $value;
    }

    /**
     * Convenience methods
     */
    public function addSay(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Say($body, $attr)
        );
    }

    public function addPlay(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Play($body, $attr)
        );
    }

    public function addDial(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Dial($body, $attr)
        );
    }

    public function addNumber(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Number($body, $attr)
        );
    }

    public function addGather(
        $attr = array()
    ) {
        return $this->append(
            new Gather($attr)
        );
    }

    public function addRecord(
        $attr = array()
    ) {
        return $this->append(
            new Record(null, $attr)
        );
    }

    public function addHangup()
    {
        return $this->append(
            new Hangup()
        );
    }

    public function addRedirect(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Redirect($body, $attr)
        );
    }

    public function addPause(
        $attr = array()
    ) {
        return $this->append(
            new Pause($attr)
        );
    }

    public function addConference(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Conference($body, $attr)
        );
    }

    public function addSms(
        $body = null,
        $attr = array()
    ) {
        return $this->append(
            new Sms($body, $attr)
        );
    }

    /**
     * Write XML
     */
    protected function write(
        $parent,
        $writeself = true
    ) {
        if ($writeself) {

            $elem = $parent->addChild(
                $this->tag,
                htmlspecialchars(
                    (string) $this->body,
                    ENT_XML1,
                    'UTF-8'
                )
            );

            foreach ($this->attr as $key => $value) {
                $elem->addAttribute(
                    $key,
                    htmlspecialchars(
                        (string) $value,
                        ENT_XML1,
                        'UTF-8'
                    )
                );
            }

            foreach ($this->children as $child) {
                $child->write($elem);
            }

        } else {

            foreach ($this->children as $child) {
                $child->write($parent);
            }
        }
    }
}


/**
 * TwiML Response
 */
class Response extends Verb
{
    private $xml =
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Response></Response>';

    protected $nesting = array(
        'Say',
        'Play',
        'Gather',
        'Record',
        'Dial',
        'Redirect',
        'Pause',
        'Hangup',
        'Sms'
    );

    public function __construct()
    {
        parent::__construct(null);
    }

    public function Respond($sendHeader = true)
    {
        if ($sendHeader) {

            if (!headers_sent()) {

                header(
                    'Content-Type: text/xml; charset=UTF-8'
                );
            }
        }

        $simplexml = new SimpleXMLElement(
            $this->xml
        );

        $this->write(
            $simplexml,
            false
        );

        print $simplexml->asXML();
    }

    public function asURL($encode = true)
    {
        $simplexml = new SimpleXMLElement(
            $this->xml
        );

        $this->write(
            $simplexml,
            false
        );

        if ($encode) {
            return urlencode(
                $simplexml->asXML()
            );
        }

        return $simplexml->asXML();
    }
}


/**
 * TwiML verbs
 */
class Say extends Verb
{
    protected $valid = array(
        'voice',
        'language',
        'loop'
    );
}

class Reject extends Verb
{
    protected $valid = array(
        'reason'
    );
}

class Play extends Verb
{
    protected $valid = array(
        'loop'
    );
}

class Record extends Verb
{
    protected $valid = array(
        'action',
        'method',
        'timeout',
        'finishOnKey',
        'maxLength',
        'transcribe',
        'transcribeCallback',
        'playBeep'
    );
}

class Dial extends Verb
{
    protected $valid = array(
        'action',
        'method',
        'timeout',
        'hangupOnStar',
        'timeLimit',
        'callerId'
    );

    protected $nesting = array(
        'Number',
        'Conference'
    );
}

class Redirect extends Verb
{
    protected $valid = array(
        'method'
    );
}

class Pause extends Verb
{
    protected $valid = array(
        'length'
    );

    public function __construct(
        $attr = array()
    ) {
        parent::__construct(
            null,
            $attr
        );
    }
}

class Hangup extends Verb
{
    public function __construct()
    {
        parent::__construct(
            null,
            array()
        );
    }
}

class Gather extends Verb
{
    protected $valid = array(
        'action',
        'method',
        'timeout',
        'finishOnKey',
        'numDigits'
    );

    protected $nesting = array(
        'Say',
        'Play',
        'Pause'
    );

    public function __construct(
        $attr = array()
    ) {
        parent::__construct(
            null,
            $attr
        );
    }
}

class Number extends Verb
{
    protected $valid = array(
        'url',
        'sendDigits'
    );
}

class Conference extends Verb
{
    protected $valid = array(
        'muted',
        'beep',
        'startConferenceOnEnter',
        'endConferenceOnExit',
        'waitUrl',
        'waitMethod'
    );
}

class Sms extends Verb
{
    protected $valid = array(
        'to',
        'from',
        'action',
        'method',
        'statusCallback'
    );
}


/**
 * ============================================================
 * Twilio Utilities
 * ============================================================
 */

class TwilioUtils
{
    protected $AccountSid;
    protected $AuthToken;

    public function __construct(
        $id,
        $token
    ) {
        $this->AuthToken = $token;
        $this->AccountSid = $id;
    }

    /**
     * Validate Twilio request signature
     */
    public function validateRequest(
        $expected_signature,
        $url,
        $data = array()
    ) {
        /*
         * Sort parameters by key
         */
        ksort($data);

        /*
         * Append key/value pairs
         */
        foreach ($data as $key => $value) {
            $url .= $key . $value;
        }

        /*
         * Generate HMAC-SHA1
         */
        $calculated_signature = base64_encode(
            hash_hmac(
                'sha1',
                $url,
                $this->AuthToken,
                true
            )
        );

        return hash_equals(
            $calculated_signature,
            $expected_signature
        );
    }
}


/* End of file Twilio.php */

