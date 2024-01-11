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

$GLOBALS['gPageCode'] = "PAYROLLPARAMETERVALUES";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "list"));
		}
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function mainContent() {
		$resultSet = executeQuery("select * from payroll_parameters order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$value = getFieldFromId("parameter_value", "payroll_parameter_values", "payroll_parameter_id", $row['payroll_parameter_id']);
			?>
            <div class="basic-form-line">
                <label for="payroll_parameter_id_<?= $row['payroll_parameter_id'] ?>"><?= htmlText($row['description']) ?></label>
                <input type="text" class="field-text" size="60" id="payroll_parameter_id_<?= $row['payroll_parameter_id'] ?>" name="payroll_parameter_id_<?= $row['payroll_parameter_id'] ?>" value="<?= htmlText($value) ?>">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
		return true;
	}

	function javascript() {
		?>
        <script>
            function saveChanges() {
                if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_changes", $("#_edit_form").serialize());
                }
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#_save_button", function () {
                saveChanges();
                return false;
            });
            displayFormHeader();
            $(".page-next-button").hide();
            $(".page-previous-button").hide();
            $(".page-record-display").hide();
            enableButtons();
            $("#_management_content").wrapInner("<form id='_edit_form'></form>");
        </script>
		<?php
		return true;
	}

	function saveChanges() {
		$returnArray = array();
		foreach ($_POST as $fieldName => $parameterValue) {
			if (substr($fieldName, 0, strlen("payroll_parameter_id_")) == "payroll_parameter_id_") {
				$payrollParameterId = substr($fieldName, strlen("payroll_parameter_id_"));
				if (!is_numeric($payrollParameterId)) {
					continue;
				}
				$resultSet = executeQuery("select * from payroll_parameter_values where payroll_parameter_id = ? and client_id = ?",
					$payrollParameterId, $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					executeQuery("update payroll_parameter_values set parameter_value = ? where payroll_parameter_value_id = ?",
						$parameterValue, $row['payroll_parameter_value_id']);
				} else {
					executeQuery("insert into payroll_parameter_values (client_id,payroll_parameter_id,parameter_value) values (?,?,?)",
						$GLOBALS['gClientId'], $payrollParameterId, $parameterValue);
				}
			}
		}
		$returnArray['info_message'] = "Parameters successfully saved";
		ajaxResponse($returnArray);
	}

	function internalCSS() {
		?>
        <style>
            #_previous_section {
                display: none;
            }
            #_next_section {
                display: none;
            }
            #_record_number_section {
                display: none;
            }
            #_selected_section {
                display: none;
            }
            .maximum-label .basic-form-line label, .basic-form-line.maximum-label label {
                width: 650px;
            }
            .maximum-label .basic-form-line span.required-tag, .basic-form-line.maximum-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 635px;
            }
            .maximum-label .basic-form-line span.help-label, .basic-form-line.maximum-label span.help-label {
                width: 635px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("payroll_parameters");
$pageObject->displayPage();
