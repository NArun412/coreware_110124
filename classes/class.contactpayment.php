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

class ContactPayment {
	private $iErrorMessage = "";
	private $iContactId = "";
	private $iECommerceObject = null;
	private $iAccountId = "";

	function __construct($contactId,$eCommerce) {
		$contactId = getFieldFromId("contact_id","contacts","contact_id",$contactId);
		if (empty($contactId)) {
			throw new Exception('Unable to create Object: Contact does not exist');
		}
		if (empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
			throw new Exception('Unable to create Object: No customer database for this merchant account');
		}
		$this->iContactId = $contactId;
		$this->iECommerceObject = $eCommerce;
	}

	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	# return true or false depending on if the contact has a merchant profile
	function hasMerchantProfile() {
		$merchantIdentifier = getFieldFromId("merchant_identifier","merchant_profiles","contact_id",$this->iContactId,"merchant_account_id = ?",$this->iECommerceObject->getMerchantAccountId());
		return (empty($merchantIdentifier) ? false : $merchantIdentifier);
	}

	# create the merchant profile for the contact
	function createMerchantProfile() {
		$merchantIdentifier = getFieldFromId("merchant_identifier","merchant_profiles","contact_id",$this->iContactId,"merchant_account_id = ?",$this->iECommerceObject->getMerchantAccountId());
		if (empty($merchantIdentifier)) {
			if (!$this->iECommerceObject) {
				$this->iErrorMessage = "No eCommerce object";
				return false;
			}
			$row = Contact::getContact($this->iContactId);
			$success = $this->iECommerceObject->createCustomerProfile(array("contact_id"=>$row['contact_id'],"first_name"=>$row['first_name'],
				"last_name"=>$row['last_name'],"business_name"=>$row['business_name'],"address_1"=>$row['address_1'],"city"=>$row['city'],
				"state"=>$row['state'],"postal_code"=>$row['postal_code'],"email_address"=>$row['email_address']));
			$response = $this->iECommerceObject->getResponse();
			if ($success) {
				$merchantIdentifier = $response['merchant_identifier'];
			} else {
				$this->iErrorMessage = "Unable to create account: " . jsonEncode($response);
				return false;
			}
		}
		return $merchantIdentifier;
	}

	# return array of active accounts
	function getActiveAccounts() {
		$accountArray = array();
		$merchantIdentifier = $this->hasMerchantProfile();
		$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null and merchant_account_id = ?" . (!$merchantIdentifier ? " and merchant_identifier is not null" : ""),
			$this->iContactId,$this->iECommerceObject->getMerchantAccountId());
		while ($row = getNextRow($resultSet)) {
			$accountArray[] = $row;
		}
		return $accountArray;
	}

	# set the Account that this class will use
	function setAccount($accountId) {
		$merchantIdentifier = $this->hasMerchantProfile();
		$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null and merchant_account_id = ? and account_id = ?" . (!$merchantIdentifier ? " and merchant_identifier is not null" : ""),
			$this->iContactId,$this->iECommerceObject->getMerchantAccountId(),$accountId);
		if ($row = getNextRow($resultSet)) {
			$this->iAccountId = $row['account_id'];
		}
		if (empty($this->iAccountId)) {
			$this->iErrorMessage = "Invalid Account";
			return false;
		} else {
			return true;
		}
	}

	# create a payment method in the eCommerce merchant account and in Coreware
	function addPaymentMethod($parameters) {
		$paymentMethodId = getFieldFromId("payment_method_id","payment_methods","payment_method_id",$parameters['payment_method_id']);
		if (empty($paymentMethodId)) {
			$this->iErrorMessage = "Invalid Payment Method";
			return false;
		}
		$paymentMethodTypeCode = getFieldFromId("payment_method_type_code","payment_method_types","payment_method_type_id",getFieldFromId("payment_method_type_id","payment_methods","payment_method_id",$paymentMethodId));
		$isBankAccount = ($paymentMethodTypeCode == "BANK_ACCOUNT");
		if (!$this->iECommerceObject) {
			$this->iErrorMessage = "No eCommerce Object";
			return false;
		}
		if (!empty($parameters['merchant_identifier'])) {
			$merchantIdentifier = $parameters['merchant_identifier'];
		} else {
			$merchantIdentifier = $this->hasMerchantProfile();
		}
		if (empty($merchantIdentifier)) {
			$this->iErrorMessage = "Invalid Merchant Profile";
			return false;
		}
		$accountLabel = $parameters['account_label'];
		if (empty($accountLabel)) {
			$accountLabel = getFieldFromId("description","payment_methods","payment_method_id",$paymentMethodId) . " - " . substr($parameters[($isBankAccount ? "bank_" : "") . "account_number"],-4);
		}
		$fullName = $parameters['first_name'] . " " . $parameters['last_name'] . (empty($parameters['business_name']) ? "" : ", " . $parameters['business_name']);
		$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
			"account_number,expiration_date,merchant_account_id,merchant_identifier) values (?,?,?,?,?, ?,?,?)",$this->iContactId,$accountLabel,$paymentMethodId,
			$fullName,"XXXX-" . substr($parameters[($isBankAccount ? "bank_" : "") . "account_number"],-4),
			(empty($parameters['expiration_year']) ? "" : date("Y-m-d",strtotime($parameters['expiration_month'] . "/01/" . $parameters['expiration_year']))),$this->iECommerceObject->getMerchantAccountId(),$merchantIdentifier);
		if (!empty($resultSet['sql_error'])) {
			$this->iErrorMessage = getSystemMessage("basic",$resultSet['sql_error']);
			return false;
		}
		$this->iAccountId = $resultSet['insert_id'];
		$paymentArray = array("contact_id"=>$this->iContactId,"account_id"=>$this->iAccountId,"merchant_identifier"=>$merchantIdentifier,
			"first_name"=>$parameters['first_name'],"last_name"=>$parameters['last_name'],"business_name"=>$parameters['business_name'],
			"address_1"=>$parameters['address_1'],"city"=>$parameters['city'],"state"=>$parameters['state'],
			"postal_code"=>$parameters['postal_code'],"country_id"=>$parameters['country_id']);
		if ($isBankAccount) {
			$paymentArray['bank_routing_number'] = $parameters['routing_number'];
			$paymentArray['bank_account_number'] = $parameters['bank_account_number'];
			$paymentArray['bank_account_type'] = str_replace(" ","",lcfirst(ucwords(strtolower(str_replace("_"," ",getFieldFromId("payment_method_code","payment_methods","payment_method_id",$parameters['payment_method_id']))))));
		} else {
			$paymentArray['card_number'] = $parameters['account_number'];
			$paymentArray['expiration_date'] = $parameters['expiration_month'] . "/01/" . $parameters['expiration_year'];
			$paymentArray['card_code'] = $parameters['cvv_code'];
		}
		$success = $this->iECommerceObject->createCustomerPaymentProfile($paymentArray);
		$response = $this->iECommerceObject->getResponse();
		if ($success) {
			return $this->iAccountId;
		} else {
			$this->iErrorMessage = "Unable to create account: " . $response->getErrorMessage();
			return false;
		}
	}

	# authorize a charge with the selected account
	# option to create donation
	# option to create order payment
	function authorizeCharge($parameters) {
		$merchantIdentifier = getFieldFromId("merchant_identifier","accounts","account_id",$this->iAccountId);
		if (empty($merchantIdentifier)) {
			$merchantIdentifier = $this->hasMerchantProfile();
		}
		if (empty($merchantIdentifier) && $this->iECommerceObject->requiresCustomerToken()) {
			$this->iErrorMessage = "Invalid Merchant Profile";
			return false;
		}
		if (empty($this->iAccountId)) {
			$this->iErrorMessage = "Invalid Account";
			return false;
		}
		$accountToken = getFieldFromId("account_token","accounts","account_id",$this->iAccountId);
		if (!$this->iECommerceObject) {
			$this->iErrorMessage = "No eCommerce Object";
			return false;
		}
		$paymentMethodId = getFieldFromId("payment_method_id","accounts","account_id",$this->iAccountId);
		if (empty($parameters['amount']) || $parameters['amount'] < 0 || !is_numeric($parameters['amount'])) {
			$this->iErrorMessage = "No Amount Given";
			return false;
		}
		$parameters['account_id'] = $this->iAccountId;
		$returnArray = array();
		$orderNumber = $parameters['order_number'];
		if (!empty($parameters['designation_id'])) {
			$designationId = getFieldFromId("designation_id","designations","designation_id",$parameters['designation_id'],"inactive = 0");
			if (empty($designationId)) {
				$this->iErrorMessage = "Invalid Designation";
				return false;
			}
			$donationFee = Donations::getDonationFee(array("designation_id"=>$parameters['designation_id'],"amount"=>$parameters['amount'],"payment_method_id"=>$paymentMethodId));
			$donationCommitmentId = Donations::getContactDonationCommitment($this->iContactId,$parameters['designation_id'],$parameters['donation_source_id']);
			$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
				"account_id,designation_id,project_name,donation_source_id,amount,anonymous_gift,donation_fee,donation_commitment_id,notes) values (?,?,now(),?,?,?,?,?, ?,?,?,?,?)",
				$GLOBALS['gClientId'],$this->iContactId,$paymentMethodId,$this->iAccountId,$parameters['designation_id'],$parameters['project_name'],
				$parameters['donation_source_id'],$parameters['amount'],(empty($parameters['anonymous_gift']) ? "0" : "1"),$donationFee,$donationCommitmentId,$parameters['notes']);
			if (!empty($resultSet['sql_error'])) {
				$this->iErrorMessage = getSystemMessage("basic",$resultSet['sql_error']);
				return false;
			}
			$returnArray['donation_id'] = $resultSet['insert_id'];
			$orderNumber = $returnArray['donation_id'];
			Donations::completeDonationCommitment($donationCommitmentId);
			Donations::processDonation($orderNumber);
			$requiresAttention = getFieldFromId("requires_attention","designations","designation_id",$parameters['designation_id']);
			if ($requiresAttention) {
				sendEmail(array("subject"=>"Designation Requires Attention","body"=>"Donation ID " . $returnArray['donation_id'] . " was created with a designation that requires attention.","email_address"=>getNotificationEmails("DONATIONS")));
			}
		} else if (!empty($parameters['order_object']) && is_a($parameters['order_object'],"Order")) {
			$thisPaymentAmount = $parameters['amount'];
			if (array_key_exists("tax_charge",$parameters) && is_numeric($parameters['tax_charge']) && !empty($parameters['tax_charge'])) {
				$thisPaymentAmount -= $parameters['tax_charge'];
			}
			if (array_key_exists("shipping_charge",$parameters) && is_numeric($parameters['shipping_charge']) && !empty($parameters['shipping_charge'])) {
				$thisPaymentAmount -= $parameters['shipping_charge'];
			}
            if (array_key_exists("handling_charge",$parameters) && is_numeric($parameters['handling_charge']) && !empty($parameters['handling_charge'])) {
                $thisPaymentAmount -= $parameters['handling_charge'];
            }
			$returnArray['order_payment_id'] = $parameters['order_object']->createOrderPayment($thisPaymentAmount,$parameters);
			if (!$returnArray['order_payment_id']) {
				$this->iErrorMessage = getSystemMessage("basic",$parameters['order_object']->getErrorMessage());
				return false;
			}
			$orderNumber = $parameters['order_object']->getOrderId();
		}

		$accountMerchantIdentifier = getFieldFromId("merchant_identifier","accounts","account_id",$this->iAccountId);
		if (empty($accountMerchantIdentifier)) {
			$accountMerchantIdentifier = $merchantIdentifier;
		}
		$addressId = getFieldFromId("address_id","accounts","account_id",$this->iAccountId);
		$success = $this->iECommerceObject->createCustomerProfileTransactionRequest(array("amount"=>$parameters['amount'],"order_number"=>$orderNumber,
			"merchant_identifier"=>$accountMerchantIdentifier,"account_token"=>$accountToken,"address_id"=>$addressId));
		$response = $this->iECommerceObject->getResponse();
		if ($success) {
			if (!empty($returnArray['donation_id'])) {
				executeQuery("update donations set transaction_identifier = ?,authorization_code = ?,bank_batch_number = ? where donation_id = ?",
					$response['transaction_id'],$response['authorization_code'],$response['bank_batch_number'],$returnArray['donation_id']);
			}
			if (!empty($returnArray['order_payment_id'])) {
				executeQuery("update order_payments set transaction_identifier = ?,authorization_code = ? where order_payment_id = ?",
					$response['transaction_id'],$response['authorization_code'],$returnArray['order_payment_id']);
			}
			$returnArray['transaction_identifier'] = $response['transaction_id'];
			$returnArray['authorization_code'] = $response['authorization_code'];
		} else {
			if (!empty($returnArray['order_payment_id'])) {
				executeQuery("delete from order_payments where order_payment_id = ?",$returnArray['order_payment_id']);
			}
			if (!empty($returnArray['donation_id'])) {
				executeQuery("delete from donations where donation_id = ?",$returnArray['donation_id']);
			}
			$this->iErrorMessage = "Charge failed: " . $response['response_reason_text'] . ":" . gethostbyname(gethostname());
			return false;
		}
		return $returnArray;
	}

    public static function notifyCRM($contactId, $paymentMethodFixed = false) {
        $activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
        $activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
        if (!empty($activeCampaignApiKey)) {
            $activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
            $activeCampaign->tagContact($contactId,"Billing Failed", $paymentMethodFixed);
        }
    }
}
