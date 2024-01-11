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

$GLOBALS['gPageCode'] = "ACCOUNTMAINT";
require_once "shared/startup.inc";

class AccountMaintenancePage extends Page {

	function setup() {
		$this->iDataSource->addColumnControl("account_type", "data_type", "hidden");
		$this->iDataSource->addColumnControl("routing_number", "data_type", "hidden");
		$this->iDataSource->addColumnControl("account_number", "data_type", "hidden");
		$this->iDataSource->addColumnControl("echeck_type", "data_type", "hidden");
		$this->iDataSource->addColumnControl("bank_name", "data_type", "hidden");
		$this->iDataSource->addColumnControl("card_number", "data_type", "hidden");
		$this->iDataSource->addColumnControl("first_name", "readonly", "true");
		$this->iDataSource->addColumnControl("merchant_identifier", "readonly", "true");
		$this->iDataSource->addColumnControl("last_name", "readonly", "true");
		$this->iDataSource->addColumnControl("business_name", "readonly", "true");
		$this->iDataSource->addColumnControl("address_1", "readonly", "true");
		$this->iDataSource->addColumnControl("address_2", "readonly", "true");
		$this->iDataSource->addColumnControl("city", "readonly", "true");
		$this->iDataSource->addColumnControl("state", "readonly", "true");
		$this->iDataSource->addColumnControl("postal_code", "readonly", "true");
		$this->iDataSource->addColumnControl("country_id", "readonly", "true");
		$this->iDataSource->addColumnControl("account_label", "readonly", "true");
		$this->iDataSource->addColumnControl("payment_method_id", "readonly", "true");
		$this->iDataSource->addColumnControl("expiration_date", "readonly", "true");
		$this->iDataSource->addColumnControl("account_number", "readonly", "true");
        $this->iDataSource->addColumnControl("address_id", "data_type", "hidden");
		$this->iDataSource->addColumnControl("bill_to_first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_city", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_state", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_postal_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bill_to_country", "data_type", "varchar");
		$this->iDataSource->addColumnControl("expiration_month", "data_type", "select");
		$this->iDataSource->addColumnControl("expiration_month", "choices", "return array('01'=>'01','02'=>'02','03'=>'03','04'=>'04','05'=>'05','06'=>'06','07'=>'07','08'=>'08','09'=>'09','10'=>'10','11'=>'11','12'=>'12')");
		$this->iDataSource->addColumnControl("expiration_month", "empty_text", "[Leave As Is]");
		$this->iDataSource->addColumnControl("expiration_month", "data-conditional-required", "\$(\"#expiration_year\").val() != \"\"");
		$this->iDataSource->addColumnControl("expiration_year", "data_type", "select");
		$years = array();
		for ($x = 0; $x < 10; $x++) {
			$years[date("Y") + $x] = date("Y") + $x;
		}
		$this->iDataSource->addColumnControl("expiration_year", "choices", $years);
		$this->iDataSource->addColumnControl("expiration_year", "empty_text", "[Leave As Is]");
		$this->iDataSource->addColumnControl("expiration_year", "data-conditional-required", "\$(\"#expiration_month\").val() != \"\"");
		$this->iDataSource->addColumnControl("bill_to_first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("bill_to_last_name", "form_label", "Last Name");
		$this->iDataSource->addColumnControl("bill_to_business_name", "form_label", "Company");
		$this->iDataSource->addColumnControl("bill_to_address_1", "form_label", "Address");
		$this->iDataSource->addColumnControl("bill_to_city", "form_label", "City");
		$this->iDataSource->addColumnControl("bill_to_state", "form_label", "State");
		$this->iDataSource->addColumnControl("bill_to_postal_code", "form_label", "Zip");
		$this->iDataSource->addColumnControl("bill_to_country", "form_label", "Country");
		$this->iDataSource->addColumnControl("expiration_month", "form_label", "Expiration Month");
		$this->iDataSource->addColumnControl("expiration_year", "form_label", "Expiration Year");

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("first_name", "last_name", "business_name", "account_label", "payment_method_id", "account_number"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("first_name", "last_name", "business_name", "account_label", "payment_method_id", "account_number"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("account_token is not null and inactive = 0");
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function afterGetRecord(&$returnArray) {
		$merchantAccountId = eCommerce::getAccountMerchantAccount($returnArray['primary_id']['data_value']);
		$merchantIdentifier = $returnArray['merchant_identifier']['data_value'];
		if (empty($merchantIdentifier)) {
			$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $returnArray['contact_id']['data_value'], "merchant_account_id = ?", $merchantAccountId);
		}
		$returnArray['merchant_identifier'] = array("data_value" => $merchantIdentifier, "crc_value" => getCrcValue($merchantIdentifier));
		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		if (!$eCommerce) {
			$returnArray['error_message'] = "No merchant services account available";
		} else {
			if (empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
				$returnArray['error_message'] = "No customer database for this merchant account";
			} else {
				$eCommerce->getCustomerPaymentProfile(array("merchant_identifier" => $returnArray['merchant_identifier']['data_value'], "account_token" => $returnArray['account_token']['data_value']));
				$response = $eCommerce->getResponse();
				if (is_array($response) && array_key_exists("payment_profile", $response)) {
					$returnArray['bill_to_first_name'] = array("data_value" => $response['first_name'], "crc_value" => getCrcValue($response['first_name']));
					$returnArray['bill_to_last_name'] = array("data_value" => $response['last_name'], "crc_value" => getCrcValue($response['last_name']));
					$returnArray['bill_to_business_name'] = array("data_value" => $response['business_name'], "crc_value" => getCrcValue($response['business_name']));
					$returnArray['bill_to_address_1'] = array("data_value" => $response['address_1'], "crc_value" => getCrcValue($response['address_1']));
					$returnArray['bill_to_city'] = array("data_value" => $response['city'], "crc_value" => getCrcValue($response['city']));
					$returnArray['bill_to_state'] = array("data_value" => $response['state'], "crc_value" => getCrcValue($response['state']));
					$returnArray['bill_to_postal_code'] = array("data_value" => $response['postal_code'], "crc_value" => getCrcValue($response['postal_code']));
					$returnArray['bill_to_country'] = array("data_value" => $response['country'], "crc_value" => getCrcValue($response['country']));
					if (array_key_exists("card_number", $response)) {
						$returnArray['expiration_month'] = array("data_value" => "", "crc_value" => getCrcValue(""));
						$returnArray['expiration_year'] = array("data_value" => "", "crc_value" => getCrcValue(""));
						$returnArray['card_number'] = array("data_value" => $response['card_number']);
					}
					if (array_key_exists("account_type", $response)) {
						$returnArray['account_type'] = array("data_value" => $response['account_type']);
						$returnArray['routing_number'] = array("data_value" => $response['routing_number']);
						$returnArray['account_number'] = array("data_value" => $response['account_number']);
						$returnArray['echeck_type'] = array("data_value" => $response['echeck_type']);
						$returnArray['bank_name'] = array("data_value" => $response['bank_name']);
					}
				} else {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
			}
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#copy_contact", function () {
                $("#bill_to_first_name").val($("#first_name").val());
                $("#bill_to_last_name").val($("#last_name").val());
                $("#bill_to_business_name").val($("#business_name").val());
                $("#bill_to_address_1").val($("#address_1").val());
                $("#bill_to_city").val($("#city").val());
                $("#bill_to_state").val($("#state").val());
                $("#bill_to_postal_code").val($("#postal_code").val());
                $("#bill_to_country").val($("#country_id option:selected").text());
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                if ("expiration_month" in returnArray) {
                    $("#credit_card_info").show();
                } else {
                    $("#credit_card_info").hide();
                }
            }
        </script>
		<?php
	}

	function saveChanges() {
		$returnArray = array();
		$merchantAccountId = eCommerce::getAccountMerchantAccount($_POST['primary_id']);

		$merchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $_POST['primary_id']);
		if (empty($merchantIdentifier)) {
			$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $returnArray['contact_id']['data_value'], "merchant_account_id = ?", $merchantAccountId);
		}

		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		if (!$eCommerce) {
			$returnArray['error_message'] = "No merchant services account available";
			ajaxResponse($returnArray);
		} else {
			if (empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
				$returnArray['error_message'] = "No customer database for this merchant account";
				ajaxResponse($returnArray);
			}
		}
		$parameters = array("merchant_identifier" => $merchantIdentifier, "account_token" => getFieldFromId("account_token", "accounts", "account_id", $_POST['primary_id']));
		$copyFields = array("account_type", "routing_number", "account_number", "echeck_type", "bank_name", "card_number");
		foreach ($copyFields as $fieldName) {
			$parameters[$fieldName] = $_POST[$fieldName];
		}
		foreach ($_POST as $fieldName => $fieldData) {
			if (startsWith($fieldName,"bill_to_")) {
				$parameters[substr($fieldName, strlen("bill_to_"))] = $fieldData;
			}
		}
		if (!empty($_POST['expiration_month']) && !empty($_POST['expiration_year'])) {
			$parameters['expiration_date'] = $_POST['expiration_year'] . "-" . $_POST['expiration_month'] . "-01";
		}
		if ($eCommerce->updateCustomerPaymentProfile($parameters)) {
			$returnArray['info_message'] = "Information Saved";
		} else {
			$returnArray['error_message'] = "Error saving information: " . $eCommerce->getErrorMessage();
		}
		ajaxResponse($returnArray);
	}
}

$pageObject = new AccountMaintenancePage("accounts");
$pageObject->displayPage();
