<?php
/**
 *
 * Copyright (c) 2014, Daniel Zahariev.
 * Copyright (c) 2011, Dan Myers.
 * Parts copyright (c) 2008, Donovan Schonknecht.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * This is a modified BSD license (the third clause has been removed).
 * The BSD license may be found here:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 * Amazon Simple Email Service is a trademark of Amazon.com, Inc. or its affiliates.
 *
 * SimpleEmailService is based on Donovan Schonknecht's Amazon S3 PHP class, found here:
 * http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 *
 * @copyright 2014 Daniel Zahariev
 * @copyright 2011 Dan Myers
 * @copyright 2008 Donovan Schonknecht
 */

/**
 * SimpleEmailService PHP class
 *
 * @link https://github.com/daniel-zahariev/php-aws-ses
 * @package AmazonSimpleEmailService
 * @version v0.9.5
 */
class SimpleEmailService
{
    /**
     * @link(AWS SES regions, https://docs.aws.amazon.com/general/latest/gr/ses.html)
     */
    const AWS_CA_CENTRAL_1 = 'email.ca-central-1.amazonaws.com';
    const AWS_AP_NORTHEAST_1 = 'email.ap-northeast-1.amazonaws.com';
    const AWS_AP_NORTHEAST_2 = 'email.ap-northeast-2.amazonaws.com';
    const AWS_AP_SOUTH_1 = 'email.ap-south-1.amazonaws.com';
    const AWS_AP_SOUTHEAST_1 = 'email.ap-southeast-1.amazonaws.com';
    const AWS_AP_SOUTHEAST_2 = 'email.ap-southeast-2.amazonaws.com';
    const AWS_EU_CENTRAL_1 = 'email.eu-central-1.amazonaws.com';
    const AWS_EU_WEST_1 = 'email.eu-west-1.amazonaws.com';
    const AWS_EU_WEST_2 = 'email.eu-west-2.amazonaws.com';
    const AWS_SA_EAST_1 = 'email.sa-east-1.amazonaws.com';
    const AWS_US_EAST_1 = 'email.us-east-1.amazonaws.com';
    const AWS_US_EAST_2 = 'email.us-east-2.amazonaws.com';
    const AWS_US_GOV_WEST_1 = 'email.us-gov-west-1.amazonaws.com';
    const AWS_US_WEST_2 = 'email.us-west-2.amazonaws.com';

    const REQUEST_SIGNATURE_V4 = 'v4';

    /**
     * AWS SES Target host of region
     */
    protected $__host;

    /**
     * AWS SES Access key
     */
    protected $__accessKey;

    /**
     * AWS Secret key
     */
    protected $__secretKey;

    /**
     * Enable/disable
     */
    protected $__trigger_errors;

    /**
     * Optionally reusable SimpleEmailServiceRequest instance
     */
    protected $__ses_request = null;

    /**
     * Controls CURLOPT_SSL_VERIFYHOST setting for SimpleEmailServiceRequest's curl handler
     */
    protected $__verifyHost = true;

    /**
     * Controls CURLOPT_SSL_VERIFYPEER setting for SimpleEmailServiceRequest's curl handler
     */
    protected $__verifyPeer = true;

    /**
     * @var string HTTP Request signature version
     */
    protected $__requestSignatureVersion;

    /**
     * Constructor
     *
     * @param string $accessKey Access key
     * @param string $secretKey Secret key
     * @param string $host Amazon Host through which to send the emails
     * @param boolean $trigger_errors Trigger PHP errors when AWS SES API returns an error
     */
    public function __construct($accessKey = null, $secretKey = null, $host = self::AWS_US_EAST_1, $trigger_errors = true) {
        if ($accessKey !== null && $secretKey !== null) {
            $this->setAuth($accessKey, $secretKey);
        }
        $this->__host = $host;
        $this->__trigger_errors = $trigger_errors;
        $this->__requestSignatureVersion = self::REQUEST_SIGNATURE_V4;
    }

    /**
     * @return string
     *
     * @deprecated Not relevant when only V4 is supported.
     */
    public function getRequestSignatureVersion() {
        return $this->__requestSignatureVersion;
    }

    /**
     * Set AWS access key and secret key
     *
     * @param string $accessKey Access key
     * @param string $secretKey Secret key
     * @return SimpleEmailService $this
     */
    public function setAuth($accessKey, $secretKey) {
        $this->__accessKey = $accessKey;
        $this->__secretKey = $secretKey;

        return $this;
    }

    /**
     * Set AWS Host
     * @param string $host AWS Host
     */
    public function setHost($host = self::AWS_US_EAST_1) {
        $this->__host = $host;

        return $this;
    }

    /**
     * @deprecated
     */
    public function enableVerifyHost($enable = true) {
        $this->__verifyHost = (bool)$enable;

        return $this;
    }

    /**
     * @deprecated
     */
    public function enableVerifyPeer($enable = true) {
        $this->__verifyPeer = (bool)$enable;

        return $this;
    }

    /**
     * @deprecated
     */
    public function verifyHost() {
        return $this->__verifyHost;
    }

    /**
     * @deprecated
     */
    public function verifyPeer() {
        return $this->__verifyPeer;
    }


    /**
     * Get AWS target host
     * @return boolean
     */
    public function getHost() {
        return $this->__host;
    }

    /**
     * Get AWS SES auth access key
     * @return string
     */
    public function getAccessKey() {
        return $this->__accessKey;
    }

    /**
     * Get AWS SES auth secret key
     * @return string
     */
    public function getSecretKey() {
        return $this->__secretKey;
    }

    /**
     * Get the verify peer CURL mode
     * @return boolean
     */
    public function getVerifyPeer() {
        return $this->__verifyPeer;
    }

    /**
     * Get the verify host CURL mode
     * @return boolean
     */
    public function getVerifyHost() {
        return $this->__verifyHost;
    }

    /**
     * Enable/disable CURLOPT_SSL_VERIFYHOST for SimpleEmailServiceRequest's curl handler
     * verifyHost and verifyPeer determine whether curl verifies ssl certificates.
     * It may be necessary to disable these checks on certain systems.
     * These only have an effect if SSL is enabled.
     *
     * @param boolean $enable New status for the mode
     * @return SimpleEmailService $this
     */
    public function setVerifyHost($enable = true) {
        $this->__verifyHost = (bool)$enable;
        return $this;
    }

    /**
     * Enable/disable CURLOPT_SSL_VERIFYPEER for SimpleEmailServiceRequest's curl handler
     * verifyHost and verifyPeer determine whether curl verifies ssl certificates.
     * It may be necessary to disable these checks on certain systems.
     * These only have an effect if SSL is enabled.
     *
     * @param boolean $enable New status for the mode
     * @return SimpleEmailService $this
     */
    public function setVerifyPeer($enable = true) {
        $this->__verifyPeer = (bool)$enable;
        return $this;
    }

    /**
     * Lists the email addresses that have been verified and can be used as the 'From' address
     *
     * @return array An array containing two items: a list of verified email addresses, and the request id.
     */
    public function listVerifiedEmailAddresses() {
        $ses_request = $this->getRequestHandler('GET');
        $ses_request->setParameter('Action', 'ListVerifiedEmailAddresses');

        $ses_response = $ses_request->getResponse();
        if($ses_response->error === false && $ses_response->code !== 200) {
            $ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
        }
        if($ses_response->error !== false) {
            $this->__triggerError('listVerifiedEmailAddresses', $ses_response->error);
            return false;
        }

        $response = array();
        if(!isset($ses_response->body)) {
            return $response;
        }

        $addresses = array();
        foreach($ses_response->body->ListVerifiedEmailAddressesResult->VerifiedEmailAddresses->member as $address) {
            $addresses[] = (string)$address;
        }

        $response['Addresses'] = $addresses;
        $response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;

        return $response;
    }

    /**
     * Requests verification of the provided email address, so it can be used
     * as the 'From' address when sending emails through SimpleEmailService.
     *
     * After submitting this request, you should receive a verification email
     * from Amazon at the specified address containing instructions to follow.
     *
     * @param string $email The email address to get verified
     * @return array The request id for this request.
     */
    public function verifyEmailAddress($email) {
        $ses_request = $this->getRequestHandler('POST');
        $ses_request->setParameter('Action', 'VerifyEmailAddress');
        $ses_request->setParameter('EmailAddress', $email);

        $ses_response = $ses_request->getResponse();
        if($ses_response->error === false && $ses_response->code !== 200) {
            $ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
        }
        if($ses_response->error !== false) {
            $this->__triggerError('verifyEmailAddress', $ses_response->error);
            return false;
        }

        $response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;
        return $response;
    }

    /**
     * Removes the specified email address from the list of verified addresses.
     *
     * @param string $email The email address to remove
     * @return array The request id for this request.
     */
    public function deleteVerifiedEmailAddress($email) {
        $ses_request = $this->getRequestHandler('DELETE');
        $ses_request->setParameter('Action', 'DeleteVerifiedEmailAddress');
        $ses_request->setParameter('EmailAddress', $email);

        $ses_response = $ses_request->getResponse();
        if($ses_response->error === false && $ses_response->code !== 200) {
            $ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
        }
        if($ses_response->error !== false) {
            $this->__triggerError('deleteVerifiedEmailAddress', $ses_response->error);
            return false;
        }

        $response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;
        return $response;
    }

    /**
     * Retrieves information on the current activity limits for this account.
     * See http://docs.amazonwebservices.com/ses/latest/APIReference/API_GetSendQuota.html
     *
     * @return array An array containing information on this account's activity limits.
     */
    public function getSendQuota() {
        $ses_request = $this->getRequestHandler('GET');
        $ses_request->setParameter('Action', 'GetSendQuota');

        $ses_response = $ses_request->getResponse();
        if($ses_response->error === false && $ses_response->code !== 200) {
            $ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
        }
        if($ses_response->error !== false) {
            $this->__triggerError('getSendQuota', $ses_response->error);
            return false;
        }

        $response = array();
        if(!isset($ses_response->body)) {
            return $response;
        }

        $response['Max24HourSend'] = (string)$ses_response->body->GetSendQuotaResult->Max24HourSend;
        $response['MaxSendRate'] = (string)$ses_response->body->GetSendQuotaResult->MaxSendRate;
        $response['SentLast24Hours'] = (string)$ses_response->body->GetSendQuotaResult->SentLast24Hours;
        $response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;

        return $response;
    }

    /**
     * Retrieves statistics for the last two weeks of activity on this account.
     * See http://docs.amazonwebservices.com/ses/latest/APIReference/API_GetSendStatistics.html
     *
     * @return array An array of activity statistics.  Each array item covers a 15-minute period.
     */
    public function getSendStatistics() {
        $ses_request = $this->getRequestHandler('GET');
        $ses_request->setParameter('Action', 'GetSendStatistics');

        $ses_response = $ses_request->getResponse();
        if($ses_response->error === false && $ses_response->code !== 200) {
            $ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
        }
        if($ses_response->error !== false) {
            $this->__triggerError('getSendStatistics', $ses_response->error);
            return false;
        }

        $response = array();
        if(!isset($ses_response->body)) {
            return $response;
        }

        $datapoints = array();
        foreach($ses_response->body->GetSendStatisticsResult->SendDataPoints->member as $datapoint) {
            $p = array();
            $p['Bounces'] = (string)$datapoint->Bounces;
            $p['Complaints'] = (string)$datapoint->Complaints;
            $p['DeliveryAttempts'] = (string)$datapoint->DeliveryAttempts;
            $p['Rejects'] = (string)$datapoint->Rejects;
            $p['Timestamp'] = (string)$datapoint->Timestamp;

            $datapoints[] = $p;
        }

        $response['SendDataPoints'] = $datapoints;
        $response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;

        return $response;
    }


    /**
     * Given a SimpleEmailServiceMessage object, submits the message to the service for sending.
     *
     * @param SimpleEmailServiceMessage $sesMessage An instance of the message class
     * @param boolean $use_raw_request If this is true or there are attachments to the email `SendRawEmail` call will be used
     * @param boolean $trigger_error Optionally overwrite the class setting for triggering an error (with type check to true/false)
     * @return array An array containing the unique identifier for this message and a separate request id.
     *         Returns false if the provided message is missing any required fields.
     *  @link(AWS SES Response formats, http://docs.aws.amazon.com/ses/latest/DeveloperGuide/query-interface-responses.html)
     */
    public function sendEmail($sesMessage, $use_raw_request = false , $trigger_error = null) {
        if(!$sesMessage->validate()) {
            $this->__triggerError('sendEmail', 'Message failed validation.');
            return false;
        }

        $ses_request = $this->getRequestHandler('POST');
        $action = !empty($sesMessage->attachments) || $use_raw_request ? 'SendRawEmail' : 'SendEmail';
        $ses_request->setParameter('Action', $action);

        // Works with both calls
        if (!is_null($sesMessage->configuration_set)) {
            $ses_request->setParameter('ConfigurationSetName', $sesMessage->configuration_set);
        }

        if($action == 'SendRawEmail') {
            // https://docs.aws.amazon.com/ses/latest/APIReference/API_SendRawEmail.html
            $ses_request->setParameter('RawMessage.Data', $sesMessage->getRawMessage());
        } else {
            $i = 1;
            foreach($sesMessage->to as $to) {
                $ses_request->setParameter('Destination.ToAddresses.member.'.$i, $sesMessage->encodeRecipients($to));
                $i++;
            }

            if(is_array($sesMessage->cc)) {
                $i = 1;
                foreach($sesMessage->cc as $cc) {
                    $ses_request->setParameter('Destination.CcAddresses.member.'.$i, $sesMessage->encodeRecipients($cc));
                    $i++;
                }
            }

            if(is_array($sesMessage->bcc)) {
                $i = 1;
                foreach($sesMessage->bcc as $bcc) {
                    $ses_request->setParameter('Destination.BccAddresses.member.'.$i, $sesMessage->encodeRecipients($bcc));
                    $i++;
                }
            }

            if(is_array($sesMessage->replyto)) {
                $i = 1;
                foreach($sesMessage->replyto as $replyto) {
                    $ses_request->setParameter('ReplyToAddresses.member.'.$i, $sesMessage->encodeRecipients($replyto));
                    $i++;
                }
            }

            $ses_request->setParameter('Source', $sesMessage->encodeRecipients($sesMessage->from));

            if($sesMessage->returnpath != null) {
                $ses_request->setParameter('ReturnPath', $sesMessage->returnpath);
            }

            if($sesMessage->subject != null && strlen($sesMessage->subject) > 0) {
                $ses_request->setParameter('Message.Subject.Data', $sesMessage->subject);
                if($sesMessage->subjectCharset != null && strlen($sesMessage->subjectCharset) > 0) {
                    $ses_request->setParameter('Message.Subject.Charset', $sesMessage->subjectCharset);
                }
            }


            if($sesMessage->messagetext != null && strlen($sesMessage->messagetext) > 0) {
                $ses_request->setParameter('Message.Body.Text.Data', $sesMessage->messagetext);
                if($sesMessage->messageTextCharset != null && strlen($sesMessage->messageTextCharset) > 0) {
                    $ses_request->setParameter('Message.Body.Text.Charset', $sesMessage->messageTextCharset);
                }
            }

            if($sesMessage->messagehtml != null && strlen($sesMessage->messagehtml) > 0) {
                $ses_request->setParameter('Message.Body.Html.Data', $sesMessage->messagehtml);
                if($sesMessage->messageHtmlCharset != null && strlen($sesMessage->messageHtmlCharset) > 0) {
                    $ses_request->setParameter('Message.Body.Html.Charset', $sesMessage->messageHtmlCharset);
                }
            }

            $i = 1;
            foreach($sesMessage->message_tags as $key => $value) {
                $ses_request->setParameter('Tags.member.'.$i.'.Name', $key);
                $ses_request->setParameter('Tags.member.'.$i.'.Value', $value);
                $i++;
            }
        }

        $ses_response = $ses_request->getResponse();
        if($ses_response->error === false && $ses_response->code !== 200) {
            $response = array(
                'code' => $ses_response->code,
                'error' => array('Error' => array('message' => 'Unexpected HTTP status')),
            );
            return $response;
        }
        if($ses_response->error !== false) {
            if (($this->__trigger_errors && ($trigger_error !== false)) || $trigger_error === true) {
                $this->__triggerError('sendEmail', $ses_response->error);
                return false;
            }
            return $ses_response;
        }

        $response = array(
            'MessageId' => (string)$ses_response->body->{"{$action}Result"}->MessageId,
            'RequestId' => (string)$ses_response->body->ResponseMetadata->RequestId,
        );
        return $response;
    }

    /**
     * Trigger an error message
     *
     * {@internal Used by member functions to output errors}
     * @param  string $functionname The name of the function that failed
     * @param array $error Array containing error information
     * @return  void
     */
    public function __triggerError($functionname, $error)
    {
        if($error == false) {
            trigger_error(sprintf("SimpleEmailService::%s(): Encountered an error, but no description given", $functionname), E_USER_WARNING);
        }
        else if(isset($error['curl']) && $error['curl'])
        {
            trigger_error(sprintf("SimpleEmailService::%s(): %s %s", $functionname, $error['code'], $error['message']), E_USER_WARNING);
        }
        else if(isset($error['Error']))
        {
            $e = $error['Error'];
            $message = sprintf("SimpleEmailService::%s(): %s - %s: %s\nRequest Id: %s\n", $functionname, $e['Type'], $e['Code'], $e['Message'], $error['RequestId']);
            trigger_error($message, E_USER_WARNING);
        }
        else {
            trigger_error(sprintf("SimpleEmailService::%s(): Encountered an error: %s", $functionname, $error), E_USER_WARNING);
        }
    }

    /**
     * Set SES Request
     *
     * @param SimpleEmailServiceRequest $ses_request description
     * @return SimpleEmailService $this
     */
    public function setRequestHandler(SimpleEmailServiceRequest $ses_request = null) {
        if (!is_null($ses_request)) {
            $ses_request->setSES($this);
        }

        $this->__ses_request = $ses_request;

        return $this;
    }

    /**
     * Get SES Request
     *
     * @param string $verb HTTP Verb: GET, POST, DELETE
     * @return SimpleEmailServiceRequest SES Request
     */
    public function getRequestHandler($verb) {
        if (empty($this->__ses_request)) {
            $this->__ses_request = new SimpleEmailServiceRequest($this, $verb);
        } else {
            $this->__ses_request->setVerb($verb);
        }

        return $this->__ses_request;
    }
}


/**
 * SimpleEmailServiceRequest PHP class
 *
 * @link https://github.com/daniel-zahariev/php-aws-ses
 * @package AmazonSimpleEmailService
 * @version v0.9.5
 */
class SimpleEmailServiceRequest {
    private $ses, $verb, $parameters = array();

    // CURL request handler that can be reused
    protected $curl_handler = null;

    // Holds the response from calling AWS's API
    protected $response;

    //
    public static $curlOptions = array();

    /**
     * Constructor
     *
     * @param SimpleEmailService $ses The SimpleEmailService object making this request
     * @param string $verb HTTP verb
     * @return void
     */
    public function __construct(SimpleEmailService $ses = null, $verb = 'GET') {
        $this->ses = $ses;
        $this->verb = $verb;
        $this->response = (object)array('body' => '', 'code' => 0, 'error' => false);
    }


    /**
     * Set SES class
     *
     * @param SimpleEmailService $ses
     * @return SimpleEmailServiceRequest $this
     */
    public function setSES(SimpleEmailService $ses) {
        $this->ses = $ses;

        return $this;
    }

    /**
     * Set HTTP method
     *
     * @param string $verb
     * @return SimpleEmailServiceRequest $this
     */
    public function setVerb($verb) {
        $this->verb = $verb;

        return $this;
    }

    /**
     * Set request parameter
     *
     * @param string $key Key
     * @param string $value Value
     * @param boolean $replace Whether to replace the key if it already exists (default true)
     * @return SimpleEmailServiceRequest $this
     */
    public function setParameter($key, $value, $replace = true) {
        if (!$replace && isset($this->parameters[$key])) {
            $temp = (array)($this->parameters[$key]);
            $temp[] = $value;
            $this->parameters[$key] = $temp;
        } else {
            $this->parameters[$key] = $value;
        }

        return $this;
    }

    /**
     * Clear the request parameters
     * @return SimpleEmailServiceRequest $this
     */
    public function clearParameters() {
        $this->parameters = array();
        return $this;
    }

    /**
     * Instantiate and setup CURL handler for sending requests.
     * Instance is cashed in `$this->curl_handler`
     *
     * @return resource $curl_handler
     */
    protected function getCurlHandler() {
        if (!empty($this->curl_handler))
            return $this->curl_handler;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, 'SimpleEmailService/php');

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, ($this->ses->verifyHost() ? 2 : 0));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ($this->ses->verifyPeer() ? 1 : 0));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        foreach (self::$curlOptions as $option => $value) {
            curl_setopt($curl, $option, $value);
        }

        $this->curl_handler = $curl;

        return $this->curl_handler;
    }

    /**
     * Get the response
     *
     * @return object | false
     */
    public function getResponse() {

        $url = 'https://' . $this->ses->getHost() . '/';
        ksort($this->parameters);
        $query = http_build_query($this->parameters, '', '&', PHP_QUERY_RFC1738);
        $headers = $this->getHeaders($query);

        $curl_handler = $this->getCurlHandler();
        curl_setopt($curl_handler, CURLOPT_CUSTOMREQUEST, $this->verb);

        // Request types
        switch ($this->verb) {
            case 'GET':
            case 'DELETE':
                $url .= '?' . $query;
                break;

            case 'POST':
                curl_setopt($curl_handler, CURLOPT_POSTFIELDS, $query);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                break;
        }
        curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl_handler, CURLOPT_URL, $url);


        // Execute, grab errors
        if (curl_exec($curl_handler)) {
            $this->response->code = curl_getinfo($curl_handler, CURLINFO_HTTP_CODE);
        } else {
            $this->response->error = array(
                'curl' => true,
                'code' => curl_errno($curl_handler),
                'message' => curl_error($curl_handler),
            );
        }

        // cleanup for reusing the current instance for multiple requests
        curl_setopt($curl_handler, CURLOPT_POSTFIELDS, '');
        $this->parameters = array();

        // Parse body into XML
        if ($this->response->error === false && !empty($this->response->body)) {
            $this->response->body = simplexml_load_string($this->response->body);

            // Grab SES errors
            if (!in_array($this->response->code, array(200, 201, 202, 204))
                && isset($this->response->body->Error)) {
                $error = $this->response->body->Error;
                $output = array();
                $output['curl'] = false;
                $output['Error'] = array();
                $output['Error']['Type'] = (string)$error->Type;
                $output['Error']['Code'] = (string)$error->Code;
                $output['Error']['Message'] = (string)$error->Message;
                $output['RequestId'] = (string)$this->response->body->RequestId;

                $this->response->error = $output;
                unset($this->response->body);
            }
        }

        $response = $this->response;
        $this->response = (object)array('body' => '', 'code' => 0, 'error' => false);

        return $response;
    }

    /**
     * Get request headers
     * @param string $query
     * @return array
     */
    protected function getHeaders($query) {
        $headers = array();

        if ($this->ses->getRequestSignatureVersion() == SimpleEmailService::REQUEST_SIGNATURE_V4) {
            $date = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
            $headers[] = 'X-Amz-Date: ' . $date;
            $headers[] = 'Host: ' . $this->ses->getHost();
            $headers[] = 'Authorization: ' . $this->__getAuthHeaderV4($date, $query);

        } else {
            // must be in format 'Sun, 06 Nov 1994 08:49:37 GMT'
            $date = gmdate('D, d M Y H:i:s e');
            $auth = 'AWS3-HTTPS AWSAccessKeyId=' . $this->ses->getAccessKey();
            $auth .= ',Algorithm=HmacSHA256,Signature=' . $this->__getSignature($date);

            $headers[] = 'Date: ' . $date;
            $headers[] = 'Host: ' . $this->ses->getHost();
            $headers[] = 'X-Amzn-Authorization: ' . $auth;
        }

        return $headers;
    }

    /**
     * Destroy any leftover handlers
     */
    public function __destruct() {
        if (!empty($this->curl_handler))
            @curl_close($this->curl_handler);
    }

    /**
     * CURL write callback
     *
     * @param resource $curl CURL resource
     * @param string $data Data
     * @return integer
     */
    private function __responseWriteCallback($curl, $data) {
        if (!isset($this->response->body)) {
            $this->response->body = $data;
        } else {
            $this->response->body .= $data;
        }

        return strlen($data);
    }

    /**
     * Generate the auth string using Hmac-SHA256
     *
     * @param string $string String to sign
     * @return string
     * @internal Used by SimpleEmailServiceRequest::getResponse()
     */
    private function __getSignature($string) {
        return base64_encode(hash_hmac('sha256', $string, $this->ses->getSecretKey(), true));
    }

    /**
     * @param string $key
     * @param string $dateStamp
     * @param string $regionName
     * @param string $serviceName
     * @param string $algo
     * @return string
     */
    private function __getSigningKey($key, $dateStamp, $regionName, $serviceName, $algo) {
        $kDate = hash_hmac($algo, $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac($algo, $regionName, $kDate, true);
        $kService = hash_hmac($algo, $serviceName, $kRegion, true);

        return hash_hmac($algo, 'aws4_request', $kService, true);
    }

    /**
     * Implementation of AWS Signature Version 4
     * @see https://docs.aws.amazon.com/general/latest/gr/sigv4_signing.html
     * @param string $amz_datetime
     * @param string $query
     * @return string
     */
    private function __getAuthHeaderV4($amz_datetime, $query) {
        $amz_date = substr($amz_datetime, 0, 8);
        $algo = 'sha256';
        $aws_algo = 'AWS4-HMAC-' . strtoupper($algo);

        $host_parts = explode('.', $this->ses->getHost());
        $service = $host_parts[0];
        $region = $host_parts[1];

        $canonical_uri = '/';
        if ($this->verb === 'POST') {
            $canonical_querystring = '';
            $payload_data = $query;
        } else {
            $canonical_querystring = $query;
            $payload_data = '';
        }

        // ************* TASK 1: CREATE A CANONICAL REQUEST *************
        $canonical_headers_list = [
            'host:' . $this->ses->getHost(),
            'x-amz-date:' . $amz_datetime
        ];

        $canonical_headers = implode("\n", $canonical_headers_list) . "\n";
        $signed_headers = 'host;x-amz-date';
        $payload_hash = hash($algo, $payload_data, false);

        $canonical_request = implode("\n", array(
            $this->verb,
            $canonical_uri,
            $canonical_querystring,
            $canonical_headers,
            $signed_headers,
            $payload_hash
        ));

        // ************* TASK 2: CREATE THE STRING TO SIGN*************
        $credential_scope = $amz_date . '/' . $region . '/' . $service . '/' . 'aws4_request';
        $string_to_sign = implode("\n", array(
            $aws_algo,
            $amz_datetime,
            $credential_scope,
            hash($algo, $canonical_request, false)
        ));

        // ************* TASK 3: CALCULATE THE SIGNATURE *************
        // Create the signing key using the function defined above.
        $signing_key = $this->__getSigningKey($this->ses->getSecretKey(), $amz_date, $region, $service, $algo);

        // Sign the string_to_sign using the signing_key
        $signature = hash_hmac($algo, $string_to_sign, $signing_key, false);

        // ************* TASK 4: ADD SIGNING INFORMATION TO THE REQUEST *************
        return $aws_algo . ' ' . implode(', ', array(
                'Credential=' . $this->ses->getAccessKey() . '/' . $credential_scope,
                'SignedHeaders=' . $signed_headers,
                'Signature=' . $signature
            ));
    }
}
