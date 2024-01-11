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

$GLOBALS['gPageCode'] = "RETAILSTORESETUP";
require_once "shared/startup.inc";
require_once "classes/easypost/lib/easypost.php";
require_once "retailstoresetup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 360000;

class ThisPage extends Page {

	var $iCustomFields = array();
	var $iDistributors = array();
	var $iUrlAliasTypes = array();
	var $iPages = array();
	var $iFeedPages = array();
	var $iPaymentMethodTypes = array();
	var $iPaymentMethods = array();
	var $iShippingCarriers = array();

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "send_text_message":
				if ($GLOBALS['gPHPVersion'] < 70200) {
					$returnArray['error_message'] = "Unable to send text, use email";
					ajaxResponse($returnArray);
					break;
				}
				$result = TextMessage::sendMessage($GLOBALS['gUserRow']['contact_id'], "Test Text Message");
				if ($result) {
					$returnArray['info_message'] = "Text successfully send";
				} else {
					$returnArray['error_message'] = TextMessage::getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "check_taxjar_api_token":
				require_once __DIR__ . '/taxjar/vendor/autoload.php';
				try {
					$client = TaxJar\Client::withApiKey($_GET['taxjar_api_token']);
					$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
					$nexusList = $client->nexusRegions();
					if(empty($nexusList) || $nexusList['status'] == 403) {
						$returnArray['error_message'] = "Invalid API Key" . (empty($nexusList['detail']) ? "" : " (" . $nexusList['detail'] . ")");
					} else {
						$nexusData = "<h2>Business Presence Locations</h2><p>Taxes are ONLY charged where you, the retailer, have a presence, called \"nexus\", listed below. Some states determine that you have a nexus in that state when you exceed some predetermined sales figure and have to pay taxes on any sales beyond that figure. Taxjar will notify you when you get close to that number so you can register as a nexus and begin collecting taxes.</p>";
						foreach ((array)$nexusList as $thisNexus) {
							$nexusData .= "<p class='taxjar-nexus'>" . $thisNexus->region . ($thisNexus->country == "United States" ? "" : ", " . $thisNexus->country);
						}
						$nexusData .= "<p>To add more locations, <a href='https://app.taxjar.com/account#states'>click here.</a></p>";
						$returnArray['taxjar_validation'] = "This API token appears to be valid";
						$returnArray['taxjar_nexus_data'] = $nexusData;
					}
				} catch (Exception $e) {
					$returnArray['error_message'] = "Invalid API Key";
				}
				ajaxResponse($returnArray);
				break;
			case "get_distributor_info":
				$locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['location_id']);
				if (empty($locationId)) {
					$returnArray['product_distributor_information'] = "<p>No location for this distributor exists. <a href='/locationmaintenance.php?url_page=new' target='_blank'>Create one</a></p>";
				}
				$locationCredentialId = getFieldFromId("location_credential_id", "location_credentials", "location_id", $locationId);
				$urlLink = "/locationcredentialmaintenance.php?url_page=show&primary_id=" . $locationId;
				$returnArray['product_distributor_information'] = "<p>Credentials for this distributor do" . (empty($locationCredentialId) ? " NOT" : "") . " exist. <a target='_blank' href='" . $urlLink .
					"'>" . (empty($locationCredentialId) ? "Add" : "Edit") . " Credentials</a>.</p>" .
					(empty($locationCredentialId) ? "" : "<p id='test_distributor_results'><button id='test_distributor'>Test Credentials</button></p>");
				ajaxResponse($returnArray);
				break;
			case "get_merchant_account_field_labels":
				$returnArray['field_labels'] = array();
				$resultSet = executeQuery("select * from merchant_service_field_labels where merchant_service_id = ?", $_GET['merchant_service_id']);
				while ($row = getNextRow($resultSet)) {
					if (substr($row['column_name'], 0, strlen("custom_field_")) == "custom_field_") {
						$customFieldCode = substr($row['column_name'], strlen("custom_field_"));
						$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", strtoupper($customFieldCode),
							"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'MERCHANT_ACCOUNTS')");
						$row['column_name'] = "merchant_accounts_custom_field_id_" . $customFieldId;
					}
					$returnArray['field_labels'][$row['column_name']] = array("form_label" => $row['form_label'], "not_null" => $row['not_null']);
				}
				ajaxResponse($returnArray);
				break;
			case "setup_nofraud":
				$returnArray = NoFraud::setup();
				ajaxResponse($returnArray);
				break;
            case "setup_flp":
                if(array_key_exists('flp_affiliate_link', $_POST)) {
                    $returnArray = FirearmsLegalProtection::setupAffiliate($_POST['flp_affiliate_link']);
                } else {
                    $returnArray = FirearmsLegalProtection::setup();
                }
				ajaxResponse($returnArray);
				break;
            case "setup_credova":
				$returnArray = CredovaClient::setup();
				ajaxResponse($returnArray);
				break;
            case "setup_listrak":
                $returnArray = Listrak::setup();
                ajaxResponse($returnArray);
                break;
            case "test_distributor":
				$productDistributor = ProductDistributor::getProductDistributorInstance($_GET['location_id']);
				if (!$productDistributor) {
					$returnArray['test_distributor_results'] = "Distributor not found. Contact support.";
					$returnArray['class'] = "red-text";
				} elseif ($productDistributor->testCredentials()) {
					$returnArray['test_distributor_results'] = "Connection to distributor works";
					$returnArray['class'] = "green-text";
				} else {
					$returnArray['test_distributor_results'] = "Connection to distributor DOES NOT work";
					if (!empty($productDistributor->getErrorMessage())) {
						$returnArray['test_distributor_results'] .= " (" . $productDistributor->getErrorMessage() . ")";
					}
					$returnArray['class'] = "red-text";
				}
				ajaxResponse($returnArray);
				break;
			case "test_merchant":
				$eCommerce = eCommerce::getEcommerceInstance($_GET['merchant_account_id']);
				if (!$eCommerce) {
					$returnArray['test_merchant_results'] = "Merchant Service not found. Contact support.";
					$returnArray['class'] = "red-text";
				} elseif ($eCommerce->testConnection()) {
					$returnArray['test_merchant_results'] = "Connection to Merchant Account works";
					$returnArray['class'] = "green-text";
				} else {
					$returnArray['test_merchant_results'] = "Connection to Merchant Account DOES NOT work";
					$returnArray['class'] = "red-text";
				}
				ajaxResponse($returnArray);
				break;
			case "test_email":
				$parameters = array("email_credential_code" => "DEFAULT");
				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("email_credentials_")) == "email_credentials_") {
						$emailCredentialsFieldName = substr($fieldName, strlen("email_credentials_"));
						$parameters[$emailCredentialsFieldName] = $fieldValue;
					}
				}
				$result = sendTestEmail($parameters);
				if ($result === true) {
					$returnArray['test_email_results'] = "Test email successfully sent to " . $GLOBALS['gUserRow']['email_address'];
					$returnArray['class'] = "green-text";
				} else {
					$returnArray['test_email_results'] = $result;
					$returnArray['class'] = "red-text";
				}
				ajaxResponse($returnArray);
				break;
			case "test_easypost":
				\EasyPost\EasyPost::setApiKey($_GET['easy_post_api_key']);

				$connectionWorks = true;
				try {
					$fromAddress = \EasyPost\Address::create(array(
						'company' => $GLOBALS['gClientRow']['business_name'],
						'street1' => $GLOBALS['gClientRow']['address_1'],
						'street2' => "",
						'city' => $GLOBALS['gClientRow']['city'],
						'state' => $GLOBALS['gClientRow']['state'],
						'zip' => $GLOBALS['gClientRow']['postal_code']
					));
					$toAddress = \EasyPost\Address::create(array(
						'name' => $GLOBALS['gClientRow']['business_name'],
						'street1' => $GLOBALS['gClientRow']['address_1'],
						'street2' => "",
						'city' => $GLOBALS['gClientRow']['city'],
						'state' => $GLOBALS['gClientRow']['state'],
						'zip' => $GLOBALS['gClientRow']['postal_code']
					));
					$parcel = \EasyPost\Parcel::create(array(
						"length" => 24,
						"width" => 12,
						"height" => 12,
						"weight" => 32
					));
					$shipment = \EasyPost\Shipment::create(array(
						"to_address" => $toAddress,
						"from_address" => $fromAddress,
						"parcel" => $parcel,
						"options" => array('delivery_confirmation' => (empty($_POST['signature_required']) ? "NO_SIGNATURE" : $_POST['signature_required']))
					));
				} catch (Exception $e) {
					if ($e->ecode == "APIKEY.INACTIVE") {
						$connectionWorks = false;
					}
				}

				if ($connectionWorks) {
					$returnArray['test_easypost_results'] = "Connection to EasyPost works";
					$returnArray['class'] = "green-text";
				} else {
					$returnArray['test_easypost_results'] = "Connection to EasyPost DOES NOT work";
					$returnArray['class'] = "red-text";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function setup() {
		loadSetupVariables($this);
		$productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
		if (empty($productPriceTypeId)) {
			executeQuery("insert into product_price_types (client_id,product_price_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], "SALE_PRICE", "Sale Price");
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "list"));
		}
		$customFieldTypes = array();
		$resultSet = executeQuery("select * from custom_field_types");
		while ($row = getNextRow($resultSet)) {
			$customFieldTypes[strtoupper($row['custom_field_type_code'])] = $row['custom_field_type_id'];
		}
		$customFields = array();
		$sortOrder = 0;
		foreach ($this->iCustomFields as $thisCustomField) {
			$sortOrder += 10;
			$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", $thisCustomField['custom_field_code'], "custom_field_type_id = ?", $customFieldTypes[strtoupper($thisCustomField['custom_field_type_code'])]);
			if (empty($customFieldId)) {
				$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label,sort_order) values (?,?,?,?,?,?)",
					$GLOBALS['gClientId'], $thisCustomField['custom_field_code'], $thisCustomField['description'], $customFieldTypes[strtoupper($thisCustomField['custom_field_type_code'])],
					(empty($thisCustomField['form_label']) ? $thisCustomField['description'] : $thisCustomField['form_label']), $sortOrder);
				$customFields[$thisCustomField['custom_field_code']] = $customFieldId = $insertSet['insert_id'];
				foreach ($thisCustomField['controls'] as $controlName => $controlValue) {
					executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, $controlName, $controlValue);
				}
			} else {
				executeQuery("update custom_fields set sort_order = ? where custom_field_id = ?", $sortOrder, $customFieldId);
				$customFields[$thisCustomField['custom_field_code']] = $customFieldId;
			}
		}

		$productDistributors = array();
		foreach ($this->iDistributors as $thisDistributor) {
			$sortOrder = $thisDistributor['sort_order'];
			$productDistributorId = getFieldFromId("product_distributor_id", "product_distributors", "product_distributor_code", $thisDistributor['product_distributor_code']);
			if (empty($productDistributorId)) {
				$insertSet = executeQuery("insert into product_distributors (product_distributor_code,description,class_name,sort_order) values (?,?,?,?)", $thisDistributor['product_distributor_code'], $thisDistributor['description'], $thisDistributor['class_name'], $sortOrder);
				$productDistributors[$thisDistributor['product_distributor_code']] = $productDistributorId = $insertSet['insert_id'];
			} else {
				$productDistributors[$thisDistributor['product_distributor_code']] = $productDistributorId;
			}
			$customFieldIds = array();
			foreach ($thisDistributor['custom_fields'] as $customFieldCode) {
				$productDistributorCustomFieldId = getFieldFromId("product_distributor_custom_field_id", "product_distributor_custom_fields", "product_distributor_id", $productDistributorId, "custom_field_id = ?", $customFields[strtoupper($customFieldCode)]);
				if (empty($productDistributorCustomFieldId)) {
					executeQuery("insert ignore into product_distributor_custom_fields (product_distributor_id,custom_field_id) values (?,?)", $productDistributorId, $customFields[strtoupper($customFieldCode)]);
				}
			}
			$fieldLabels = $thisDistributor['field_labels'];
			$resultSet = executeQuery("select * from product_distributor_field_labels where product_distributor_id = ?", $productDistributorId);
			while ($row = getNextRow($resultSet)) {
				$notNull = 0;
				if (array_key_exists("not_null", $thisDistributor) && in_array($row['column_name'], $thisDistributor['not_null'])) {
					$notNull = 1;
				}
				if (array_key_exists($row['column_name'], $fieldLabels) && !empty($fieldLabels[$row['column_name']])) {
					if ($row['form_label'] != $fieldLabels[$row['column_name']] || $row['not_null'] != $notNull) {
						executeQuery("update product_distributor_field_labels set form_label = ?, not_null = ? where product_distributor_field_label_id = ?", $fieldLabels[$row['column_name']], $notNull, $row['product_distributor_field_label_id']);
					}
					unset($fieldLabels[$row['column_name']]);
				} else {
					executeQuery("delete from product_distributor_field_labels where product_distributor_field_label_id = ?", $row['product_distributor_field_label_id']);
				}
			}
			foreach ($fieldLabels as $columnName => $formLabel) {
				if (empty($formLabel)) {
					executeQuery("delete from product_distributor_field_labels where product_distributor_id = ? and column_name = ?", $productDistributorId, $columnName);
				} else {
					$notNull = 0;
					if (array_key_exists("not_null", $thisDistributor) && in_array($columnName, $thisDistributor['not_null'])) {
						$notNull = 1;
					}
					executeQuery("insert ignore into product_distributor_field_labels (product_distributor_id, column_name, form_label, not_null) values (?,?,?,?)",
						$productDistributorId, $columnName, $formLabel, $notNull);
				}
			}
		}

		$paymentMethodTypes = array();
		foreach ($this->iPaymentMethodTypes as $index => $thisPaymentMethodType) {
			$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", $thisPaymentMethodType['payment_method_type_code']);
			if (empty($paymentMethodTypeId)) {
				$insertSet = executeQuery("insert into payment_method_types (client_id,payment_method_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], $thisPaymentMethodType['payment_method_type_code'], $thisPaymentMethodType['description']);
				$paymentMethodTypeId = $insertSet['insert_id'];
			}
			$paymentMethodTypes[$thisPaymentMethodType['payment_method_type_code']] = $paymentMethodTypeId;
		}
		foreach ($this->iPaymentMethods as $index => $thisPaymentMethod) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", $thisPaymentMethod['payment_method_code']);
			if (empty($paymentMethodId)) {
				$insertSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,payment_method_type_id,internal_use_only) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], $thisPaymentMethod['payment_method_code'], $thisPaymentMethod['description'], $paymentMethodTypes[$thisPaymentMethod['payment_method_type_code']], (empty($thisPaymentMethod['internal_use_only']) ? 0 : 1));
			}
		}
		foreach ($this->iShippingCarriers as $index => $thisShippingCarrier) {
			$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($thisShippingCarrier['shipping_carrier_code']));
			if (empty($shippingCarrierId)) {
				$insertSet = executeQuery("insert into shipping_carriers (client_id,shipping_carrier_code,description,link_url) values (?,?,?,?)",
					$GLOBALS['gClientId'], $thisShippingCarrier['shipping_carrier_code'], $thisShippingCarrier['description'], $thisShippingCarrier['link_url']);
			}
		}
		foreach($this->iFeedPages as $thisPage) {
			$pageId = getFieldFromId("page_id","pages", "script_filename", $thisPage['script_filename'], "client_id = ?", $GLOBALS['gDefaultClientId']);
			if(empty($pageId)) {
				if(!$GLOBALS['gUserRow']['superuser_flag']) {
					$userId = getFieldFromId("user_id", "users", "superuser_flag",1,"inactive = 0");
				} else {
					$userId = $GLOBALS['gUserId'];
				}
				$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $thisPage['subsystem_code']);
				$insertSet = executeQuery("insert into pages (client_id,page_code,description,date_created,creator_user_id,script_filename,subsystem_id) values (?,?,?,current_date,?,?,?)",
					$GLOBALS['gDefaultClientId'], $thisPage['page_code'], $thisPage['description'], $userId, $thisPage['script_filename'], $subsystemId);
				$pageId = $insertSet['insert_id'];
			}
			$pageAccessRow = getRowFromId("page_access", "page_id", $pageId,"public_access = 1");
			if(empty($pageAccessRow)) {
				executeQuery("insert into page_access (page_id,all_client_access,public_access,permission_level) values (?,1,1,3)", $pageId);
			}
		}
        coreSTORE::createSetupCorestoreApiApp();
	}

	function templateChoices($showInactive = false) {
		$templateChoices = array();
		$resultSet = executeQuery("select * from templates where inactive = 0 and include_crud = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$templateChoices[$row['template_id']] = array("key_value" => $row['template_id'], "description" => $row['description'], "inactive" => false);
		}
		return $templateChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnLikeColumn("template_id", "pages", "template_id");
		$this->iDataSource->addColumnControl("template_id", "get_choices", "templateChoices");
		$this->iDataSource->addColumnLikeColumn("location_id", "location_credentials", "location_id");
		$this->iDataSource->addColumnControl("location_id", "not_null", false);

		$this->iDataSource->addColumnControl("hide_using_ffl", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("hide_using_ffl", "form_label", "Do not show this question again");

		$this->iDataSource->addColumnControl("setup_email_credentials", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_email_credentials", "form_label", "Set up the default Email Credentials");
		$this->iDataSource->addColumnControl("setup_merchant_account", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_merchant_account", "form_label", "Set up the default Merchant Account");
		$this->iDataSource->addColumnControl("setup_pricing_structure", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_pricing_structure", "form_label", "Set up the default Pricing Structure");

		$this->iDataSource->addColumnLikeColumn("email_credentials_full_name", "email_credentials", "full_name");
		$this->iDataSource->addColumnControl("email_credentials_full_name", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("email_credentials_email_address", "email_credentials", "email_address");
		$this->iDataSource->addColumnControl("email_credentials_email_address", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_host", "email_credentials", "smtp_host");
		$this->iDataSource->addColumnControl("email_credentials_smtp_host", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_port", "email_credentials", "smtp_port");
		$this->iDataSource->addColumnControl("email_credentials_smtp_port", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("email_credentials_security_setting", "email_credentials", "security_setting");
		$this->iDataSource->addColumnControl("email_credentials_security_setting", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_authentication_type", "email_credentials", "smtp_authentication_type");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_user_name", "email_credentials", "smtp_user_name");
		$this->iDataSource->addColumnControl("email_credentials_smtp_user_name", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_password", "email_credentials", "smtp_password");
		$this->iDataSource->addColumnControl("email_credentials_smtp_password", "data-conditional-required", "\$(\"#setup_email_credentials\").prop(\"checked\")");
		$resultSet = executeQuery("select * from page_controls where page_id = (select page_id from pages where page_code = 'EMAILCREDENTIALMAINT')");
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("email_credentials_" . $row['column_name'], $row['control_name'], $row['control_value']);
		}

		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_account_id", "merchant_accounts", "merchant_account_id");
		$this->iDataSource->addColumnControl("merchant_accounts_merchant_account_id", "data_type", "hidden");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_service_id", "merchant_accounts", "merchant_service_id");
		$this->iDataSource->addColumnControl("merchant_accounts_merchant_service_id", "data-conditional-required", "\$(\"#setup_merchant_account\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_account_login", "merchant_accounts", "account_login");
		$this->iDataSource->addColumnControl("merchant_accounts_account_login", "data-conditional-required", "\$(\"#setup_merchant_account\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_account_key", "merchant_accounts", "account_key");
		$this->iDataSource->addColumnControl("merchant_accounts_account_key", "data-conditional-required", "\$(\"#setup_merchant_account\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_identifier", "merchant_accounts", "merchant_identifier");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_link_url", "merchant_accounts", "link_url");
		$resultSet = executeQuery("select * from page_controls where page_id = (select page_id from pages where page_code = 'MERCHANTACCOUNTMAINT')");
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("merchant_accounts_" . $row['column_name'], $row['control_name'], $row['control_value']);
		}

		$resultSet = executeQuery("select * from preferences where preference_id in (select preference_id from preference_group_links where " .
			"preference_group_id in (select preference_group_id from preference_groups where preference_group_code = 'INTEGRATION_SETTINGS'))");
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl(strtolower($row['preference_code']), "data_type", $row['data_type']);
			$this->iDataSource->addColumnControl(strtolower($row['preference_code']), "form_label", $row['description']);
			if (strlen($row['minimum_value']) > 0) {
				$this->iDataSource->addColumnControl(strtolower($row['preference_code']), "minimum_value", $row['minimum_value']);
			}
			if (strlen($row['maximum_value']) > 0) {
				$this->iDataSource->addColumnControl(strtolower($row['preference_code']), "maximum_value", $row['maximum_value']);
			}
			if (!empty($row['choices'])) {
				$rawChoices = getContentLines($row['choices']);
				$choices = array();
				foreach ($rawChoices as $thisChoice) {
					$choices[$thisChoice] = $thisChoice;
				}
				$this->iDataSource->addColumnControl(strtolower($row['preference_code']), "choices", $choices);
			}
		}

		$this->iDataSource->addColumnLikeColumn("pricing_structure_percentage", "pricing_structures", "percentage");
		$this->iDataSource->addColumnControl("pricing_structure_percentage", "data-conditional-required", "\$(\"#setup_pricing_structure\").prop(\"checked\")");
		$this->iDataSource->addColumnLikeColumn("pricing_structure_price_calculation_type_id", "pricing_structures", "price_calculation_type_id");
		$this->iDataSource->addColumnControl("pricing_structure_price_calculation_type_id", "data-conditional-required", "\$(\"#setup_pricing_structure\").prop(\"checked\")");
		$this->iDataSource->addColumnControl("pricing_structure_price_calculation_type_id", "default_value", "1");

		$this->iDataSource->addColumnControl("domain_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("domain_name", "form_label", "Primary Domain Name");
	}

	function gundealsPreferences() {
		?>
        <div class='basic-form-line'>
            <label for='gundeals_domain_name'>Domain Name</label>
            <span class='help-label'>Domain name used for gun.deals listings. Leave blank to use your default domain name.</span>
            <input type='text' size='80' id='gundeals_domain_name' name='gundeals_domain_name'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class='basic-form-line'>
            <label for='gundeals_free_shipping_text'>Free shipping text</label>
            <span class='help-label'>Text to be displayed on gun.deals if shipping cost is zero. Default is "FREE shipping".</span>
            <input type='text' size='80' id='gundeals_free_shipping_text' name='gundeals_free_shipping_text'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <h2>Settings by Department</h2>
		<?php
		$resultSet = executeQuery("select * from product_departments where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h3><?= htmlText($row['description']) ?></h3>
            <div class='basic-form-line inline-block'>
                <label for='gundeals_department_products_<?= $row['product_department_id'] ?>'>What is listed?</label>
                <select id='gundeals_department_products_<?= $row['product_department_id'] ?>' name='gundeals_department_products_<?= $row['product_department_id'] ?>'>
                    <option value='all'>All Products</option>
                    <option value=''>Only In-Stock Products</option>
                    <option value='local'>All Products at Local Location(s)</option>
                    <option value='localstock'>In Stock at Local Location(s)</option>
                    <option value='nothing'>Nothing</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class='basic-form-line inline-block'>
                <label for='gundeals_department_shipping_<?= $row['product_department_id'] ?>'>Shipping Charge</label>
                <input type='text' class='align-right validate[custom[number]]' data-decimal-places="2" size='12' id='gundeals_department_shipping_<?= $row['product_department_id'] ?>' name='gundeals_department_shipping_<?= $row['product_department_id'] ?>'>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}

	function ammoSeekPreferences() {
		?>
        <p>The domain name should <strong>ONLY</strong> be your domain name. Do <strong>not</strong> put the ammoseek feed URL. It should be something simple like "https://yourdomainname.com".</p>
        <div class='basic-form-line'>
            <label for='ammo_seek_domain_name'>Domain Name</label>
            <input type='text' size='80' id='ammo_seek_domain_name' name='ammo_seek_domain_name'>
            <div class='basic-form-line-messages'><span class="help-label">Domain name used for AmmoSeek listings. Leave blank to use your default domain name.</span><span class='field-error-text'></span></div>
        </div>

        <div class='basic-form-line'>
            <label for='ammo_seek_exclude_category_codes'>Exclude Category Codes</label>
            <span class='help-label'>Comma separated list of category codes to exclude from the feed.</span>
            <input type='text' size='120' id='ammo_seek_exclude_category_codes' name='ammo_seek_exclude_category_codes'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <p>AmmoSeek only permits fully assembled firearms to be listed. All product categories in the Uppers & Lowers or Gun Parts category groups will be automatically excluded.</p>

        <div class='basic-form-line'>
            <span class='help-label'>Check this box to allow custom non-numeric UPCs to be listed.</span>
            <input type='checkbox' id='ammo_seek_allow_non_numeric_upc' name='ammo_seek_allow_non_numeric_upc' value="1"><label class="checkbox-label" for='ammo_seek_allow_non_numeric_upc'>Allow Non-Numeric UPCs</label>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <h2>Settings by Department</h2>
		<?php
		$resultSet = executeQuery("select * from product_departments where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h3><?= htmlText($row['description']) ?></h3>
            <div class='basic-form-line inline-block'>
                <label for='ammo_seek_department_products_<?= $row['product_department_id'] ?>'>What is listed?</label>
                <select id='ammo_seek_department_products_<?= $row['product_department_id'] ?>' name='ammo_seek_department_products_<?= $row['product_department_id'] ?>'>
                    <option value='all'>All Products</option>
                    <option value=''>Only In-Stock Products</option>
                    <option value='local'>All Products at Local Location(s)</option>
                    <option value='localstock'>In Stock at Local Location(s)</option>
                    <option value='nothing'>Nothing</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class='basic-form-line inline-block'>
                <label for='ammo_seek_department_shipping_<?= $row['product_department_id'] ?>'>Shipping Charge</label>
                <input type='text' size="12" class='align-right validate[custom[number]]' data-decimal-places="2" id='ammo_seek_department_shipping_<?= $row['product_department_id'] ?>' name='ammo_seek_department_shipping_<?= $row['product_department_id'] ?>'>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}

	function gunSaleFinderPreferences() {
		?>
        <div class='basic-form-line'>
            <label for='gun_sale_finder_domain_name'>Domain Name</label>
            <span class='help-label'>Domain name used for Gun Sale Finder listings. Leave blank to use your default domain name.</span>
            <input type='text' size='80' id='gun_sale_finder_domain_name' name='gun_sale_finder_domain_name'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class='basic-form-line'>
            <label for='gun_sale_finder_free_shipping_text'>Free shipping text</label>
            <span class='help-label'>Text to be displayed on Gun Sale Finder if shipping cost is zero. Default is "FREE shipping".</span>
            <input type='text' size='80' id='gun_sale_finder_free_shipping_text' name='gun_sale_finder_free_shipping_text'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <h2>Settings by Department</h2>
		<?php
		$resultSet = executeQuery("select * from product_departments where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h3><?= htmlText($row['description']) ?></h3>
            <div class='basic-form-line inline-block'>
                <label for='gun_sale_finder_department_products_<?= $row['product_department_id'] ?>'>What is listed?</label>
                <select id='gun_sale_finder_department_products_<?= $row['product_department_id'] ?>' name='gun_sale_finder_department_products_<?= $row['product_department_id'] ?>'>
                    <option value='all'>All Products</option>
                    <option value=''>Only In-Stock Products</option>
                    <option value='local'>All Products at Local Location(s)</option>
                    <option value='localstock'>In Stock at Local Location(s)</option>
                    <option value='nothing'>Nothing</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class='basic-form-line inline-block'>
                <label for='gun_sale_finder_department_shipping_<?= $row['product_department_id'] ?>'>Shipping Charge</label>
                <input type='text' size="12" class='align-right validate[custom[number]]' data-decimal-places="2" id='gun_sale_finder_department_shipping_<?= $row['product_department_id'] ?>' name='gun_sale_finder_department_shipping_<?= $row['product_department_id'] ?>'>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}

	function highCapDealsPreferences() {
		?>
        <div class='basic-form-line'>
            <label for='high_cap_deals_domain_name'>Domain Name</label>
            <span class='help-label'>Domain name used for High Cap Deals listings. Leave blank to use your default domain name.</span>
            <input type='text' size='80' id='high_cap_deals_domain_name' name='high_cap_deals_domain_name'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class='basic-form-line'>
            <label for='high_cap_deals_free_shipping_text'>Free shipping text</label>
            <span class='help-label'>Text to be displayed on High Cap Deals if shipping cost is zero. Default is "FREE shipping".</span>
            <input type='text' size='80' id='high_cap_deals_free_shipping_text' name='high_cap_deals_free_shipping_text'>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <h2>Settings by Department</h2>
		<?php
		$resultSet = executeQuery("select * from product_departments where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h3><?= htmlText($row['description']) ?></h3>
            <div class='basic-form-line inline-block'>
                <label for='high_cap_deals_department_products_<?= $row['product_department_id'] ?>'>What is listed?</label>
                <select id='high_cap_deals_department_products_<?= $row['product_department_id'] ?>' name='high_cap_deals_department_products_<?= $row['product_department_id'] ?>'>
                    <option value='all'>All Products</option>
                    <option value=''>Only In-Stock Products</option>
                    <option value='local'>All Products at Local Location(s)</option>
                    <option value='localstock'>In Stock at Local Location(s)</option>
                    <option value='nothing'>Nothing</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class='basic-form-line inline-block'>
                <label for='high_cap_deals_department_shipping_<?= $row['product_department_id'] ?>'>Shipping Charge</label>
                <input type='text' class='align-right validate[custom[number]]' data-decimal-places="2" size='12' id='high_cap_deals_department_shipping_<?= $row['product_department_id'] ?>' name='high_cap_deals_department_shipping_<?= $row['product_department_id'] ?>'>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}

	function merchantAccountCustomFields() {
		$customFields = CustomField::getCustomFields("merchant_accounts");
		ob_start();
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customField->addColumnControl("form_line_classes", "merchant-account");
			echo $customField->getControl(array("basic_form_line" => true));
		}
		$html = ob_get_clean();
		echo str_replace("custom_field_", "merchant_accounts_custom_field_", $html);
	}

	function coreClearAvsSettings() {
		$avsApprovals = array(
			array("code" => 'zip_match', "description" => "Postal code matches but address does not."),
			array("code" => 'address_match', "description" => "Address matches but postal code does not."),
			array("code" => 'no_match', "description" => "Neither address nor postal code match."),
			array("code" => 'retry', "description" => "Address verification returned an inconclusive response. Try again."),
			array("code" => 'not_supported', "description" => "Address verification is not supported by the bank."));
		?>

        <h2>Address Verification Settings for coreCLEAR</h2>
        <p>You may choose whether to allow transactions to be processed in coreCLEAR for each of the following AVS Responses.<br>
            Full address and postal code matches will always be processed.</p>
		<?php
		foreach ($avsApprovals as $thisAvsApproval) {
			?>
            <div class='basic-form-line'>
                <input name="coreclear_avs_approval_<?= $thisAvsApproval['code'] ?>" id="coreclear_avs_approval_<?= $thisAvsApproval['code'] ?>" type="checkbox" value="1">
                <label class="checkbox-label" for='coreclear_avs_approval_<?= $thisAvsApproval['code'] ?>'><?= $thisAvsApproval['description'] ?></label>
            </div>
			<?php
		}
		?><p>Some banks store PO Box addresses in such a way that the street address will never match.<br>
            This setting allows transactions with PO Box addresses to be processed if only the postal code matches.</p>
        <div class='basic-form-line'>
            <input name="coreclear_approve_avs_zip_match_for_po_boxes" type="hidden">
            <input name="coreclear_approve_avs_zip_match_for_po_boxes" id="coreclear_approve_avs_zip_match_for_po_boxes" type="checkbox" value="1">
            <label class="checkbox-label" for='coreclear_approve_avs_zip_match_for_po_boxes'>Allow "Postal code matches but address does not" for PO Box addresses.</label>
        </div>
		<?php
	}

	function getActiveCampaignUrl() {
		echo sprintf("https://%s.api-us1.com", str_replace("_", "-", $GLOBALS['gClientRow']['client_code']));
	}

	function checkNoFraudSetup() {
		if (!NoFraud::isSetup()) {
			?>
            <div id="_setup_nofraud">
                <button id="setup_nofraud">Set Up NoFraud</button>
            </div>
		<?php } else { ?>
            <span class="green-text">NoFraud is properly set up.</span>
		<?php } ?>
        <div class='clear-div'></div>
		<?php
	}

    function checkListrakSetup() {
        if (in_array($GLOBALS['gClientRow']['client_code'], explode(",", getPreference("ENABLE_LISTRAK_CLIENTS")))) {
            if (!Listrak::isSetup()) {
                ?>
                <div id="_setup_listrak">
                    <button id="setup_listrak">Set Up Listrak</button>
                </div>
            <?php } else { ?>
                <span class="green-text">Listrak is properly set up.</span>
            <?php } ?>
            <div class='clear-div'></div>
            <?php
        }
    }
	function checkFlpSetup() {
		$result = FirearmsLegalProtection::needsSetup();
		if ($result) {
			?>
            <div id="_setup_flp">
                <button id="setup_flp"><?= $result ?></button>
            </div>
		<?php } else { ?>
            <span class="green-text">FLP product is set up and ready for sale.</span>
		<?php } ?>
        <div class='clear-div'></div>
		<?php
	}

	function checkCredovaSetup() {
		$credovaCredentials = getCredovaCredentials();
		if(!empty($credovaCredentials)) {
			$credova = new CredovaClient();
			if (!$credova->testCredentials()) {
				?>
                <span class="red-text">Credova credentials are invalid.</span>
				<?php
			} else {
				if (!CredovaClient::isSetup()) {
					?>
                    <div id="_setup_credova">
                        <button id="setup_credova">Set Up Credova</button>
                    </div>
				<?php } else { ?>
                    <span class="green-text">Credova payment method is set up and ready for use.</span>
				<?php }
			}
		} else { ?>
            <span>Credova Credentials must be saved before checking setup.</span>
		<?php } ?>
        <div class='clear-div'></div>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$gunDealsPreferences = Page::getClientPagePreferences("GUNDEALSFEED");
		foreach ($gunDealsPreferences as $fieldName => $fieldValue) {
			$returnArray['gundeals_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}

		$gunSaleFinderPreferences = Page::getClientPagePreferences("GUNSALEFINDERFEED");
		foreach ($gunSaleFinderPreferences as $fieldName => $fieldValue) {
			$returnArray['gun_sale_finder_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}

		$ammoSeekPreferences = Page::getClientPagePreferences("AMMOSEEKFEED");
		foreach ($ammoSeekPreferences as $fieldName => $fieldValue) {
			$returnArray['ammo_seek_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}
		$highCapDealsPreferences = Page::getClientPagePreferences("HIGHCAPDEALSFEED");
		foreach ($highCapDealsPreferences as $fieldName => $fieldValue) {
			$returnArray['high_cap_deals_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}
		$coreClearAvsSettings = Page::getClientPagePreferences("CORECLEAR_AVS_APPROVALS");
		if (empty($coreClearAvsSettings)) {
			$coreClearAvsSettings = array('zip_match' => 0, 'address_match' => 0, 'no_match' => 0, 'retry' => 0, 'not_supported' => 0);
		}
		foreach ($coreClearAvsSettings as $fieldName => $fieldValue) {
			$returnArray['coreclear_avs_approval_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}

		$productUpdateFields = ProductCatalog::getProductUpdateFields();
		$resultSet = executeQuery("select * from client_product_update_settings where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productUpdateFields[strtolower($row['product_update_field_code'])]['update_setting'] = $row['update_setting'];
		}
		foreach ($productUpdateFields as $productUpdateFieldCode => $productUpdateFieldInfo) {
			if ($productUpdateFieldInfo['internal_use_only']) {
				continue;
			}
			$returnArray['product_update_field_' . strtolower($productUpdateFieldInfo['product_update_field_code'])] = array("data_value" => $productUpdateFieldInfo['update_setting'], "crc_value" => getCrcValue($productUpdateFieldInfo['update_setting']));
		}

		$resultSet = executeQuery("select * from loyalty_programs where client_id = ?", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			$returnArray['loyalty_program_wrapper'] = array("data_value" => "<p class='red-text'>Loyalty program already exists</p>");
		}

		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_coreware_defaults", $postParameters);
		$responseArray = json_decode($response, true);
		$defaults = (is_array($responseArray) && array_key_exists("defaults", $responseArray) ? $responseArray['defaults'] : array());

		if (!array_key_exists("emails", $defaults)) {
			$defaults['emails'] = array();
		}
		if (!array_key_exists("fragments", $defaults)) {
			$defaults['fragments'] = array();
		}
		if (!array_key_exists("notifications", $defaults)) {
			$defaults['notifications'] = array();
		}

		ob_start();
		echo "<p>Check the emails you wish to create in the system. Once the email exists in the system, you can go to Contacts->Email->Emails to make changes to it. Make sure the Primary Domain Name is set in the Pages tab.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='emails_filter_text' placeholder='Filter Emails'></p>";
		foreach ($defaults['emails'] as $thisEmail) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", $thisEmail['email_code']);
			if (empty($emailId)) {
				?>
                <div class='default-section filter-section'>
                    <div class='basic-form-line'>
                        <label for='default_email_<?= $thisEmail['email_code'] ?>'><?= htmlText($thisEmail['description']) ?></label>
                        <select name='default_email_<?= $thisEmail['email_code'] ?>' id='default_email_<?= $thisEmail['email_code'] ?>'>
                            <option value=''>Use System Default</option>
                            <option value='1'>Customize</option>
                        </select>
                    </div>
					<?= makeHtml($thisEmail['detailed_description']) ?>
                </div>
				<?php
			} else {
				executeQuery("update emails set detailed_description = ? where email_id = ?", $thisEmail['detailed_description'], $emailId);
				?>
                <div class='default-section filter-section'>
                    <p class='highlighted-text'><?= htmlText($thisEmail['description']) ?></p>
					<?php if (canAccessPageCode("EMAILMAINT")) { ?>
                        <p>Email already created. Click <a href='/emails?url_page=show&primary_id=<?= $emailId ?>' target='_blank'>here</a> to edit it.</p>
					<?php } else { ?>
                        <p>Email already created. Go to Contacts->Email->Emails to make changes.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_emails'] = array("data_value" => ob_get_clean());

		ob_start();
		echo "<p>While any fragment can be customized, generally, it is best to use the system default for fragments. Customizing a fragment may cause delay of implementation of new CoreFORCE features, so DO NOT customize a fragment unless there is a very clear and specific reason to do so. Make sure the Primary Domain Name is set in the Pages tab if any fragments are customized. Once you have selected that a fragment is to be customized here, you can make changes to it at Website->Fragments.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='fragment_filter_text' placeholder='Filter Fragments'></p>";
		foreach ($defaults['fragments'] as $thisFragment) {
			$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $thisFragment['fragment_code']);
			if (empty($fragmentId)) {
				?>
                <div class='default-section filter-section'>
                    <div class='basic-form-line'>
                        <label for='default_fragment_<?= $thisFragment['fragment_code'] ?>'><?= htmlText($thisFragment['description']) ?></label>
                        <select name='default_fragment_<?= $thisFragment['fragment_code'] ?>' id='default_fragment_<?= $thisFragment['fragment_code'] ?>'>
                            <option value=''>Use System Default</option>
                            <option value='1'>Customize</option>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?= makeHtml($thisFragment['detailed_description']) ?>
                </div>
				<?php
			} else {
				executeQuery("update fragments set detailed_description = ? where fragment_id = ?", $thisFragment['detailed_description'], $fragmentId);
				?>
                <div class='default-section filter-section'>
                    <p class='highlighted-text'><?= htmlText($thisFragment['description']) ?></p>
					<?php if (canAccessPageCode("FRAGMENTMAINT")) { ?>
                        <p>Fragment already created. Click <a href='/fragments?url_page=show&primary_id=<?= $fragmentId ?>' target='_blank'>here</a> to edit it.</p>
					<?php } else { ?>
                        <p>Fragment already created. Go to Website->Fragments to make changes.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_fragments'] = array("data_value" => ob_get_clean());

		ob_start();
		echo "<p>For each notification, add a comma separated list of email addresses you wish to be included in that notification.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='notification_filter_text' placeholder='Filter Notifications'></p>";
		foreach ($defaults['notifications'] as $thisNotification) {
			$notificationId = getFieldFromId("notification_id", "notifications", "notification_code", $thisNotification['notification_code']);
			if (empty($notificationId)) {
				?>
                <div class='default-section filter-section'>
                    <div class='basic-form-line'>
                        <label><?= $thisNotification['description'] ?></label>
                        <input type='text' size="100" name='default_notification_<?= $thisNotification['notification_code'] ?>' id='default_notification_<?= $thisNotification['notification_code'] ?>'>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?= makeHtml($thisNotification['detailed_description']) ?>
                </div>
				<?php
			} else {
				?>
                <div class='default-section filter-section'>
                    <p class='highlighted-text'><?= htmlText($thisNotification['description']) ?>
						<?php if (canAccessPageCode("NOTIFICATIONMAINT")) { ?>
                    <p>Notification already created. Click <a href='/notification-maintenance?url_page=show&primary_id=<?= $notificationId ?>' target='_blank'>here</a> to edit it.</p>
					<?php } else { ?>
                        <p>Notification already created. Go to System->Notifications to make changes.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_notifications'] = array("data_value" => ob_get_clean());

		$productResultHtmlOptions = "";
		$fragmentId = getPreference("PRODUCT_RESULT_HTML_FRAGMENT_ID");
		$resultSet = executeQuery("select * from fragments where fragment_type_id in (select fragment_type_id from fragment_types where fragment_type_code = 'PRODUCT_RESULT_HTML') and " .
			"(client_id = ? or client_id = ?) order by sort_order,description", $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productResultHtmlOptions .= "<p" . ($fragmentId == $row['fragment_id'] ? " class='selected'" : "") . "><span data-fragment_id='" . $row['fragment_id'] . "' class='product-result-html-options'><span class='far fa-square'></span><span class='far fa-check-square'></span>" . htmlText($row['description']) . ($row['fragment_code'] == "RETAIL_STORE_CATALOG_DETAIL" ? " (<strong>System Default</strong>)" : "") .
				"</span>" . (empty($row['image_id']) ? "" : " - <a href='/getimage.php?id=" . $row['image_id'] . "' class='pretty-photo'>View Thumbnail</a>") . "</p>";
		}
		$returnArray['product_result_html_options'] = array("data_value" => $productResultHtmlOptions);
		$returnArray['product_result_html_fragment_id'] = array("data_value" => $fragmentId, "crc_value" => getCrcValue($fragmentId));

		$productDetailHtmlOptions = "";
		$fragmentId = getPreference("PRODUCT_DETAIL_HTML_FRAGMENT_ID");
		$resultSet = executeQuery("select * from fragments where fragment_type_id in (select fragment_type_id from fragment_types where fragment_type_code = 'PRODUCT_DETAIL_HTML') and " .
			"(client_id = ? or client_id = ?) order by sort_order,description", $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productDetailHtmlOptions .= "<p" . ($fragmentId == $row['fragment_id'] ? " class='selected'" : "") . "><span data-fragment_id='" . $row['fragment_id'] . "' class='product-detail-html-options'><span class='far fa-square'></span><span class='far fa-check-square'></span>" . htmlText($row['description']) . ($row['fragment_code'] == "RETAIL_STORE_PRODUCT_DETAIL" ? " (<strong>System Default</strong>)" : "") .
				"</span>" . (empty($row['image_id']) ? "" : " - <a href='/getimage.php?id=" . $row['image_id'] . "' class='pretty-photo'>View Thumbnail</a>") . "</p>";
		}
		$returnArray['product_detail_html_options'] = array("data_value" => $productDetailHtmlOptions);
		$returnArray['product_detail_html_fragment_id'] = array("data_value" => $fragmentId, "crc_value" => getCrcValue($fragmentId));

		$returnArray['domain_name'] = array("data_value" => getDomainName());
		$managementTemplateId = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT", "client_id = " . $GLOBALS['gDefaultClientId']);
		ob_start();
		?>
        <table class="grid-table" id="page_list_table">
            <tr>
                <th>Page</th>
                <th>Create</th>
            </tr>
			<?php
			foreach ($this->iPages as $index => $thisPage) {
				$pageId = getFieldFromId("page_id", "pages", "script_filename", $thisPage['script_filename'], "template_id <> ?", $managementTemplateId);
				if (empty($pageId)) {
					?>
                    <tr>
                        <td><?= $thisPage['description'] ?></td>
						<?php if ($thisPage['core_page']) { ?>
                            <input type="hidden" id='create_page_<?= $index ?>' name='create_page_<?= $index ?>' value='<?= $thisPage['page_code'] ?>'>
						<?php } ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" class="create-page" <?= ($thisPage['core_page'] ? "" : "id='create_page_" . $index . "' name='create_page_" . $index . "'") ?> value="<?= $thisPage['page_code'] ?>" <?= ($thisPage['core_page'] ? "disabled='disabled'" : "") ?> checked="checked"></td>
                    </tr>
					<?php
				} else {
					$pageAccessId = getFieldFromId("page_access_id", "page_access", "page_id", $pageId);
					if (empty($pageAccessId)) {
						switch ($thisPage['page_access']) {
							case "users":
								executeQuery("insert into page_access (page_id,all_user_access,permission_level) values (?,1,3)", $pageId);
								break;
							case "public":
								executeQuery("insert into page_access (page_id,public_access,permission_level) values (?,1,3)", $pageId);
								break;
						}
					}
					?>
                    <tr>
                        <td><?= htmlText($thisPage['description']) ?></td>
                        <td class="align-center">Already Exists</td>
                    </tr>
					<?php
				}
			}
			?>
        </table>
		<?php
		$returnArray['page_list'] = array("data_value" => ob_get_clean());
		ob_start();
		$resultSet = executeQuery("select count(*) from products where client_id = ? and inactive = 0 and custom_product = 0", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
			?>
            <p><?= (empty($count) ? "No" : $count) ?> product<?= ($count == 1 ? "" : "s") ?> found</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from products where client_id = ? and inactive = 0 and custom_product = 0 and product_id not in (select product_id from product_category_links)", $GLOBALS['gClientId']);
		$count = 0;
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		?>
        <p><?= (empty($count) ? "No" : $count) ?> product<?= ($count == 1 ? "" : "s") ?> found that are not in a category</p>
        <p class="extra-bottom"><a href="/productmaintenance.php" target="_blank">Go to product maintenance</a></p>
		<?php
		$resultSet = executeQuery("select count(*) from product_categories where client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
			?>
            <p><?= (empty($count) ? "No" : $count) ?> product categor<?= ($count == 1 ? "y" : "ies") ?> found</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from product_categories where client_id = ? and inactive = 0 and product_category_id not in (select product_category_id from product_category_group_links)", $GLOBALS['gClientId']);
		$count = 0;
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		?>
        <p><?= (empty($count) ? "No" : $count) ?> product categor<?= ($count == 1 ? "y" : "ies") ?> found that are not in a group</p>
        <p class="extra-bottom"><a href="/product-categories" target="_blank">Go to product categories maintenance</a></p>
		<?php
		$resultSet = executeQuery("select count(*) from product_category_groups where client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
			?>
            <p><?= (empty($count) ? "No" : $count) ?> product category group<?= ($count == 1 ? "" : "s") ?> found</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from product_category_groups where client_id = ? and inactive = 0 and product_category_group_id not in (select product_category_group_id from product_category_group_departments)", $GLOBALS['gClientId']);
		$count = 0;
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		?>
        <p><?= (empty($count) ? "No" : $count) ?> product category group<?= ($count == 1 ? "" : "s") ?> found that are not in a department</p>
        <p class="extra-bottom"><a href="/product-category-group-maintenance" target="_blank">Go to product category group maintenance</a></p>
		<?php
		$resultSet = executeQuery("select count(*) from product_departments where client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
			?>
            <p><?= (empty($count) ? "No" : $count) ?> product department<?= ($count == 1 ? "" : "s") ?> found</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from product_departments where client_id = ? and inactive = 0 and product_department_id not in (select product_department_id from product_category_group_departments)", $GLOBALS['gClientId']);
		$count = 0;
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		?>
        <p><?= (empty($count) ? "No" : $count) ?> product department<?= ($count == 1 ? "" : "s") ?> found that have no category groups in them</p>
        <p class="extra-bottom"><a href="/product-department-maintenance" target="_blank">Go to product department maintenance</a></p>
		<?php
		$returnArray['taxonomy_stats'] = array("data_value" => ob_get_clean());

		$integrationPreferenceIds = array();
		$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", "INTEGRATION_SETTINGS");
		$resultSet = executeQuery("select * from preferences join preference_group_links using (preference_id) where inactive = 0" .
			" and preference_group_id = ? and client_setable = 1 order by sequence_number,description", $preferenceGroupId);
		while ($row = getNextRow($resultSet)) {
			$integrationPreferenceIds[] = $row['preference_id'];
			$fieldValue = getFieldFromId("preference_value", "client_preferences", "preference_id", $row['preference_id'],
				"preference_qualifier is null and client_id = ?", $GLOBALS['gClientId']);
			if ($row['data_type'] == "tinyint") {
				$fieldValue = (empty($fieldValue) ? "0" : "1");
			}
			$returnArray[strtolower($row['preference_code'])] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}

		$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", "RETAIL_STORE");
		ob_start();
		echo "<p><input tabindex='10' class='filter-text' type='text' id='preference_filter_text' placeholder='Filter Preferences'></p>";
		$resultSet = executeQuery("select * from preferences join preference_group_links using (preference_id) where inactive = 0" .
			" and preference_group_id = ? and client_setable = 1 order by sequence_number,description", $preferenceGroupId);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['preference_id'], $integrationPreferenceIds)) {
				continue;
			}
			?>
            <div class="basic-form-line preference-field filter-section" id="_preference_id_<?= $row['preference_id'] ?>_row">
				<?php if (!empty($row['detailed_description'])) { ?>
                    <div class="preference-info">
						<?= makeHtml($row['detailed_description']) ?>
                    </div>
				<?php } ?>
                <label><?= htmlText($row['description']) ?></label>
				<?php
				$controlElement = "";
				$validationClasses = array();
				$classes = array();
				if (strlen($row['minimum_value']) > 0) {
					$validationClasses[] = "min[" . $row['minimum_value'] . "]";
				}
				if (strlen($row['maximum_value']) > 0) {
					$validationClasses[] = "max[" . $row['maximum_value'] . "]";
				}
				$classes[] = "field-text";
				switch ($row['data_type']) {
					case "select":
						$controlElement = "<select tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'>\n";
						$controlElement .= "<option value=''>[None]</option>\n";
						if (array_key_exists("choices", $row)) {
							$choices = getContentLines($row['choices']);
							foreach ($choices as $index => $choiceValue) {
								$controlElement .= "<option value='" . htmlText($choiceValue) . "'>" . htmlText($choiceValue) . "</option>\n";
							}
						}
						$controlElement .= "</select>\n";
						break;
					case "bigint":
					case "int":
						$validationClasses[] = "custom[integer]";
						$classes[] = "align-right";
						$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' size='10' maxlength='10' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
						break;
					case "decimal":
						$validationClasses[] = "custom[number]";
						$classes[] = "align-right";
						$controlElement = "<input tabindex='10' data-decimal-places='2' class='field-text %classString%' type='text' size='10' maxlength='12' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
						break;
					case "tinyint":
						$controlElement = "<select tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'>\n";
						$controlElement .= "<option value=''>[Use Default]</option>\n";
						$controlElement .= "<option value='true'>True</option>\n";
						$controlElement .= "<option value='false'>False</option>\n";
						$controlElement .= "</select>\n";
						break;
					case "varchar":
						$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' size='40' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
						break;
					case "text":
						$controlElement = "<textarea tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'></textarea>";
						break;
				}
				$validationClassString = implode(",", $validationClasses);
				if (!empty($validationClassString) && !$row['readonly']) {
					$validationClassString = "validate[" . $validationClassString . "]";
					$classes[] = $validationClassString;
				}
				$classString = implode(" ", $classes);
				$controlElement = str_replace("%classString%", $classString, $controlElement);
				echo $controlElement;
				?>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                <div class='clear-div'></div>
            </div>
			<?php
		}
		$returnArray['tab_settings'] = array("data_value" => ob_get_clean());

		$resultSet = executeQuery("select * from email_credentials where client_id = ? and email_credential_code = 'DEFAULT'", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['setup_email_credentials'] = array("data_value" => "1");
			foreach ($row as $fieldName => $fieldValue) {
				if (in_array($fieldName, array("email_credential_id", "email_credential_code", "version"))) {
					continue;
				}
				$returnArray['email_credentials_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			}
		}

		$returnArray['setup_merchant_account'] = array("data_value" => "0");
		$resultSet = executeQuery("select * from merchant_accounts where client_id = ? and merchant_account_code = 'DEFAULT'", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			foreach ($row as $fieldName => $fieldValue) {
				if (!in_array($fieldName, array("merchant_account_id", "merchant_service_id", "account_login", "account_key", "merchant_identifier", "link_url"))) {
					continue;
				}
				$returnArray['merchant_accounts_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			}
			$customFields = CustomField::getCustomFields("merchant_accounts");
			foreach ($customFields as $thisCustomField) {
				$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
				$customFieldData = $customField->getRecord($row['merchant_account_id']);
				$returnArray['merchant_accounts_custom_field_id_' . $thisCustomField['custom_field_id']] = $customFieldData['custom_field_id_' . $thisCustomField['custom_field_id']];
			}
		}

		$resultSet = executeQuery("select * from pricing_structures where client_id = ? and pricing_structure_code = 'DEFAULT'", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['setup_pricing_structure'] = array("data_value" => "1");
			foreach ($row as $fieldName => $fieldValue) {
				if (!in_array($fieldName, array("percentage", "price_calculation_type_id"))) {
					continue;
				}
				$returnArray['pricing_structure_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			}
		}

		$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", "RETAIL_STORE");
		$resultSet = executeQuery("select * from preferences join preference_group_links using (preference_id) where inactive = 0" .
			" and preference_group_id = ? and client_setable = 1 order by sequence_number,description", $preferenceGroupId);
		while ($row = getNextRow($resultSet)) {
			$fieldValue = getFieldFromId("preference_value", "client_preferences", "preference_id", $row['preference_id'],
				"preference_qualifier is null and client_id = ?", $GLOBALS['gClientId']);
			$returnArray['preference_value_' . $row['preference_id']] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#taxjar_api_token").trigger("change");
                buildFeeds();
                $("#merchant_accounts_merchant_service_id").trigger("change");
                $(".merchant-account").addClass("hidden");

                setTimeout(function () {
                    if ($("#setup_email_credentials").prop("checked")) {
                        $(".email-credential").removeClass("hidden");
                    } else {
                        $(".email-credential").addClass("hidden");
                    }
                    if (empty($("#merchant_accounts_merchant_account_id").val())) {
                        $("#test_merchant").addClass("hidden");
                    } else {
                        $("label[for='setup_merchant_account'].checkbox-label").text("Default Merchant Account is set up. Click here to make changes");
                    }
                    if ($("#setup_pricing_structure").prop("checked")) {
                        $(".pricing-structure").removeClass("hidden");
                    } else {
                        $(".pricing-structure").addClass("hidden");
                    }
                    if (empty($("#twilio_account_sid").val())) {
                        $("#send_text_message_wrapper").addClass("hidden");
                    } else {
                        $("#send_text_message_wrapper").removeClass("hidden");
                    }
                }, 500)
            }

            function buildFeeds() {
                $("#feed_pages").html("");
                if (!empty($("#domain_name").val())) {
                    $("#feed_pages").append("<p><input type='text' size='60' readonly='readonly' value='" + ($("#domain_name").val().substring(0, 4) == "http" ? "" : "https://") + $("#domain_name").val() + "/gundealsfeed.php'></p>");
                    $("#feed_pages").append("<p><input type='text' size='60' readonly='readonly' value='" + ($("#domain_name").val().substring(0, 4) == "http" ? "" : "https://") + $("#domain_name").val() + "/ammoseekfeed.php'></p>");
                    $("#feed_pages").append("<p><input type='text' size='60' readonly='readonly' value='" + ($("#domain_name").val().substring(0, 4) == "http" ? "" : "https://") + $("#domain_name").val() + "/wikiarmsfeed.php'></p>");
                    $("#feed_pages").append("<p><input type='text' size='60' readonly='readonly' value='" + ($("#domain_name").val().substring(0, 4) == "http" ? "" : "https://") + $("#domain_name").val() + "/googleproductfeed.php'></p>");
                    $("#feed_pages").append("<p><input type='text' size='60' readonly='readonly' value='" + ($("#domain_name").val().substring(0, 4) == "http" ? "" : "https://") + $("#domain_name").val() + "/highcapdealsfeed.php'></p>");
                }
            }

            function loadFieldLabels() {
                if (!$("#setup_merchant_account").prop("checked")) {
                    return;
                }
                $("#tab_merchant").find("div.basic-form-line").each(function () {
                    if (empty($(this).data("default_label"))) {
                        if ($(this).find("input[type=checkbox]").length > 0) {
                            $(this).find("label").not(".checkbox-label").remove();
                            $(this).data("default_label", $(this).find("label.checkbox-label").html());
                        } else {
                            $(this).data("default_label", $(this).find("label").html());
                        }
                    }
                });
                if (empty($("#merchant_accounts_merchant_service_id").val())) {
                    $("#tab_merchant").find("div.basic-form-line").each(function () {
                        $(this).removeClass("hidden").find("label").html($(this).data("default_label"));
                    });
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_merchant_account_field_labels&merchant_service_id=" + $("#merchant_accounts_merchant_service_id").val(), function (returnArray) {
                        if ("field_labels" in returnArray) {
                            $("#tab_merchant").find("div.basic-form-line").each(function () {
                                let labeledFields = ['_merchant_accounts_account_login_row', '_merchant_accounts_account_key_row','_merchant_accounts_merchant_identifier_row','_merchant_accounts_link_url_row'];
                                if(!labeledFields.includes(this.id) && !this.id.startsWith('_merchant_accounts_custom_field')) {
                                    return;
                                }
                                const columnName = $(this).data("column_name");
                                if (columnName in returnArray['field_labels']) {
                                    if (empty(returnArray['field_labels'][columnName])) {
                                        $(this).addClass("hidden");
                                    } else {
                                        $(this).removeClass("hidden");
                                        $(this).find("label").html(returnArray['field_labels'][columnName]['form_label']);
                                        if (!empty(returnArray['field_labels'][columnName]['not_null'])) {
                                            $(this).find("label").append("<span class='required-tag fa fa-asterisk'></span>");
                                            $(this).find("input, textarea").attr("class", "validate[required]");
                                        } else {
                                            $(this).find("label").find("span.required-tag").remove();
                                            $(this).find("input, textarea").attr("class", "");
                                        }
                                    }
                                } else {
                                    $(this).addClass("hidden");
                                    $(this).find("label").find("span.required-tag").remove();
                                    $(this).find("input, textarea").attr("class", "");
                                }
                            });
                        }
                    });
                }
                if ($("#merchant_accounts_merchant_service_id option:selected").text() == "coreCLEAR") {
                    $("#coreclear_avs_settings").removeClass("hidden");
                } else {
                    $("#coreclear_avs_settings").addClass("hidden");
                }
            }

        </script>
		<?php
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gClientId'];
	}

	function internalCSS() {
		?>
        <style>
            #tab_gunbroker ul {
                list-style-type: disc;
                margin-left: 40px;
            }
            #tab_gunbroker ul li {
                padding-bottom: 4px;
            }
            #infusionsoft_authorized {
                border: none;
                padding: 0;
                width: 100%;
                color: rgb(0, 150, 0);
            }
            #highlevel_authorized {
                border: none;
                padding: 0;
                width: 100%;
                color: rgb(0, 150, 0);
            }
            .basic-form-line.product-update-field label {
                display: inline-block;
                width: 180px;
                font-size: .9rem;
                text-align: right;
                padding-right: 10px;
            }
            .taxjar-nexus {
                margin: 5px 20px;
                font-size: 1rem;
                font-weight: 900;
                color: rgb(0, 100, 0);
            }
            #taxjar_validation {
                color: rgb(0, 150, 0);
                margin-bottom: 40px;
            }

            .integration-logo {
                width: 300px;
                max-width: 100%;
            }

            #loyalty_program_wrapper {
                margin-top: 40px;
            }

            .default-section ul {
                margin-bottom: 10px;
                list-style-type: disc;
                margin-left: 30px;
            }

            .default-section {
                padding: 20px;
                margin: 20px;
                border-bottom: 1px solid rgb(200, 200, 200);
            }

            #product_result_html_options {
                margin-top: 20px;
            }

            #product_result_html_options .far {
                margin-right: 10px;
            }

            #product_result_html_options p {
                margin-left: 40px;
                padding: 10px 20px;
            }

            .product-result-html-options {
                cursor: pointer;
                padding: 10px;
            }

            #product_result_html_options p .fa-check-square {
                display: none;
            }

            #product_result_html_options p.selected .fa-check-square {
                display: inline;
            }

            #product_result_html_options p.selected .fa-square {
                display: none;
            }

            .product-result-html-options:hover {
                color: rgb(150, 150, 150);
            }

            #product_detail_html_options {
                padding: 10px;
                margin-top: 20px;
            }

            #product_detail_html_options .far {
                margin-right: 10px;
            }

            #product_detail_html_options p {
                margin-left: 40px;
                padding: 10px 20px;
            }

            .product-detail-html-options {
                cursor: pointer;
            }

            #product_detail_html_options p .fa-check-square {
                display: none;
            }

            #product_detail_html_options p.selected .fa-check-square {
                display: inline;
            }

            #product_detail_html_options p.selected .fa-square {
                display: none;
            }

            .product-detail-html-options:hover {
                color: rgb(150, 150, 150);
            }

            #page_list_table {
                margin-bottom: 20px;
            }

            .extra-bottom {
                margin-bottom: 20px;
            }

            #using_ffl_wrapper {
                margin-bottom: 40px;
            }

            .preference-info p {
                font-size: .8rem;
                color: rgb(100, 100, 100);
                max-width: 600px;
            }

            .preference-field {
                margin-bottom: 40px;
                border-bottom: 1px solid rgb(200, 200, 200);
                padding-bottom: 20px;
            }

            #tab_gift_cards ul {
                list-style: disc;
                margin-left: 40px;
                margin-bottom: 40px;
            }

            #tab_gift_cards ul li {
                padding-bottom: 5px;
                font-size: 1rem;
            }


        </style>
		<?php
	}

	function onLoadJavascript() {
		$valuesArray = Page::getPagePreferences();
		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
		$giftCardProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "GIFT_CARD");
		$giftCardExists = false;
		if (!empty($giftCardProductTypeId)) {
			$giftCardProductId = getFieldFromId("product_id", "products", "product_type_id", $giftCardProductTypeId);
			$giftCardPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "GIFT_CARD");
			$giftCardPaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "GIFT_CARD");
			if (!empty($giftCardProductId) && !empty($giftCardPaymentMethodTypeId) && !empty($giftCardPaymentMethodId)) {
				$giftCardExists = true;
			}
		}
		?>
        <script>
            $(document).on("change", "#twilio_account_sid,#twilio_auth_token,#twilio_from_number", function () {
                $("#send_text_message_wrapper").addClass("hidden");
            });
            $(document).on("click", "#send_text_message", function () {
                $("#twilio_error_message").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_text_message", function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#twilio_error_message").html(returnArray['error_message']);
                    }
                });
                return false;
            });
            $("#taxjar_api_token").change(function () {
                if (empty($(this).val())) {
                    $("#_taxjar_api_reporting_row").addClass("hidden");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_taxjar_api_token&taxjar_api_token=" + $(this).val(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#taxjar_validation").html("");
                            $("#taxjar_nexus_data").html("");
                            $("#_taxjar_api_reporting_row").addClass("hidden");
                        } else {
                            $("#taxjar_validation").html(returnArray['taxjar_validation']);
                            $("#taxjar_nexus_data").html(returnArray['taxjar_nexus_data']);
                            $("#_taxjar_api_reporting_row").removeClass("hidden");
                        }
                    });
                }
            })
            $(document).on("keyup", ".filter-text", function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $(this).closest("div").find(".filter-section").removeClass("hidden");
                } else {
                    $(this).closest("div").find(".filter-section").each(function () {
                        const description = $(this).find("p").text().toLowerCase() + " " + $(this).find("label").text().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
			<?php if (!empty($valuesArray['hide_using_ffl'])) { ?>
            $("#using_ffl_wrapper").addClass("hidden");
			<?php } ?>
			<?php if (empty($fflRequiredProductTagId)) { ?>
            $(".ffl-store").addClass("hidden");
			<?php } else { ?>
            $("#using_ffl_button").closest("p").html("FFL is setup on this store").addClass("red-text");
            $("#_hide_using_ffl_row").addClass("hidden");
			<?php } ?>
            $("#template_id").change(function () {
                if (empty($(this).val())) {
                    $("#page_list").addClass("hidden");
                } else {
                    $("#page_list").removeClass("hidden");
                }
            });
            $(document).on("click", ".product-detail-html-options", function () {
                $(".product-detail-html-options").closest("p").removeClass("selected");
                $(this).closest("p").addClass("selected");
                $("#product_detail_html_fragment_id").val($(this).data("fragment_id"));
            });
            $(document).on("click", ".product-result-html-options", function () {
                $(".product-result-html-options").closest("p").removeClass("selected");
                $(this).closest("p").addClass("selected");
                $("#product_result_html_fragment_id").val($(this).data("fragment_id"));
            });
            $("#domain_name").change(function () {
                buildFeeds();
            });
            $("#using_ffl_button").click(function () {
                $("#using_ffl").val("1");
                $(this).closest("p").html("FFL will be setup when Orders Setup is saved").addClass("red-text");
                $(".ffl-store").removeClass("hidden");
                return false;
            });
            $("#import_defaults_button").click(function () {
                $("#import_defaults").val("1");
                $(this).closest("p").html("Default Coreware departments, category groups, categories and manufacturers will be imported").addClass("red-text");
                return false;
            });
            $("#import_ffl_dealers_button").click(function () {
                $("#import_ffl_dealers").val("1");
                $(this).closest("p").html("Default Coreware FFL Dealers will be imported").addClass("red-text");
                return false;
            });
            $("#create_gift_cards_button").click(function () {
                $("#create_gift_cards").val("1");
                $(this).closest("p").html("Gift Card Details will be created when CoreFORCE Setup is saved").addClass("red-text");
                return false;
            });

            $("#create_loyalty_program_button").click(function () {
                $("#create_loyalty_program").val("1");
                $(this).closest("p").html("A basic loyalty program will be created when CoreFORCE Setup is saved").addClass("red-text");
                return false;
            });

            $("#setup_email_credentials").click(function () {
                if ($(this).prop("checked")) {
                    $(".email-credential").removeClass("hidden");
                } else {
                    $(".email-credential").addClass("hidden");
                }
            });
            $("#setup_merchant_account").click(function () {
                if ($(this).prop("checked")) {
                    $(".merchant-account").removeClass("hidden");
                    loadFieldLabels();
                } else {
                    $(".merchant-account").addClass("hidden");
                }
            });
            $("#setup_pricing_structure").click(function () {
                if ($(this).prop("checked")) {
                    $(".pricing-structure").removeClass("hidden");
                } else {
                    $(".pricing-structure").addClass("hidden");
                }
            });
            $("#location_id").change(function () {
                $("#product_distributor_information").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_distributor_info&location_id=" + $(this).val(), function (returnArray) {
                        if ("product_distributor_information" in returnArray) {
                            $("#product_distributor_information").html(returnArray['product_distributor_information']);
                        }
                    });
                }
            });
            $("#setup_nofraud").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_nofraud", function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_setup_nofraud").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_setup_nofraud").html("Fields set up successfully").addClass("green-text");
                    }
                });
                return false;
            });
            $("#setup_listrak").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_listrak", function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_setup_listrak").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_setup_listrak").html("Fields set up successfully").addClass("green-text");
                    }
                });
                return false;
            });
            $("#setup_flp").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_flp", function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_setup_flp").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_setup_flp").html("FLP set up successfully").addClass("green-text");
                    }
                });
                return false;
            });
            $("#setup_flp_affiliate").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_flp",{flp_affiliate_link: $("#flp_affiliate_link").val()}, function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_setup_flp").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_setup_flp").html("FLP affiliate link added successfully").addClass("green-text");
                    }
                });
                return false;
            });
            $("#setup_credova").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_credova", function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_setup_credova").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_setup_credova").html("Credova set up successfully").addClass("green-text");
                    }
                });
                return false;
            });

            $(document).on("click", "#test_distributor", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_distributor&location_id=" + $("#location_id").val(), function (returnArray) {
                    if ("test_distributor_results" in returnArray) {
                        $("#test_distributor_results").html(returnArray['test_distributor_results']).addClass(returnArray['class']);
                    }
                });
                return false;
            });
            $("#test_merchant").click(function () {
                if (empty($("#merchant_accounts_merchant_account_id").val())) {
                    displayErrorMessage("Save to create the merchant account first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_merchant&merchant_account_id=" + $("#merchant_accounts_merchant_account_id").val(), function (returnArray) {
                        if ("test_merchant_results" in returnArray) {
                            $("#test_merchant_results").html(returnArray['test_merchant_results']).addClass(returnArray['class']);
                        }
                    });
                }
                return false;
            });
            $("#test_email").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_email", $("#_edit_form").serialize(), function (returnArray) {
                    if ("test_email_results" in returnArray) {
                        $("#test_email_results").html(returnArray['test_email_results']).addClass(returnArray['class']);
                    }
                });
                return false;
            });
            $("#test_easypost").click(function () {
                $("#test_easypost_results").html("").prop("class", "");
                if (empty($("#easy_post_api_key").val())) {
                    displayErrorMessage("Easy Post API Key required");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_easypost&easy_post_api_key=" + $("#easy_post_api_key").val(), function (returnArray) {
                        if ("test_easypost_results" in returnArray) {
                            $("#test_easypost_results").html(returnArray['test_easypost_results']).addClass(returnArray['class']);
                        }
                    });
                }
                return false;
            });
			<?php
			$infusionsoftTokenExpiration = getPreference('INFUSIONSOFT_TOKEN_EXPIRES');
			if (strtotime($infusionsoftTokenExpiration) > time()) { ?>
            $("#infusionsoft_authorized").html("coreFORCE is authorized.");
			<?php } ?>
            $("#get_infusionsoft_token").attr("href", "<?= InfusionSoft::getAuthorizeUrl() ?>");

            <?php
            $highLevelTokenExpiration = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_TOKEN_EXPIRES");
            if (strtotime($highLevelTokenExpiration) > time()) { ?>
            $("#highlevel_authorized").html("coreFORCE is authorized.");
            <?php } ?>
            $("#get_highlevel_token").attr("href", "<?= HighLevel::getAuthorizeUrl() ?>");

			<?php if ($giftCardExists) { ?>
            $("#create_gift_cards_wrapper").html("Gift card product type and product already exist").addClass("red-text");
			<?php } ?>
            $("#merchant_accounts_merchant_service_id").change(function () {
                if ($("#merchant_accounts_merchant_service_id option:selected").text() == "coreCLEAR") {
                    $("#coreclear_avs_settings").removeClass("hidden");
                } else {
                    $("#coreclear_avs_settings").addClass("hidden");
                }
                loadFieldLabels();
            });

        </script>
		<?php
	}

	function saveChanges() {
		$gunDealsPreferences = array();
		foreach ($_POST as $fieldName => $fieldValue) {
			if (startsWith($fieldName, "gundeals_")) {
				$gunDealsPreferences[substr($fieldName, strlen("gundeals_"))] = $fieldValue;
			}
		}
		Page::setClientPagePreferences($gunDealsPreferences, "GUNDEALSFEED");

		$gunSaleFinderPreferences = array();
		foreach ($_POST as $fieldName => $fieldValue) {
			if (startsWith($fieldName, "gun_sale_finder_")) {
				$gunSaleFinderPreferences[substr($fieldName, strlen("gun_sale_finder_"))] = $fieldValue;
			}
		}
		Page::setClientPagePreferences($gunSaleFinderPreferences, "GUNSALEFINDERFEED");

		$ammoSeekPreferences = array();
		foreach ($_POST as $fieldName => $fieldValue) {
			if (startsWith($fieldName, "ammo_seek_")) {
				$ammoSeekPreferences[substr($fieldName, strlen("ammo_seek_"))] = $fieldValue;
			}
		}
		Page::setClientPagePreferences($ammoSeekPreferences, "AMMOSEEKFEED");

		$highCapDealsPreferences = array();
		foreach ($_POST as $fieldName => $fieldValue) {
			if (startsWith($fieldName, "high_cap_deals_")) {
				$highCapDealsPreferences[substr($fieldName, strlen("high_cap_deals_"))] = $fieldValue;
			}
		}
		Page::setClientPagePreferences($highCapDealsPreferences, "HIGHCAPDEALSFEED");

		$coreClearAvsPreferences = array();
		foreach ($_POST as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("coreclear_avs_approval_")) != "coreclear_avs_approval_") {
				continue;
			}
			$coreClearAvsPreferences[substr($fieldName, strlen("coreclear_avs_approval_"))] = $fieldValue;
		}
		Page::setClientPagePreferences($coreClearAvsPreferences, "CORECLEAR_AVS_APPROVALS");

		if (!empty($_POST['twilio_account_sid'])) {
			$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
			$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "RECEIVE_SMS", "custom_field_type_id = ?", $customFieldTypeId);
			if (empty($customFieldId)) {
				$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], "RECEIVE_SMS", "Receive Text Notifications", $customFieldTypeId, "Receive Text Notifications");
				$customFieldId = $insertSet['insert_id'];
				executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
			}
		}

		$productUpdateFields = ProductCatalog::getProductUpdateFields();
		foreach ($productUpdateFields as $productUpdateFieldCode => $productUpdateFieldInfo) {
			if ($productUpdateFieldInfo['internal_use_only']) {
				continue;
			}
			if (!empty($_POST['product_update_field_' . strtolower($productUpdateFieldInfo['product_update_field_code'])])) {
				$clientProductUpdateSettingRow = getRowFromId("client_product_update_settings", "product_update_field_code", $productUpdateFieldInfo['product_update_field_code']);
				if ($_POST['product_update_field_' . strtolower($productUpdateFieldInfo['product_update_field_code'])] != $clientProductUpdateSettingRow['update_setting']) {
					if (empty($clientProductUpdateSettingRow)) {
						if ($_POST['product_update_field_' . strtolower($productUpdateFieldInfo['product_update_field_code'])] != $productUpdateFieldInfo['update_setting']) {
							executeQuery("insert into client_product_update_settings (client_id,product_update_field_code,update_setting) values (?,?,?)",
								$GLOBALS['gClientId'], $productUpdateFieldInfo['product_update_field_code'], $_POST['product_update_field_' . strtolower($productUpdateFieldInfo['product_update_field_code'])]);
						}
					} else {
						executeQuery("update client_product_update_settings set update_setting = ? where client_product_update_setting_id = ?",
							$_POST['product_update_field_' . strtolower($productUpdateFieldInfo['product_update_field_code'])], $clientProductUpdateSettingRow['client_product_update_setting_id']);
					}
				}
			}
		}

		if (!empty($_POST['create_loyalty_program'])) {
			$resultSet = executeQuery("insert into loyalty_programs (client_id,loyalty_program_code,description) values (?,'LOYALTY','Loyalty Program')", $GLOBALS['gClientId']);
			$loyaltyProgramId = $resultSet['insert_id'];
			if (!empty($loyaltyProgramId)) {
				executeQuery("insert into loyalty_program_awards (loyalty_program_id,point_value) values (?,1)", $loyaltyProgramId);
				executeQuery("insert into loyalty_program_values (loyalty_program_id,point_value) values (?,1)", $loyaltyProgramId);
			}

			$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "LOYALTY_POINTS");
			if (empty($paymentMethodTypeId)) {
				$insertSet = executeQuery("insert into payment_method_types (client_id,payment_method_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], "LOYALTY_POINTS", "Loyalty Points");
				$paymentMethodTypeId = $insertSet['insert_id'];
			}
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_type_id", $paymentMethodTypeId);
			if (empty($paymentMethodId)) {
				$insertSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,detailed_description,payment_method_type_id) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], "LOYALTY_POINTS", "Loyalty Points", "<p>You have earned %points_available% points. Up to $%point_dollars_available% can be used on this order.</p>", $paymentMethodTypeId);
			}
		}

		$valuesArray = Page::getPagePreferences();
		$valuesArray['hide_using_ffl'] = $_POST['hide_using_ffl'];
		Page::setPagePreferences($valuesArray);

		if (!empty($_POST['using_ffl'])) {
			$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
			if (empty($fflRequiredProductTagId)) {
				$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'FFL_REQUIRED','FFL Required',1)", $GLOBALS['gClientId']);
			}
			$class3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
			if (empty($class3ProductTagId)) {
				$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'CLASS_3','Class 3 Products',1)", $GLOBALS['gClientId']);
			}
		}
		$templateId = $_POST['template_id'];
		if (!empty($templateId)) {
			$pageCodes = array();
			foreach ($_POST as $fieldName => $fieldValue) {
				if (substr($fieldName, 0, strlen("create_page_")) == "create_page_") {
					$pageCodes[] = $fieldValue;
				}
			}
			$returnArray['page_list'] = array();
			$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "content");
			$afterFormTemplateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "after_form_content");
			foreach ($this->iPages as $thisPage) {
				if (in_array($thisPage['page_code'], $pageCodes)) {
					$insertSet = executeQuery("insert into pages (client_id,page_code,description,date_created,creator_user_id,link_name,template_id,javascript_code,css_content,script_filename) values (?,?,?,current_date,?,?,?,?,?,?)",
						$GLOBALS['gClientId'], $GLOBALS['gClientRow']['client_code'] . "_" . $thisPage['page_code'], $GLOBALS['gClientRow']['business_name'] . " " . $thisPage['description'],
						$GLOBALS['gUserId'], $thisPage['link_name'], $templateId, $thisPage['javascript_code'], $thisPage['css_content'], $thisPage['script_filename']);
					$pageId = $insertSet['insert_id'];
					if (!empty($thisPage['content'])) {
						executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $templateDataId, $thisPage['content']);
					}
					if (!empty($thisPage['after_form_content'])) {
						executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $afterFormTemplateDataId, $thisPage['after_form_content']);
					}
					switch ($thisPage['page_access']) {
						case "users":
							executeQuery("insert into page_access (page_id,all_user_access,permission_level) values (?,1,3)", $pageId);
							break;
						case "public":
							executeQuery("insert into page_access (page_id,public_access,permission_level) values (?,1,3)", $pageId);
							break;
					}
					if (!empty($thisPage['additional_link_names'])) {
						foreach ($thisPage['additional_link_names'] as $thisLinkName) {
							executeQuery("insert into page_aliases (page_id,link_name) values (?,?)", $pageId, $thisLinkName);
						}
					}
					$returnArray['page_list'][] = $thisPage['page_code'];
				}
			}
		}

		$this->createUrlAliasTypes();
		if (!empty($_POST['domain_name'])) {
			$domainName = (substr($_POST['domain_name'], 0, 4) == "http" ? $_POST['domain_name'] : "https://" . $_POST['domain_name']);
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "WEB_URL");
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $domainName);
		}
		if (!empty($_POST['domain_name'])) {
			foreach ($this->iUrlAliasTypes as $thisAliasType) {
				if ($thisAliasType['script_filename'] != "retailstore/productsearchresults.php") {
					continue;
				}
			}
		}

		if (!empty($_POST['create_gift_cards'])) {
			$giftCardPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "GIFT_CARD");
			if (empty($giftCardPaymentMethodTypeId)) {
				$resultSet = executeQuery("insert into payment_method_types (client_id,payment_method_type_code,description) values (?,'GIFT_CARD','Gift Card')", $GLOBALS['gClientId']);
				$giftCardPaymentMethodTypeId = $resultSet['insert_id'];
			}
			$giftCardPaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "GIFT_CARD");
			if (empty($giftCardPaymentMethodId)) {
				$resultSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,payment_method_type_id) values (?,'GIFT_CARD','Gift Card',?)", $GLOBALS['gClientId'], $giftCardPaymentMethodTypeId);
				$giftCardPaymentMethodId = $resultSet['insert_id'];
			}

			$giftCardProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "GIFT_CARD");
			if (empty($giftCardProductTypeId)) {
				$resultSet = executeQuery("insert into product_types (client_id,product_type_code,description) values (?,'GIFT_CARD','Gift Card')", $GLOBALS['gClientId']);
				$giftCardProductTypeId = $resultSet['insert_id'];
			}
			$giftCardProductId = getFieldFromId("product_id", "products", "product_type_id", $giftCardProductTypeId);
			if (empty($giftCardProductId)) {
				$insertSet = executeQuery("insert into products (client_id,product_code,description,link_name,product_type_id,virtual_product,cannot_dropship,custom_product," .
					"not_taxable,non_inventory_item,date_created,time_changed) values (?,'GIFT_CARD','Gift Card','gift-card',?,1,1,1,1,1,now(),now())", $GLOBALS['gClientId'], $giftCardProductTypeId);
				$giftCardProductId = $insertSet['insert_id'];
			}
			if (!empty($giftCardProductId)) {
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "RECIPIENT_EMAIL_ADDRESS");
				if (!empty($customFieldId)) {
					$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "product_id", $giftCardProductId, "custom_field_id = ?", $customFieldId);
					if (empty($productCustomFieldId)) {
						executeQuery("insert ignore into product_custom_fields (product_id,custom_field_id) values (?,?)", $giftCardProductId, $customFieldId);
					}
				}
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "GIFT_MESSAGE");
				if (!empty($customFieldId)) {
					$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "product_id", $giftCardProductId, "custom_field_id = ?", $customFieldId);
					if (empty($productCustomFieldId)) {
						executeQuery("insert ignore into product_custom_fields (product_id,custom_field_id) values (?,?)", $giftCardProductId, $customFieldId);
					}
				}
			}
		}

		$startBackgroundProcess = false;
		if (!empty($_POST['import_defaults'])) {
			$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
			$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_taxonomy_structure";
			$response = getCurlReturn($hostUrl, $parameters);
			$taxonomyStructure = json_decode($response, true);

			$productDepartments = array();
			$resultSet = executeQuery("select * from product_departments where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productDepartments[$row['product_department_code']] = $row['product_department_id'];
			}
			foreach ($taxonomyStructure['product_departments'] as $thisDepartment) {
				if (!array_key_exists($thisDepartment['product_department_code'], $productDepartments)) {
					$insertSet = executeQuery("insert into product_departments (client_id,product_department_code,description,link_name) values (?,?,?,?)", $GLOBALS['gClientId'], $thisDepartment['product_department_code'], $thisDepartment['description'], $thisDepartment['link_name']);
					$productDepartments[$thisDepartment['product_department_code']] = $insertSet['insert_id'];
				} else if (!empty($thisDepartment['link_name'])) {
					executeQuery("update product_departments set link_name = ? where product_department_id = ? and link_name is null", $thisDepartment['link_name'], $productDepartments[$thisDepartment['product_department_code']]);
				}
			}
			$productCategoryGroups = array();
			$resultSet = executeQuery("select * from product_category_groups where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productCategoryGroups[$row['product_category_group_code']] = $row['product_category_group_id'];
			}
			foreach ($taxonomyStructure['product_category_groups'] as $thisCategoryGroup) {
				if (!array_key_exists($thisCategoryGroup['product_category_group_code'], $productCategoryGroups)) {
					$insertSet = executeQuery("insert into product_category_groups (client_id,product_category_group_code,description,link_name) values (?,?,?,?)", $GLOBALS['gClientId'], $thisCategoryGroup['product_category_group_code'], $thisCategoryGroup['description'], $thisCategoryGroup['link_name']);
					$productCategoryGroups[$thisCategoryGroup['product_category_group_code']] = $insertSet['insert_id'];
				} else if (!empty($thisCategoryGroup['link_name'])) {
					executeQuery("update product_category_groups set link_name = ? where product_category_group_id = ? and link_name is null", $thisCategoryGroup['link_name'], $productCategoryGroups[$thisCategoryGroup['product_category_group_code']]);
				}
			}
			$productCategories = array();
			$resultSet = executeQuery("select * from product_categories where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productCategories[$row['product_category_code']] = $row['product_category_id'];
			}
			foreach ($taxonomyStructure['product_categories'] as $thisCategory) {
				if (!array_key_exists($thisCategory['product_category_code'], $productCategories)) {
					$insertSet = executeQuery("insert into product_categories (client_id,product_category_code,description,link_name,atf_firearm_type_id) values (?,?,?,?,?)",
						$GLOBALS['gClientId'], $thisCategory['product_category_code'], $thisCategory['description'], $thisCategory['link_name'], $thisCategory['atf_firearm_type_id']);
					$productCategories[$thisCategory['product_category_code']] = $insertSet['insert_id'];
				} else if (!empty($thisCategory['link_name']) || !empty($thisCategory['atf_firearm_type_id'])) {
					executeQuery("update product_categories set link_name = ?, atf_firearm_type_id = ? where product_category_id = ? and link_name is null",
						$thisCategory['link_name'], $thisCategory['atf_firearm_type_id'], $productCategories[$thisCategory['product_category_code']]);
				}
			}
			$productFacets = array();
			$resultSet = executeQuery("select * from product_facets where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productFacets[$row['product_facet_code']] = $row['product_facet_id'];
			}
			foreach ($taxonomyStructure['product_facets'] as $thisFacet) {
				if (!array_key_exists($thisFacet['product_facet_code'], $productFacets)) {
					$insertSet = executeQuery("insert into product_facets (client_id,product_facet_code,description) values (?,?,?)", $GLOBALS['gClientId'], $thisFacet['product_facet_code'], $thisFacet['description']);
					$productFacets[$thisFacet['product_facet_code']] = $insertSet['insert_id'];
				}
			}

			foreach ($taxonomyStructure['product_departments'] as $thisDepartment) {
				$productDepartmentId = $productDepartments[$thisDepartment['product_department_code']];
				if (empty($productDepartmentId)) {
					continue;
				}
				foreach ($thisDepartment['product_categories'] as $productCategoryCode) {
					$productCategoryId = $productCategories[$productCategoryCode];
					if (empty($productCategoryId)) {
						continue;
					}
					$productCategoryDepartmentId = getFieldFromId("product_category_department_id", "product_category_departments", "product_department_id", $productDepartmentId,
						"product_category_id = ?", $productCategoryId);
					if (empty($productCategoryDepartmentId)) {
						executeQuery("insert ignore into product_category_departments (product_department_id,product_category_id) values (?,?)", $productDepartmentId, $productCategoryId);
					}
				}
				foreach ($thisDepartment['product_category_groups'] as $productCategoryGroupCode) {
					$productCategoryGroupId = $productCategoryGroups[$productCategoryGroupCode];
					if (empty($productCategoryGroupId)) {
						continue;
					}
					$productCategoryGroupDepartmentId = getFieldFromId("product_category_group_department_id", "product_category_group_departments", "product_department_id", $productDepartmentId,
						"product_category_group_id = ?", $productCategoryGroupId);
					if (empty($productCategoryGroupDepartmentId)) {
						executeQuery("insert into product_category_group_departments (product_department_id,product_category_group_id) values (?,?)", $productDepartmentId, $productCategoryGroupId);
					}
				}
			}

			foreach ($taxonomyStructure['product_category_groups'] as $thisCategoryGroup) {
				$productCategoryGroupId = $productCategoryGroups[$thisCategoryGroup['product_category_group_code']];
				if (empty($productCategoryGroupId)) {
					continue;
				}
				foreach ($thisCategoryGroup['product_categories'] as $productCategoryCode) {
					$productCategoryId = $productCategories[$productCategoryCode];
					if (empty($productCategoryId)) {
						continue;
					}
					$productCategoryGroupLinkId = getFieldFromId("product_category_group_link_id", "product_category_group_links", "product_category_group_id", $productCategoryGroupId,
						"product_category_id = ?", $productCategoryId);
					if (empty($productCategoryGroupLinkId)) {
						executeQuery("insert ignore into product_category_group_links (product_category_group_id,product_category_id) values (?,?)", $productCategoryGroupId, $productCategoryId);
					}
				}
			}

			foreach ($taxonomyStructure['product_facets'] as $thisFacet) {
				$productFacetId = $productFacets[$thisFacet['product_facet_code']];
				if (empty($productFacetId)) {
					continue;
				}
				foreach ($thisFacet['product_categories'] as $productCategoryCode) {
					$productCategoryId = $productCategories[$productCategoryCode];
					if (empty($productCategoryId)) {
						continue;
					}
					$productFacetCategoryId = getFieldFromId("product_facet_category_id", "product_facet_categories", "product_facet_id", $productFacetId,
						"product_category_id = ?", $productCategoryId);
					if (empty($productFacetCategoryId)) {
						executeQuery("insert ignore into product_facet_categories (product_facet_id,product_category_id) values (?,?)", $productFacetId, $productCategoryId);
					}
				}
			}

			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "SYNC_PRODUCT_MANUFACTURERS");
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,'true')", $GLOBALS['gClientId'], $preferenceId);
			$startBackgroundProcess = true;

		}

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "PRODUCT_DETAIL_HTML_FRAGMENT_ID");
		executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
		if (!empty($_POST['product_detail_html_fragment_id'])) {
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $_POST['product_detail_html_fragment_id']);
		}

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "PRODUCT_RESULT_HTML_FRAGMENT_ID");
		executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
		if (!empty($_POST['product_result_html_fragment_id'])) {
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $_POST['product_result_html_fragment_id']);
		}

		if (!empty($_POST['import_ffl_dealers'])) {
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "SYNC_FEDERAL_FIREARMS_LICENSEES");
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,'true')", $GLOBALS['gClientId'], $preferenceId);
			$startBackgroundProcess = true;
		}

		if (!empty($_POST['setup_email_credentials'])) {
			$emailCredentialRow = getRowFromId("email_credentials", "email_credential_code", "DEFAULT");
			$dataSource = new DataSource("email_credentials");
			if (empty($emailCredentialRow)) {
				$emailCredentialRow['description'] = "Default Email Credentials";
				$emailCredentialRow['email_credential_code'] = "DEFAULT";
				$emailCredentialRow['user_id'] = $GLOBALS['gUserId'];
			}
			foreach ($_POST as $fieldName => $fieldValue) {
				if (substr($fieldName, 0, strlen("email_credentials_")) == "email_credentials_") {
					$emailCredentialRow[substr($fieldName, strlen("email_credentials_"))] = $fieldValue;
				}
			}
			if (!$dataSource->saveRecord(array("name_values" => $emailCredentialRow, "primary_id" => $emailCredentialRow['email_credential_id']))) {
				return $dataSource->getErrorMessage();
			}
		}

		if (!empty($_POST['setup_merchant_account'])) {
			$merchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_code", "DEFAULT");
			$dataSource = new DataSource("merchant_accounts");
			if (empty($merchantAccountRow)) {
				$merchantAccountRow['description'] = "Default Merchant Account";
				$merchantAccountRow['merchant_account_code'] = "DEFAULT";
			}
			$customFieldNameValues = array();
			foreach ($_POST as $fieldName => $fieldValue) {
				if (substr($fieldName, 0, strlen("merchant_accounts_")) == "merchant_accounts_") {
					$merchantAccountFieldName = substr($fieldName, strlen("merchant_accounts_"));
					if (substr($merchantAccountFieldName, 0, strlen("custom_field")) == "custom_field") {
						$customFieldNameValues[$merchantAccountFieldName] = array("primary_id" => $merchantAccountRow['merchant_account_id'], $merchantAccountFieldName => $fieldValue);
					} else {
						$merchantAccountRow[$merchantAccountFieldName] = $fieldValue;
					}
				}
			}
			if (!$dataSource->saveRecord(array("name_values" => $merchantAccountRow, "primary_id" => $merchantAccountRow['merchant_account_id']))) {
				return $dataSource->getErrorMessage();
			}
			$customFields = CustomField::getCustomFields("merchant_accounts");
			foreach ($customFields as $thisCustomField) {
				$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
				if (array_key_exists($customField->getColumnName(), $customFieldNameValues)) {
					if (!$customField->saveData($customFieldNameValues[$customField->getColumnName()])) {
						return $customField->getErrorMessage();
					}
				}
			}

		}

		if (!empty($_POST['setup_pricing_structure'])) {
			$pricingStructureRow = getRowFromId("pricing_structures", "pricing_structure_code", "DEFAULT");
			$dataSource = new DataSource("pricing_structures");
			if (empty($pricingStructureRow)) {
				$pricingStructureRow['description'] = "Default Pricing Structure";
				$pricingStructureRow['pricing_structure_code'] = "DEFAULT";
			}
			foreach ($_POST as $fieldName => $fieldValue) {
				if (substr($fieldName, 0, strlen("pricing_structure_")) == "pricing_structure_") {
					$pricingStructureRow[substr($fieldName, strlen("pricing_structure_"))] = $fieldValue;
				}
			}
			if (!$dataSource->saveRecord(array("name_values" => $pricingStructureRow, "primary_id" => $pricingStructureRow['pricing_structure_id']))) {
				return $dataSource->getErrorMessage();
			}
		}

		$integrationPreferenceIds = array();
		$resultSet = executeQuery("select * from preferences where preference_id in (select preference_id from preference_group_links where " .
			"preference_group_id in (select preference_group_id from preference_groups where preference_group_code = 'INTEGRATION_SETTINGS'))");
		while ($row = getNextRow($resultSet)) {
			$integrationPreferenceIds[] = $row['preference_id'];
			if (!array_key_exists(strtolower($row['preference_code']), $_POST)) {
				continue;
			}
			$clientPreferenceId = getFieldFromId("client_preference_id", "client_preferences", "preference_id", $row['preference_id'],
				"preference_qualifier is null and client_id = ?", $GLOBALS['gClientId']);
			$dataSource = new DataSource("client_preferences");
			$saveValues = array();
			$saveValues['client_id'] = $GLOBALS['gClientId'];
			$saveValues['preference_qualifier'] = "";
			$saveValues['preference_id'] = $row['preference_id'];
			$saveValues['preference_value'] = $_POST[strtolower($row['preference_code'])];
			if (empty($saveValues['preference_value'])) {
				if (!empty($clientPreferenceId)) {
					$dataSource->deleteRecord(array("primary_id" => $clientPreferenceId));
				}
			} else {
				$clientPreferenceId = $dataSource->saveRecord(array("name_values" => $saveValues, "primary_id" => $clientPreferenceId));
			}
		}

		if (!empty($_POST['taxjar_api_token'])) {
			$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "PRODUCTS");
			$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "TAXJAR_PRODUCT_CATEGORY_CODE", "custom_field_type_id = ?", $customFieldTypeId);
			if (empty($customFieldId)) {
				$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], "TAXJAR_PRODUCT_CATEGORY_CODE", "TaxJar Product Category Code", $customFieldTypeId, "TaxJar Product Category Code");
				$customFieldId = $insertSet['insert_id'];
				executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "varchar");
				executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "help_label", "Click <a href='#' id='taxjar_categories'>here</a> for a list of categories.");
			}
		}

		if ($startBackgroundProcess) {
			executeQuery("update background_processes set run_immediately = 1 where background_process_code = 'SYNC_RETAIL_STORE'");
		}

		$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", "RETAIL_STORE");
		$resultSet = executeQuery("select * from preferences join preference_group_links using (preference_id) where inactive = 0" .
			" and preference_group_id = ? and client_setable = 1 order by sequence_number,description", $preferenceGroupId);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists("preference_value_" . $row['preference_id'], $_POST)) {
				continue;
			}
			if (in_array($row['preference_id'], $integrationPreferenceIds)) {
				continue;
			}
			$clientPreferenceId = getFieldFromId("client_preference_id", "client_preferences", "preference_id", $row['preference_id'],
				"preference_qualifier is null and client_id = ?", $GLOBALS['gClientId']);
			$dataSource = new DataSource("client_preferences");
			$saveValues = array();
			$saveValues['client_id'] = $GLOBALS['gClientId'];
			$saveValues['preference_qualifier'] = "";
			$saveValues['preference_id'] = $row['preference_id'];
			$saveValues['preference_value'] = $_POST['preference_value_' . $row['preference_id']];
			if (empty($saveValues['preference_value'])) {
				if (!empty($clientPreferenceId)) {
					$dataSource->deleteRecord(array("primary_id" => $clientPreferenceId));
				}
			} else {
				$clientPreferenceId = $dataSource->saveRecord(array("name_values" => $saveValues, "primary_id" => $clientPreferenceId));
			}
		}

		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_coreware_defaults", $postParameters);
		$responseArray = json_decode($response, true);
		$defaults = $responseArray['defaults'];

		if (!array_key_exists("emails", $defaults)) {
			$defaults['emails'] = array();
		}
		if (!array_key_exists("fragments", $defaults)) {
			$defaults['fragments'] = array();
		}
		if (!array_key_exists("notifications", $defaults)) {
			$defaults['notifications'] = array();
		}

		$clientAddressBlock = $GLOBALS['gClientRow']['address_1'];
		$clientCity = $GLOBALS['gClientRow']['city'];
		if (!empty($GLOBALS['gClientRow']['state'])) {
			$clientCity .= (empty($clientCity) ? "" : ", ") . $GLOBALS['gClientRow']['state'];
		}
		if (!empty($GLOBALS['gClientRow']['postal_code'])) {
			$clientCity .= (empty($clientCity) ? "" : ", ") . $GLOBALS['gClientRow']['postal_code'];
		}
		if (!empty($clientCity)) {
			$clientAddressBlock .= (empty($clientAddressBlock) ? "" : "<br>\n") . $clientCity;
		}
		$clientCountry = "";
		if ($GLOBALS['gClientRow']['country_id'] != 1000) {
			$clientCountry = getFieldFromId("country_name", "countries", "country_id", $GLOBALS['gClientRow']['country_id']);
		}
		if (!empty($clientCountry)) {
			$clientAddressBlock .= (empty($clientAddressBlock) ? "" : "<br>\n") . $clientCountry;
		}
		$clientFields = array("client_name" => $GLOBALS['gClientRow']['business_name'],
			"client_address_1" => $GLOBALS['gClientRow']['address_1'],
			"client_city" => $GLOBALS['gClientRow']['city'],
			"client_state" => $GLOBALS['gClientRow']['state'],
			"client_postal_code" => $GLOBALS['gClientRow']['postal_code'],
			"client_address_block" => $clientAddressBlock,
			"client_domain_name" => $_POST['domain_name'],
			"client_email_address" => $GLOBALS['gClientRow']['email_address']
		);

		foreach ($defaults['emails'] as $thisEmail) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", $thisEmail['email_code']);
			if (empty($emailId)) {
				foreach ($clientFields as $thisFieldName => $thisFieldValue) {
					$thisEmail['subject'] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $thisEmail['subject']);
					$thisEmail['content'] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $thisEmail['content']);
				}
				if (!empty($_POST['default_email_' . $thisEmail['email_code']])) {
					executeQuery("insert into emails (client_id,email_code,description,detailed_description,subject,content) values (?,?,?,?,?, ?)", $GLOBALS['gClientId'], $thisEmail['email_code'],
						$thisEmail['description'], $thisEmail['detailed_description'], $thisEmail['subject'], $thisEmail['content']);
				}
			}
		}
		foreach ($defaults['fragments'] as $thisFragment) {
			$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $thisFragment['fragment_code']);
			if (empty($fragmentId)) {
				foreach ($clientFields as $thisFieldName => $thisFieldValue) {
					$thisFragment['content'] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $thisFragment['content']);
				}
				if (!empty($_POST['default_fragment_' . $thisFragment['fragment_code']])) {
					executeQuery("insert into fragments (client_id,fragment_code,description,detailed_description,content) values (?,?,?,?,?)", $GLOBALS['gClientId'], $thisFragment['fragment_code'],
						$thisFragment['description'], $thisFragment['detailed_description'], $thisFragment['content']);
				}
			}
		}
		foreach ($defaults['notifications'] as $thisNotification) {
			$notificationId = getFieldFromId("notification_id", "notifications", "notification_code", $thisNotification['notification_code']);
			if (empty($notificationId)) {
				if (!empty($_POST['default_notification_' . $thisNotification['notification_code']])) {
					$insertSet = executeQuery("insert into notifications (client_id,notification_code,description,detailed_description) values (?,?,?,?)", $GLOBALS['gClientId'], $thisNotification['notification_code'],
						$thisNotification['description'], $thisNotification['detailed_description']);
					$notificationId = $insertSet['insert_id'];
					$emailAddresses = explode(",", str_replace(" ", ",", str_replace(";", ",", $_POST['default_notification_' . $thisNotification['notification_code']])));
					foreach ($emailAddresses as $emailAddress) {
						executeQuery("insert into notification_emails (notification_id,email_address) values (?,?)", $notificationId, trim($emailAddress));
					}
				}
			}
		}

		$corestoreUrl = getPreference("CORESTORE_URL");
		if (!empty($corestoreUrl)) {
			$menuItemId = getFieldFromId("menu_item_id", "menu_items", "list_item_classes", "corestore-link");
			if (empty($menuItemId)) {
				$insertSet = executeQuery("insert into menu_items (client_id,description,link_title,link_url,list_item_classes,separate_window,administrator_access) values (?,'coreSTORE','coreSTORE',?,'corestore-link',1,1)", $GLOBALS['gClientId'], $corestoreUrl);
				$menuItemId = $insertSet['insert_id'];
				$myAccountMenuId = getFieldFromId("menu_id", "menus", "menu_code", "core_my_account");
				$myAccountMenuItemId = getFieldFromId("menu_item_id", "menu_items", "menu_id", $myAccountMenuId);
				$adminMenuId = getFieldFromId("menu_id", "menus", "menu_code", "ADMIN_MENU");
				$sequenceNumber = getFieldFromId("sequence_number", "menu_contents", "menu_item_id", $myAccountMenuItemId, "menu_id = ?", $adminMenuId);
				if (!empty($myAccountMenuId) && !empty($myAccountMenuItemId) && !empty($adminMenuId) && !empty($sequenceNumber)) {
					executeQuery("insert into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)", $adminMenuId, $menuItemId, ($sequenceNumber + 1));
				}
			} else {
				executeQuery("update menu_items set link_url = ? where menu_item_id = ?", $corestoreUrl, $menuItemId);
			}
		}

		$GLOBALS['gSystemPreferences'] = array();
		$returnArray['info_message'] = "Information Saved";
		ajaxResponse($returnArray);
	}

	function createUrlAliasTypes() {
		foreach ($this->iUrlAliasTypes as $thisAliasType) {
			$urlAliasTypeId = getFieldFromId("url_alias_type_id", "url_alias_types", "url_alias_type_code", $thisAliasType['url_alias_type_code']);
			if (!empty($urlAliasTypeId)) {
				continue;
			}
			$pageId = getFieldFromId("page_id", "pages", "client_id", $GLOBALS['gClientId'], "script_filename like ?", $thisAliasType['script_filename']);
			if (empty($pageId)) {
				continue;
			}
			$tableId = getFieldFromId("table_id", "tables", "table_name", $thisAliasType['table_name']);
			executeQuery("insert into url_alias_types (client_id,url_alias_type_code,description,table_id,page_id,parameter_name) values (?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $thisAliasType['url_alias_type_code'], $thisAliasType['description'], $tableId, $pageId, $thisAliasType['parameter_name']);
		}
	}

	function productUpdateFields() {
		$productUpdateFields = ProductCatalog::getProductUpdateFields();
		foreach ($productUpdateFields as $productUpdateFieldCode => $productUpdateFieldInfo) {
			if ($productUpdateFieldInfo['internal_use_only']) {
				continue;
			}
			?>
            <div class='basic-form-line product-update-field' id="_product_update_field_<?= strtolower($productUpdateFieldInfo['product_update_field_code']) ?>_row">
                <label><?= $productUpdateFieldInfo['description'] ?></label>
                <select id="product_update_field_<?= strtolower($productUpdateFieldInfo['product_update_field_code']) ?>" name="product_update_field_<?= strtolower($productUpdateFieldInfo['product_update_field_code']) ?>">
                    <option value='Y'>Always Update</option>
                    <option value='M'>Update if Empty</option>
                    <option value='N'>Never Update</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}
}

$pageObject = new ThisPage("clients");
$pageObject->displayPage();
