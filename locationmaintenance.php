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

$GLOBALS['gPageCode'] = "LOCATIONMAINT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 60000;

class LocationMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
				$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address", "link_name", "sort_order", "inactive"));
			} else {
				$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "location_code", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address", "link_name", "sort_order", "inactive", "internal_use_only"));
			}
			if (!$GLOBALS['gUserRow']['superuser_flag']) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			}
			$filters = array();
			$filters['non_distributor'] = array("form_label" => "Show only non-distributor locations", "where" => "product_distributor_id is null");

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$this->iTemplateObject->getTableEditorObject()->addCustomAction("create_locations", "Create all distributor locations");
			}
		}
	}

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
			case "create_locations":
				if (!$GLOBALS['gUserRow']['superuser_flag']) {
					ajaxResponse($returnArray);
					exit;
				}
				$productDistributors = array();
				$resultSet = executeQuery("select * from product_distributors where internal_use_only = 0 and inactive = 0 and product_distributor_id not in (select product_distributor_id from locations where client_id = ? and product_distributor_id is not null)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productDistributors[] = $row;
				}
				foreach ($productDistributors as $productDistributorRow) {
					$insertSet = executeQuery("insert into contacts (client_id,business_name,country_id,date_created) values (?,?,1000,current_date)", $GLOBALS['gClientId'], $productDistributorRow['description']);
					if (!empty($insertSet['sql_error'])) {
						return getSystemMessage("basic", $insertSet['sql_error']);
					}
					$productDistributorContactId = $insertSet['insert_id'];
					$dataTable = new DataTable("locations");
					if (!$dataTable->saveRecord(array("name_values" => array("location_code" => $productDistributorRow['product_distributor_code'], "description" => $productDistributorRow['description'], "contact_id" => $productDistributorContactId,
						"product_distributor_id" => $productDistributorRow['product_distributor_id'], "user_id" => $GLOBALS['gUserId'])))) {
						return $dataTable->getErrorMessage();
					}
				}
				ajaxResponse($returnArray);
				exit;
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("out_of_stock_threshold", "help_label", "Quantity levels at or below this amount will be considered out of stock");
		$this->iDataSource->addColumnControl("cost_threshold", "help_label", "Products with cost at or below this amount at this location will be considered out of stock (distributors only)");

		$this->iDataSource->addColumnControl("warehouse_location", "help_label", "A warehouse location is treated like a distributor.");
		$this->iDataSource->addColumnControl("product_distributor_id", "empty_text", "[Local Location]");

		$this->iDataSource->addColumnControl("phone_numbers", "form_label", "Phone Numbers");
		$this->iDataSource->addColumnControl("phone_numbers", "data_type", "custom");
		$this->iDataSource->addColumnControl("phone_numbers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("phone_numbers", "foreign_key_field", "contact_id");
		$this->iDataSource->addColumnControl("phone_numbers", "primary_key_field", "contact_id");
		$this->iDataSource->addColumnControl("phone_numbers", "list_table", "phone_numbers");
		$this->iDataSource->addColumnControl("phone_numbers", "list_table_controls", array("description" => array("inline-width" => "150px"), "phone_number" => array("inline-width" => "150px")));

		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("country_id", "default_value", "1000");
		$this->iDataSource->addColumnControl("date_created", "default_value", "return date(\"m/d/Y\")");
		$this->iDataSource->addColumnControl("state", "css-width", "80px");
		$this->iDataSource->addColumnControl("user_id", "default_value", $GLOBALS['gUserId']);

		$this->iDataSource->addColumnControl("cannot_ship", "form_label", "Does not ship (Local Location) or Does not dropship (Distributor)");

		$this->iDataSource->addColumnControl("percentage", "help_label", "Increase distributor's cost by this percentage. Only applies to distributor locations.");
		$this->iDataSource->addColumnControl("amount", "help_label", "Increase distributor's cost by this amount. Only applies to distributor locations.");

		if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->addColumnControl("location_code", "default_value", strtoupper(getRandomString(40)));
			$this->iDataSource->addColumnControl("user_location", "default_value", "1");
			$this->iDataSource->setFilterWhere("((user_id = " . $GLOBALS['gUserId'] . " and user_location = 1) or (location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))))");
		} else {
			$this->iDataSource->setFilterWhere("user_location = 0");
		}
		$this->iDataSource->getPrimaryTable()->setSubtables(array("location_availability", "location_closures", "location_credentials"));

		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "data_type", "select");
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "form_label", "FFL");
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "get_choices", "fflChoices");
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "not_null", false);
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "not_editable", true);
		}
		$this->iDataSource->addColumnControl("license_lookup", "size", 32);
		$this->iDataSource->addColumnControl("license_lookup", "maximum_length", 32);
	}

	function fflChoices($showInactive = false) {
		$fflChoices = array();
		$resultSet = executeQuery("select * from federal_firearms_licensees join contacts using (contact_id) where client_id = ? and federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$fflName = (empty($row['business_name']) ? $row['licensee_name'] : $row['business_name']);
			$fflChoices[$row['federal_firearms_licensee_id']] = array("key_value" => $row['federal_firearms_licensee_id'], "description" => $fflName, "inactive" => ($row['inactive'] == 1));
		}
		freeResult($resultSet);
		return $fflChoices;
	}

	function availabilityHours() {
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
				$displayHour = (floor($hour) == 12 || floor($hour) == 0 ? "12" : ($hour > 12 ? floor($hour) - 12 : floor($hour)));
				if ($hour == 0 || $hour == 12) {
					$displayHour .= ($hour == 0 ? " midnight" : ($hour == 12 ? " noon" : ""));
				} else {
					$displayHour .= ":" . ($hour == floor($hour) ? "00" : "30");
					$displayHour .= " " . ($hour > 12 ? "pm" : "am");
				}
				$dataHour = floor($hour) . (floor($hour) == $hour ? "" : "p5");
				?>
                <tr>
                    <th class="hour" data-hour="<?= $dataHour ?>"><?= $displayHour ?></th>
					<?php for ($weekday = 0; $weekday < 7; $weekday++) { ?>
                        <td class="align-center"><input type="checkbox"<?= ($GLOBALS['gPermissionLevel'] < 2 ? "disabled='disabled' " : "") ?> id="available_<?= $weekday ?>_<?= $dataHour ?>" name="available_<?= $weekday ?>_<?= $dataHour ?>" value="1"/></td>
					<?php } ?>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$nameValues['user_location'] = 1;
		}
		$nameValues['license_lookup'] = str_replace("-", "", $nameValues['license_lookup']);
		if (strlen($nameValues['license_lookup']) == 15) {
			$nameValues['license_lookup'] = substr($nameValues['license_lookup'], 0, 1) . "-" . substr($nameValues['license_lookup'], 1, 2) . "-" . substr($nameValues['license_lookup'], 10, 5);
		}
		if (strlen($nameValues['license_lookup']) == 8) {
			$nameValues['license_lookup'] = substr($nameValues['license_lookup'], 0, 1) . "-" . substr($nameValues['license_lookup'], 1, 2) . "-" . substr($nameValues['license_lookup'], 3, 5);
		}
		$licenseParts = explode("-",$nameValues['license_lookup']);
		if (count($licenseParts) > 3) {
			$nameValues['license_lookup'] = $licenseParts[0] . "-" . $licenseParts[1] . "-" . $licenseParts[5];
		}
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#product_distributor_id").change(function() {
                if (empty($(this).val())) {
                    $("#local_location_wrapper").removeClass("hidden");
                } else {
                    $("#local_location_wrapper").addClass("hidden");
                }
            });
            $("#all_available").click(function () {
                $("input[type=checkbox][id^=available_]").prop("checked", !$("#available_0_0").is(":checked"));
            });
            $(".weekday").click(function () {
                $("input[type=checkbox][id^=available_" + $(this).data("weekday") + "]").prop("checked", !$("#available_" + $(this).data("weekday") + "_0").is(":checked"));
            });
            $(".hour").click(function () {
                var thisHour = $(this).data("hour");
                $("input[type=checkbox][id^=available_]").filter("input[type=checkbox][id$=_" + thisHour + "]").prop("checked", !$("#available_0_" + thisHour).is(":checked"));
            });
            $("#postal_code").blur(function () {
                if ($("#country_id").val() == "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() == "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() == "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() == "1000") {
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
            $("#license_lookup").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=validate_ffl&license_lookup=" + encodeURIComponent($(this).val()), function (returnArray) {
                        if ("ffl_information" in returnArray) {
                            $("#ffl_information").html(returnArray['ffl_information']);
                        }
                        if ("license_lookup" in returnArray) {
                            $("#license_lookup").val(returnArray['license_lookup']);
                        }
                    });
                } else {
                    $("#ffl_information").html("");
                }
                return false;
            })
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			if (!empty($nameValues['federal_firearms_licensee_id'])) {
				executeQuery("insert into ffl_locations (federal_firearms_licensee_id,location_id) values (?,?)", $nameValues['federal_firearms_licensee_id'], $nameValues['primary_id']);
			}
		}
		executeQuery("delete from location_availability where location_id = ?", $nameValues['primary_id']);
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (!empty($fieldValue) && substr($fieldName, 0, strlen("available_")) == "available_") {
				$parts = explode("_", $fieldName);
				$weekday = $parts[1];
				$hour = $parts[2];
				$hour = floatval(str_replace("p", ".", $hour));
				$resultSet = executeQuery("insert into location_availability (location_id,weekday,hour) values (?,?,?)",
					$nameValues['primary_id'], $weekday, $hour);
				if (!empty($resultSet['sql_error'])) {
					return getSystemMessage("basic", $resultSet['sql_error']);
				}
			}
		}
		$customFields = CustomField::getCustomFields("locations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			$federalFirearmsLicenseeId = getFieldFromId("federal_firearms_licensee_id", "ffl_locations", "location_id", $returnArray['primary_id']['data_value']);
			$returnArray['federal_firearms_licensee_id'] = array("data_value" => $federalFirearmsLicenseeId, "crc_value" => getCrcValue($federalFirearmsLicenseeId));
		}
		if (!empty($returnArray['primary_id']['data_value'])) {
			$resultSet = executeQuery("select * from location_availability where location_id = ?", $returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				$displayHour = floor($row['hour']) . (floor($row['hour']) == $row['hour'] ? "" : "p5");
				$returnArray['available_' . $row['weekday'] . "_" . $displayHour] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
			}
		}
		for ($hour = 0; $hour < 24; $hour += .5) {
			for ($weekday = 0; $weekday < 7; $weekday++) {
				$fieldName = "available_" . $weekday . "_" . floor($hour) . (floor($hour) == $hour ? "" : "p5");
				if (!array_key_exists($fieldName, $returnArray)) {
					$returnArray[$fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				}
			}
		}
		$customFields = CustomField::getCustomFields("locations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
		return true;
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#city").add("#state").prop("readonly", $("#country_id").val() == "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() == "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
                if (!empty(returnArray['primary_id']['data_value'])) {
                    if (empty(returnArray['product_distributor_id']['data_value'])) {
                        $("#product_distributor_id").prop("disabled", false);
                    } else {
                        $("#product_distributor_id").prop("disabled", true);
                    }
                } else {
                    $("#product_distributor_id").prop("disabled", false);
                }
            }

            function customActions(actionName) {
                if (actionName === "create_locations") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_locations", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                        }
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}

	function addCustomFields() {
		$customFields = CustomField::getCustomFields("locations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("locations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}

$pageObject = new LocationMaintenancePage("locations");
$pageObject->displayPage();
