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

$GLOBALS['gPageCode'] = "FORMMAINT";
require_once "shared/startup.inc";

class FormMaintenancePage extends Page {

	function executePageUrlActions() {
		if ($_GET['url_action'] == "select_contacts" && $GLOBALS['gPermissionLevel'] > 1) {
			$count = 0;
			$pageId = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
			$resultSet = executeQuery("select contact_id from forms where form_id in (select primary_identifier from selected_rows where " .
				"page_id = ? and user_id = ?) and contact_id is not null and contact_id not in (select primary_identifier from selected_rows where user_id = ? and page_id = ?)",
				$GLOBALS['gPageId'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $pageId);
			$contactArray = array();
			while ($row = getNextRow($resultSet)) {
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) values (?,?,?)", $GLOBALS['gUserId'], $pageId, $row['contact_id']);
				$count++;
			}

			$returnArray['info_message'] = $count . " contacts selected";
			echo jsonEncode(array());
			exit;
		}
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['no_connected_form'] = array("form_label" => "No Connected Form", "where" => "form_id not in (select parent_form_id from forms where parent_form_id is not null) and parent_form_id is null", "conjunction" => "and", "data_type" => "tinyint");
			$filters['from_date'] = array("form_label" => "Earliest Date Submitted", "where" => "date_created >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['to_date'] = array("form_label" => "Latest Date Submitted", "where" => "date_created <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");

			$formGroups = array();
			$resultSet = executeQuery("select * from form_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$formGroups[$row['form_group_id']] = $row['description'];
			}
			$filters['form_group_id'] = array("form_label" => "Form Group", "where" => "form_id in (select form_id from form_group_links where form_group_id = %key_value%)", "data_type" => "select", "choices" => $formGroups);

			$resultSet = executeQuery("select * from form_definitions where client_id = ? and parent_form_required = 0 order by description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['form_definition_' . $row['form_definition_id']] = array("form_label" => $row['description'], "where" => "form_definition_id = " . $row['form_definition_id'], "data_type" => "tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "form_definition_id", "date_created", "date_completed", "first_name", "last_name", "connected_forms"));
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("select_contacts", "Select Contacts of Selected Forms");
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("form_group_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("form_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("form_group_links", "form_label", "Groups");
		$this->iDataSource->addColumnControl("form_group_links", "links_table", "form_group_links");
		$this->iDataSource->addColumnControl("form_group_links", "control_table", "form_groups");
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = forms.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = forms.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last Name");
		$this->iDataSource->addColumnControl("ip_address", "readonly", true);
		$this->iDataSource->addColumnControl("connected_forms", "select_value", "select count(*) from forms as subform where parent_form_id = forms.form_id");
		$this->iDataSource->addColumnControl("connected_forms", "data_type", "int");
		$this->iDataSource->addColumnControl("connected_forms", "form_label", "Connected Forms");
		$this->iDataSource->getPrimaryTable()->setSubtables(array("form_data", "form_notes"));
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->setFilterWhere("parent_form_id is null and (form_definition_id in (select form_definition_id from form_definitions where user_group_id is null) or " .
			$GLOBALS['gUserId'] . " in (select user_id from user_group_members where user_group_id = (select user_group_id from form_definitions where " .
			"form_definition_id = forms.form_definition_id))" . ($GLOBALS['gUserRow']['superuser_flag'] ? " or 1=1" : "") . ") and form_definition_id in " .
			"(select form_definition_id from form_definitions where client_id = " . $GLOBALS['gClientId'] . ")");
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "select_contacts") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_contacts");
                    return true;
                }
                return false;
            }

            function afterGetRecord(returnArray) {
                console.log(returnArray);
                $("#form_status").html("");
                if ("form_definition_status" in returnArray) {
                    for (const i in returnArray['form_definition_status']) {
                        if (returnArray['form_definition_status'][i]['text_only'] === "1") {
                            $("#form_status").append("<h3>" + returnArray['form_definition_status'][i]['description'] + "</h3>");
                        } else {
                            $("#form_status").append("<p class='form-definition-status-wrapper'><input class='form-definition-status' type='checkbox'" + (empty(returnArray['form_definition_status'][i]['checked']) ? "" : " checked") +
                                " name='form_definition_status_id_" + returnArray['form_definition_status'][i]['form_definition_status_id'] +
                                "' id='form_definition_status_id_" + returnArray['form_definition_status'][i]['form_definition_status_id'] +
                                "' data-crc_value='" + getCrcValue(returnArray['form_definition_status'][i]['checked']) + "'>" +
                                "<input type='text' class='form-definition-status-date-completed validate[custom[date]]' id='form_definition_status_date_completed_" + returnArray['form_definition_status'][i]['form_definition_status_id'] +
                                "' name='form_definition_status_date_completed_" + returnArray['form_definition_status'][i]['form_definition_status_id'] + "' placeholder='Date Completed' value='" + returnArray['form_definition_status'][i]['date_completed'] + "'>" +
                                "<label for='form_definition_status_id_" + returnArray['form_definition_status'][i]['form_definition_status_id'] +
                                "' class='checkbox-label' >" + returnArray['form_definition_status'][i]['description'] + "</label></p>");
                        }
                    }
                }
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("change",".form-definition-status-date-completed",function() {
                console.log($(this).val());
                if (!empty($(this).val())) {
                    $(this).closest(".form-definition-status-wrapper").find(".form-definition-status").prop("checked",true);
                }
            });
        </script>
		<?php
	}

	function beforeDeleteRecord($primaryId) {
		$contactId = getFieldFromId("contact_id", "forms", "form_id", $primaryId);
		if (!empty($contactId)) {
			removeCachedData("last_form_date_" . $contactId, "*", true);
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$paymentDetails = "";
		if (!empty($returnArray['donation_id']['data_value'])) {
			$paymentDetails = "Includes payment of $" . number_format(getFieldFromId("amount", "donations", "donation_id", $returnArray['donation_id']['data_value']), 2, ".", ",") . " for " . getFieldFromId("description", "designations", "designation_id", getFieldFromId("designation_id", "donations", "donation_id", $returnArray['donation_id']['data_value']));
		}
		$returnArray['payment_details'] = array("data_value" => $paymentDetails);
		$returnArray['first_name'] = array("data_value" => getFieldFromId("first_name", "contacts", "contact_id", $returnArray['contact_id']['data_value']));
		$returnArray['last_name'] = array("data_value" => getFieldFromId("last_name", "contacts", "contact_id", $returnArray['contact_id']['data_value']));
		$returnArray['user_id'] = array("data_value" => (empty($returnArray['user_id']['data_value']) ? "" : getUserDisplayName($returnArray['user_id']['data_value'])));
		$returnArray['contact_link'] = array("data_value" => (empty($returnArray['contact_id']['data_value']) ? "" : "<a href='/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $returnArray['contact_id']['data_value'] . "' target='_blank'>Contact Record</a>"));
		$formId = $returnArray['primary_id']['data_value'];
		if (!empty($returnArray['primary_id']['data_value'])) {
			$returnArray['forms_links'] = array();
			$formLinks = "<a target='_blank' href='/displayform.php?form_id=" . $formId . "'>View this form</a>";
			$resultSet = executeQuery("select * from forms where parent_form_id = ?", $formId);
			while ($row = getNextRow($resultSet)) {
				$description = getFieldFromId("description", "form_definitions", "form_definition_id", $row['form_definition_id']);
				$formLinks .= "<br/><a target='_blank' href='/displayform.php?form_id=" . $row['form_id'] . "'>View related form '" . htmlText($description) . "'</a>";
			}
			$returnArray['forms_links']['data_value'] = $formLinks;
		}
		$returnArray['form_definition_status'] = array();
		$resultSet = executeQuery("select * from form_definition_status where form_definition_id = ? order by sort_order,description", $returnArray['form_definition_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$formStatusRow = getRowFromId("form_status", "form_id", $formId, "form_definition_status_id = ?", $row['form_definition_status_id']);
			$checkbox = (empty($formStatusRow) ? "0" : "1");
			$returnArray['form_definition_status'][] = array("form_definition_status_id" => $row['form_definition_status_id'], "description" => htmlText($row['description']), "text_only" => $row['text_only'], "checked" => $checkbox, "date_completed" => (empty($formStatusRow['date_completed']) ? "" : date("m/d/Y", strtotime($formStatusRow['date_completed']))));
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("select * from form_definition_status where form_definition_id = ? order by sort_order,description", getFieldFromId("form_definition_id", "forms", "form_id", $nameValues['primary_id']));
		while ($row = getNextRow($resultSet)) {
			if (empty($nameValues['form_definition_status_id_' . $row['form_definition_status_id']])) {
				$saveSet = executeQuery("delete from form_status where form_definition_status_id = ? and form_id = ?", $row['form_definition_status_id'], $nameValues['primary_id']);
				if (!empty($saveSet['sql_error'])) {
					return getSystemMessage("basic", $resultSet['sql_error']);
				}
			} else {
				$formStatusId = getFieldFromId("form_status_id", "form_status", "form_id", $nameValues['primary_id'], "form_definition_status_id = ?", $row['form_definition_status_id']);
				if (empty($formStatusId)) {
					$saveSet = executeQuery("insert ignore into form_status (form_id,form_definition_status_id,date_completed) values (?,?,?)", $nameValues['primary_id'], $row['form_definition_status_id'], (empty($nameValues['form_definition_status_date_completed_' . $row['form_definition_status_id']]) ? "" : date("Y-m-d", strtotime($nameValues['form_definition_status_date_completed_' . $row['form_definition_status_id']]))));
					if (!empty($saveSet['sql_error'])) {
						return getSystemMessage("basic", $resultSet['sql_error']);
					}
				} else {
					$saveSet = executeQuery("update form_status set date_completed = ? where form_status_id = ?", (empty($nameValues['form_definition_status_date_completed_' . $row['form_definition_status_id']]) ? "" : date("Y-m-d", strtotime($nameValues['form_definition_status_date_completed_' . $row['form_definition_status_id']]))), $formStatusId);
					if (!empty($saveSet['sql_error'])) {
						return getSystemMessage("basic", $resultSet['sql_error']);
					}
				}
			}
		}
		return true;
	}

	function internalCSS() {
		?>
        <style>
            input[type=text].form-definition-status-date-completed {
                margin-left: 15px;
                margin-right: 10px;
                width: 150px;
            }
        </style>
		<?php
	}
}

$pageObject = new FormMaintenancePage("forms");
$pageObject->displayPage();
