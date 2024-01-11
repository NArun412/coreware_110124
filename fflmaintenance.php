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

$GLOBALS['gPageCode'] = "FEDERALFIREARMSLICENSEMAINT";
require_once "shared/startup.inc";

class FederalFirearmsLicenseeMaintenance extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("license_lookup", "license_number", "licensee_name", "business_name", "address_1", "city", "state", "postal_code", "email_address", "phone_number", "expiration_date"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$filters = array();

			if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));
                $filters['image_exists'] = array("form_label" => "Copy of FFL license on file", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensee_details where file_id is not null and client_id = " . $GLOBALS['gClientId'] . ")", "data_type" => "tinyint", "conjunction" => "and");
                $filters['sot_image_exists'] = array("form_label" => "Copy of SOT license on file", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensee_details where sot_file_id is not null and client_id = " . $GLOBALS['gClientId'] . ")", "data_type" => "tinyint", "conjunction" => "and");
			} else {
                $filters['image_exists'] = array("form_label" => "Copy of FFL license on file", "where" => "file_id is not null", "data_type" => "tinyint", "conjunction" => "and");
                $filters['sot_image_exists'] = array("form_label" => "Copy of SOT license on file", "where" => "sot_file_id is not null", "data_type" => "tinyint", "conjunction" => "and");
            }
            $this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where user_location = 0 and inactive = 0 and product_distributor_id is null and client_id = ? order by description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "custom_field_data",
				"referenced_column_name" => "primary_identifier", "foreign_key" => "federal_firearms_licensee_id", "description" => "text_data",
				"extra_where" => "custom_field_id in (select custom_field_id from custom_fields where custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS'))"));

		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("ffl_locations", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_locations", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_locations", "form_label", "FFL Location");
		$this->iDataSource->addColumnControl("ffl_locations", "list_table", "ffl_locations");
		$this->iDataSource->addColumnControl("ffl_locations", "help_label", "Connect this FFL to one of your store locations.");
		$this->iDataSource->addColumnControl("ffl_locations", "get_choices", "locationChoices");

		$this->iDataSource->addColumnControl("ffl_category_restrictions", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_category_restrictions", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("ffl_category_restrictions", "form_label", "Restricted Categories");
		$this->iDataSource->addColumnControl("ffl_category_restrictions", "links_table", "ffl_category_restrictions");
		$this->iDataSource->addColumnControl("ffl_category_restrictions", "control_table", "product_categories");

		$this->iDataSource->addColumnControl("contact_id_display", "data_type", "int");
		$this->iDataSource->addColumnControl("contact_id_display", "readonly", true);
		$this->iDataSource->addColumnControl("contact_id_display", "form_label", "Contact ID");
		if (canAccessPageCode("CONTACTMAINT")) {
			$this->iDataSource->addColumnControl("contact_id_display", "help_label", "Click to open Contact record");
		}

		$this->iDataSource->addColumnControl("ffl_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_product_manufacturers", "form_label", "Manufacturer Tags");
		$this->iDataSource->addColumnControl("ffl_product_manufacturers", "list_table", "ffl_product_manufacturers");

		$this->iDataSource->addColumnControl("ffl_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_product_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("ffl_product_departments", "form_label", "Departments Carried");
		$this->iDataSource->addColumnControl("ffl_product_departments", "control_table", "product_departments");
		$this->iDataSource->addColumnControl("ffl_product_departments", "links_table", "ffl_product_departments");

		$this->iDataSource->addColumnControl("ffl_contacts", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_contacts", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_contacts", "form_label", "Store Contacts");
		$this->iDataSource->addColumnControl("ffl_contacts", "list_table", "ffl_contacts");

		$this->iDataSource->addColumnControl("image_id", "form_label", "Primary Image");
		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");

		$this->iDataSource->addColumnControl("ffl_manufacturer_restrictions", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_manufacturer_restrictions", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("ffl_manufacturer_restrictions", "form_label", "Restricted Manufacturers");
		$this->iDataSource->addColumnControl("ffl_manufacturer_restrictions", "links_table", "ffl_manufacturer_restrictions");
		$this->iDataSource->addColumnControl("ffl_manufacturer_restrictions", "control_table", "product_manufacturers");

		$this->iDataSource->addColumnControl("ffl_product_restrictions", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_product_restrictions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_product_restrictions", "form_label", "Restricted Products");
		$this->iDataSource->addColumnControl("ffl_product_restrictions", "list_table", "ffl_product_restrictions");

		$this->iDataSource->addColumnControl("license_lookup", "readonly", false);
		$this->iDataSource->addColumnControl("license_lookup", "not_null", false);

		$this->iDataSource->addColumnLikeColumn("mailing_address_1", "contacts", "address_1");
		$this->iDataSource->addColumnLikeColumn("mailing_address_2", "contacts", "address_2");
		$this->iDataSource->addColumnLikeColumn("mailing_city", "contacts", "city");
		$this->iDataSource->addColumnLikeColumn("mailing_state", "contacts", "state");
		$this->iDataSource->addColumnLikeColumn("mailing_postal_code", "contacts", "postal_code");
		$this->iDataSource->addColumnControl("mailing_postal_code", "data-state_field", "mailing_state");
		$this->iDataSource->addColumnControl("mailing_postal_code", "data-city_field", "mailing_city");
		$this->iDataSource->addColumnLikeColumn("mailing_country_id", "contacts", "country_id");
		$this->iDataSource->addColumnControl("phone_number", "not_null", false);
		$this->iDataSource->addColumnControl("phone_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("phone_number", "form_label", "Phone Number");
		$this->iDataSource->addColumnControl("phone_number", "select_value", "select phone_number from phone_numbers where description = 'Store' and contact_id = federal_firearms_licensees.contact_id limit 1");

		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("mailing_city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("mailing_city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("business_name", "not_null", true);
		$this->iDataSource->addColumnControl("country_id", "default_value", "1000");
		$this->iDataSource->addColumnControl("country_id", "data_type", "hidden");
		$this->iDataSource->addColumnControl("mailing_country_id", "default_value", "1000");
		$this->iDataSource->addColumnControl("mailing_country_id", "data_type", "hidden");
		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("date_created", "readonly", true);
		$this->iDataSource->addColumnControl("expiration_date", "not_null", true);
		$this->iDataSource->addColumnControl("state", "css-width", "60px");
		$this->iDataSource->addColumnControl("mailing_state", "css-width", "60px");
		$this->iDataSource->addColumnControl("preferred", "form_label", "Preferred Dealer");

		$this->iDataSource->addColumnControl("content", "css-height", "400px");

		$this->iDataSource->addColumnControl("ffl_images", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_images", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_images", "form_label", "Images");
		$this->iDataSource->addColumnControl("ffl_images", "list_table", "ffl_images");
		$this->iDataSource->addColumnControl("ffl_images", "list_table_controls", array("image_id" => array("data_type" => "image_input")));

		$this->iDataSource->addColumnControl("ffl_files", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_files", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_files", "form_label", "Files");
		$this->iDataSource->addColumnControl("ffl_files", "list_table", "ffl_files");

		$this->iDataSource->addColumnControl("ffl_videos", "data_type", "custom");
		$this->iDataSource->addColumnControl("ffl_videos", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("ffl_videos", "form_label", "Videos");
		$this->iDataSource->addColumnControl("ffl_videos", "list_table", "ffl_videos");

		if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
			$this->iDataSource->addColumnControl("contact_id_display", "data_type", "hidden");
			$this->iDataSource->getPrimaryTable()->setLimitByClient(false);
			$this->iDataSource->getJoinTable()->setLimitByClient(false);
			$this->iDataSource->addFilterWhere("federal_firearms_licensees.client_id = " . $GLOBALS['gDefaultClientId']);

			$allColumns = $this->iDataSource->getColumns();
			$dataTable = new DataTable("federal_firearms_licensee_details");
			$detailsColumns = $dataTable->getColumns();
			foreach ($allColumns as $thisColumn) {
				$columnName = $thisColumn->getName();
				if (!array_key_exists("federal_firearms_licensee_details." . $columnName, $detailsColumns)) {
					$this->iDataSource->addColumnControl($columnName, "readonly", true);
				} else {
					$this->iDataSource->addColumnControl($columnName, "form_line_classes", "centralized-editable");
				}
			}
		}
		$allColumns = $this->iDataSource->getColumns();
	}

	function addCustomFields() {
		$customFields = CustomField::getCustomFields("ffl");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
				$customField->addColumnControl("readonly", true);
			}
			echo $customField->getControl();
		}
	}

	function beforeSaveChanges(&$nameValues) {
		if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
			return true;
		}
		if (strlen($nameValues['license_number']) == 15) {
			$nameValues['license_number'] = substr($nameValues['license_number'], 0, 1) . "-" . substr($nameValues['license_number'], 1, 2) . "-" . substr($nameValues['license_number'], 3, 3) . "-" . substr($nameValues['license_number'], 6, 2) . "-" . substr($nameValues['license_number'], 8, 2) . "-" . substr($nameValues['license_number'], 10, 5);
		}
		if (strlen($nameValues['license_number']) != 20) {
			return "Invalid License Number";
		}
		$nameValues['license_number'] = strtoupper($nameValues['license_number']);
		for ($x = 0; $x < 20; $x++) {
			$thisChar = substr($nameValues['license_number'], $x, 1);
			if ($x == 1 || $x == 4 || $x == 8 || $x == 11 || $x == 14) {
				if ($thisChar != "-") {
					return "Invalid License Number";
				}
			} else if ($x == 13) {
				if ($thisChar < "A" || $thisChar > "M") {
					return "Invalid License Number";
				}
			} else if ($thisChar < "0" || $thisChar > "9") {
				return "Invalid License Number";
			}
		}
		$nameValues['license_lookup'] = substr($nameValues['license_number'], 0, 5) . substr($nameValues['license_number'], 15, 5);
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['contact_id_display'] = array("data_value" => $returnArray['contact_id']['data_value']);
		if (!empty($returnArray['source_id']['data_value'])) {
			$returnArray['atf_source'] = true;
		} else {
			$returnArray['atf_source'] = "";
		}
		$resultSet = executeQuery("select * from addresses where contact_id = ? and address_label = 'Mailing'", $returnArray['contact_id']['data_value']);
		if (!$row = getNextRow($resultSet)) {
			$row = array();
		}
		$returnArray["mailing_address_1"] = array("data_value" => $row['address_1'], "crc_value" => getCrcValue($row['address_1']));
		$returnArray["mailing_address_2"] = array("data_value" => $row['address_2'], "crc_value" => getCrcValue($row['address_2']));
		$returnArray["mailing_city"] = array("data_value" => $row['city'], "crc_value" => getCrcValue($row['city']));
		$returnArray["mailing_state"] = array("data_value" => $row['state'], "crc_value" => getCrcValue($row['state']));
		$returnArray["mailing_postal_code"] = array("data_value" => $row['postal_code'], "crc_value" => getCrcValue($row['postal_code']));
		$returnArray["mailing_country_id"] = array("data_value" => "1000", "crc_value" => getCrcValue("1000"));
		$resultSet = executeQuery("select * from phone_numbers where description = 'Store' and contact_id = ?", $returnArray['contact_id']['data_value']);
		if (!$row = getNextRow($resultSet)) {
			$row = array();
		}
		$returnArray["phone_number"] = array("data_value" => $row['phone_number'], "crc_value" => getCrcValue($row['phone_number']));

		$resultSet = executeQuery("select * from ffl_availability where federal_firearms_licensee_id = ?", $returnArray['primary_id']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['available_' . $row['weekday'] . "_" . str_replace(".", "p", showSignificant($row['hour'], 2))] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
		}
		for ($hour = 0; $hour < 24; $hour += .5) {
			for ($weekday = 0; $weekday < 7; $weekday++) {
				$displayHour = showSignificant($hour, 2);
				$fieldName = "available_" . $weekday . "_" . str_replace(".", "p", $displayHour);
				if (!array_key_exists($fieldName, $returnArray)) {
					$returnArray[$fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				}
			}
		}
		$customFields = CustomField::getCustomFields("ffl");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
		if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
			$detailsRow = getRowFromId("federal_firearms_licensee_details", "federal_firearms_licensee_id", $returnArray['primary_id']['data_value']);
			if (!empty($detailsRow)) {
				foreach ($detailsRow as $fieldName => $fieldData) {
					if (strlen($fieldData) == 0 || in_array($fieldName, array("federal_firearms_licensee_detail_id", "federal_firearms_licensee_id", "version"))) {
						continue;
					}
                    if(in_array($fieldName, ['file_id','sot_file_id'])) {
                        $returnArray[$fieldName . "_download"] = array("data_value" => "download.php?id=" . $fieldData);
                    }
                    $returnArray[$fieldName] = array("data_value" => $fieldData, "crc_value" => getCrcValue($fieldData));
				}
			}
		}
		return true;
	}

	function saveChanges() {
		if ($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] || empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
			return false;
		}
		$detailsRow = getRowFromId("federal_firearms_licensee_details", "federal_firearms_licensee_id", $_POST['primary_id']);
		$dataTable = new DataTable("federal_firearms_licensee_details");
		$returnArray = array();
		$_POST['federal_firearms_licensee_id'] = $_POST['primary_id'];
		if (!$dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => $detailsRow['federal_firearms_licensee_detail_id']))) {
			$returnArray['error_message'] = $dataTable->getErrorMessage();
		} else {
			$returnArray['info_message'] = "Information Saved";
		}
		ajaxResponse($returnArray);
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
			return true;
		}
		$customFields = CustomField::getCustomFields("ffl");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}

		$resultSet = executeQuery("delete from ffl_availability where federal_firearms_licensee_id = ?", $nameValues['primary_id']);
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (!empty($fieldValue) && substr($fieldName, 0, strlen("available_")) == "available_") {
				$parts = explode("_", $fieldValue);
				$weekday = $parts[0];
				$hour = $parts[1];
				$resultSet = executeQuery("insert into ffl_availability (federal_firearms_licensee_id,weekday,hour) values (?,?,?)",
						$nameValues['primary_id'], $weekday, $hour);
				if (!empty($resultSet['sql_error'])) {
					return getSystemMessage("basic", $resultSet['sql_error']);
				}
			}
		}

		$dataTable = new DataTable("phone_numbers");
		$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "description", "Store", "contact_id = ?", $nameValues['contact_id']);
		$dataTable->setPrimaryId($phoneNumberId);
		if (empty($nameValues['phone_number'])) {
			$dataTable->deleteRecord();
		} else {
			$dataTable->saveRecord(array("name_values" => array("contact_id" => $nameValues['contact_id'], "phone_number" => $nameValues['phone_number'], "description" => "Store")));
		}
		$dataTable = new DataTable("addresses");
		$addressId = getFieldFromId("address_id", "addresses", "address_label", "Mailing", "contact_id = ?", $nameValues['contact_id']);
		$dataTable->setPrimaryId($addressId);
		if (empty($nameValues['mailing_address_1']) && empty($nameValues['mailing_postal_code'])) {
			$dataTable->deleteRecord();
		} else {
			$dataTable->saveRecord(array("name_values" => array("contact_id" => $nameValues['contact_id'], "address_label" => "Mailing", "address_1" => $nameValues['mailing_address_1'], "address_2" => $nameValues['mailing_address_2'], "city" => $nameValues['mailing_city'], "state" => $nameValues['mailing_state'], "postal_code" => $nameValues['mailing_postal_code'], "country_id" => $nameValues['mailing_country_id'])));
		}
		$geoCode = getAddressGeocode($nameValues);
		if (!empty($geoCode) && !empty($geoCode['validation_status']) && !empty($geoCode['latitude']) && !empty($geoCode['longitude'])) {
			executeQuery("update contacts set latitude = ?,longitude = ? where contact_id = ?", $geoCode['latitude'], $geoCode['longitude'], $nameValues['contact_id']);
		}
		return true;
	}

	function afterSaveDone($nameValues) {
		if (!empty($nameValues['file_id'])) {
			$gunBrokerOrderSet = executeQuery("select * from orders where date_completed is null and client_id = ? and federal_firearms_licensee_id = ? and "
					. "purchase_order_number is not null and order_method_id in (select order_method_id from order_methods where order_method_code = 'GUNBROKER')",
					$GLOBALS['gClientId'], $nameValues['primary_id']);
			while ($gunBrokerOrderRow = getNextRow($gunBrokerOrderSet)) {
				Order::updateGunbrokerOrder($gunBrokerOrderRow['order_id'], $gunBrokerOrderRow);
			}
		}
	}

	function onLoadJavascript() {
		?>
		<script>
			<?php if (canAccessPageCode("CONTACTMAINT")) { ?>
			$("#contact_id_display").click(function () {
				window.open("/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=" + $(this).val());
			});
			<?php } ?>
			$("#all_available").click(function () {
				$("input[type=checkbox][id^=available_]").prop("checked", !$("#available_0_0.00").is(":checked"));
			});
			$(".weekday").click(function () {
				const selectedWeekday = $(this).data("weekday");
				let isChecked = null;
				$("input[type=checkbox][id^=available_]").each(function () {
					if ($(this).data("weekday") === selectedWeekday) {
						if (isChecked === null) {
							isChecked = $(this).prop("checked");
						}
						$(this).prop("checked", !isChecked);
					}
				});
			});
			$(".hour").click(function () {
				const selectedHour = $(this).data("hour");
				let isChecked = null;
				$("input[type=checkbox][id^=available_]").each(function () {
					if ($(this).data("hour") === selectedHour) {
						if (isChecked === null) {
							isChecked = $(this).prop("checked");
						}
						$(this).prop("checked", !isChecked);
					}
				});
			});
			<?php if ($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] || !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) { ?>
			$("#license_number").blur(function () {
				$("#license_lookup").val($("#license_number").val().substr(0, 5) + $("#license_number").val().substr(15, 5));
			});
			$("#license_lookup").blur(function () {
				$("#license_lookup").val($("#license_number").val().substr(0, 5) + $("#license_number").val().substr(15, 5));
			});
			$("#postal_code").blur(function () {
				if ($("#country_id").val() === "1000") {
					validatePostalCode();
				}
			});
			<?php } ?>
			$("#country_id").change(function () {
				$("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
				$("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
				$("#_city_row").show();
				$("#_city_select_row").hide();
				if ($("#country_id").val() === "1000") {
					validatePostalCode();
				}
			});
			$("#city_select").change(function () {
				$("#city").val($(this).val());
				$("#state").val($(this).find("option:selected").data("state"));
			});
			<?php if ($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] || !empty(getPreference("CENTRALIZED_FFL_STORAGE"))) { ?>
			$("#mailing_postal_code").blur(function () {
				if ($("#mailing_country_id").val() === "1000") {
					validatePostalCode("mailing_postal_code");
				}
			});
			<?php } ?>
			$("#mailing_country_id").change(function () {
				$("#mailing_city").add("#mailing_state").prop("readonly", $("#mailing_country_id").val() === "1000");
				$("#mailing_city").add("#mailing_state").attr("tabindex", ($("#mailing_country_id").val() === "1000" ? "9999" : "10"));
				$("#_mailing_city_row").show();
				$("#_mailing_city_select_row").hide();
				if ($("#mailing_country_id").val() === "1000") {
					validatePostalCode("mailing_postal_code");
				}
			});
			$("#mailing_city_select").change(function () {
				$("#mailing_city").val($(this).val());
				$("#mailing_state").val($(this).find("option:selected").data("state"));
			});
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			function afterGetRecord(returnArray) {
				$("#_city_select_row").hide();
				$("#_city_row").show();
				$("#_mailing_city_select_row").hide();
				$("#_mailing_city_row").show();
				$("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
				$("#mailing_city").add("#mailing_state").attr("tabindex", ($("#mailing_country_id").val() === "1000" ? "9999" : "10"));
				<?php if ($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] || empty(getPreference("CENTRALIZED_FFL_STORAGE"))) { ?>
				$("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
				$("#mailing_city").add("#mailing_state").prop("readonly", $("#mailing_country_id").val() === "1000");
				$("#business_name").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#address_1").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#address_2").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#city").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#state").prop("readonly", !empty(returnArray['atf_source']));
				$("#postal_code").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#mailing_address_1").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#mailing_address_2").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#mailing_city").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#mailing_state").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				$("#mailing_postal_code").prop("readonly", (!empty(returnArray['atf_source']) && !empty($("#primary_id").val())));
				<?php } ?>
			}
		</script>
		<?php
	}

	function availabilityHours() {
		$readonly = ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE")));
		?>
		<table class="grid-table">
			<tr>
				<th id="all_available">Hour</th>
				<th class="weekday" data-weekday="0">Sunday</th>
				<th class="weekday" data-weekday="1">Monday</th>
				<th class="weekday" data-weekday="2">Tuesday</th>
				<th class="weekday" data-weekday="3">Wednesday</th>
				<th class="weekday" data-weekday="4">Thursday</th>
				<th class="weekday" data-weekday="5">Friday</th>
				<th class="weekday" data-weekday="6">Saturday</th>
			</tr>
			<?php
			for ($hour = 0; $hour < 24; $hour += .5) {
				$displayHour = ($hour == 0 ? "12 midnight" : ($hour >= 12.5 ? floor(($hour < 13 ? 12 : $hour - 12)) . ($hour == floor($hour) ? ":00" : ":30") . " pm" : ($hour == 12 ? $hour . " noon" : floor($hour < 1 ? 12 : $hour) . ($hour == floor($hour) ? ":00" : ":30") . " am")));
				?>
				<tr>
					<th class="hour" data-hour="<?= showSignificant($hour, 2) ?>"><?= $displayHour ?></th>
					<?php
					for ($weekday = 0; $weekday < 7; $weekday++) {
						$fieldId = $weekday . "_" . str_replace(".", "p", showSignificant($hour, 2));
						?>
						<td class="align-center"><input data-weekday="<?= $weekday ?>" data-hour="<?= showSignificant($hour, 2) ?>" <?= ($readonly ? "disabled='disabled' " : "") ?> type="checkbox"<?= ($GLOBALS['gPermissionLevel'] < 2 ? "disabled='disabled' " : "") ?> id="available_<?= $fieldId ?>" name="available_<?= $fieldId ?>" value="<?= $weekday ?>_<?= showSignificant($hour, 2) ?>"/></td>
					<?php } ?>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			.centralized-editable label,p.centralized-editable {
				color: rgb(160, 65, 190);
				font-weight: 900;
			}
			#facility_calendar {
				border: 1px solid rgb(200, 200, 200);
				padding: 20px;
			}

			#contact_id_display {
				cursor: pointer;
			}
		</style>
		<?php
	}

}

$pageObject = new FederalFirearmsLicenseeMaintenance("federal_firearms_licensees");
$pageObject->displayPage();
