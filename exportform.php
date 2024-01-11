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

$GLOBALS['gPageCode'] = "EXPORTFORM";
$GLOBALS['gProxyPageCode'] = "FORMMAINT";
require_once "shared/startup.inc";

class ExportFormPage extends Page {
    var $iFormDefinitionRow = array();

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$formDefinitionId = getReadFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_POST['form_definition_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")"));
				if (empty($formDefinitionId)) {
					$returnArray['error_message'] = "Invalid Form";
					ajaxResponse($returnArray);
					break;
				}
                $this->iFormDefinitionRow = getReadRowFromId("form_definitions","form_definition_id",$formDefinitionId);
				$formDefinitionCode = $this->iFormDefinitionRow['form_definition_code'];
				$this->fileHeader($formDefinitionCode);

				$query = "select * from forms where form_definition_id = ?" . (empty($_POST['date_created_from']) ? "" : " and date_created >= ?") . (empty($_POST['date_created_to']) ? "" : " and date_created <= ?");
				$parameters = array($formDefinitionId);
				if (!empty($_POST['date_created_from'])) {
					$parameters[] = makeDateParameter($_POST['date_created_from']);
				}
				if (!empty($_POST['date_created_to'])) {
					$parameters[] = makeDateParameter($_POST['date_created_to']);
				}
				$this->exportData($query, $parameters);
				exit;
		}
	}

	function fileHeader($formDefinitionCode) {
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=\"" . (empty($formDefinitionCode) ? "forms" : strtolower($formDefinitionCode)) . ".csv\"");
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
	}

	function exportData($query, $parameters) {
		$resultSet = executeReadQuery($query, $parameters);
		$formDataArray = array();
		$userSubmitted = false;
		$contactCreated = false;
		$donationsExist = false;
        $domainName = getDomainName();
        $fieldOrder = array();
        if (!empty($this->iFormDefinitionRow['form_content'])) {
	        $formContentArray = getContentLines($this->iFormDefinitionRow['form_content']);
            foreach ($formContentArray as $thisLine) {
	            if (startsWith($thisLine,"%field:")) {
		            $fieldName = str_replace("%","",substr($thisLine,strlen("%field:")));
		            $fieldOrder[] = $fieldName . ":0";
	            }
	            if (startsWith($thisLine,"%parent:")) {
		            $fieldName = str_replace("%","",substr($thisLine,strlen("%parent:")));
		            $fieldOrder[] = $fieldName . ":1";
	            }
            }
        }
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['user_id'])) {
				$userSubmitted = true;
			}
			if (!empty($row['contact_id'])) {
				$contactCreated = true;
			}
			$rawDataArray = array();
			$rawFieldArray = array();
			if (empty($_POST['include_parent'])) {
				$dataSet = executeReadQuery("select * from form_data join form_fields using (form_field_id) where form_id = ? and " .
					"form_field_id not in (select form_field_id from form_field_controls where control_name = 'data_type' and control_value = 'signature')", $row['form_id']);
			} else {
				$dataSet = executeReadQuery("select * from form_data join form_fields using (form_field_id) where (form_id = ? or form_id = ?) and " .
					"form_field_id not in (select form_field_id from form_field_controls where control_name = 'data_type' and control_value = 'signature')", $row['form_id'], $row['parent_form_id']);
			}
			while ($dataRow = getNextRow($dataSet)) {
				$parentForm = ($dataRow['form_id'] == $row['parent_form_id']);
                $fieldKey = $dataRow['form_field_code'] . ":" . ($parentForm ? "1" : "0");
				$rawFieldArray[$fieldKey] = array("parent_form" => $parentForm, "form_field_id" => $dataRow['form_field_id'], "description" => $dataRow['description'] . ($parentForm ? " (Parent Form)" : ""));
				$rawDataArray[$fieldKey] = $dataRow;
			}
            $dataArray = array();
            $fieldArray = array();
			foreach ($fieldOrder as $fieldKey) {
				if (array_key_exists($fieldKey,$rawDataArray)) {
					$dataArray[$fieldKey] = $rawDataArray[$fieldKey];
					$parentForm = ($rawDataArray[$fieldKey]['form_id'] == $row['parent_form_id']);
					$fieldArray[] = array("parent_form" => $parentForm, "form_field_id" => $rawDataArray[$fieldKey]['form_field_id'], "form_field_code"=>$rawDataArray[$fieldKey]['form_field_code'], "description" => $rawDataArray[$fieldKey]['description'] . ($parentForm ? " (Parent Form)" : ""));
				}
			}
			foreach ($rawDataArray as $fieldKey => $thisData) {
				if (!in_array($fieldKey,$fieldOrder)) {
					$dataArray[$fieldKey] = $thisData;
					$parentForm = ($thisData['form_id'] == $row['parent_form_id']);
					$fieldArray[] = array("parent_form" => $parentForm, "form_field_id" => $thisData['form_field_id'], "form_field_code"=>$thisData['form_field_code'], "description" => $thisData['description'] . ($parentForm ? " (Parent Form)" : ""));
				}
			}
			$row['form_data'] = $dataArray;
			if (!empty($row['donation_id'])) {
				$donationsExist = true;
			}
			$formDataArray[] = $row;
		}
		$header = '"Date Created"';
		if ($userSubmitted) {
			$header .= (empty($header) ? "" : ",") . '"Submitted By"';
		}
		if ($contactCreated) {
			$header .= (empty($header) ? "" : ",") . '"First Name"';
			$header .= (empty($header) ? "" : ",") . '"Last Name"';
			$header .= (empty($header) ? "" : ",") . '"Address 1"';
			$header .= (empty($header) ? "" : ",") . '"Address 2"';
			$header .= (empty($header) ? "" : ",") . '"City"';
			$header .= (empty($header) ? "" : ",") . '"State"';
			$header .= (empty($header) ? "" : ",") . '"Postal Code"';
			$header .= (empty($header) ? "" : ",") . '"Country"';
			$header .= (empty($header) ? "" : ",") . '"Email Address"';
			$header .= (empty($header) ? "" : ",") . '"Birthdate"';
		}
		foreach ($fieldArray as $formFieldCode => $formFieldInfo) {
			$header .= (empty($header) ? "" : ",") . '"' . str_replace('"', '""', $formFieldInfo['description']) . '"';
		}
		if ($donationsExist) {
			$header .= ',"Designation","Amount"';
		}

		echo $header . "\n";
		foreach ($formDataArray as $formRow) {
			$dataLine = "";
			$dataLine .= (empty($dataLine) ? "" : ",") . '"' . date("m/d/Y", strtotime($formRow['date_created'])) . '"';
			if ($userSubmitted) {
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . (empty($formRow['user_id']) ? "" : getUserDisplayName($formRow['user_id'])) . '"';
			}
			if ($contactCreated) {
				$contactRow = Contact::getContact($formRow['contact_id']);
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['first_name']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['last_name']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['address_1']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['address_2']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['city']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['state']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['postal_code']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', getReadFieldFromId("country_name", "countries", "country_id", $contactRow['country_id'])) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $contactRow['email_address']) . '"';
				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . (empty($contactRow['birthdate']) ? "" : date("m/d/Y", strtotime($contactRow['birthdate']))) . '"';
			}
			foreach ($fieldArray as $formFieldCode => $formFieldInfo) {
				$parentForm = $formFieldInfo['parent_form'];
                $fieldKey = $formFieldInfo['form_field_code'] . ":" . ($parentForm ? "1" : "0");
				if (!array_key_exists($fieldKey, $formRow['form_data'])) {
					$dataLine .= (empty($dataLine) ? "" : ",") . '""';
					continue;
				}
				$dataRow = $formRow['form_data'][$fieldKey];
				if (!empty($dataRow['integer_data'])) {
					$fieldValue = $dataRow['integer_data'];
				} else if (!empty($dataRow['number_data'])) {
					$fieldValue = $dataRow['number_data'];
				} else if (!empty($dataRow['date_data'])) {
					$fieldValue = $dataRow['date_data'];
				} else if (!empty($dataRow['image_id'])) {
					$fieldValue = ($_POST['use_download_links'] ? $domainName . "/getimage.php?id=" : "") . $dataRow['image_id'];
				} else if (!empty($dataRow['file_id'])) {
					$fieldValue = ($_POST['use_download_links'] ? $domainName . "/download.php?id=" : "") . $dataRow['file_id'];
				} else {
					$fieldValue = $dataRow['text_data'];
				}
				$dataType = getReadFieldFromId("control_value", "form_field_controls", "form_field_id", $formFieldInfo['form_field_id'], "control_name = 'data_type'");
				switch ($dataType) {
					case "radio":
					case "select":
						$thisColumn = new DataColumn($formFieldInfo['form_field_code']);
						$choicesControlValue = getReadFieldFromId("control_value", "form_field_controls", "form_field_id", $formFieldInfo['form_field_id'], "control_name = 'choices'");
						$thisColumn->setControlValue("choices", $choicesControlValue);
						$choices = $thisColumn->getChoices($this, true);
						$choiceSet = executeReadQuery("select * from form_field_choices where form_field_id = (select form_field_id from form_fields where form_field_code = ? and client_id = ?)", $thisColumn->getControlValue('column_name'), $GLOBALS['gClientId']);
						while ($choiceRow = getNextRow($choiceSet)) {
							$choices[$choiceRow['key_value']] = array("key_value" => $choiceRow['key_value'], "description" => $choiceRow['description']);
						}
						if (!empty($choices)) {
							$fieldValue = $choices[$fieldValue]['description'];
						}
						break;
				}

				$dataLine .= (empty($dataLine) ? "" : ",") . '"' . str_replace('"', '""', $fieldValue) . '"';
			}
			if ($donationsExist) {
				$donationRow = getReadRowFromId("donations", "donation_id", $formRow['donation_id']);
				$dataLine .= ',"' . getReadFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']) . '","' . $donationRow['amount'] . '"';
			}
			echo $dataLine . "\r\n";
		}
	}

	function mainContent() {
		$formDefinitionId = getReadFieldFromId("form_definition_id", "form_definitions", "form_definition_code", $_GET['code'],
			"(user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = ?))", $GLOBALS['gUserId']);
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line" id="_form_definition_id_row">
                    <label for="form_definition_id" class="required-label">Form Definition</label>
                    <select tabindex="10" id="form_definition_id" name="form_definition_id" class="validate[required]<?= (empty($formDefinitionId) ? "" : " disabled-select") ?>">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeReadQuery("select * from form_definitions where client_id = ? " . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "and " .
								"(user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")) ") . "order by description", $GLOBALS['gClientId']);
						while ($row = getnextRow($resultSet)) {
							?>
                            <option<?= ($formDefinitionId == $row['form_definition_id'] ? " selected" : "") ?> value="<?= $row['form_definition_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_date_created_row">
                    <label for="date_created_from">Date Submitted: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_created_from" name="date_created_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_created_to" name="date_created_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_parent_row">
                    <input tabindex="10" type="checkbox" id="include_parent" name="include_parent"><label for="include_parent" class="checkbox-label">Include Parent Form Data</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_use_download_links_row">
                    <input tabindex="10" type="checkbox" id="use_download_links" name="use_download_links"><label for="use_download_links" class="checkbox-label">Use links for attached files and images</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Download CSV</button>
                </div>

            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#create_report", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                }
                return false;
            });
        </script>
		<?php
	}

}

$pageObject = new ExportFormPage();
$formDefinitionId = "";
if (!empty($_GET['id'])) {
	$formDefinitionId = getReadFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_GET['id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")"));
	$formDefinitionCode = getReadFieldFromId("form_definition_code", "form_definitions", "form_definition_id", $formDefinitionId);
	$pageObject->fileHeader($formDefinitionCode);
	if (empty($formDefinitionId)) {
		echo "Invalid Form";
		exit;
	}

	$query = "select * from forms where form_definition_id = ?";
	$parameters = array($formDefinitionId);
	$pageObject->exportData($query, $parameters);
	exit;
} else {
	$pageObject->displayPage();
}
