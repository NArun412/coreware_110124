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

$GLOBALS['gPageCode'] = "CLIENTONBOARD";
require_once "shared/startup.inc";
require_once "retailstoresetup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 3000000;

class ClientOnboardPage extends Page {

	var $iSubstitutions = array();
	var $iCustomFields = array();
	var $iDistributors = array();
	var $iUrlAliasTypes = array();
	var $iPages = array();
	var $iShippingCarriers = array();
	// unsupported email domains do not work with SES.
	var $iUnsupportedEmailDomains = array("yahoo.com", "outlook.com", "hotmail.com", "live.com");
	private $iLogging;
	private $iLogEntry;
	private $iLastLogTime;
	private $iFirstLogTime;
	private $iLastMemory;

    function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_template_custom_fields":
				$customFields = CustomField::getCustomFields("templates");
				ob_start();
				$resultSet = executeQuery("select * from template_custom_fields where template_id = ? order by sequence_number", $_GET['template_id']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['custom_field_id'], $customFields)) {
						continue;
					}
					$customField = CustomField::getCustomField($row['custom_field_id']);
					echo $customField->getControl();
				}
				$returnArray['website_custom_fields'] = ob_get_clean();
				$imageId = getFieldFromId("image_id", "templates", "template_id", $_GET['template_id']);
				if (empty($imageId)) {
					$returnArray['template_image'] = "";
				} else {
					$returnArray['template_image'] = '<a href="' . getImageFilename($imageId, array("use_cdn" => true)) . '" class="pretty-photo"><img src="' . getImageFilename($imageId, array("use_cdn" => true, "image_type_code" => "small")) . '" alt="Template Image" title="Template Image"></a>';
				}
				ajaxResponse($returnArray);
				exit;
			case "check_email_address":
				$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_GET['email_address'],
						"contact_id in (select contact_id from users)");
				if (!empty($existingContactId)) {
					$returnArray['error_email_address_message'] = "A User already exists with this email address.";
				}
				foreach ($this->iUnsupportedEmailDomains as $unsupportedEmailDomain) {
					if (stristr($_GET['email_address'], $unsupportedEmailDomain) !== false) {
						$returnArray['error_email_address_message'] = $unsupportedEmailDomain . " email addresses are not supported. Please use a different email address.";
						break;
					}
				}
				ajaxResponse($returnArray);
				exit;
		}
	}

	function setup() {
		$this->iPreferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "COMPLETE_CLIENT_ONBOARD");
		if (empty($this->iPreferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code,description,client_setable,data_type,internal_use_only) values ('COMPLETE_CLIENT_ONBOARD'," .
				"'Complete Client Onboard',1,'tinyint',1)");
			$this->iPreferenceId = $resultSet['insert_id'];
		}
		$this->iLogging = !empty(getPreference("LOG_CLIENT_ONBOARD"));
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "list", "add"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
	}

	function internalCSS() {
		?>
		<style>
			#default_product_markup {
				width: 120px;
				text-align: right;
			}

			#_changes_button, #_delete_button, #_list_button, #_add_button {
				display: none;
			}

			#template_image img {
				max-height: 250px;
				margin-bottom: 20px;
				border: 5px solid rgb(200, 200, 200);
			}

		</style>
		<?php
	}

	function templateChoices($showInactive = false) {
		$templateGroupCode = $this->getPageTextChunk("template_group_code");
		if (empty($templateGroupCode)) {
			$templateGroupCode = "COREFIRE";
		}
		$templateChoices = array();
		$resultSet = executeQuery("select * from templates where inactive = 0 and include_crud = 0 and client_id = ? and template_group_id in (select template_group_id from template_groups where template_group_code = ?)", $GLOBALS['gClientId'], $templateGroupCode);
		while ($row = getNextRow($resultSet)) {
			$templateChoices[$row['template_id']] = array("key_value" => $row['template_id'], "description" => $row['description'], "inactive" => false);
		}
		return $templateChoices;
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->getPrimaryTable()->setLimitByClient(false);
		$this->iDataSource->getJoinTable()->setLimitByClient(false);
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("template_id", "not_null", "true");
		$this->iDataSource->addColumnControl("template_id", "data_type", "select");
		$this->iDataSource->addColumnControl("template_id", "get_choices", "templateChoices");
		$this->iDataSource->addColumnControl("template_id", "form_label", "Website Template");

		$this->iDataSource->addColumnControl("client_timezone", "not_null", "true");
		$this->iDataSource->addColumnControl("client_timezone", "data_type", "select");
		$this->iDataSource->addColumnControl("client_timezone", "choices", array("America/New_York" => "Eastern", "America/Chicago" => "Central", "America/Denver" => "Mountain", "America/Los_Angeles" => "Pacific", "America/Anchorage" => "Alaska", "Pacific/Honolulu" => "Hawaii"));
		$this->iDataSource->addColumnControl("client_timezone", "form_label", "Your Timezone");

		$this->iDataSource->addColumnControl("allow_pickup", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("allow_pickup", "form_label", "Pickup available at this location");
		$this->iDataSource->addColumnControl("allow_pickup", "initial_value", "1");

		$this->iDataSource->addColumnControl("business_name", "not_null", "true");
		$this->iDataSource->addColumnControl("first_name", "not_null", "true");
		$this->iDataSource->addColumnControl("last_name", "not_null", "true");
		$this->iDataSource->addColumnControl("address_1", "not_null", "true");
		$this->iDataSource->addColumnControl("city", "not_null", "true");
		$this->iDataSource->addColumnControl("state", "not_null", "true");
		$this->iDataSource->addColumnControl("postal_code", "not_null", "true");
		$this->iDataSource->addColumnControl("country_id", "not_null", "true");
		$this->iDataSource->addColumnControl("email_address", "not_null", "true");
		$this->iDataSource->addColumnControl("email_address", "form_label", "Store Email Address");
		$this->iDataSource->addColumnControl("web_page", "not_null", "true");

		$this->iDataSource->addColumnControl("logo_image_id", "not_null", "true");
		$this->iDataSource->addColumnControl("logo_image_id", "data_type", "image_input");
		$this->iDataSource->addColumnControl("logo_image_id", "form_label", "Logo");

		$this->iDataSource->addColumnControl("favicon_image_id", "not_null", "true");
		$this->iDataSource->addColumnControl("favicon_image_id", "data_type", "image_input");
		$this->iDataSource->addColumnControl("favicon_image_id", "form_label", "Favicon/Icon");
		$this->iDataSource->addColumnControl("favicon_image_id", "help_label", "This icon will be displayed in the web browser tab.");

		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("country_id", "default_value", "1000");
		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("date_created", "readonly", "true");
		$this->iDataSource->addColumnLikeColumn("store_phone_number", "phone_numbers", "phone_number");
		$this->iDataSource->addColumnControl("store_phone_number", "data_format", "phone");
		$this->iDataSource->addColumnControl("store_phone_number", "form_label", "Store Phone Number");
		$this->iDataSource->addColumnControl("start_date", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("state", "css-width", "60px");

		$this->iDataSource->addColumnControl("city_select", "initial_value", $GLOBALS['gUserRow']["city"]);
		$this->iDataSource->addColumnControl("state_select", "initial_value", $GLOBALS['gUserRow']["state"]);
		$phoneNumber = Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'], "Store");
		$this->iDataSource->addColumnControl("store_phone_number", "initial_value", $phoneNumber);

		$this->iDataSource->addColumnLikeColumn("new_user_user_name", "users", "user_name");
		$this->iDataSource->addColumnControl("business_name", "not_null", true);
		$this->iDataSource->addColumnControl("new_user_user_name", "not_null", "true");
		$this->iDataSource->addColumnControl("new_user_user_name", "classes", "allow-dash");
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("new_user_user_name", "initial_value", $GLOBALS['gUserRow']['user_name']);
		}
		$this->iDataSource->addColumnLikeColumn("new_user_password", "users", "password");
		$this->iDataSource->addColumnControl("new_user_password", "not_null", "true");
		$this->iDataSource->addColumnControl("new_user_password", "data_type", "password");
		$this->iDataSource->addColumnControl("new_user_password", "show_password", true);
		$this->iDataSource->addColumnLikeColumn("new_user_password_confirm", "users", "password");
		$this->iDataSource->addColumnControl("new_user_password_confirm", "validation_classes", "equals[new_user_password]");
		$this->iDataSource->addColumnControl("new_user_password_confirm", "form_label", "Retype Password");
		$this->iDataSource->addColumnControl("new_user_password_confirm", "data_type", "password");
		$this->iDataSource->addColumnControl("new_user_password_confirm", "show_password", true);
		$this->iDataSource->addColumnLikeColumn("new_user_email_address", "contacts", "email_address");
		$this->iDataSource->addColumnControl("new_user_email_address", "not_null", "true");
		$this->iDataSource->addColumnControl("new_user_email_address", "form_label", "User's Email Address");
	}

	function newUserPassword() {
		$minimumPasswordLength = getPreference("minimum_password_length");
		if (empty($minimumPasswordLength)) {
			$minimumPasswordLength = 10;
		}
		?>
		<div class="basic-form-line" id="_new_user_password_row">
			<label for="new_user_password" class="required-label">Password</label>
			<span class='help-label'>Create a secure password, minimum length of <?= $minimumPasswordLength ?></span>
			<input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]<?= ($GLOBALS['gLoggedIn'] ? "" : ",required") ?>] password-strength" type="password" size="40" maxlength="40" id="new_user_password" name="new_user_password" value=""><span class='fad fa-eye show-password'></span>
			<div class='strength-bar-div hidden' id='new_user_password_strength_bar_div'>
				<p class='strength-bar-label' id='new_user_password_strength_bar_label'></p>
				<div class='strength-bar' id='new_user_password_strength_bar'></div>
			</div>
			<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
		</div>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$customFieldGroupCode = getPageTextChunk("custom_field_group_code");
		if (empty($customFieldGroupCode)) {
			$customFieldGroupCode = "CLIENT_ONBOARD";
		}
		$customFields = CustomField::getCustomFields("contacts", $customFieldGroupCode);
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['contact_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
	}

	function paymentDetails() {

		?>
		<div class='basic-form-line'>
			<label for='default_markup' class='required-label'>Default Product Markup</label>
			<span class='help-label'>You can add other product pricing structures later</span>
			<input type='text' size='12' id='default_product_markup' name='default_product_markup' class='validate[required,custom[number],min[5]]' data-decimal-places='2'>
			<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
		</div>
		<?php

		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}

		$distributorArray = array();
		$resultSet = executeQuery("select * from product_distributors where inactive = 0 and internal_use_only = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$distributorArray[] = $row;
		}
		if (empty($distributorArray)) {
			?>
			<h3>No distributor feeds available</h3>
			<?php
		} else {
			?>
			<h3>Choose your distributors</h3>
			<div class="basic-form-line" id="_distributors_row">
				<?php
				foreach ($distributorArray as $index => $thisDistributor) {
					?>
					<input type="checkbox"<?= (count($distributorArray) == 1 ? " checked" : "") ?> class="product-distributor" name="product_distributor_id_<?= $index ?>" id="product_distributor_id_<?= $index ?>" value="<?= $thisDistributor['product_distributor_id'] ?>"><label class="checkbox-label" for="product_distributor_id_<?= $index ?>"><?= htmlText($thisDistributor['description']) ?></label><br>
					<?php
				}
				?>
				<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
			</div>
			<?php
		}
	}

	function addResultLog($resultLine = "", $includeTotalTime = false) {
		$timeNow = getMilliseconds();
		$this->iFirstLogTime = $this->iFirstLogTime ?: $timeNow;
		$this->iLastLogTime = $this->iLastLogTime ?: $timeNow;
		$resultLine .= " (" . getTimeElapsed($this->iLastLogTime, $timeNow) . ")";
		$this->iLastLogTime = $timeNow;
		if ($GLOBALS['gDevelopmentServer'] || $this->iLogging) {
			$currentMemory = memory_get_usage() / 1000;
			$memoryChange = $currentMemory - $this->iLastMemory;
			$this->iLastMemory = $currentMemory;
			addDebugLog($resultLine . " Memory Used: " . number_format($currentMemory, 0, "", ",")
					. " KB Change: " . number_format($memoryChange, 0, "", ",") . " KB");
		}
		$this->iLogEntry .= (empty($this->iLogEntry) ? "" : "\n") . $resultLine;
		if ($includeTotalTime) {
			$this->iLogEntry .= (empty($this->iLogEntry) ? "" : "\n") . "Total time taken: " . getTimeElapsed($this->iFirstLogTime, $timeNow);
		}
	}

	function getResultLog() {
		return $this->iLogEntry;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$this->addResultLog("Starting Client Onboard for " . $nameValues['business_name']);
		loadSetupVariables($this);

		removeCachedData("client_subsystems", "client_subsystems");
		removeCachedData("client_subsystem_codes", "client_subsystem_codes");

		$templateId = $nameValues['template_id'];
		$templateCode = "";
		$resultSet = executeQuery("select * from templates where template_id = ?", $templateId);
		if ($row = getNextRow($resultSet)) {
			$templateCode = $row['template_code'];
		}
		if (array_key_exists("logo_image_id_file", $_FILES) && !empty($_FILES['logo_image_id_file']['name'])) {
			$logoImageId = createImage("logo_image_id_file", array("maximum_width" => 500, "maximum_height" => 500, "image_code" => "HEADER_LOGO", "client_id" => $nameValues['primary_id']));
			$logoImageId = createImage("logo_image_id_file", array("maximum_width" => 500, "maximum_height" => 500, "image_code" => $templateCode . "_HEADER_LOGO", "client_id" => $nameValues['primary_id']));
			if ($logoImageId === false) {
				return "Error processing image";
			}
		}
		if (array_key_exists("favicon_image_id_file", $_FILES) && !empty($_FILES['favicon_image_id_file']['name'])) {
			$faviconImageId = createImage("favicon_image_id_file", array("maximum_width" => 500, "maximum_height" => 500, "image_code" => "FAVICON", "client_id" => $nameValues['primary_id']));
			if ($faviconImageId === false) {
				return "Error processing image";
			}
		}
		$clientId = $nameValues['primary_id'];
		$resultSet = executeQuery("select * from clients join contacts using (contact_id) where clients.client_id = ?", $clientId);
		$clientRow = getNextRow($resultSet);
		$clientContactId = $clientRow['contact_id'];
		$clientCode = $clientRow['client_code'];
		executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Store')", $clientRow['contact_id'], $nameValues['store_phone_number']);

		$resultSet = executeQuery("select * from subsystems");
		while ($row = getNextRow($resultSet)) {
			executeQuery("insert ignore into client_subsystems (client_id,subsystem_id) values (?,?)", $clientId, $row['subsystem_id']);
		}

		$substitutions = $clientRow;
		$substitutions['template_id'] = $nameValues['template_id'];
		$substitutions['store_phone_number'] = $nameValues['store_phone_number'];
		$substitutions['store_email_address'] = $nameValues['email_address'];
		$substitutions['address_block'] = $clientRow['address_1'] . "<br>" . (empty($clientRow['address_2']) ? "" : $clientRow['address_2'] . "<br>") . $clientRow['city'] . ", " . $clientRow['state'] . " " . $clientRow['postal_code'];

		$this->addResultLog("Client Row created");

		# Save Custom Fields

		$customFieldGroupCode = getPageTextChunk("custom_field_group_code");
		if (empty($customFieldGroupCode)) {
			$customFieldGroupCode = "CLIENT_ONBOARD";
		}
		$customFields = CustomField::getCustomFields("contacts", $customFieldGroupCode);
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$substitutions[strtolower($thisCustomField['custom_field_code'])] = $nameValues['custom_field_id_' . $thisCustomField['custom_field_id']];
			$customFieldData = $nameValues;
			$customFieldData['primary_id'] = $nameValues['contact_id'];
			if (!$customField->saveData($customFieldData)) {
				return $customField->getErrorMessage();
			}
		}
		$templateTextChunks = array();
		$customFields = CustomField::getCustomFields("templates");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$dataType = $customField->getColumnControl("data_type");
			if (empty($customField->getColumnControl("ignore_blank")) || !empty($nameValues['custom_field_id_' . $thisCustomField['custom_field_id']])) {
				if ($dataType == "text") {
					$customFieldValue = makeHtml($nameValues['custom_field_id_' . $thisCustomField['custom_field_id']], array("use_br" => true, "no_outer_wrapper" => true));
				} else {
					$customFieldValue = $nameValues['custom_field_id_' . $thisCustomField['custom_field_id']];
				}
				$substitutions[strtolower($thisCustomField['custom_field_code'])] = $customFieldValue;
				$templateTextChunks[strtolower($thisCustomField['custom_field_code'])] = $customFieldValue;
			}
		}

		$this->addResultLog("Custom Fields created");

		# Copy User Group

		$userGroupCode = $this->getPageTextChunk("user_group_code");
		if (empty($userGroupCode)) {
			$userGroupCode = "COREFIRE_ADMIN";
		}
		$primaryUserGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_code", $userGroupCode);
		$userGroupId = false;
		if (!empty($primaryUserGroupId)) {
			$resultSet = executeQuery("insert into user_groups (client_id,user_group_code,description) select ?,user_group_code,description from user_groups where user_group_id = ?",
					$clientId, $primaryUserGroupId);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
			$userGroupId = $resultSet['insert_id'];
			$resultSet = executeQuery("insert into user_group_access (user_group_id,page_id,permission_level) select ?,page_id,permission_level from user_group_access where user_group_id = ?",
					$userGroupId, $primaryUserGroupId);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
			$resultSet = executeQuery("insert into user_group_subsystem_access (user_group_id,subsystem_id,permission_level) select ?,subsystem_id,permission_level from user_group_subsystem_access where user_group_id = ?",
					$userGroupId, $primaryUserGroupId);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
		}
		$this->addResultLog("Copy User Group done");

		# Copy Client Page Templates

		$resultSet = executeQuery("insert into client_page_templates (client_id,page_id,template_id) " .
				"select ?,page_id,template_id from client_page_templates where client_id = ?", $clientId, $GLOBALS['gClientId']);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$this->addResultLog("Copy Client Page Templates done");

		# Copy Order Statuses

		$resultSet = executeQuery("insert into order_status (client_id,order_status_code,description,display_color,mark_completed,internal_use_only) " .
				"select ?,order_status_code,description,display_color,mark_completed,internal_use_only from order_status where client_id = ? and inactive = 0", $clientId, $GLOBALS['gClientId']);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$this->addResultLog("Copy Order Statuses done");

		# Copy Product Tags

		$resultSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,detailed_description,display_color,link_name,requires_user,cannot_sell,internal_use_only) " .
				"select ?,product_tag_code,description,detailed_description,display_color,link_name,requires_user,cannot_sell,internal_use_only from product_tags where client_id = ? and inactive = 0", $clientId, $GLOBALS['gClientId']);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$this->addResultLog("Copy Product Tags done");

		# Copy Product Manufacturer Tags

		$productManufacturerTags = array();
		$resultSet = executeQuery("select * from product_manufacturer_tags where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$insertSet = executeQuery("insert into product_manufacturer_tags (client_id,product_manufacturer_tag_code,description,internal_use_only) " .
					"values (?,?,?,?)", $clientId, $row['product_manufacturer_tag_code'], $row['description'], $row['internal_use_only']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$productManufacturerTags[$row['product_manufacturer_tag_id']] = array("product_manufacturer_tag_id" => $insertSet['insert_id'], "product_manufacturer_codes" => array());
		}
		$resultSet = executeQuery("select product_manufacturer_code,product_manufacturer_tag_id from product_manufacturer_tag_links join product_manufacturers using (product_manufacturer_id) where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['product_manufacturer_tag_id'], $productManufacturerTags)) {
				$productManufacturerTags[$row['product_manufacturer_tag_id']]['product_manufacturer_codes'][$row['product_manufacturer_code']] = $row['product_manufacturer_code'];
			}
		}
		$this->addResultLog("Copy Product Manufacturer Tags done");

		# Copy Shipping Methods

		$shippingMethods = array();
		$resultSet = executeQuery("select * from shipping_methods where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$insertSet = executeQuery("insert into shipping_methods (client_id,shipping_method_code,description,pickup,internal_use_only,inactive) " .
					"values (?,?,?,?,?,?)", $clientId, $row['shipping_method_code'], $row['description'], $row['pickup'], $row['internal_use_only'], (!empty($row['pickup']) && empty($_POST['allow_pickup']) ? "1" : "0"));
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$shippingMethods[$row['shipping_method_id']] = $insertSet['insert_id'];
		}
		$this->addResultLog("Copy Shipping Methods done");

		# Copy Shipping Charges

		$resultSet = executeQuery("select * from shipping_charges where shipping_method_id in (select shipping_method_id from shipping_methods where client_id = ?)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$row['shipping_charge_id'] = "";
			$row['shipping_method_id'] = $shippingMethods[$row['shipping_method_id']];
			if (empty($row['shipping_method_id'])) {
				continue;
			}
			$insertSet = executeQuery("insert into shipping_charges values (" . implode(",", array_fill(0, count($row), "?")) . ")", $row);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$shippingChargeId = $insertSet['insert_id'];
			executeQuery("insert into shipping_locations (shipping_charge_id,sequence_number,country_id) values (?,1,1000)", $shippingChargeId);
		}
		$this->addResultLog("Copy Shipping Charges done");

		# Copy Payment Method Types

		$resultSet = executeQuery("select * from payment_method_types where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		$paymentMethodTypes = array();
		while ($row = getNextRow($resultSet)) {
			$insertSet = executeQuery("insert into payment_method_types (client_id,payment_method_type_code,description,internal_use_only) " .
					"values (?,?,?,?)", $clientId, $row['payment_method_type_code'], $row['description'], $row['internal_use_only']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$paymentMethodTypes[$row['payment_method_type_id']] = $insertSet['insert_id'];
		}

		# Copy Payment Methods

		$resultSet = executeQuery("select * from payment_methods where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$insertSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,payment_method_type_id,no_address_required,internal_use_only) " .
					"values (?,?,?,?,?,?)", $clientId, $row['payment_method_code'], $row['description'], $paymentMethodTypes[$row['payment_method_type_id']], $row['no_address_required'], $row['internal_use_only']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
		}
		$this->addResultLog("Copy Payment Methods done");

		$priceCalculationTypeId = getFieldFromId("price_calculation_type_id", "price_calculation_types", "price_calculation_type_code", "MARKUP");
		executeQuery("insert into pricing_structures (client_id,pricing_structure_code,description,percentage,price_calculation_type_id) values (?,'DEFAULT','Default',?,?)",
				$clientId, $_POST['default_product_markup'], $priceCalculationTypeId);

		# Create the Primary user account

		$resultSet = executeQuery("insert into contacts (client_id,first_name,last_name,email_address,country_id,date_created) values (?,?,?,?,1000,now())",
				$clientId, $nameValues['first_name'], $nameValues['last_name'], $nameValues['new_user_email_address']);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$contactId = $resultSet['insert_id'];
		$passwordSalt = getRandomString(64);
		$password = hash("sha256", $passwordSalt . $_POST['new_user_password']);
		$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['new_user_user_name']), "superuser_flag = 1");
		if (!empty($checkUserId)) {
			return "User name is unavailable. Choose another";
		}
		$resultSet = executeQuery("insert into users (client_id,contact_id,user_name,password_salt,password,date_created,administrator_flag) values (?,?,?,?,?,now(),1)",
				$clientId, $contactId, strtolower($nameValues['new_user_user_name']), $passwordSalt, $password);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$userId = $resultSet['insert_id'];
		$password = hash("sha256", $userId . $passwordSalt . $nameValues['new_user_password']);
		executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
		$resultSet = executeQuery("update users set password = ? where user_id = ?", $password, $userId);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		if (!empty($userGroupId)) {
			executeQuery("insert ignore into user_group_members (user_group_id,user_id) values (?,?)", $userGroupId, $userId);
		}
		$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
		$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "COREFIRE_ADMIN",
				"custom_field_type_id = ? and client_id = ?", $customFieldTypeId, $clientId);
		if (empty($customFieldId)) {
			$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
					$clientId, "COREFIRE_ADMIN", "coreFIRE Admin", $customFieldTypeId, "coreFIRE Admin");
			$customFieldId = $insertSet['insert_id'];
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
		}
		executeQuery("insert into custom_field_data (primary_identifier, custom_field_id, text_data) values (?,?,'true')", $contactId, $customFieldId);
        coreSTORE::createSetupCorestoreApiApp($clientId);

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "MAINTENANCE_SHOW_INACTIVE");
		executeQuery("insert into user_preferences (user_id,preference_id,preference_value) values (?,?,'true')", $userId, $preferenceId);
		executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,'true')", $clientId, $preferenceId);

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "TIMEZONE");
		executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $clientId, $preferenceId, $_POST['client_timezone']);

		$this->addResultLog("Create the Primary user account done");

		# create product taxonomy

		if (function_exists("_localClientOnboardTaxonomy")) {
			$taxonomyStructure = _localClientOnboardTaxonomy();
		} else {
			$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
			$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_taxonomy_structure";
			$response = getCurlReturn($hostUrl, $parameters);
			if (empty($response)) {
				return "Unable to get taxonomy";
			}
			$taxonomyStructure = json_decode($response, true);
			if (empty($taxonomyStructure)) {
				return "Unable to get taxonomy";
			}
		}

		$productDepartments = array();
		$resultSet = executeQuery("select * from product_departments where client_id = ?", $clientId);
		while ($row = getNextRow($resultSet)) {
			$productDepartments[$row['product_department_code']] = $row['product_department_id'];
		}
		foreach ($taxonomyStructure['product_departments'] as $thisDepartment) {
			if (!array_key_exists($thisDepartment['product_department_code'], $productDepartments)) {
				$insertSet = executeQuery("insert into product_departments (client_id,product_department_code,description,link_name) values (?,?,?,?)", $clientId, $thisDepartment['product_department_code'], $thisDepartment['description'], $thisDepartment['link_name']);
				if (!empty($insertSet['sql_error'])) {
					return getSystemMessage("basic", $insertSet['sql_error']);
				}
				$productDepartments[$thisDepartment['product_department_code']] = $insertSet['insert_id'];
			} else if (!empty($thisDepartment['link_name'])) {
				executeQuery("update product_departments set link_name = ? where product_department_id = ? and link_name is null", $thisDepartment['link_name'], $productDepartments[$thisDepartment['product_department_code']]);
			}
		}
		$productCategoryGroups = array();
		$resultSet = executeQuery("select * from product_category_groups where client_id = ?", $clientId);
		while ($row = getNextRow($resultSet)) {
			$productCategoryGroups[$row['product_category_group_code']] = $row['product_category_group_id'];
		}
		foreach ($taxonomyStructure['product_category_groups'] as $thisCategoryGroup) {
			if (!array_key_exists($thisCategoryGroup['product_category_group_code'], $productCategoryGroups)) {
				$insertSet = executeQuery("insert into product_category_groups (client_id,product_category_group_code,description,link_name) values (?,?,?,?)", $clientId, $thisCategoryGroup['product_category_group_code'], $thisCategoryGroup['description'], $thisCategoryGroup['link_name']);
				if (!empty($insertSet['sql_error'])) {
					return getSystemMessage("basic", $insertSet['sql_error']);
				}
				$productCategoryGroups[$thisCategoryGroup['product_category_group_code']] = $insertSet['insert_id'];
			} else if (!empty($thisCategoryGroup['link_name'])) {
				executeQuery("update product_category_groups set link_name = ? where product_category_group_id = ? and link_name is null", $thisCategoryGroup['link_name'], $productCategoryGroups[$thisCategoryGroup['product_category_group_code']]);
			}
		}
		$productCategories = array();
		$resultSet = executeQuery("select * from product_categories where client_id = ?", $clientId);
		while ($row = getNextRow($resultSet)) {
			$productCategories[$row['product_category_code']] = $row['product_category_id'];
		}
		foreach ($taxonomyStructure['product_categories'] as $thisCategory) {
			if (!array_key_exists($thisCategory['product_category_code'], $productCategories)) {
				$insertSet = executeQuery("insert into product_categories (client_id,product_category_code,description,link_name,atf_firearm_type_id) values (?,?,?,?,?)",
						$clientId, $thisCategory['product_category_code'], $thisCategory['description'], $thisCategory['link_name'], $thisCategory['atf_firearm_type_id']);
				if (!empty($insertSet['sql_error'])) {
					return getSystemMessage("basic", $insertSet['sql_error']);
				}
				$productCategories[$thisCategory['product_category_code']] = $insertSet['insert_id'];
			} else if (!empty($thisCategory['link_name']) || !empty($thisCategory['atf_firearm_type_id'])) {
				executeQuery("update product_categories set link_name = ?, atf_firearm_type_id = ? where product_category_id = ? and link_name is null",
						$thisCategory['link_name'], $thisCategory['atf_firearm_type_id'], $productCategories[$thisCategory['product_category_code']]);
			}
		}
		$productFacets = array();
		$resultSet = executeQuery("select * from product_facets where client_id = ?", $clientId);
		while ($row = getNextRow($resultSet)) {
			$productFacets[$row['product_facet_code']] = $row['product_facet_id'];
		}
		$flaggedProductFacets = array();
		$resultSet = executeQuery("select * from product_facets where client_id = ? and (inactive = 1 or exclude_reductive = 1 or exclude_details = 1)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$flaggedProductFacets[$row['product_facet_code']] = $row;
		}
		foreach ($taxonomyStructure['product_facets'] as $thisFacet) {
			if (!array_key_exists($thisFacet['product_facet_code'], $productFacets)) {
				$insertSet = executeQuery("insert into product_facets (client_id,product_facet_code,description,exclude_reductive,exclude_details,inactive) values (?,?,?,?,?, ?)",
						$clientId, $thisFacet['product_facet_code'], $thisFacet['description'], (empty($flaggedProductFacets[$thisFacet['product_facet_code']]['exclude_reductive']) ? 0 : 1),
						(empty($flaggedProductFacets[$thisFacet['product_facet_code']]['exclude_details']) ? 0 : 1), (empty($flaggedProductFacets[$thisFacet['product_facet_code']]['inactive']) ? 0 : 1));
				if (!empty($insertSet['sql_error'])) {
					return getSystemMessage("basic", $insertSet['sql_error']);
				}
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
		$this->addResultLog("Create product taxonomy done");

		# create product manufacturers

		if (function_exists("_localClientOnboardManufacturers")) {
			$productManufacturers = _localClientOnboardManufacturers();
		} else {
			$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
			$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_product_manufacturers";
			$postParameters = "";
			foreach ($parameters as $parameterKey => $parameterValue) {
				$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
			curl_setopt($ch, CURLOPT_URL, $hostUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
			curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
			$response = curl_exec($ch);
			if (empty($response)) {
				return "Unable to get manufacturers";
			}
			curl_close($ch);
			$productManufacturers = json_decode($response, true);
		}

		if (empty($productManufacturers)) {
			return "Unable to get manufacturers";
		}

		$mapPolicies = array();
		$mapSet = executeQuery("select * from map_policies");
		while ($mapRow = getNextRow($mapSet)) {
			$mapPolicies[$mapRow['map_policy_code']] = $mapRow['map_policy_id'];
		}
		$existingProductManufacturers = array();
		$resultSet = executeQuery("select product_manufacturer_id,product_manufacturer_code from product_manufacturers where client_id = ?", $clientId);
		while ($row = getNextRow($resultSet)) {
			$existingProductManufacturers[$row['product_manufacturer_code']] = $row['product_manufacturer_id'];
		}
		$existingProductDepartments = array();
		$resultSet = executeQuery("select product_department_id,product_department_code from product_departments where client_id = ?", $clientId);
		while ($row = getNextRow($resultSet)) {
			$existingProductDepartments[$row['product_department_code']] = $row['product_department_id'];
		}
		$existingProductDistributors = array();
		$resultSet = executeQuery("select product_distributor_id,product_distributor_code from product_distributors");
		while ($row = getNextRow($resultSet)) {
			$existingProductDistributors[$row['product_distributor_code']] = $row['product_distributor_id'];
		}
		foreach ($productManufacturers['product_manufacturers'] as $thisManufacturer) {
			if (array_key_exists($thisManufacturer['product_manufacturer_code'],$existingProductManufacturers)) {
				continue;
			}
			$insertSet = executeQuery("insert into contacts (client_id,title,first_name,middle_name,last_name,suffix,preferred_first_name,alternate_name,business_name," .
					"job_title,salutation,address_1,address_2,city,state,postal_code,country_id,attention_line,email_address,web_page,date_created) values " .
					"(?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, now())", $clientId, $thisManufacturer['title'], $thisManufacturer['first_name'], $thisManufacturer['middle_name'],
					$thisManufacturer['last_name'], $thisManufacturer['suffix'], $thisManufacturer['preferred_first_name'], $thisManufacturer['alternate_name'],
					$thisManufacturer['business_name'], $thisManufacturer['job_title'], $thisManufacturer['salutation'], $thisManufacturer['address_1'],
					$thisManufacturer['address_2'], $thisManufacturer['city'], $thisManufacturer['state'], $thisManufacturer['postal_code'],
					$thisManufacturer['country_id'], $thisManufacturer['attention_line'], $thisManufacturer['email_address'], $thisManufacturer['web_page']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$manufacturerContactId = $insertSet['insert_id'];
			$insertSet = executeQuery("insert into product_manufacturers (client_id,product_manufacturer_code,description,contact_id,link_name,map_policy_id,percentage,cannot_dropship) values (?,?,?,?,?, ?,?,?)",
					$clientId, $thisManufacturer['product_manufacturer_code'], $thisManufacturer['description'], $manufacturerContactId, $thisManufacturer['link_name'], $mapPolicies[$thisManufacturer['map_policy_code']],
					$thisManufacturer['percentage'], $thisManufacturer['cannot_dropship']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$productManufacturerId = $insertSet['insert_id'];

			foreach ($thisManufacturer['distributors'] as $productDistributorCode) {
				$productDistributorId = $existingProductDistributors[$productDistributorCode];
				if (!empty($productDistributorId)) {
					executeQuery("insert ignore into product_manufacturer_distributor_dropships (product_manufacturer_id,product_distributor_id) values (?,?)", $productManufacturerId, $productDistributorId);
				}
			}
			foreach ($thisManufacturer['departments'] as $productDepartmentCode) {
				$productDepartmentId = $existingProductDepartments[$productDepartmentCode];
				if (!empty($productDepartmentId)) {
					executeQuery("insert ignore into product_manufacturer_dropship_exclusions (product_manufacturer_id,product_department_id) values (?,?)", $productManufacturerId, $productDepartmentId);
				}
			}
			foreach ($productManufacturerTags as $thisProductManufacturerTag) {
				$tagId = $thisProductManufacturerTag['product_manufacturer_tag_id'];
				if (array_key_exists($thisManufacturer['product_manufacturer_code'], $thisProductManufacturerTag['product_manufacturer_codes'])) {
					executeQuery("insert into product_manufacturer_tag_links (product_manufacturer_id,product_manufacturer_tag_id) values (?,?)", $productManufacturerId, $tagId);
				}
			}
		}

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "SYNC_FEDERAL_FIREARMS_LICENSEES");
		executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $clientId, $preferenceId);
		executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,'true')", $clientId, $preferenceId);

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "SYNC_PRODUCT_MANUFACTURERS");
		executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $clientId, $preferenceId);
		executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,'true')", $clientId, $preferenceId);

		$this->addResultLog("Create product manufacturers done");

		$clientAddressBlock = $clientRow['address_1'];
		$clientCity = $clientRow['city'];
		if (!empty($clientRow['state'])) {
			$clientCity .= (empty($clientCity) ? "" : ", ") . $clientRow['state'];
		}
		if (!empty($clientRow['postal_code'])) {
			$clientCity .= (empty($clientCity) ? "" : ", ") . $clientRow['postal_code'];
		}
		if (!empty($clientCity)) {
			$clientAddressBlock .= (empty($clientAddressBlock) ? "" : "<br>\n") . $clientCity;
		}
		$clientCountry = "";
		if ($clientRow['country_id'] != 1000) {
			$clientCountry = getFieldFromId("country_name", "countries", "country_id", $clientRow['country_id']);
		}
		if (!empty($clientCountry)) {
			$clientAddressBlock .= (empty($clientAddressBlock) ? "" : "<br>\n") . $clientCountry;
		}
		$clientFields = array("client_name" => $clientRow['business_name'],
				"client_address_1" => $clientRow['address_1'],
				"client_city" => $clientRow['city'],
				"client_state" => $clientRow['state'],
				"client_postal_code" => $clientRow['postal_code'],
				"client_address_block" => $clientAddressBlock,
				"client_domain_name" => $_POST['domain_name'],
				"client_email_address" => $clientRow['email_address']
		);

		$resultSet = executeQuery("select * from emails where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			foreach ($row as $fieldName => $fieldValue) {
				foreach ($clientFields as $thisFieldName => $thisFieldValue) {
					$row[$fieldName] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $row[$fieldName]);
				}
			}
			executeQuery("insert into emails (client_id,email_code,description,detailed_description,subject,content) values (?,?,?,?,?, ?)", $clientId, $row['email_code'],
					$row['description'], $row['detailed_description'], $row['subject'], $row['content']);
		}

		$this->addResultLog("Create emails done");

		# Create notifications

		$notificationSet = executeQuery("select * from notifications where client_id = ? and internal_use_only = 0", $GLOBALS['gClientId']);
		while ($notificationRow = getNextRow($notificationSet)) {
			$insertSet = executeQuery("insert into notifications (client_id, notification_code, description, detailed_description) values (?,?,?,?)",
					$clientId, $notificationRow['notification_code'], $notificationRow['description'], $notificationRow['detailed_description']);
			executeQuery("insert into notification_emails (notification_id, email_address) values (?,?)", $insertSet['insert_id'], $nameValues['email_address']);
		}
		$this->addResultLog("Create notifications done");

		# Create Domain name references

		$domainName = str_replace("https://", "", str_replace("http://", "", trim($_POST['web_page'], "/ \t\n\r\0\x0B")));
		if (substr($domainName, 0, 4) == "www.") {
			$domainName = str_replace("www.", "", $domainName);
		}
		if ($domainName != $_POST['web_page']) {
			executeQuery("update contacts set web_page = ? where contact_id = ?", $domainName, $clientContactId);
		}
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "WEB_URL");
		executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $clientId, $preferenceId);
		executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $clientId, $preferenceId, $domainName);

		$insertSet = executeQuery("insert into domain_names (domain_name,domain_client_id) values (?,?)",
				"www." . $domainName, $clientId);
		if (!empty($insertSet['sql_error'])) {
			return getSystemMessage("basic", $insertSet['sql_error']);
		}
		$domainNameId = $insertSet['insert_id'];
		$insertSet = executeQuery("insert into domain_names (domain_name,forward_domain_name,domain_client_id) values (?,?,?)",
				$domainName, "www." . $domainName, $clientId);
		if (!empty($insertSet['sql_error'])) {
			return getSystemMessage("basic", $insertSet['sql_error']);
		}
		removeCachedData("domain_name_row", $domainName);
		$rootDomainName = getPageTextChunk("domain_name");
		if (empty($rootDomainName)) {
			$rootDomainName = "corefire.shop";
		}
		$parts = explode(".", $domainName);
		$adminDomainName = "";
		$adminDomainNameId = "";
		$pageId = $GLOBALS['gAllPageCodes']["ORDERSDASHBOARD_LITE"];
		if (count($parts) > 1) {
			$adminDomainName = $parts[count($parts) - 2] . "." . $rootDomainName;
			$insertSet = executeQuery("insert into domain_names (domain_name,domain_client_id,page_id) values (?,?,?)",
					$adminDomainName, $clientId, $pageId);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$adminDomainNameId = $insertSet['insert_id'];
		}
		removeCachedData("domain_name_row", $adminDomainName);
		$substitutions['admin_domain_name'] = "https://" . $adminDomainName;

		$this->addResultLog("Create Domain name references done");

		# Copy Sass Headers

		$resultSet = executeQuery("select * from sass_headers where client_id = ? and inactive = 0 and sass_header_id in (select sass_header_id from template_sass_headers where template_id = ?)", $GLOBALS['gClientId'], $templateId);
		$sassHeaders = array();
		while ($row = getNextRow($resultSet)) {
			foreach ($substitutions as $substitutionName => $substitutionValue) {
				$textChunkValue = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", strtoupper($substitutionName), "template_id = ?", $templateId);
				if (!empty($textChunkValue) && !empty($substitutionValue)) {
					$row['content'] = str_replace($textChunkValue, $substitutionValue, $row['content']);
				}
			}
			$insertSet = executeQuery("insert into sass_headers (client_id,description,content) values (?,?,?)", $clientId, $row['description'], $row['content']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$sassHeaders[$row['sass_header_id']] = $insertSet['insert_id'];
		}
		$this->addResultLog("Copy Sass Headers done");

		# Copy CSS File

		$resultSet = executeQuery("select * from css_files where client_id = ? and css_file_id in (select css_file_id from templates where template_id = ?)", $GLOBALS['gClientId'], $templateId);
		$cssFileId = "";
		$row = getNextRow($resultSet);
		if (!empty($row)) {
			foreach ($substitutions as $substitutionName => $substitutionValue) {
				$textChunkValue = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", strtoupper($substitutionName), "template_id = ?", $templateId);
				if (!empty($textChunkValue) && !empty($substitutionValue)) {
					$row['content'] = str_replace($textChunkValue, $substitutionValue, $row['content']);
				}
			}
			$insertSet = executeQuery("insert into css_files (client_id,css_file_code,description,content) values (?,?,?,?)", $clientId, $row['css_file_code'], $row['description'], $row['content']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$cssFileId = $insertSet['insert_id'];
		}

		$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "PRIMARY_COLOR");
		if (!empty($nameValues['custom_field_id_' . $customFieldId])) {
			executeQuery("insert into fragments (client_id,fragment_code,description,content) values (?,?,?,?)", $clientId, "MANAGEMENT_COLOR",
					"Management Color", $nameValues['custom_field_id_' . $customFieldId]);
		}
		$this->addResultLog("Copy CSS File done");

		# Create Template and pages

		$insertSet = executeQuery("insert into analytics_code_chunks (client_id,analytics_code_chunk_code,description) values (?,'WEBSITE_CODE','Website Code')", $clientId);
		$analyticsCodeChunkId = $insertSet['insert_id'];

		$newTemplateId = "";
		$resultSet = executeQuery("select * from templates where template_id = ?", $templateId);
		if ($row = getNextRow($resultSet)) {
			$templateCode = $row['template_code'];
			foreach ($row as $fieldName => $fieldValue) {
				foreach ($substitutions as $substitutionName => $substitutionValue) {
					$textChunkValue = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", strtoupper($substitutionName), "template_id = ?", $templateId);
					if (!empty($textChunkValue) && !empty($substitutionValue)) {
						$row[$fieldName] = str_replace($textChunkValue, $substitutionValue, $row[$fieldName]);
					}
				}
			}
			$insertSet = executeQuery("insert into templates (client_id,template_code,description,css_content,analytics_code_chunk_id,javascript_code,content,css_file_id) values (?,?,?,?,?, ?,?,?)",
					$clientId, $clientRow['client_code'], $clientRow['business_name'] . " Template", $row['css_content'], $analyticsCodeChunkId, $row['javascript_code'], $row['content'], $cssFileId);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$newTemplateId = $insertSet['insert_id'];
			$insertSet = executeQuery("insert into template_data_uses (template_data_id,template_id,sequence_number) select template_data_id,?,sequence_number from template_data_uses where template_id = ?", $newTemplateId, $templateId);
			foreach ($sassHeaders as $sassHeaderId) {
				$insertSet = executeQuery("insert into template_sass_headers (template_id,sass_header_id) values (?,?)", $newTemplateId, $sassHeaderId);
			}
		}
		foreach ($templateTextChunks as $textChunkCode => $textChunkValue) {
			executeQuery("insert into template_text_chunks (template_text_chunk_code,template_id,description,content) values (?,?,?,?)", strtoupper($textChunkCode), $newTemplateId, ucwords(str_replace("_", " ", $textChunkCode)), $textChunkValue);
		}
		executeQuery("insert into template_text_chunks (template_text_chunk_code,template_id,description,content) values (?,?,?,?)", "PRIMARY_TEMPLATE_CODE", $newTemplateId, "Primary Template Code", $templateCode);

		$pageTranslations = array();
		$homePageId = "";
		$resultSet = executeQuery("select * from pages where template_id = ? and client_id = ?", $templateId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			foreach ($row as $fieldName => $fieldValue) {
				foreach ($substitutions as $substitutionName => $substitutionValue) {
					$textChunkValue = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", strtoupper($substitutionName), "template_id = ?", $templateId);
					if (!empty($textChunkValue) && !empty($substitutionValue)) {
						$row[$fieldName] = str_replace($textChunkValue, $substitutionValue, $row[$fieldName]);
					}
				}
			}
			$row['date_created'] = date("Y-m-d");
			$row['creator_user_id'] = $userId;
			$row['template_id'] = $newTemplateId;
			$row['analytics_code_chunk_id'] = "";
			$row['client_id'] = $clientId;
			$originalPageCode = $row['page_code'];
			$row['page_code'] = $row['page_code'] . "_" . $clientRow['client_code'];
			$pageId = $row['page_id'];
			$row['page_id'] = "";
			$insertSet = executeQuery("insert into pages values (" . implode(",", array_fill(0, count($row), "?")) . ")", $row);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$newPageId = $insertSet['insert_id'];
			$pageTranslations[$pageId] = $newPageId;
			if ($row['link_name'] == "home") {
				executeQuery("update domain_names set page_id = ?, user_page_id = ?, admin_page_id = ? where domain_name_id = ?", $newPageId, $newPageId, $newPageId, $domainNameId);
				if ($this->getPageTextChunk("use_admin_domain_name")) {
					executeQuery("update domain_names set page_id = ?, user_page_id = ? where domain_name_id = ?", $newPageId, $newPageId, $adminDomainNameId);
				}
			}
			$pageDataSet = executeQuery("select * from page_data where page_id = ?", $pageId);
			while ($pageDataRow = getNextRow($pageDataSet)) {
				$insertSet = executeQuery("insert into page_data (page_id,template_data_id,sequence_number,integer_data,number_data,text_data,date_data) values (?,?,?,?,?, ?,?)",
						$newPageId, $pageDataRow['template_data_id'], $pageDataRow['sequence_number'], $pageDataRow['integer_data'], $pageDataRow['number_data'], $pageDataRow['text_data'], $pageDataRow['date_date']);
			}
			$insertSet = executeQuery("insert into page_access (page_id,all_client_access,administrator_access,all_user_access,public_access,permission_level) select ?,all_client_access,administrator_access,all_user_access,public_access,permission_level from page_access where page_id = ?", $newPageId, $pageId);
		}

		foreach ($this->iUrlAliasTypes as $thisAliasType) {
			$urlAliasTypeId = getFieldFromId("url_alias_type_id", "url_alias_types", "url_alias_type_code", $thisAliasType['url_alias_type_code'], "client_id = ?", $clientId);
			if (!empty($urlAliasTypeId)) {
				continue;
			}
			$pageId = getFieldFromId("page_id", "pages", "client_id", $clientId, "script_filename like ?", $thisAliasType['script_filename']);
			if (empty($pageId)) {
				continue;
			}
			$tableId = getFieldFromId("table_id", "tables", "table_name", $thisAliasType['table_name']);
			executeQuery("insert into url_alias_types (client_id,url_alias_type_code,description,table_id,page_id,parameter_name) values (?,?,?,?,?,?)",
					$clientId, $thisAliasType['url_alias_type_code'], $thisAliasType['description'], $tableId, $pageId, $thisAliasType['parameter_name']);
		}
		$this->addResultLog("Create Template and pages done");

		# Copy Images in album with same code as template

		$insertSet = executeQuery("insert into albums (client_id,album_code,description) values (?,?,'Theme Images')", $clientId, makeCode($clientRow['business_name']));
		$albumId = $insertSet['insert_id'];

        $existingImageIds = array();
        $resultSet = executeQuery("select image_id, image_code from images where client_id = ?",$clientId);
        while ($row = getNextRow($resultSet)) {
            $existingImageIds[$row['image_code']] = $row['image_id'];
        }

		$imageIdTranslations = array();
        $imageString = "";
		$resultSet = executeQuery("select image_id from images where client_id = ? and (image_id in (select image_id from album_images where album_id = " .
				"(select album_id from albums where album_code = ? and client_id = ?)) or image_id in (select image_id from template_images where template_id = ?))",
				$GLOBALS['gClientId'], $templateCode, $GLOBALS['gClientId'], $templateId);
        while ($row = getNextRow($resultSet)) {
            $imageString .= (empty($imageString) ? "" : ",") . $row['image_id'];
        }
        if (!empty($imageString)) {
	        $resultSet = executeQuery("select image_id from images where client_id = ? and image_id in (" . $imageString . ")");
	        while ($row = getNextRow($resultSet)) {
		        $imageId = $existingImageIds[$row['image_code']];
		        if (empty($imageId)) {
			        if (empty($row['file_content']) && !empty($row['os_filename'])) {
				        $imageContents = getExternalImageContents($row['os_filename']);
			        } else {
				        $imageContents = $row['file_content'];
			        }
			        if (empty($imageContents)) {
				        continue;
			        }
			        $imageId = createImage(array("client_id" => $clientId, "image_code" => $row['image_code'], "extension" => $row['extension'], "file_content" => $imageContents, "name" => $row['file_name'], "description" => $row['description'], "detailed_description" => $row['detailed_description']));
		        }
		        $imageIdTranslations[$row['image_id']] = $imageId;
		        if (!empty($albumId) && !empty($imageId)) {
			        executeQuery("insert ignore into album_images (album_id,image_id) values (?,?)", $albumId, $imageId);
		        }
	        }
        }
		$this->addResultLog("Copy images done");

		# Create Banners

		$bannerGroupTranslations = array();
		$bannerGroupSet = executeQuery("select * from banner_groups where client_id = ? and banner_group_id in (select banner_group_id from template_banner_groups where template_id = ?)", $GLOBALS['gClientId'], $templateId);
		while ($bannerGroupRow = getNextRow($bannerGroupSet)) {
			$insertSet = executeQuery("insert into banner_groups (client_id,banner_group_code,description) values (?,?,?)", $clientId, $bannerGroupRow['banner_group_code'], $bannerGroupRow['description']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$bannerGroupTranslations[$bannerGroupRow['banner_group_id']] = $insertSet['insert_id'];
		}
		$bannerIdArray = array();
		foreach ($bannerGroupTranslations as $oldBannerGroupId => $newBannerGroupId) {
			$resultSet = executeQuery("select * from banners join banner_group_links using (banner_id) where banner_group_id = ? and inactive = 0 order by sequence_number", $oldBannerGroupId);
			$sequenceNumber = 0;
			while ($row = getNextRow($resultSet)) {
				$bannerId = $bannerIdArray[$row['banner_id']];
				if (empty($bannerId)) {
					$imageId = "";
					if (!empty($row['image_id'])) {
						$imageId = $imageIdTranslations[$row['image_id']];
						if (empty($imageId)) {
							$imageRow = getRowFromId("images", "image_id", $row['image_id']);
							if (empty($imageRow['file_content']) && !empty($imageRow['os_filename'])) {
								$imageContents = getExternalImageContents($imageRow['os_filename']);
							} else {
								$imageContents = $imageRow['file_content'];
							}
							if (!empty($imageContents)) {
								$imageId = getFieldFromId("image_id", "images", "image_code", $imageRow['image_code'], "client_id = ?", $clientId);
								if (empty($imageId)) {
									$imageId = createImage(array("client_id" => $clientId, "image_code" => $imageRow['image_code'], "extension" => $imageRow['extension'], "file_content" => $imageContents, "name" => $imageRow['file_name'], "description" => $imageRow['description'], "detailed_description" => $imageRow['detailed_description']));
								}
								$imageIdTranslations[$row['image_id']] = $imageId;
							}
						}
					}
					$insertSet = executeQuery("insert into banners (client_id,banner_code,description,content,css_classes,image_id,link_url,domain_name,sort_order,use_content,hide_description) values (?,?,?,?,?, ?,?,?,?,?, ?)",
							$clientId, $row['banner_code'], $row['description'], $row['content'], $row['css_classes'], $imageId, $row['link_url'], $row['domain_name'], $row['sort_order'], $row['use_content'], $row['hide_description']);
					if (!empty($insertSet['sql_error'])) {
						return getSystemMessage("basic", $insertSet['sql_error']);
					}
					$bannerId = $insertSet['insert_id'];
					$bannerIdArray[$row['banner_id']] = $bannerId;
				}
				$sequenceNumber += 10;
				executeQuery("insert into banner_group_links (banner_group_id,banner_id,sequence_number) values (?,?,?)", $newBannerGroupId, $bannerId, $sequenceNumber);
			}
		}
		$this->addResultLog("Create Banners done");

		# Create menus

		$menuIdTranslations = array();
		$menuSet = executeQuery("select * from menus where client_id = ? and (menu_code = 'WEBSITE_MENU' or menu_id in (select menu_id from template_menus where template_id = ?))", $GLOBALS['gClientId'], $templateId);
		while ($menuRow = getNextRow($menuSet)) {
			$existingMenuId = getFieldFromId("menu_id", "menus", "menu_code", $menuRow['menu_code'], "client_id = ?", $clientId);
			if (!empty($existingMenuId)) {
				continue;
			}
			$insertSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)", $clientId, $menuRow['menu_code'], $menuRow['description']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$newMenuId = $insertSet['insert_id'];
			$menuIdTranslations[$menuRow['menu_id']] = $newMenuId;
		}
		$menuItemIdArray = array();
		foreach ($menuIdTranslations as $oldMenuId => $newMenuId) {
			$resultSet = executeQuery("select * from menu_contents join menu_items using (menu_item_id) where menu_contents.menu_id = ? order by sequence_number", $oldMenuId);
			$sequenceNumber = 0;
			while ($row = getNextRow($resultSet)) {
				$menuItemId = $menuItemIdArray[$row['menu_item_id']];
				if (empty($menuItemId)) {
					$insertSet = executeQuery("insert into menu_items (client_id,description,link_title,link_url,list_item_identifier,list_item_classes,menu_id,page_id,query_string,not_logged_in,logged_in,administrator_access) values (?,?,?,?,?, ?,?,?,?,?, ?,?)",
							$clientId, $row['description'], $row['link_title'], $row['link_url'], $row['list_item_identifier'], $row['list_item_classes'], $menuIdTranslations[$row['menu_id']], $pageTranslations[$row['page_id']], $row['query_string'], $row['not_logged_in'], $row['logged_in'], $row['administrator_access']);
					if (!empty($insertSet['sql_error'])) {
						return getSystemMessage("basic", $insertSet['sql_error']);
					}
					$menuItemId = $insertSet['insert_id'];
					$menuItemIdArray[$row['menu_item_id']] = $menuItemId;
				}
				$sequenceNumber += 10;
				executeQuery("insert into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)", $newMenuId, $menuItemId, $sequenceNumber);
			}
		}
		$this->addResultLog("Create menus done");

		# copy Fragments

		$resultSet = executeQuery("insert into fragments (client_id,fragment_code,description,detailed_description,content) " .
				"select ?,fragment_code,description,detailed_description,content from fragments where client_id = ? and inactive = 0 and " .
				"fragment_id in (select fragment_id from template_fragments where template_id = ?)", $clientId, $GLOBALS['gClientId'], $templateId);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$this->addResultLog("Create Fragments done");

		# copy Mailing Lists

		$resultSet = executeQuery("insert into mailing_lists (client_id,mailing_list_code,description) " .
				"select ?,mailing_list_code,description from mailing_lists where client_id = ? and inactive = 0", $clientId, $GLOBALS['gClientId']);
		if (!empty($resultSet['sql_error'])) {
			return getSystemMessage("basic", $resultSet['sql_error']);
		}
		$this->addResultLog("Create Mailing Lists done");

		# copy Admin menu structure

		$menuItemArray = array();
		$menuArray = array();

		$menuRow = getRowFromId("menus", "menu_code", "ADMIN_LITE");
		$menuArray[$menuRow['menu_id']] = $menuRow;
		$menuIds = $menuRow['menu_id'];

		do {
			$newMenuFound = false;
			$resultSet = executeQuery("select * from menu_items where menu_item_id in (select menu_item_id from menu_contents where menu_id in (" . $menuIds . ")) " .
					"and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$menuItemArray[$row['menu_item_id']] = $row;
				if (!empty($row['menu_id']) && !array_key_exists($row['menu_id'], $menuArray)) {
					$menuArray[$row['menu_id']] = getRowFromId("menus", "menu_id", $row['menu_id']);
					$menuIds .= "," . $row['menu_id'];
					$newMenuFound = true;
				}
			}
		} while ($newMenuFound);

		foreach ($menuArray as $menuId => $thisMenu) {
			$insertSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)", $clientId, $thisMenu['menu_code'], $thisMenu['description']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$menuArray[$menuId]['menu_id'] = $insertSet['insert_id'];
		}

		foreach ($menuItemArray as $menuItemId => $thisMenuItem) {
			$insertSet = executeQuery("insert into menu_items (client_id,description,link_title,link_url,list_item_identifier,list_item_classes,menu_id,page_id," .
					"query_string,subsystem_id,display_color,separate_window,not_logged_in,logged_in,administrator_access,sort_order) values (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?)",
					$clientId, $thisMenuItem['description'], $thisMenuItem['link_title'], $thisMenuItem['link_url'], $thisMenuItem['list_item_identifier'], $thisMenuItem['list_item_classes'],
					(empty($thisMenuItem['menu_id']) ? "" : $menuArray[$thisMenuItem['menu_id']]['menu_id']), $thisMenuItem['page_id'], $thisMenuItem['query_string'], $thisMenuItem['subsystem_id'], $thisMenuItem['display_color'],
					$thisMenuItem['separate_window'], $thisMenuItem['not_logged_in'], $thisMenuItem['logged_in'], $thisMenuItem['administrator_access'], $thisMenuItem['sort_order']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$menuItemArray[$menuItemId]['menu_item_id'] = $insertSet['insert_id'];
		}

		foreach ($menuArray as $menuId => $thisMenu) {
			$resultSet = executeQuery("select * from menu_contents where menu_id = ?", $menuId);
			while ($row = getNextRow($resultSet)) {
				$menuItemId = $menuItemArray[$row['menu_item_id']]['menu_item_id'];
				if (!empty($menuItemId)) {
					$insertSet = executeQuery("insert into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)",
							$thisMenu['menu_id'], $menuItemId, $row['sequence_number']);
					if (!empty($insertSet['sql_error'])) {
						return getSystemMessage("basic", $insertSet['sql_error']);
					}
				}
			}
		}
		$this->addResultLog("Create Admin Menus done");

		$distributorArray = array();
		$resultSet = executeQuery("select * from product_distributors where inactive = 0 and internal_use_only = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$distributorArray[] = $row;
		}

		executeQuery("update clients set client_code = ?,template_id = null where client_id = ?", makeCode($domainName), $clientId);

		$distributorArray = array();
		$resultSet = executeQuery("select * from product_distributors where inactive = 0 and internal_use_only = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$distributorArray[] = $row;
		}

		$count = 0;
		foreach ($_POST as $fieldName => $fieldData) {
			if (empty($fieldData)) {
				continue;
			}
			if (substr($fieldName, 0, strlen("product_distributor_id_")) != "product_distributor_id_") {
				continue;
			}
			$productDistributorRow = getRowFromId("product_distributors", "product_distributor_id", $fieldData);
			if (empty($productDistributorRow)) {
				continue;
			}
			$insertSet = executeQuery("insert into contacts (client_id,business_name,country_id,date_created) values (?,?,1000,current_date)", $clientId, $productDistributorRow['description']);
			if (!empty($insertSet['sql_error'])) {
				return getSystemMessage("basic", $insertSet['sql_error']);
			}
			$productDistributorContactId = $insertSet['insert_id'];
			$dataTable = new DataTable("locations");
			if (!$dataTable->saveRecord(array("name_values" => array("client_id"=>$clientId, "location_code" => $productDistributorRow['product_distributor_code'], "description" => $productDistributorRow['description'], "contact_id" => $productDistributorContactId,
				"product_distributor_id" => $productDistributorRow['product_distributor_id'], "user_id" => $userId)))) {
				return $dataTable->getErrorMessage();
			}
			$count++;
		}
		$insertSet = executeQuery("insert into contacts (client_id,business_name,country_id,date_created) select client_id,business_name,country_id,date_created from contacts where contact_id = ?", $contactId);
		$locationContactId = $insertSet['insert_id'];

		$dataTable = new DataTable("locations");
		if (!$locationId = $dataTable->saveRecord(array("name_values" => array("client_id"=>$clientId, "location_code" => "STORE", "description" => "Store", "contact_id" => $locationContactId, "user_id" => $userId)))) {
			return $dataTable->getErrorMessage();
		}
		executeQuery("update shipping_methods set location_id = ? where pickup = 1 and client_id = ?", $locationId, $clientId);
		if ($_POST['allow_pickup']) {
			executeQuery("insert into client_preferences (client_id, preference_id, preference_value) select ?, preference_id, 1 from preferences where preference_code = 'RETAIL_STORE_SHOW_LOCATION_AVAILABILITY'", $clientId);
		}
		$this->addResultLog("Create Locations done");

		$emailId = getFieldFromId("email_id", "emails", "email_code", "COREFFL_REGISTRATION", "inactive = 0");
		if (!empty($emailId)) {
			sendEmail(array("email_id" => $emailId, "email_address" => $_POST['email_address'], "substitutions" => $substitutions, "contact_id" => $GLOBALS['gUserRow']['contact_id']));
		}

		$sesAccessKey = getPreference("AWS_SES_ACCESS_KEY");
		$sesSecretKey = getPreference("AWS_SES_SECRET_KEY");
		if (!empty($sesAccessKey) && !empty($sesSecretKey)) {
			$ses = new SimpleEmailService($sesAccessKey, $sesSecretKey);
			$ses->verifyEmailAddress($nameValues['email_address']);
			$sesSmtpUsername = getPreference("AWS_SES_SMTP_USERNAME");
			$sesSmtpPassword = getPreference("AWS_SES_SMTP_PASSWORD");
			executeQuery("insert into email_credentials (client_id, full_name, email_address, user_id, email_credential_code, description, smtp_host, smtp_port, security_setting, smtp_user_name, smtp_password) " .
					"values (?,?,?,?,'DEFAULT',?,'email-smtp.us-east-1.amazonaws.com','587','tls',?,?)",
					$clientId, $nameValues['business_name'], $nameValues['email_address'], $userId, $nameValues['email_address'] . " (SES)", $sesSmtpUsername, $sesSmtpPassword);
		}
		$this->iSubstitutions = $substitutions;

		$this->addResultLog("Client onboard complete", true);
		addProgramLog($this->getResultLog());

		return true;
	}

	function afterSaveDone($nameValues) {
		$returnArray['response'] = $this->getFragment("COREFIRE_REGISTRATION");
		if (empty($returnArray['response'])) {
			$returnArray['response'] = $this->getFragment("COREFFL_REGISTRATION");
		}
		if (empty($returnArray['response'])) {
			$returnArray['response'] = $this->getFragment("CLIENT_ONBOARD_RESPONSE");
		}
		$returnArray['response'] = PlaceHolders::massageContent($returnArray['response'], $this->iSubstitutions);
		return $returnArray;
	}

	function createSetupCorestoreApiApp($clientId) {
		// make sure setup_corestore api method exists
		$setupCorestoreApiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", "SETUP_CORESTORE");
		if (empty($setupCorestoreApiMethodId)) {
			$insertSet = executeQuery("insert into api_methods (api_method_code, description, detailed_description) values ('SETUP_CORESTORE', 'Set up coreSTORE', 'Use this method to link coreFIRE/coreFORCE to coreSTORE.  Login must be called first and session_identifier passed to this method.  The user who logs in to create the connection must be a full client access user or have a value in the custom field COREFIRE_ADMIN.  This method can only be called once per coreFORCE client.  If changes are needed after setup, they must be done manually.  api_app_code and api_app_version must also be included in the call.')");
			$setupCorestoreApiMethodId = $insertSet['insert_id'];
		}
		$apiParameterArray = array(array("column_name" => "corestore_endpoint", "data_type" => "varchar", "description" => "coreSTORE Endpoint"),
				array("column_name" => "corestore_api_key", "data_type" => "varchar", "description" => "coreSTORE API Key"),
				array("column_name" => "session_identifier", "data_type" => "varchar", "description" => "Session Identifier"));
		$apiParametersDataTable = new DataTable('api_parameters');
		$apiParametersDataTable->setSaveOnlyPresent(true);
		foreach ($apiParameterArray as $thisParameter) {
			$apiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", $thisParameter['column_name']);
			if (empty($apiParameterId)) {
				$apiParameterId = $apiParametersDataTable->saveRecord(array("name_values" => $thisParameter));
			}
			$apiMethodParameterId = getFieldFromId("api_method_parameter_id", "api_method_parameters", "api_parameter_id", $apiParameterId,
					"api_method_id = ?", $setupCorestoreApiMethodId);
			if (empty($apiMethodParameterId)) {
				executeQuery("insert into api_method_parameters (api_method_id, api_parameter_id) values (?,?)", $setupCorestoreApiMethodId, $apiParameterId);
			}
		}
		$loginApiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", "LOGIN");
		// make sure setup_corestore api method group exists
		$setupCorestoreApiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", "SETUP_CORESTORE");
		if (empty($setupCorestoreApiMethodGroupId)) {
			$insertSet = executeQuery("insert into api_method_groups (api_method_group_code, description) values ('SETUP_CORESTORE', 'Set up coreSTORE')");
			$setupCorestoreApiMethodGroupId = $insertSet['insert_id'];
		}
		executeQuery("insert ignore into api_method_group_links (api_method_group_id, api_method_id) values (?,?)", $setupCorestoreApiMethodGroupId, $setupCorestoreApiMethodId);
		executeQuery("insert ignore into api_method_group_links (api_method_group_id, api_method_id) values (?,?)", $setupCorestoreApiMethodGroupId, $loginApiMethodId);
		// create setup_corestore api app (must be created for every client)
		$insertSet = executeQuery("insert into api_apps (client_id, api_app_code, description, current_version, minimum_version, recommended_version) " .
				"values (?,'SETUP_CORESTORE', 'Set up coreSTORE', 1.0, 1.0, 1.0)", $clientId);
		$setupCorestoreApiAppId = $insertSet['insert_id'];
		executeQuery("insert into api_app_method_groups (api_app_id, api_method_group_id) values (?,?)", $setupCorestoreApiAppId, $setupCorestoreApiMethodGroupId);
	}

	function onLoadJavascript() {
		$distributorArray = array();
		$resultSet = executeQuery("select * from product_distributors where inactive = 0 and internal_use_only = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$distributorArray[] = $row;
		}
		$waitHtml = $this->getFragment("CLIENT_ONBOARD_WAIT");
		?>
		<script>
			<?php if (!empty($waitHtml)) { ?>
			$(".modal").html("<?= str_replace('"', '\"', $waitHtml) ?>");
			<?php } ?>
			$(document).on("click", "#_create_site", function () {
				$("#_save_button").trigger("click");
				return false;
			});

			$("#_changes_button").remove();
			$("#_add_button").remove();
			$("#_list_button").remove();
			$("#_delete_button").remove();
			$("#template_id").change(function () {
				$("#website_custom_fields").html("");
				$("#template_image").html("");
				if (!empty($(this).val())) {
					loadAjaxRequest(scriptFilename + "?url_action=get_template_custom_fields&template_id=" + $(this).val(), function (returnArray) {
						if ("website_custom_fields" in returnArray) {
							$("#website_custom_fields").html(returnArray['website_custom_fields']);
							if ($().minicolors) {
								$(".minicolors").minicolors({letterCase: 'uppercase', control: 'wheel'});
							}
						}
						if ("template_image" in returnArray) {
							$("#template_image").html(returnArray['template_image']);
							if ($().prettyPhoto) {
								$("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({social_tools: false, default_height: 480, default_width: 854, deeplinking: false});
							}
						}
					});
				}
			});
			$("#new_user_user_name").change(function () {
				const $userNameMessage = $("#_user_name_message");
				if (!empty($(this).val())) {
					loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val(), function (returnArray) {
						$userNameMessage.removeClass("info-message").removeClass("error-message");
						if ("info_user_name_message" in returnArray) {
							$userNameMessage.html(returnArray['info_user_name_message']).addClass("info-message");
						}
						if ("error_user_name_message" in returnArray) {
							$userNameMessage.html(returnArray['error_user_name_message']).addClass("error-message");
							$("#new_user_user_name").val("").focus();
							setTimeout(function () {
								$("#_edit_form").validationEngine("hideAll");
							}, 10);
						}
					});
				} else {
					$userNameMessage.val("");
				}
			});
			$(document).on("blur", "#postal_code", function () {
				if ($("#country_id").val() === "1000") {
					validatePostalCode();
				}
			});
			$("#country_id").change(function () {
				const $city = $("#city");
				const $countryId = $("#country_id");
				$city.add("#state").prop("readonly", $countryId.val() === "1000");
				$city.add("#state").attr("tabindex", ($countryId.val() === "1000" ? "9999" : "10"));
				$("#_city_row").show();
				$("#_city_select_row").hide();
				if ($countryId.val() === "1000") {
					validatePostalCode();
				}
			});
			$("#city_select").change(function () {
				$("#city").val($(this).val());
				$("#state").val($(this).find("option:selected").data("state"));
			});
			$(document).on("blur", "#email_address", function () {
				$("#_email_address_message").removeClass("info-message").removeClass("error-message").html("");
				if (!empty($(this).val())) {
					loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_email_address&email_address=" + $(this).val(), function (returnArray) {
						$("#_email_address_message").removeClass("info-message").removeClass("error-message");
						if ("error_email_address_message" in returnArray) {
							$("#_email_address_message").html(returnArray['error_email_address_message']).addClass("error-message");
							$("#email_address").focus();
							setTimeout(function () {
								$("#_edit_form").validationEngine("hideAll");
							}, 10);
						}
					});
				} else {
					$("#_email_address_message").val("");
				}
			});
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			function afterSaveChanges(returnArray) {
				if ("response" in returnArray) {
					$("#_signup_process_wrapper").html(returnArray['response']);
					$("html,body").animate({scrollTop: 0}, 400);
					return true;
				}
				return false;
			}

			function afterGoToTabbedContentPage($listItem) {
				if ($listItem.is(":last-child")) {
					$("#_create_site").removeClass("disabled-button");
				} else {
					$("#_create_site").addClass("disabled-button");
				}
			}

			function afterGetRecord() {
				const $city = $("#city");
				const $countryId = $("#country_id");
				$city.add("#state").prop("readonly", $countryId.val() === "1000");
				$city.add("#state").attr("tabindex", ($countryId.val() === "1000" ? "9999" : "10"));
				$("#_city_select_row").hide();
				$("#_city_row").show();
				$("#template_id").trigger("change");
			}
		</script>
		<?php
	}
}

$_GET['url_page'] = "new";
$pageObject = new ClientOnboardPage("clients");
$pageObject->displayPage();
