<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "TIMELOG";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts where contact_id = time_log.contact_id");

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("first_name", "last_name", "business_name"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_id", "business_name", "description", "log_date", "total_hours"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("user_id", "business_name", "description"));
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("totaltime", "Total Selected Time");

			$filters = array();
			$contactList = array();
			$resultSet = executeQuery("select * from time_log join contacts using (contact_id) where time_log.user_id = ? order by business_name,last_name", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$contactList[$row['contact_id']] = (empty($row['business_name']) ? getDisplayName($row['contact_id']) : $row['business_name']);
			}

			$filters['contact_id'] = array("form_label" => "Contact", "where" => "contact_id = %key_value%", "data_type" => "select", "choices" => $contactList);
			$filters['current_month'] = array("form_label" => "Current Month", "where" => "MONTH(log_date) = MONTH(CURRENT_DATE()) AND YEAR(log_date) = YEAR(CURRENT_DATE())", "data_type" => "tinyint");
			$filters['last_month'] = array("form_label" => "Last Month", "where" => "log_date BETWEEN DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01 00:00:00') AND DATE_FORMAT(LAST_DAY(NOW() - INTERVAL 1 MONTH), '%Y-%m-%d 23:59:59')", "data_type" => "tinyint");

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function contactPresets() {
		$resultSet = executeQuery("select contact_id,address_1,state,city,email_address from contacts where deleted = 0 and client_id = ? " .
			"and contact_id in (select contact_id from time_log where client_id = ? and log_date > date_sub(current_date,interval 6 month)) order by date_created", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
		$contactList = array();
		while ($row = getNextRow($resultSet)) {
			$description = getDisplayName($row['contact_id'], array("include_company" => true, "prepend_company" => true));
			if (!empty($row['address_1'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['address_1'];
			}
			if (!empty($row['state'])) {
				if (!empty($row['city'])) {
					$row['city'] .= ", ";
				}
				$row['city'] .= $row['state'];
			}
			if (!empty($row['city'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['city'];
			}
			if (!empty($row['email_address'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['email_address'];
			}
			$contactList[$row['contact_id']] = $description;
		}
		asort($contactList);
		return $contactList;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "totaltime":
				$totalHours = 0;
				$totalIncome = 0;
				$badCompany = "";
				$resultSet = executeQuery("select contact_id,sum(total_hours) total_hours from time_log where time_log_id in (" .
					"select primary_identifier from selected_rows where user_id = ? and page_id = ?) group by contact_id",
					$GLOBALS['gUserId'], $GLOBALS['gPageId']);
				while ($row = getNextRow($resultSet)) {
					$hourlyRate = getFieldFromId("number_data", "custom_field_data", "primary_identifier", $row['contact_id'],
						"custom_field_id = (select custom_field_id from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and client_id = ? and custom_field_code = 'HOURLY_RATE')",
						$GLOBALS['gClientId']);
					$thisHours = $row['total_hours'];
					$totalHours += $thisHours;
					if ($totalIncome !== false) {
						if (empty($hourlyRate)) {
							$totalIncome = false;
							$badCompany = getDisplayName($row['contact_id']);
						} else {
							$totalIncome += $hourlyRate * $thisHours;
						}
					}
				}
				$returnArray['info_message'] = "Total time is " . number_format($totalHours, 2, ".", ",");
				if ($totalIncome === false) {
					$returnArray['info_message'] .= ", Income can't be calculated: " . $badCompany;
				} else {
					$returnArray['info_message'] .= ", Income is " . number_format($totalIncome, 2, ".", ",");
				}
				ajaxResponse($returnArray);
				break;
			case "calculate_time":
				$startTime = $_GET['time_started'];
				$endTime = date("U");
				$totalHours = round(($endTime - $startTime) / 3600, 1);
				$returnArray['total_hours'] = $totalHours;
				ajaxResponse($returnArray);
				break;
		}
	}

	function userChoices($showInactive) {
		return userChoices($showInactive);
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#start_clock_button").click(function () {
                $("#start_clock").val("start");
                $("#_start_time_row").show();
                return false;
            });
            $("#total_time_button").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=calculate_time&time_started=" + $("#time_started").val(), function(returnArray) {
                    if ("total_hours" in returnArray) {
                        $("#total_hours").val(returnArray['total_hours']);
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "totaltime") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=totaltime", function(returnArray) {
                        if ("info_message" in returnArray) {
                            displayInfoMessage(returnArray['info_message']);
                        }
                    });
                    return true;
                }
                return false;
            }
            function afterGetRecord() {
                if ($("#time_started").val() == "") {
                    $("#_total_time_button_row").hide();
                    $("#_start_clock_button_row").show();
                    $("#_start_time_row").hide();
                } else {
                    $("#_total_time_button_row").show();
                    $("#_start_clock_button_row").hide();
                    $("#_start_time_row").show();
                }
            }
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if ($nameValues['start_clock'] == "start") {
			executeQuery("update time_log set start_time = now() where time_log_id = ?", $nameValues['primary_id']);
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['time_started'] = array("data_value" => ($returnArray['start_time']['data_value'] ? date("U", strtotime($returnArray['start_time']['data_value'])) : ""));
		if (empty($returnArray['start_time']['data_value'])) {
			$returnArray['start_time']['data_value'] = date("m/d/Y g:i:s");
		}
	}
}

$pageObject = new ThisPage("time_log");
$pageObject->displayPage();
