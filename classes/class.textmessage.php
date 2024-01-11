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

require 'Twilio/autoload.php';
use Twilio\Rest\Client;

class TextMessage {

	private static $iErrorMessage = "";

	public static function getErrorMessage() {
		return self::$iErrorMessage;
	}

	public static function canSendTextMessage($contactId) {
	    if ($GLOBALS['gPHPVersion'] < 70200) {
	        return false;
        }
		$administratorFlag = getFieldFromId("administrator_flag","users","contact_id",$contactId);
		$superuserFlag = getFieldFromId("superuser_flag","users","contact_id",$contactId,"client_id is not null");
		if (empty($administratorFlag) && empty($superuserFlag) && empty(CustomField::getCustomFieldData($contactId,"RECEIVE_SMS"))) {
			self::$iErrorMessage = "Contact not set to receive SMS";
			return false;
		}

		$accountSid = getPreference("TWILIO_ACCOUNT_SID");
		$authToken = getPreference("TWILIO_AUTH_TOKEN");
		$fromPhone = getPreference("TWILIO_FROM_NUMBER");
		if (!empty($fromPhone)) {
			$fromPhone = formatPhoneNumber($fromPhone);
			$fromPhone = "+1" . str_replace("(", "", str_replace(")", "", str_replace("-", "", str_replace(" ", "", $fromPhone))));
		}
		if (strlen($fromPhone) != 12 || empty($accountSid) || empty($authToken)) {
			self::$iErrorMessage = "Twilio Account information not properly set up";
			return false;
		}
		$phoneNumber = getFieldFromId("phone_number","phone_numbers","contact_id",$contactId,"description = 'cell'");
		if (empty($phoneNumber)) {
			$phoneNumber = getFieldFromId("phone_number","phone_numbers","contact_id",$contactId,"description = 'mobile'");
		}
		if (empty($phoneNumber)) {
			$phoneNumber = getFieldFromId("phone_number","phone_numbers","contact_id",$contactId,"description = 'text'");
		}
		if (empty($phoneNumber)) {
			self::$iErrorMessage = "Contact has no text phone number";
			return false;
		}
		return true;
	}

	public static function sendMessageCode($contactId,$textMessageCode,$substitutions = array()) {
		if (is_array($GLOBALS['gClientRow'])) {
			$substitutions = array_merge($GLOBALS['gClientRow'], $substitutions);
		}
		if (is_array($GLOBALS['gUserRow'])) {
			$substitutions = array_merge($GLOBALS['gUserRow'], $substitutions);
		}
		$textMessageContent = getFieldFromId("content","text_messages","text_message_code",$textMessageCode,"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		if (empty($textMessageContent) && $textMessageCode == "TWO_FACTOR_AUTHENTICATION") {
			$textMessageContent = "Your verification code is %random_code% requested from %ip_address% for client %client_code%. If you did not request this, you might want to log in and change your password ASAP.";
		}
		return self::sendMessage($contactId, $textMessageContent, $substitutions);
	}

	public static function sendMessage($contactId,$textMessageContent,$substitutions = array()) {
        if ($GLOBALS['gPHPVersion'] < 70200) {
            return false;
        }
		if (empty($textMessageContent)) {
			self::$iErrorMessage = "No text content to send";
			return false;
		}
		$administratorFlag = getFieldFromId("administrator_flag","users","contact_id",$contactId);
		$superuserFlag = getFieldFromId("superuser_flag","users","contact_id",$contactId,"client_id is not null");
		if (empty($administratorFlag) && empty($superuserFlag) && empty(CustomField::getCustomFieldData($contactId,"RECEIVE_SMS"))) {
			self::$iErrorMessage = "Contact not set to receive SMS";
			return false;
		}

		$accountSid = getPreference("TWILIO_ACCOUNT_SID");
		$authToken = getPreference("TWILIO_AUTH_TOKEN");
		$fromPhone = getPreference("TWILIO_FROM_NUMBER");
		if (!empty($fromPhone)) {
			$fromPhone = formatPhoneNumber($fromPhone);
			$fromPhone = "+1" . str_replace("(", "", str_replace(")", "", str_replace("-", "", str_replace(" ", "", $fromPhone))));
		}
		if (strlen($fromPhone) != 12 || empty($accountSid) || empty($authToken)) {
			if ($superuserFlag) {
				$accountSid = "AC617b5242bca2bf130f2c0a43c410170e";
				$authToken = "af389668c68f8fc3c7953dd1d16e4c75";
				$fromPhone = "+18507790944";
			} else {
				self::$iErrorMessage = "Twilio Account information not properly set up";
				return false;
			}
		}
		$phoneNumber = getFieldFromId("phone_number","phone_numbers","contact_id",$contactId,"description = 'cell'");
		if (empty($phoneNumber)) {
			$phoneNumber = getFieldFromId("phone_number","phone_numbers","contact_id",$contactId,"description = 'mobile'");
		}
		if (empty($phoneNumber)) {
			$phoneNumber = getFieldFromId("phone_number","phone_numbers","contact_id",$contactId,"description = 'text'");
		}
		if (empty($phoneNumber)) {
			self::$iErrorMessage = "Contact has no text phone number";
			return false;
		}

		if (!empty($substitutions) && is_array($substitutions)) {
            $textMessageContent = PlaceHolders::massageContent($textMessageContent,$substitutions);
			$newContent = "";
			$contentLines = getContentLines($textMessageContent);
			$useLine = true;
			foreach ($contentLines as $thisLine) {
				if ($thisLine == "%endif%") {
					$useLine = true;
					continue;
				}
				if (substr($thisLine, 0, strlen("%if_has_value:")) == "%if_has_value:") {
					$substitutionFieldName = substr($thisLine, strlen("%if_has_value:"));
					if (substr($substitutionFieldName, -1) == "%") {
						$substitutionFieldName = substr($substitutionFieldName, 0, -1);
					}
					$useLine = !empty($substitutions[$substitutionFieldName]);
					continue;
				}
				if ($useLine) {
					$newContent .= $thisLine . "\n";
				}
			}
			$textMessageContent = $newContent;
		}
		$clientSubstitutions = array();
		$clientSubstitutions['current_year'] = date("Y");
		$clientSubstitutions['client_name'] = $GLOBALS['gClientRow']['business_name'];
		$clientSubstitutions['domain_name'] = getDomainName();
		$clientSubstitutions['client_address_1'] = $GLOBALS['gClientRow']['address_1'];
		$clientSubstitutions['client_address_2'] = $GLOBALS['gClientRow']['address_2'];
		$clientSubstitutions['client_city'] = $GLOBALS['gClientRow']['city'];
		$clientSubstitutions['client_state'] = $GLOBALS['gClientRow']['state'];
		$clientSubstitutions['client_postal_code'] = $GLOBALS['gClientRow']['postal_code'];
		$clientSubstitutions['client_country'] = getFieldFromId("country_name", "countries", "country_id", $GLOBALS['gClientRow']['country_id']);
		$clientSubstitutions['client_email_address'] = $GLOBALS['gClientRow']['email_address'];
		$clientSubstitutions['client_phone_number'] = Contact::getContactPhoneNumber($GLOBALS['gClientRow']['contact_id']);
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $GLOBALS['gClientRow']['contact_id']);
		$count = 0;
		while ($row = getNextRow($resultSet)) {
			$count++;
			$clientSubstitutions['client_phone_number_' . $count] = $row['phone_number'];
			$clientSubstitutions['client_phone_description_' . $count] = $row['description'];
			if (!empty($row['description'])) {
				$clientSubstitutions['client_' . makeCode($row['description'], array("lowercase" => true)) . '_phone_number'] = $row['phone_number'];
			}
		}
		$addressBlock = $GLOBALS['gClientRow']['address_1'];
		if (!empty($GLOBALS['gClientRow']['address_2'])) {
			$addressBlock .= (empty($addressBlock) ? "" : "\n") . $GLOBALS['gClientRow']['address_2'];
		}
		$cityLine = $GLOBALS['gClientRow']['city'];
		if (!empty($GLOBALS['gClientRow']['state'])) {
			$cityLine .= (empty($cityLine) ? "" : ", ") . $GLOBALS['gClientRow']['state'];
		}
		if (!empty($GLOBALS['gClientRow']['postal_code'])) {
			$cityLine .= (empty($cityLine) ? "" : " ") . $GLOBALS['gClientRow']['postal_code'];
		}
		if (!empty($cityLine)) {
			$addressBlock .= (empty($addressBlock) ? "" : "\n") . $cityLine;
		}
		if ($GLOBALS['gClientRow']['country_id'] != 1000) {
			$addressBlock .= (empty($addressBlock) ? "" : "\n") . getFieldFromId("country_name", "countries", "country_id", $GLOBALS['gClientRow']['country_id']);
		}
		$clientSubstitutions['client_address_block'] = $addressBlock;
        $textMessageContent = PlaceHolders::massageContent($textMessageContent,$clientSubstitutions);

		try {
			$client = new Client($accountSid, $authToken);
			$client->messages->create($phoneNumber, array('from' => $fromPhone, 'body' => $textMessageContent));
		} catch (Exception $e) {
			$GLOBALS['gPrimaryDatabase']->logError($e->getMessage());
			self::$iErrorMessage = $e->getMessage();
			return false;
		}
		return true;
	}
}
