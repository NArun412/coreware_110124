<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

abstract class eCommerce {
	var $iResponse = array();
	protected $iErrorMessage = "";
	protected $iCleanFields = array();
	protected $iMerchantAccountRow = array();
    protected static $iAlwaysAlertErrors = array("decline threshold exceeded", "the account is inactive");

	protected $iRequiredParameters = array(
		"authorize_card" => array("amount", "card_number", "expiration_date", "card_code", "order_number", "description", "first_name", "last_name", "address_1", "city", "country_id", "contact_id"),
		"authorize_check" => array("amount", "bank_routing_number", "bank_account_number", "bank_account_type", "order_number", "description", "first_name", "last_name", "address_1", "city", "country_id", "contact_id"),
		"capture_charge" => array("transaction_identifier", "authorization_code"),
		"void_card" => array("transaction_identifier"),
		"void_check" => array("transaction_identifier"),
		"refund_card" => array("transaction_identifier", "amount"),
		"refund_check" => array("transaction_identifier", "amount", "account_token"),
		"get_customer_profile" => array("merchant_identifier"),
		"create_customer_profile" => array("contact_id"),
		"get_customer_payment_profile" => array("merchant_identifier", "account_token"),
		"create_customer_payment_profile_card" => array("merchant_identifier", "address_1", "postal_code", "card_number", "expiration_date", "card_code"),
		"create_customer_payment_profile_check" => array("merchant_identifier", "address_1", "postal_code", "bank_routing_number", "bank_account_number", "bank_account_type"),
		"delete_customer_profile" => array("merchant_identifier"),
		"delete_customer_payment_profile" => array("merchant_identifier", "account_token"),
		"update_customer_payment_profile" => array("merchant_identifier", "account_token"),
		"create_customer_payment_transaction_request" => array("amount", "order_number", "merchant_identifier", "account_token"));

	public static function getPaymentMethodIcon($paymentMethodCode, $paymentMethodTypeCode = "") {
		$paymentMethodIcons = array("VISA" => "fab fa-cc-visa",
			"MASTERCARD" => "fab fa-cc-mastercard",
			"CHARGE_ACCOUNT" => "fad fa-charging-station",
			"DISCOVER" => "fab fa-cc-discover",
			"GIFT_CARD" => "fad fa-gift-card",
			"STORE_CREDIT" => "fad fa-store",
			"CREDOVA" => "fad fa-hand-holding-usd",
			"eCHECK" => "fad fa-money-check",
			"AMEX" => "fab fa-cc-amex",
			"LAYAWAY" => "fad fa-stopwatch",
			"CASH" => "fad fa-money-bill",
			"INVOICE" => "fad fa-file-invoice");
		$iconClasses = false;
		if (array_key_exists(strtoupper($paymentMethodCode), $paymentMethodIcons)) {
			$iconClasses = $paymentMethodIcons[strtoupper($paymentMethodCode)];
		}
		if (empty($iconClasses)) {
			if (empty($paymentMethodTypeCode)) {
				$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_code", $paymentMethodCode));
			}
			$iconClasses = self::getPaymentMethodTypeIcon($paymentMethodTypeCode);
		}
		if (empty($iconClasses)) {
			$iconClasses = "fad fa-money-bill-wave";
		}
		return $iconClasses;
	}

	public static function getPaymentMethodTypeIcon($paymentMethodTypeCode) {
		$paymentMethodIcons = array("CREDIT_CARD" => "fad fa-credit-card",
			"CHARGE_ACCOUNT" => "fad fa-charging-station",
			"BANK_ACCOUNT" => "fad fa-piggy-bank",
			"GIFT_CARD" => "fad fa-gift-card");
		if (array_key_exists(strtoupper($paymentMethodTypeCode), $paymentMethodIcons)) {
			return $paymentMethodIcons[strtoupper($paymentMethodTypeCode)];
		} else {
			return false;
		}
	}

	public static function doWriteLog($accountInformation, $responseMessage, $failure) {
		executeQuery("insert into ecommerce_log (client_id,link_url,ip_address,hash_code,failure,error_message) values (?,?,?,?,?,?)", $GLOBALS['gClientId'],
			(empty($_SERVER['HTTP_HOST']) ? "CLI" : $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']), (empty($_SERVER['REMOTE_ADDR']) ? "unknown" : $_SERVER['REMOTE_ADDR']),
			md5($_SERVER['REMOTE_ADDR'] . $accountInformation), ($failure ? 1 : 0), $responseMessage . "\n\n" . jsonEncode($_SESSION) . "\n\n" . jsonEncode($_SERVER));
		if ($failure) {
            # send alert for urgent errors
            if(in_array(strtolower(trim($responseMessage)), self::$iAlwaysAlertErrors)) {
                // todo: use redis for this when available
                $alertSent = file_get_contents("{$GLOBALS['gDocumentRoot']}/cache/" . makeCode("ecommerce alert {$GLOBALS['gClientId']} $responseMessage") . ".txt");
                if(strtotime($alertSent) < strtotime("-1 hour")) {
                    self::sendEcommerceAlert("{$GLOBALS['gClientRow']['client_code']}: eCommerce returned urgent error '$responseMessage'");
                    file_put_contents("{$GLOBALS['gDocumentRoot']}/cache/" . makeCode("ecommerce alert {$GLOBALS['gClientId']} $responseMessage") . ".txt", date("Y-m-d H:i:s"));
                }
            }
            # send alert for excessive failures
            // todo: use redis for this when available
            $excessiveFailureAlertSent = file_get_contents("{$GLOBALS['gDocumentRoot']}/cache/" . makeCode("ecommerce alert {$GLOBALS['gClientId']} excessive ecommerce failures") . ".txt");
            if(strtotime($excessiveFailureAlertSent) < strtotime("-1 hour")) {
                $resultSet = executeQuery("select count(*) from ecommerce_log where client_id = ? and log_time > (now() - interval 60 minute) and failure = 1", $GLOBALS['gClientId']);
                if ($row = getNextRow($resultSet)) {
                    if ($row['count(*)'] >= 50) {
                        self::sendEcommerceAlert("{$GLOBALS['gClientRow']['client_code']}:  There have been " . $row['count(*)'] . " eCommerce failures in the last hour.");
                        file_put_contents("{$GLOBALS['gDocumentRoot']}/cache/" . makeCode("ecommerce alert {$GLOBALS['gClientId']} excessive ecommerce failures") . ".txt", date("Y-m-d H:i:s"));
                    }
                }
            }

            if(!$GLOBALS['gCommandLine'] && !$GLOBALS['gUserRow']['administrator_flag']) {
                sendEmail(array("subject" => "eCommerce Failure", "body" => "An eCommerce transaction failed: " . $responseMessage, "notification_code" => "ECOMMERCE_FAILURE"));

				# 5 or more failures in 2 minutes will turn on captcha
				$resultSet = executeQuery("select count(distinct hash_code) from ecommerce_log where client_id = ? and log_time > (now() - interval 2 minute) and failure = 1", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(distinct hash_code)'] >= 5) {
						setClientPreference("USE_ORDER_CAPTCHA", "true");
						setClientPreference("USE_DONATION_CAPTCHA", "true");
					}
				}

				# 3 or more unique card failures in 5 minutes by same IP address will block it.
				$resultSet = executeQuery("select count(distinct hash_code) from ecommerce_log where client_id = ? and ip_address = ? and log_time > (now() - interval 5 minute) and failure = 1", $GLOBALS['gClientId'], $_SERVER['REMOTE_ADDR']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(distinct hash_code)'] >= 3) {
						blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Repeated Failures at eCommerce: more than 2 distinct credit cards in 5 minutes");
					}
				}
				# 5 or more unique card failures in 10 minutes by same IP address will block it.
				$resultSet = executeQuery("select count(distinct hash_code) from ecommerce_log where client_id = ? and ip_address = ? and log_time > (now() - interval 10 minute) and failure = 1", $GLOBALS['gClientId'], $_SERVER['REMOTE_ADDR']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(distinct hash_code)'] >= 6) {
						blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Repeated Failures at eCommerce: more than 5 distinct credit cards in 10 minutes");
					}
				}
				# 10 total failures in 10 minutes by same IP address will block it.
				$resultSet = executeQuery("select count(*) from ecommerce_log where client_id = ? and ip_address = ? and log_time > (now() - interval 10 minute) and failure = 1", $GLOBALS['gClientId'], $_SERVER['REMOTE_ADDR']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] >= 10) {
						blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Repeated Failures at eCommerce: more than 10 failures in 10 minutes");
					}
				}
			}
		}
	}

    public static function sendEcommerceAlert($message) {
        $ecommerceAlertEmails = getCachedData("ecommerce_alert_notification", "email_addresses");
        if (empty($ecommerceAlertEmails)) {
            $notificationEmailResult = executeQuery("select * from notification_emails where notification_id in (select notification_id from notifications where notification_code = 'ECOMMERCE_ALERT' and (client_id = ? or client_id = ?))",
                $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
            $ecommerceAlertEmails = array();
            while ($notificationEmailRow = getNextRow($notificationEmailResult)) {
                $ecommerceAlertEmails[] = $notificationEmailRow['email_address'];
            }
        }
        if (!empty($ecommerceAlertEmails)) {
            setCachedData("ecommerce_alert_notification", "email_addresses", $ecommerceAlertEmails);
            sendEmail(array("subject" => "eCommerce alert from {$GLOBALS['gClientRow']['client_code']}", "body" => $message, "email_addresses" => $ecommerceAlertEmails));
        }
    }

	/**
	 * @param string $merchantAccountId
	 * @return eCommerce|bool
	 */
	public static function getEcommerceInstance($merchantAccountId = "") {
		if (false && IpQualityScore::shouldNotAllowEcommerce()) {
			return false;
		}
		if (isWebCrawler()) {
			addSecurityLog(getFieldFromId("user_name", "users", "user_id", $GLOBALS['gUserId']), "WEBCRAWLER-ECOMMERCE", jsonEncode($_SERVER));
			return false;
		}
		if (empty($merchantAccountId)) {
			$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		}
		$merchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_id", $merchantAccountId, ($GLOBALS['gDevelopmentServer'] ? "client_id is not null" : ""));

		if (empty($merchantAccountRow)) {
			return false;
		}
		$eCommerceClass = getFieldFromId("class_name", "merchant_services", "merchant_service_id", $merchantAccountRow['merchant_service_id']);
		if (empty($eCommerceClass)) {
			return false;
		} else {
			return new $eCommerceClass($merchantAccountRow);
		}
	}

	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	function getResponse() {
		return $this->iResponse;
	}

	function canDoPreAuthOnly() {
		return true;
	}

	function cleanLogContent($postFields, $removeValues) {
		if (is_object($postFields)) {
			$postFields = get_object_vars($postFields);
		}
		if (is_array($postFields) && is_array($removeValues)) {
			foreach ($postFields as $index => $value) {
				if (array_key_exists($index, $removeValues)) {
					$postFields[$index] = $removeValues[$index];
				}
			}
			$removeValues = "";
		}
		if (is_array($postFields)) {
			$postFields = $this->stringifyArray($postFields);
		}
		if (is_array($removeValues)) {
			foreach ($removeValues as $thisValue) {
				if (is_array($thisValue)) {
					$thisValue = $this->stringifyArray($thisValue);
				}
				$postFields = str_replace($thisValue, "", $postFields);
			}
		}
		return $postFields;
	}

	function stringifyArray($array) {
		$newValue = "";
		foreach ($array as $index => $fieldValue) {
			if (is_object($fieldValue)) {
				$fieldValue = (array)$fieldValue;
			}
			$newValue .= (empty($newValue) ? "" : "&") . $index . "=>" . (is_array($fieldValue) ? "array(" . $this->stringifyArray($fieldValue) . ")" : $fieldValue);
		}
		return $newValue;
	}

	function writeLog($accountInformation, $responseMessage, $failure) {
		$GLOBALS['gPrimaryDatabase']->logLastEcommerceError($accountInformation, $responseMessage, $failure);
		self::doWriteLog($accountInformation, $responseMessage, $failure);
	}

	function writeExternalLog($logContent) {
		// double check that card numbers are not being logged
		$logContent = preg_replace("/\b(?:4[0-9]{12}(?:[0-9]{3})?|(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12}|(?:2131|1800|35\d{3})\d{11})\b/",
			"", $logContent);
		if (!$GLOBALS['gDevelopmentServer']) {
			error_log("\n" . $GLOBALS['gClientRow']['client_code'] . " : " . date("m/d/Y H:i:s") . " : " . $logContent, 3, "/var/log/merchant.log");
		}
		executeQuery("insert into merchant_log (client_id,content) values (?,?)", $GLOBALS['gClientId'], (is_array($logContent) ? json_encode($logContent) : $logContent));
	}

	function getMerchantAccountId() {
		return $this->iMerchantAccountRow['merchant_account_id'];
	}

	function hasCustomerDatabase() {
		return (empty($this->iMerchantAccountRow['no_customer_database']));
	}

	function requiresCustomerToken() {
		return true;
	}

	function refundCharge($parameters) {
		$this->iErrorMessage = "This merchant gateway has not yet been enabled to process refunds";
		return false;
	}

	abstract function testConnection();

	abstract function voidCharge($parameters);

	abstract function authorizeCharge($parameters);

	abstract function captureCharge($parameters);

	abstract function getCustomerProfile($parameters);

	abstract function createCustomerProfile($parameters);

	abstract function getCustomerPaymentProfile($parameters);

	abstract function createCustomerPaymentProfile($parameters);

	abstract function deleteCustomerProfile($parameters);

	abstract function deleteCustomerPaymentProfile($parameters);

	abstract function createCustomerProfileTransactionRequest($parameters);

	abstract function updateCustomerPaymentProfile($parameters);

	protected function deleteCustomerAccount($accountId) {
		executeQuery("update accounts set account_token = null,merchant_identifier = null,merchant_account_id = null where account_id = ?", $accountId);
	}

	protected function createMerchantProfile($contactId, $merchantIdentifier) {
		if (!empty($contactId) && !empty($merchantIdentifier)) {
			$merchantProfileId = getFieldFromId("merchant_profile_id", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $this->iMerchantAccountRow['merchant_account_id']);
			if (empty($merchantProfileId)) {
				executeQuery("insert into merchant_profiles (contact_id,merchant_account_id,merchant_identifier) values (?,?,?)",
					$contactId, $this->iMerchantAccountRow['merchant_account_id'], $merchantIdentifier);
			} else {
				executeQuery("update merchant_profiles set merchant_identifier = ? where contact_id = ? and merchant_account_id = ?",
					$merchantIdentifier, $contactId, $this->iMerchantAccountRow['merchant_account_id']);
			}
		}
	}

	protected function setAccountToken($accountId, $merchantIdentifier, $accountToken) {
		if (!empty($accountId) && !empty($accountToken) && !empty($merchantIdentifier)) {
			executeQuery("update accounts set merchant_account_id = ?,merchant_identifier = ?, account_token = ?, inactive = ? where account_id = ?",
				$this->iMerchantAccountRow['merchant_account_id'], $merchantIdentifier, $accountToken, (empty($accountToken) ? 1 : 0), $accountId);
		}
	}

	public static function getClientMerchantAccountIds() {
		if ($GLOBALS['gDevelopmentServer']) {
			$GLOBALS['gDefaultMerchantAccountId'] = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DEVELOPMENT");
			if (empty($GLOBALS['gDefaultMerchantAccountId'])) {
				$GLOBALS['gDefaultMerchantAccountId'] = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DEVELOPMENT", "client_id = 1");
			}
		} else {
			$GLOBALS['gDefaultMerchantAccountId'] = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DEFAULT");
		}
		$GLOBALS['gMerchantAccountId'] = $GLOBALS['gDefaultMerchantAccountId'];
	}

	public static function getAccountMerchantAccount($accountId) {
		$merchantAccountId = $GLOBALS['gDefaultMerchantAccountId'];
		$thisMerchantAccountId = getFieldFromId("merchant_account_id", "accounts", "account_id", $accountId);
		if (!empty($thisMerchantAccountId)) {
			$merchantAccountId = $thisMerchantAccountId;
		}
		return $merchantAccountId;
	}

}
