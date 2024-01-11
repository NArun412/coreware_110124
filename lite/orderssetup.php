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

$GLOBALS['gPageCode'] = "ORDERSSETUP_LITE";
require_once "shared/startup.inc";
require_once "classes/easypost/lib/easypost.php";
require_once "retailstoresetup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 360000;

class OrdersSetupLitePage extends Page {

	var $iCustomFields = array();
	var $iDistributors = array();
	var $iUrlAliasTypes = array();
	var $iPages = array();
	var $iPaymentMethodTypes = array();
	var $iPaymentMethods = array();
	var $iShippingCarriers = array();

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "validate_ffl":
				$licenseLookup = str_replace("-", "", $_GET['license_lookup']);
				if (strlen($licenseLookup) == 15) {
					$licenseLookup = substr($licenseLookup, 0, 1) . "-" . substr($licenseLookup, 1, 2) . "-" . substr($licenseLookup, 10, 5);
				}
				if (strlen($licenseLookup) == 8) {
					$licenseLookup = substr($licenseLookup, 0, 1) . "-" . substr($licenseLookup, 1, 2) . "-" . substr($licenseLookup, 3, 5);
				}
				$licenseParts = explode("-",$licenseLookup);
				if (count($licenseParts) > 3) {
					$licenseLookup = $licenseParts[0] . "-" . $licenseParts[1] . "-" . $licenseParts[5];
				}
				$resultSet = executeQuery("select * from federal_firearms_licensees join contacts using (contact_id) where license_lookup = ? and federal_firearms_licensees.client_id in (?,?) order by federal_firearms_licensees.client_id desc",
					$licenseLookup, $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['ffl_information'] = (empty($row['business_name']) ? $row['licensee_name'] : $row['business_name']);
					$returnArray['license_lookup'] = $licenseLookup;
				} else {
					$returnArray['ffl_information'] = "FFL license number not found";
				}
				ajaxResponse($returnArray);
				exit;
			case "check_taxjar_api_token":
				require_once __DIR__ . '/taxjar/vendor/autoload.php';
				try {
					$client = TaxJar\Client::withApiKey($_GET['taxjar_api_token']);
					$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
					$nexusList = $client->nexusRegions();
					$nexusData = "<h2>Business Presence Locations</h2><p>Taxes are ONLY charged where you, the retailer, have a presence, called \"nexus\", listed below. Some states determine that you have a nexus in that state when you exceed some predetermined sales figure and have to pay taxes on any sales beyond that figure. Taxjar will notify you when you get close to that number so you can register as a nexus and begin collecting taxes.</p>";
					foreach ((array)$nexusList as $thisNexus) {
						$nexusData .= "<p class='taxjar-nexus'>" . $thisNexus->region . ($thisNexus->country == "United States" ? "" : ", " . $thisNexus->country);
					}
					$nexusData .= "<p>To add more locations, <a href='https://app.taxjar.com/account#states'>click here.</a></p>";
					$returnArray['taxjar_validation'] = "This API token appears to be valid";
					$returnArray['taxjar_nexus_data'] = $nexusData;
				} catch (Exception $e) {
					$returnArray['error_message'] = "Invalid API Key";
				}
				ajaxResponse($returnArray);
				break;
			case "get_merchant_account_field_labels":
				$returnArray['field_labels'] = array();
				$resultSet = executeQuery("select * from merchant_service_field_labels where merchant_service_id = ?", $_GET['merchant_service_id']);
				while ($row = getNextRow($resultSet)) {
					if (substr($row['column_name'], 0, strlen("custom_field_")) == "custom_field_") {
						$customFieldCode = substr($row['column_name'], strlen("custom_field_"));
						$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode, "MERCHANT_ACCOUNTS");
						$row['column_name'] = "merchant_accounts_custom_field_id_" . $customFieldId;
					}
					$returnArray['field_labels'][$row['column_name']] = array("form_label" => $row['form_label'], "not_null" => $row['not_null']);
				}
				ajaxResponse($returnArray);
				break;
			case "test_merchant":
				$eCommerce = eCommerce::getEcommerceInstance($_GET['merchant_account_id']);
				if ($eCommerce->testConnection()) {
					$returnArray['test_merchant_results'] = "Connection to Merchant Account works";
					$returnArray['class'] = "green-text";
				} else {
					$returnArray['test_merchant_results'] = "Connection to Merchant Account DOES NOT work";
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
		}
	}

	function setup() {
		loadSetupVariables($this);
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
			$customFieldId = CustomField::getCustomFieldIdFromCode($thisCustomField['custom_field_code'], $thisCustomField['custom_field_type_code']);
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
		$sortOrder = 0;
		foreach ($this->iDistributors as $thisDistributor) {
			$sortOrder += 10;
			$productDistributorId = getFieldFromId("product_distributor_id", "product_distributors", "product_distributor_code", $thisDistributor['product_distributor_code']);
			if (empty($productDistributorId)) {
				$insertSet = executeQuery("insert into product_distributors (product_distributor_code,description,class_name,sort_order) values (?,?,?,?)", $thisDistributor['product_distributor_code'], $thisDistributor['description'], $thisDistributor['class_name'], $sortOrder);
				$productDistributors[$thisDistributor['product_distributor_code']] = $productDistributorId = $insertSet['insert_id'];
			} else {
				$productDistributors[$thisDistributor['product_distributor_code']] = $productDistributorId;
			}
			foreach ($thisDistributor['custom_fields'] as $customFieldCode) {
				$productDistributorCustomFieldId = getFieldFromId("product_distributor_custom_field_id", "product_distributor_custom_fields", "product_distributor_id", $productDistributorId, "custom_field_id = ?", $customFields[strtoupper($customFieldCode)]);
				if (empty($productDistributorCustomFieldId)) {
					executeQuery("insert ignore into product_distributor_custom_fields (product_distributor_id,custom_field_id) values (?,?)", $productDistributorId, $customFields[strtoupper($customFieldCode)]);
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
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("analytics_code", "data_type", "text");

		$this->iDataSource->addColumnLikeColumn("credit_card_handling_fee", "payment_methods", "fee_percent");
		$this->iDataSource->addColumnControl("credit_card_handling_fee", "form_label", "Credit Card Handling Fee Percentage");
		$this->iDataSource->addColumnControl("credit_card_handling_fee", "help_label", "Percent of the order total that will be added as a handling fee");

		$this->iDataSource->addColumnControl("setup_merchant_account", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_merchant_account", "form_label", "Set up the default Merchant Account");
		$this->iDataSource->addColumnControl("setup_pricing_structure", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_pricing_structure", "form_label", "Set up the default Pricing Structure");

		$this->iDataSource->addColumnLikeColumn("client_business_name", "contacts", "business_name");
		$this->iDataSource->addColumnControl("client_business_name", "not_null", true);
		$this->iDataSource->addColumnLikeColumn("client_first_name", "contacts", "first_name");
		$this->iDataSource->addColumnLikeColumn("client_last_name", "contacts", "last_name");
		$this->iDataSource->addColumnLikeColumn("client_address_1", "contacts", "address_1");
		$this->iDataSource->addColumnControl("client_address_1", "not_null", true);
		$this->iDataSource->addColumnLikeColumn("client_address_2", "contacts", "address_2");
		$this->iDataSource->addColumnLikeColumn("client_city", "contacts", "city");
		$this->iDataSource->addColumnControl("client_city", "not_null", true);
		$this->iDataSource->addColumnLikeColumn("client_state", "contacts", "state");
		$this->iDataSource->addColumnControl("client_state", "not_null", true);
		$this->iDataSource->addColumnControl("client_state", "data_type", "select");
		$this->iDataSource->addColumnControl("client_state", "choices", getStateArray());
		$this->iDataSource->addColumnLikeColumn("client_postal_code", "contacts", "postal_code");
		$this->iDataSource->addColumnControl("client_postal_code", "not_null", true);
		$this->iDataSource->addColumnLikeColumn("client_phone_number", "phone_numbers", "phone_number");
		$this->iDataSource->addColumnControl("client_phone_number", "not_null", true);
		$this->iDataSource->addColumnControl("client_phone_number", "data_format", "phone");
		$this->iDataSource->addColumnLikeColumn("client_email_address", "contacts", "email_address");
		$this->iDataSource->addColumnControl("client_email_address", "not_null", true);
		$this->iDataSource->addColumnControl("client_email_address", "data_format", "email");

		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_account_id", "merchant_accounts", "merchant_account_id");
		$this->iDataSource->addColumnControl("merchant_accounts_merchant_account_id", "data_type", "hidden");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_service_id", "merchant_accounts", "merchant_service_id");
		$this->iDataSource->addColumnControl("merchant_accounts_merchant_service_id", "data-conditional-required", '$("#setup_merchant_account").prop("checked")');
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_account_login", "merchant_accounts", "account_login");
		$this->iDataSource->addColumnControl("merchant_accounts_account_login", "data-conditional-required", '$("#setup_merchant_account").prop("checked")');
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_account_key", "merchant_accounts", "account_key");
		$this->iDataSource->addColumnControl("merchant_accounts_account_key", "data-conditional-required", '$("#setup_merchant_account").prop("checked")');
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_identifier", "merchant_accounts", "merchant_identifier");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_link_url", "merchant_accounts", "link_url");

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
				$choices = getContentLines($row['choices']);
				$this->iDataSource->addColumnControl(strtolower($row['preference_code']), "choices", $choices);
			}
		}
		$this->iDataSource->addColumnControl("default_map_policy_id", "data_type", "select");
		$this->iDataSource->addColumnControl("default_map_policy_id", "empty_text", "Use Manufacturer's Map Policy");
		$this->iDataSource->addColumnControl("default_map_policy_id", "choices",
			array("2" => "MAP is minimum, but selling price can be more",
				"8" => "MAP is always the selling price"));

		$this->iDataSource->addColumnLikeColumn("pricing_structure_percentage", "pricing_structures", "percentage");
		$this->iDataSource->addColumnLikeColumn("pricing_structure_price_calculation_type_id", "pricing_structures", "price_calculation_type_id");
		$this->iDataSource->addColumnControl("pricing_structure_price_calculation_type_id", "default_value", "1");
		$this->iDataSource->addColumnControl("pricing_structure_percentage", "data-conditional-required", "$(\"#setup_pricing_structure\").prop(\"checked\")");
		$this->iDataSource->addColumnControl("pricing_structure_price_calculation_type_id", "data-conditional-required", "$(\"#setup_pricing_structure\").prop(\"checked\")");
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

	function afterGetRecord(&$returnArray) {
		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_coreware_defaults", $postParameters);
		$responseArray = json_decode($response, true);
		$defaults = (is_array($responseArray) && array_key_exists("defaults", $responseArray) ? $responseArray['defaults'] : array());

		$gunDealsPreferences = Page::getClientPagePreferences("GUNDEALSFEED");
		foreach ($gunDealsPreferences as $fieldName => $fieldValue) {
			$returnArray['gundeals_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
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
		$returnArray['domain_name'] = array("data_value" => getDomainName());
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
		$filteredEmailArray = array("ACCOUNT_CONFIRMATION", "CART_ABANDONED", "FORGOT_USER_NAME", "GUNBROKER_CUSTOMER_CART", "NEW_ACCOUNT", "PICKUP_DONE", "READY_FOR_PICKUP", "RETAIL_STORE_GIFT_CARD", "RETAIL_STORE_IN_STOCK_NOTIFICATION", "RETAIL_STORE_ORDER_CONFIRMATION", "RETAIL_STORE_TRACKING_EMAIL", "RETAIL_STORE_ORDER_NOTIFICATION", "RESET_PASSWORD");
		foreach ($defaults['emails'] as $thisEmail) {
			if (!in_array($thisEmail['email_code'], $filteredEmailArray)) {
				continue;
			}
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
		echo "<p>For each notification, add a comma separated list of email addresses you wish to be included in that notification.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='notification_filter_text' placeholder='Filter Notifications'></p>";
		$filteredNotificationArray = array("BCC_ALL_EMAILS", "CREDOVA_RETURN", "DISTRIBUTOR_ERRORS", "ECOMMERCE_FAILURE", "GUNBROKER_ERRORS", "INVENTORY_UPDATE_ERROR", "PRODUCT_COST_DIFFERENCE", "RETAIL_STORE_ORDER_NOTIFICATION");
		foreach ($defaults['notifications'] as $thisNotification) {
			if (!in_array($thisNotification['notification_code'], $filteredNotificationArray)) {
				continue;
			}
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
                        <p>Notification already created.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_notifications'] = array("data_value" => ob_get_clean());

		$feePercent = getFieldFromId("fee_percent", "payment_methods", "client_id", $GLOBALS['gClientId'], "payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_CARD')");
		$returnArray['credit_card_handling_fee'] = array("data_value" => $feePercent, "crc_value" => getCrcValue($feePercent));

		$returnArray['client_business_name'] = array("data_value" => $GLOBALS['gClientRow']['business_name'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['business_name']));
		$returnArray['client_first_name'] = array("data_value" => $GLOBALS['gClientRow']['first_name'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['first_name']));
		$returnArray['client_last_name'] = array("data_value" => $GLOBALS['gClientRow']['last_name'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['last_name']));
		$returnArray['client_address_1'] = array("data_value" => $GLOBALS['gClientRow']['address_1'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['address_1']));
		$returnArray['client_address_2'] = array("data_value" => $GLOBALS['gClientRow']['address_2'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['address_2']));
		$returnArray['client_city'] = array("data_value" => $GLOBALS['gClientRow']['city'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['city']));
		$returnArray['client_state'] = array("data_value" => $GLOBALS['gClientRow']['state'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['state']));
		$returnArray['client_postal_code'] = array("data_value" => $GLOBALS['gClientRow']['postal_code'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['postal_code']));
		$returnArray['client_email_address'] = array("data_value" => $GLOBALS['gClientRow']['email_address'], "crc_value" => getCrcValue($GLOBALS['gClientRow']['email_address']));
		$phoneNumber = Contact::getContactPhoneNumber($GLOBALS['gClientRow']['contact_id'], 'Store');
		$returnArray['client_phone_number'] = array("data_value" => $phoneNumber, "crc_value" => getCrcValue($phoneNumber));

		$resultSet = executeQuery("select * from locations where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
            $fflName = "";
            if (!empty($row['license_lookup']) && empty($row['product_distributor_id'])) {
	            $fflSet = executeQuery("select * from federal_firearms_licensees join contacts using (contact_id) where license_lookup = ? and federal_firearms_licensees.client_id in (?,?) order by federal_firearms_licensees.client_id desc",
		            $row['license_lookup'], $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
	            if ($fflRow = getNextRow($fflSet)) {
		            $fflName = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
	            } else {
		            $fflName = "FFL license number not found";
	            }
            }
			$returnArray['location_description_' . $row['location_id']] = array("data_value" => $row['description'], "crc_value" => getCrcValue($row['description']));
			$returnArray['ignore_inventory_' . $row['location_id']] = array("data_value" => $row['ignore_inventory'], "crc_value" => getCrcValue($row['ignore_inventory']));
			$returnArray['internal_use_only_' . $row['location_id']] = array("data_value" => $row['internal_use_only'], "crc_value" => getCrcValue($row['internal_use_only']));
			$returnArray['license_lookup_' . $row['location_id']] = array("data_value" => $row['license_lookup'], "crc_value" => getCrcValue($row['license_lookup']));
			$returnArray['ffl_name_' . $row['location_id']] = array("data_value" => $fflName);
		}
		$resultSet = executeQuery("select * from sass_headers where client_id = ? order by sass_header_id", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$contentLines = getContentLines($row['content']);
			foreach ($contentLines as $thisLine) {
				$parts = explode(":", $thisLine);
				if (count($parts) != 2) {
					continue;
				}
				$variableName = trim($parts[0]);
				$variableValue = trim($parts[1], " ;");
				if (!startsWith($variableName, "$") || strpos($variableName, "color") === false || (strlen($variableValue) != 7 && strlen($variableValue) != 4)) {
					continue;
				}
				$label = ucwords(str_replace("$", "", str_replace("-", " ", $variableName)));
				$fieldName = "sass_header_" . makeCode($label, array("lowercase" => true));
				$returnArray[$fieldName] = array("data_value" => $variableValue, "crc_value" => getCrcValue($variableValue));
			}
		}
		$resultSet = executeQuery("select * from template_text_chunks where template_id in (select template_id from templates where client_id = ? and include_crud = 0 and internal_use_only = 0 and inactive = 0)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$fieldName = "template_text_chunk-" . $row['template_id'] . "-" . strtolower($row['template_text_chunk_code']);
			$returnArray[$fieldName] = array("data_value" => $row['content'], "crc_value" => getCrcValue($row['content']));
		}
		$analyticsCode = getFieldFromId("content", "analytics_code_chunks", "analytics_code_chunk_code", "WEBSITE_CODE");
		$returnArray['analytics_code'] = array("data_value" => $analyticsCode, "crc_value" => getCrcValue($analyticsCode));

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

	function locationSettings() {
		$resultSet = executeQuery("select * from locations where client_id = ?", $GLOBALS['gClientId']);
		?>
        <table class='grid-table'>
            <tr>
                <th>Location</th>
                <th>Location Type</th>
                <th>Ignore Inventory</th>
                <th>Internal Use Only</th>
                <th>FFL License</th>
                <th></th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr>
                    <td><input type='text' style='width: 250px;' class='validate[required]' id='location_description_<?= $row['location_id'] ?>' name='location_description_<?= $row['location_id'] ?>'></td>
                    <td><?= htmlText(empty($row['product_distributor_id']) ? "Local Location" : getFieldFromId("description", "product_distributors", "product_distributor_id", $row['product_distributor_id'])) ?></td>
                    <td class='align-center'><input type='checkbox' name='ignore_inventory_<?= $row['location_id'] ?>' id='ignore_inventory_<?= $row['location_id'] ?>' value='1'></td>
                    <td class='align-center'><input type='checkbox' name='internal_use_only_<?= $row['location_id'] ?>' id='internal_use_only_<?= $row['location_id'] ?>' value='1'></td>
                    <td class='align-center'><?php if (empty($row['product_distributor_id'])) { ?><input size='20' maxlength='32' class='license-lookup' name='license_lookup_<?= $row['location_id'] ?>' id='license_lookup_<?= $row['location_id'] ?>' value=''><?php } ?></td>
                    <td class='ffl-name' id="ffl_name_<?= $row['location_id'] ?>"></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
	}

	function sassColors() {
		$templateId = getFieldFromId("template_id", "templates", "client_id", $GLOBALS['gClientId']);
		$resultSet = executeQuery("select * from sass_headers where client_id = ? and sass_header_id in (select sass_header_id from template_sass_headers where template_id = ?) order by sass_header_id", $GLOBALS['gClientId'], $templateId);
		if ($row = getNextRow($resultSet)) {
			$contentLines = getContentLines($row['content']);
			foreach ($contentLines as $thisLine) {
				$parts = explode(":", $thisLine);
				if (count($parts) != 2) {
					continue;
				}
				$variableName = trim($parts[0]);
				$variableValue = trim($parts[1], " ;");
				if (!startsWith($variableName, "$") || strpos($variableName, "color") === false || (strlen($variableValue) != 7 && strlen($variableValue) != 4)) {
					continue;
				}
				$label = ucwords(str_replace("$", "", str_replace("-", " ", $variableName)));
				$fieldName = "sass_header_" . makeCode($label, array("lowercase" => true));
				?>
                <div class="basic-form-line">
                    <label><?= $label ?></label>
                    <input tabindex="10" type='text' size='10' id='<?= $fieldName ?>' name='<?= $fieldName ?>' value='<?= $variableValue ?>' class='validate[required] minicolors'>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
				<?php
			}
		}
	}

	function templateTextChunks() {
		$resultSet = executeQuery("select * from template_text_chunks where template_id in (select template_id from templates where client_id = ? and include_crud = 0 and internal_use_only = 0 and inactive = 0)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$label = $row['description'];
			$fieldName = "template_text_chunk-" . $row['template_id'] . "-" . strtolower($row['template_text_chunk_code']);
			?>
            <div class="basic-form-line">
                <label><?= $label ?></label>
                <textarea id='<?= $fieldName ?>' name='<?= $fieldName ?>' taxindex="10"><?= htmlText($row['content']) ?></textarea>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}

	function javascript() {
		?>
        <script>
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

            function afterGetRecord() {
                $("#taxjar_api_token").trigger("change");
                $("#merchant_accounts_merchant_service_id").trigger("change");
                $(".merchant-account").addClass("hidden");

                setTimeout(function () {
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
                    $("#domain_name").trigger("change");
                }, 500);
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
                                let labeledFields = [ '_merchant_accounts_account_login_row', '_merchant_accounts_account_key_row', '_merchant_accounts_merchant_identifier_row', '_merchant_accounts_link_url_row' ];
                                if (!labeledFields.includes(this.id) && !this.id.startsWith('_merchant_accounts_custom_field')) {
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
            #status_table td {
                padding: 2px 10px;
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

            .integration-logo {
                width: 300px;
                max-width: 100%;
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

        </style>
		<?php
	}

	function onLoadJavascript() {
		$valuesArray = Page::getPagePreferences();
		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
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
            $(".license-lookup").change(function () {
                $licenseLookup = $(this);
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=validate_ffl&license_lookup=" + encodeURIComponent($(this).val()), function (returnArray) {
                        if ("ffl_information" in returnArray) {
                            $licenseLookup.closest("tr").find(".ffl-name").html(returnArray['ffl_information']);
                        }
                        if ("license_lookup" in returnArray) {
                            $licenseLookup.val(returnArray['license_lookup']);
                        }
                    });
                } else {
                    $licenseLookup.closest("tr").find(".ffl-name").html("");
                }
                return false;
            })
            $("#domain_name").change(function () {
                buildFeeds();
            });
            $("#create_gift_cards_button").click(function () {
                $("#create_gift_cards").val("1");
                $(this).closest("p").html("Gift Card Details will be created when CoreFORCE Setup is saved").addClass("red-text");
                return false;
            });
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
            $("#merchant_accounts_merchant_service_id").change(function () {
                loadFieldLabels();
            });
            $("#setup_nofraud").click(function () {
                if (empty($("#nofraud_customer_number").val())) {
                    displayErrorMessage("Save NoFraud Customer Number first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_nofraud", function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#_setup_nofraud").html(returnArray['error_message']).addClass("red-text");
                        } else {
                            $("#_setup_nofraud").html("NoFraud set up successfully").addClass("green-text");
                        }
                    });
                }
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
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_flp", {flp_affiliate_link: $("#flp_affiliate_link").val()}, function (returnArray) {
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
            $(".go-to-tab").click(function () {
                let tab = $($(this).prop("hash"));
                if (tab != "undefined") {
                    tab.find("a").trigger("click")
                }
            })

			<?php if ($giftCardExists) { ?>
            $("#create_gift_cards_wrapper").html("Gift card product type and product already exist").addClass("red-text");
			<?php } ?>


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

		if (!empty($_POST['domain_name'])) {
			$domainName = (substr($_POST['domain_name'], 0, 4) == "http" ? $_POST['domain_name'] : "https://" . $_POST['domain_name']);
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "WEB_URL");
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $domainName);
		}

		if (!empty($_POST['create_gift_cards'])) {
			$giftCardPage = array("page_code" => "RETAILSTOREPURCHASEGIFTCARD", "description" => "Purchase Gift Card", "link_name" => "purchase-gift-card", "script_filename" => "retailstore/purchasegiftcard.php", "page_access" => "public",
				"css_content" => "div.form-line { margin: 20px 0 40px 0; }\n.margin-div { width: 100%; border: 1px solid rgba(177, 177, 177, .4); box-shadow: 0 1px 0.5px 0 rgba(177, 177, 177, .4); padding: 20px; background: #fff; display: flex; }\n#form_wrapper { flex: 0 0 50%; padding: 20px;}\n@media (max-width: 700px) {\n\t.margin-div { flex-direction: column-reverse; }\n\t.gift-card-graphic { margin: 20px 0; }\n\t#_main_content_inner h2 { text-align: center; }\n}\n",
				"content" => "<div class='margin-div'>\n<div id='form_wrapper'>\n<h2>Purchase Gift Card</h2>\n<p>Add a gift card with the specified amount to your shopping cart. At checkout time, you will be able to personalize it.</p>\n<p class='error-message' id='error_message'></p>\n<form id='_edit_form'>\n<div class='form-line'>\n\t<label>Gift Card Amount</label>\n\t<input type='text' id='gift_card_amount' name='gift_card_amount' class='validate[required,custom[number]]' data-decimal-places='2'>\n\t<div class='clear-div'></div>\n</div>\n<p><button id='create_gift_card'>Add to Cart</button></p>\n</form>\n</div>\n<div class='gift-card-graphic'>\n<img src='/getimage.php?code=GIFTCARD_IMAGE' title='gift card'>\n</div>\n</div>");

			$templateId = getFieldFromId("template_id", "templates", "client_id", $GLOBALS['gClientId'], "inactive = 0 and template_id in (select template_id from pages where client_id = ? and script_filename = 'retailstore/checkoutv2.php')", $GLOBALS['gClientId']);
			$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "content");
			$afterFormTemplateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "after_form_content");
			if (!empty($templateId)) {
				$pageId = getFieldFromId("page_id", "pages", "template_id", $templateId, "script_filename = ?", $giftCardPage['script_filename']);
				if (empty($pageId)) {
					$insertSet = executeQuery("insert into pages (client_id,page_code,description,date_created,creator_user_id,link_name,template_id,javascript_code,css_content,script_filename) values (?,?,?,current_date,?,?,?,?,?,?)",
						$GLOBALS['gClientId'], $GLOBALS['gClientRow']['client_code'] . "_" . $giftCardPage['page_code'], $GLOBALS['gClientRow']['business_name'] . " " . $giftCardPage['description'],
						$GLOBALS['gUserId'], $giftCardPage['link_name'], $templateId, $giftCardPage['javascript_code'], $giftCardPage['css_content'], $giftCardPage['script_filename']);
					$pageId = $insertSet['insert_id'];
					if (!empty($giftCardPage['content'])) {
						executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $templateDataId, $giftCardPage['content']);
					}
					if (!empty($giftCardPage['after_form_content'])) {
						executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $afterFormTemplateDataId, $giftCardPage['after_form_content']);
					}
					switch ($giftCardPage['page_access']) {
						case "users":
							executeQuery("insert into page_access (page_id,all_user_access,permission_level) values (?,1,3)", $pageId);
							break;
						case "public":
							executeQuery("insert into page_access (page_id,public_access,permission_level) values (?,1,3)", $pageId);
							break;
					}
					if (!empty($giftCardPage['additional_link_names'])) {
						foreach ($giftCardPage['additional_link_names'] as $thisLinkName) {
							executeQuery("insert into page_aliases (page_id,link_name) values (?,?)", $pageId, $thisLinkName);
						}
					}
				}
			}

			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "MANUAL_GIFT_CARD_ISSUANCE");
			if (empty($preferenceId)) {
				$resultSet = executeQuery("insert into preferences (preference_code,description,data_type) values ('MANUAL_GIFT_CARD_ISSUANCE','Manual Gift Card Issuance','tinyint')");
				$preferenceId = $resultSet['insert_id'];
			}
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,'true')", $GLOBALS['gClientId'], $preferenceId);
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
				if (empty($customFieldId)) {
					$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDER_ITEMS");
					$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
						$GLOBALS['gClientId'], "RECIPIENT_EMAIL_ADDRESS", "Recipient Email Address", $customFieldTypeId, "Recipient Email Address");
					$customFieldId = $insertSet['insert_id'];
					executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','varchar')", $customFieldId);
					executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_format','email')", $customFieldId);
				}
				$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "product_id", $giftCardProductId, "custom_field_id = ?", $customFieldId);
				if (empty($productCustomFieldId)) {
					executeQuery("insert ignore into product_custom_fields (product_id,custom_field_id) values (?,?)", $giftCardProductId, $customFieldId);
				}
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "GIFT_MESSAGE");
				if (empty($customFieldId)) {
					$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDER_ITEMS");
					$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
						$GLOBALS['gClientId'], "GIFT_MESSAGE", "Gift Message", $customFieldTypeId, "Gift Message");
					$customFieldId = $insertSet['insert_id'];
					executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','text')", $customFieldId);
				}
				$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "product_id", $giftCardProductId, "custom_field_id = ?", $customFieldId);
				if (empty($productCustomFieldId)) {
					executeQuery("insert ignore into product_custom_fields (product_id,custom_field_id) values (?,?)", $giftCardProductId, $customFieldId);
				}
			}
		}

		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_coreware_defaults", $postParameters);
		$responseArray = json_decode($response, true);
		$defaults = $responseArray['defaults'];

		if (!array_key_exists("emails", $defaults)) {
			$defaults['emails'] = array();
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

		executeQuery("update payment_methods set fee_percent = ? where client_id = ? and payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_CARD')", $_POST['credit_card_handling_fee'], $GLOBALS['gClientId']);

		$clientFields = array("business_name", "first_name", "last_name", "address_1", "address_2", "city", "state", "postal_code", "email_address");
		$contactDataTable = new DataTable("contacts");
		$nameValues = array();
		foreach ($clientFields as $fieldName) {
			$nameValues[$fieldName] = $_POST["client_" . $fieldName];
		}
		$contactDataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $GLOBALS['gClientRow']['contact_id']));
		removeCachedData("client_row", $GLOBALS['gClientId'], true);
		removeCachedData("client_name", $GLOBALS['gClientId'], true);
		if (!empty($_POST['client_phone_number'])) {
			$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $GLOBALS['gClientRow']['contact_id'], "description = 'Store'");
			if (empty($phoneNumberId)) {
				$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $GLOBALS['gClientRow']['contact_id']);
			}
			$phoneNumberDataTable = new DataTable("phone_numbers");
			$phoneNumberDataTable->saveRecord(array("name_values" => array("phone_number" => $_POST['client_phone_number'], "description" => "Store"), "primary_id" => $phoneNumberId));
		}

		$locationDataTable = new DataTable("locations");
		$locationDataTable->setSaveOnlyPresent(true);
		$resultSet = executeQuery("select * from locations where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
            if (array_key_exists("location_description_" . $row['location_id'],$_POST)) {
                if (strlen($_POST['license_lookup_' . $row['location_id']]) > 10) {
	                $_POST['license_lookup_' . $row['location_id']] = substr($_POST['license_lookup_' . $row['location_id']], 0, 10);
                }
	            $locationDataTable->saveRecord(array("name_values" => array("description" => $_POST['location_description_' . $row['location_id']], "license_lookup" => $_POST['license_lookup_' . $row['location_id']], "ignore_inventory" => (empty($_POST['ignore_inventory_' . $row['location_id']]) ? "0" : "1"), "internal_use_only" => (empty($_POST['internal_use_only_' . $row['location_id']]) ? "0" : "1")), "primary_id" => $row['location_id']));
            }
		}
		$resultSet = executeQuery("select * from sass_headers where client_id = ? order by sass_header_id", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$contentLines = getContentLines($row['content']);
			$newContent = "";
			foreach ($contentLines as $thisLine) {
				$parts = explode(":", $thisLine);
				if (count($parts) != 2) {
					$newContent .= $thisLine . "\n";
					continue;
				}
				$variableName = trim($parts[0]);
				$variableValue = trim($parts[1], " ;");
				if (strpos($variableName, "color") === false || strlen($variableValue) != 7) {
					$newContent .= $thisLine . "\n";
					continue;
				}
				$label = ucwords(str_replace("$", "", str_replace("-", " ", $variableName)));
				$fieldName = "sass_header_" . makeCode($label, array("lowercase" => true));
				if (!array_key_exists($fieldName, $_POST)) {
					$newContent .= $thisLine . "\n";
					continue;
				}
				$newContent .= $variableName . ": " . $_POST[$fieldName] . ";\n";
			}
			executeQuery("update sass_headers set content = ? where sass_header_id = ?", $newContent, $row['sass_header_id']);
			$templateId = getFieldFromId("template_id", "templates", "client_id", $GLOBALS['gClientId'], "inactive = 0 and template_id in (select template_id from pages where client_id = ? and script_filename = 'retailstore/checkoutv2.php')", $GLOBALS['gClientId']);
			if (!empty($templateId)) {
				removeCachedData("sass_headers", $templateId, true);
				removeCachedData("template_row", $templateId, true);
			}
		}

		foreach ($_POST as $fieldName => $fieldValue) {
			if (!startsWith($fieldName, "template_text_chunk-")) {
				continue;
			}
			$parts = explode("-", $fieldName);
			$templateId = $parts[1];
			$templateTextChunk = $parts[2];
			executeQuery("update template_text_chunks set content = ? where template_id = ? and template_text_chunk_code = ?", $fieldValue, $templateId, strtoupper($templateTextChunk));
		}
		executeQuery("update analytics_code_chunks set content = ? where client_id = ? and analytics_code_chunk_code = 'WEBSITE_CODE'", $_POST['analytics_code'], $GLOBALS['gClientId']);

		$coreClearAvsPreferences = array();
		foreach ($_POST as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("coreclear_avs_approval_")) != "coreclear_avs_approval_") {
				continue;
			}
			$coreClearAvsPreferences[substr($fieldName, strlen("coreclear_avs_approval_"))] = $fieldValue;
		}
		Page::setClientPagePreferences($coreClearAvsPreferences, "CORECLEAR_AVS_APPROVALS");

		$this->createUrlAliasTypes();

		if (!empty($_POST['setup_merchant_account'])) {
			$merchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_code", "DEFAULT");
			$dataSource = new DataSource("merchant_accounts");
			if (empty($merchantAccountRow)) {
				$merchantAccountRow['description'] = "Default Merchant Account";
				$merchantAccountRow['merchant_account_code'] = "DEFAULT";
			}
			$customFieldNameValues = array();
			foreach ($_POST as $fieldName => $fieldValue) {
				if (!empty($fieldValue) && substr($fieldName, 0, strlen("merchant_accounts_")) == "merchant_accounts_") {
					$merchantAccountFieldName = substr($fieldName, strlen("merchant_accounts_"));
					if (substr($merchantAccountFieldName, 0, strlen("custom_field")) == "custom_field") {
						$customFieldNameValues[$merchantAccountFieldName] = array("primary_id" => $merchantAccountRow['merchant_account_id'], $merchantAccountFieldName => $fieldValue);
					} else {
						$merchantAccountRow[$merchantAccountFieldName] = $fieldValue;
					}
				}
			}
			if (!($merchantAccountId = $dataSource->saveRecord(array("name_values" => $merchantAccountRow, "primary_id" => $merchantAccountRow['merchant_account_id'])))) {
				$returnArray['error_message'] = $dataSource->getErrorMessage();
				ajaxResponse($returnArray);
			}
			$customFields = CustomField::getCustomFields("merchant_accounts");
			foreach ($customFields as $thisCustomField) {
				$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
				if (array_key_exists($customField->getColumnName(), $customFieldNameValues)) {
					if (!$customField->saveData($customFieldNameValues[$customField->getColumnName()], $merchantAccountId)) {
						$returnArray['error_message'] = $customField->getErrorMessage();
						ajaxResponse($returnArray);
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
				$returnArray['error_message'] = $dataSource->getErrorMessage();
				ajaxResponse($returnArray);
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
			$pageId = getFieldFromId("page_id", "pages", "script_filename", $thisAliasType['script_filename']);
			if (empty($pageId)) {
				continue;
			}
			$tableId = getFieldFromId("table_id", "tables", "table_name", $thisAliasType['table_name']);
			executeQuery("insert into url_alias_types (client_id,url_alias_type_code,description,table_id,page_id,parameter_name) values (?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $thisAliasType['url_alias_type_code'], $thisAliasType['description'], $tableId, $pageId, $thisAliasType['parameter_name']);
		}
	}

	function goLiveChecklist() {

		if (!$GLOBALS['gDevelopmentServer'] && getPreference("system_name") != 'COREWARE20') {
			return;
		}

		$allDone = true;

		$itemsArray = array();
		$title = "Watch coreU course 'Getting Started with coreFIRE'";
		$actionText = '<a href="https://coreu.coreware.com/course/corefire-getting-started">Getting Started with coreFIRE in coreU</a>';
		$itemsArray[] = array("title" => $title, "status" => "N/A", "action" => $actionText);

		$title = "Verify Email Address";
		$done = !empty(getFieldFromId("email_log_id", "email_log", "client_id", $GLOBALS['gClientId'], "parameters not like '%\"primary_client\":true%'"));
		$allDone = $done && $allDone;
		$actionText = '<a href="https://help.coreware.com">Contact Support</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		$title = "Add Distributor Credentials";
		$done = !empty(getFieldFromId("location_credential_id", "location_credentials", "location_id",
			getFieldFromId("location_id", "locations", "client_id", $GLOBALS['gClientId'], "product_distributor_id is not null")));
		$allDone = $done && $allDone;
		$actionText = '<a href="/location-credentials">Distributor Credentials</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		$title = "Connect coreSTORE";
		$done = (!empty(getPreference("CORESTORE_ENDPOINT")) && !empty(getPreference("CORESTORE_API_KEY")));
		$allDone = $done && $allDone;
		$actionText = 'Use Get Credentials in coreSTORE';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		$title = "Homepage Setup: Menu, Products, & Brands";
		$done = (!empty(getFieldFromId("log_id", "change_log", "primary_identifier",
			getFieldFromId("page_id", "pages", "link_name", "home"), "table_name = 'pages'")));
		$actionText = '<a href="/pagecreator.php">Page Creator</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='orange-text'>No Recent changes</span>"), "action" => ($done ? "" : $actionText));

		$title = "Personalize coreFIRE Website";
		$actionText = '<a href="/pagecreator.php">Page Creator</a>';
		$itemsArray[] = array("title" => $title, "status" => "<span class='green-text'>Optional</span>", "action" => $actionText);

		$title = "Set Up Pricing Structures";
		$done = !empty(getFieldFromId("pricing_structure_id", "pricing_structures", "pricing_structure_code", "DEFAULT"));
		$allDone = $done && $allDone;
		$actionText = '<a href="#_pricing_tab" class="go-to-tab">Pricing</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		$title = "Set Up Tax Rates";
		$done = !empty(getFieldFromId("state_tax_rate_id", "state_tax_rates", "client_id", $GLOBALS['gClientId']));
		$done = $done || !empty(getFieldFromId("postal_code_tax_rate_id", "postal_code_tax_rates", "client_id", $GLOBALS['gClientId']));
		$done = $done || !empty(getPreference("taxjar_api_token"));
		$actionText = '<a href="/state-tax-rates">State Tax Rates</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='orange-text'>None Created</span>"), "action" => ($done ? "" : $actionText));

		$title = "Set Up Shipping Charges";
		$done = !empty(getFieldFromId("log_id", "change_log", "client_id", $GLOBALS['gClientId'], "table_name = 'shipping_charges'"));
		$actionText = '<a href="/shipping-charges">Shipping Charges</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='orange-text'>No Recent changes</span>"), "action" => ($done ? "" : $actionText));

		$title = "Set Up Merchant Account";
		$done = !empty(getFieldFromId("merchant_account_id", "merchant_accounts", "client_id", $GLOBALS['gClientId'], "inactive = 0"));
		$allDone = $done && $allDone;
		$actionText = '<a href="#_merchant_tab" class="go-to-tab">Merchant Account</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		$title = "Connect System Integrations";
		$actionText = 'See other tabs';
		$itemsArray[] = array("title" => $title, "status" => "<span class='green-text'>Optional</span>", "action" => $actionText);

		$title = "Test Checkout";
		$done = !empty(getFieldFromId("order_id", "orders", "client_id", $GLOBALS['gClientId']));
		$allDone = $done && $allDone;
		$actionText = '<a href="/home">Place Order</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		$title = "Point Domain Name to coreFIRE";
		$domainNameRow = getRowFromId("domain_names", "domain_client_id", $GLOBALS['gClientId'], "domain_name not like '%corefire.shop' and forward_domain_name is null");
		if (empty($domainNameRow)) {
			$domainNameRow = getRowFromId("domain_names", "domain_client_id", $GLOBALS['gClientId'], "domain_name not like '%corefire.shop'");
		}
		$domainTestResult = getCachedData("domain_test_result", $domainNameRow['domain_name']);
		if (empty($domainTestResult)) {
			$domainTestResult = getCurlReturn("https://" . $domainNameRow['domain_name'], array(), 3);
			setCachedData("domain_test_result", $domainNameRow['domain_name'], $domainTestResult, .1);
		}
		$done = stristr($domainTestResult, "coreware.com") !== false;
		$allDone = $done && $allDone;
		$domainDone = $done;
		$actionText = '<a href="https://help.coreware.com/support/solutions/articles/73000584972-pointing-your-domain-name-to-corefire">Go-live: Pointing your Domain Name to coreFIRE</a>';
		$itemsArray[] = array("title" => $title, "status" => ($done ? "<span class='green-text'>Done</span>" : "<span class='red-text'>Not Done</span>"), "action" => ($done ? "" : $actionText));

		if (!$allDone) {
			if (!$domainDone) {
				echo "<h3 class='red-text'>Your site is not yet live. You have pending action items below.</h3>";
			} else {
				echo "<h3 class='red-text'>Important: Your site is live, but you have pending action items below.</h3>";
			}
		}
		echo "<table id='status_table'><tr><th>Item</th><th>Status</th><th>Action Item</th></tr>";
		foreach ($itemsArray as $thisItem) {
			echo sprintf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>", $thisItem['title'], $thisItem['status'], $thisItem['action']);
		}
		echo "</table>";
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

	function checkNoFraudSetup() {
		if (!NoFraud::isSetup()) {
			?>
            <div id="_setup_nofraud">
                <button id="setup_nofraud">Set Up NoFraud</button>
            </div>
		<?php } else { ?>
            NoFraud is properly set up.
		<?php } ?>
        <div class='clear-div'></div>
		<?php
	}

	function checkFlpSetup() {
		$result = FirearmsLegalProtection::needsSetup();
		if ($result) {
			?>
            <div id="_setup_flp">
                <button id="setup_flp"><?= $result ?></button>
            </div>
		<?php } else { ?>
            FLP product is set up and ready for sale.
		<?php } ?>
        <div class='clear-div'></div>
		<?php
	}

	function checkCredovaSetup() {
		$credovaCredentials = getCredovaCredentials();
		if (!empty($credovaCredentials)) {
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
}


$pageObject = new OrdersSetupLitePage("clients");
$pageObject->displayPage();
