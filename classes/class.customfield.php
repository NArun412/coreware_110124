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

class CustomField {
	static private $iCreatedCustomFields = array();
	static private $iCustomFields = false;
    static private $iCustomFieldsClientId = false;
    static private $iLoadedPrimaryIds = array();
	private $iCustomFieldId;
	private $iCustomFieldRow = array();
	private $iCustomFieldControl = array();
	private $iCustomFieldControls = false;
	private $iAdditionalControls = array();
	private $iCustomFieldChoices = false;
	private $iErrorMessage = "";
	private $iPrimaryIdentifier = "";
	private $iColumnName = "";

	function __construct($customFieldId, $columnName = "") {
        self::loadCustomFields();
		$this->iCustomFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_id", $customFieldId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		if (empty($this->iCustomFieldId)) {
			return false;
		} else {
			$this->iCustomFieldRow = getRowFromId("custom_fields", "custom_field_id", $this->iCustomFieldId);
		}
		if (!$GLOBALS['gUserRow']['full_client_access'] && !empty($this->iCustomFieldRow['user_group_id'])) {
			$userGroupMemberId = getFieldFromId("user_group_member_id", "user_group_members", "user_group_id", $this->iCustomFieldRow['user_group_id'],
			"user_id = ?", $GLOBALS['gUserId']);
			if (empty($userGroupMemberId)) {
				return false;
			}
		}
		$this->iColumnName = (empty($columnName) ? "custom_field_id_" . $this->iCustomFieldId : $columnName);
		$thisColumn = new DataColumn($this->iColumnName);
		$this->iCustomFieldControls = array();
		$thisColumn->setControlValue("form_label", $this->iCustomFieldRow['form_label']);
		$this->iCustomFieldControls['form_label'] = array("control_name" => "form_label", "control_value" => $this->iCustomFieldRow['form_label']);

		$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
		while ($controlRow = getNextRow($controlSet)) {
			$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
		}

		foreach ($this->iCustomFieldControls as $controlRow) {
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$choices = $thisColumn->getControlValue("choices");
		if ($this->iCustomFieldChoices === false) {
			$this->iCustomFieldChoices = array();
			$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $this->iCustomFieldId);
			while ($choiceRow = getNextRow($choiceSet)) {
				$this->iCustomFieldChoices[] = $choiceRow;
			}
		}
		foreach ($this->iCustomFieldChoices as $choiceRow) {
			$choices[$choiceRow['key_value']] = $choiceRow['description'];
		}
		$thisColumn->setControlValue("choices", $choices);
		$this->iCustomFieldControl = $thisColumn;
	}

    private static function loadCustomFields() {
	    if (self::$iCustomFields === false || self::$iCustomFieldsClientId != $GLOBALS['gClientId']) {
		    self::$iCustomFields = array();
		    $resultSet = executeQuery("select custom_field_id,custom_field_code,custom_field_type_id," .
			    "(select custom_field_type_code from custom_field_types where custom_field_type_id = custom_fields.custom_field_type_id) as custom_field_type_code," .
                "(select control_value from custom_field_controls where custom_field_id = custom_fields.custom_field_id and control_name = 'data_type' limit 1) as data_type from custom_fields " .
			    "where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		    while ($row = getNextRow($resultSet)) {
			    self::$iCustomFields[$row['custom_field_code'] . ":" . $row['custom_field_type_code']] = $row;
		    }
            self::$iCustomFieldsClientId = $GLOBALS['gClientId'];
	    }
    }

	public static function getCustomFields($customFieldTypeCode, $customFieldGroupCode = false, $excludeCustomFieldCodes = array()) {
		self::loadCustomFields();
		$parameters = array(strtoupper($customFieldTypeCode), $GLOBALS['gClientId']);
		if (empty($GLOBALS['gUserRow']['full_client_access'])) {
			$parameters[] = $GLOBALS['gUserId'];
		}
		if (!empty($customFieldGroupCode)) {
			if (!is_array($customFieldGroupCode)) {
				$customFieldGroupCode = explode(",", $customFieldGroupCode);
			}
			foreach ($customFieldGroupCode as $thisGroupCode) {
				$parameters[] = strtoupper($thisGroupCode);
			}
			$parameters[] = $GLOBALS['gClientId'];
		}
		$customFields = array();
        $resultSet = executeQuery("select *,(select group_concat(custom_field_group_code) from custom_field_group_links join custom_field_groups using (custom_field_group_id) where " .
            "custom_field_id = custom_fields.custom_field_id) custom_field_group_codes from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = ?) and " .
            "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? " .
            ($GLOBALS['gUserRow']['full_client_access'] ? "" : " and (user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = ?))") .
            (empty($customFieldGroupCode) ? ($customFieldGroupCode === false ? "" : " and custom_field_id not in (select custom_field_id from custom_field_group_links)") :
                " and custom_field_id in (select custom_field_id from custom_field_group_links where custom_field_group_id = (select custom_field_group_id from custom_field_groups where " .
                "custom_field_group_code in (" . implode(",", array_fill(0, count($customFieldGroupCode), "?")) . ") and client_id = ?))") . " order by sort_order,description", $parameters);
		while ($row = getNextRow($resultSet)) {
			$customFieldGroupCodes = array_filter(explode(",", $row['custom_field_group_codes']));
			if (in_array($row['custom_field_code'], $excludeCustomFieldCodes)) {
				continue;
			}
			$row['custom_field_group_codes'] = $customFieldGroupCodes;
			$customFields[$row['custom_field_id']] = $row;
		}
		return $customFields;
	}

	public static function getCustomFieldByCode($customFieldCode, $customFieldTypeCode = "CONTACTS", $columnName = "") {
		self::loadCustomFields();
        if (array_key_exists(strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode),self::$iCustomFields)) {
            $customFieldId = self::$iCustomFields[strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode)]['custom_field_id'];
        } else {
            $customFieldId = false;
        }
		return self::getCustomField($customFieldId, $columnName);
	}

    public static function getCustomFieldIdFromCode($customFieldCode, $customFieldTypeCode = "CONTACTS") {
	    self::loadCustomFields();
	    if (array_key_exists(strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode),self::$iCustomFields)) {
		    $customFieldId = self::$iCustomFields[strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode)]['custom_field_id'];
	    } else {
		    $customFieldId = false;
	    }
        return $customFieldId;
    }

	public static function getCustomField($customFieldId, $columnName = "") {
		self::loadCustomFields();
		if (empty($customFieldId)) {
			return false;
		}
		if (array_key_exists($customFieldId . ":" . $columnName, self::$iCreatedCustomFields)) {
			$customField = self::$iCreatedCustomFields[$customFieldId . ":" . $columnName];
		} else {
			$customField = getCachedData("custom_field_object", $customFieldId . ":" . $columnName);
			if (empty($customField) || !is_object($customField) || !is_a($customField, "CustomField") || empty($customField->getColumn()) || !is_object($customField->getColumn()) || !is_a($customField->getColumn(), "DataColumn")) {
				$customField = new CustomField($customFieldId, $columnName);
				setCachedData("custom_field_object", $customFieldId . ":" . $columnName, $customField);
			}
			if ($customField) {
				self::$iCreatedCustomFields[$customFieldId . ":" . $columnName] = $customField;
			}
		}
		return $customField;
	}

	public static function getCustomFieldData($primaryIdentifier, $customFieldCode, $customFieldTypeCode = "CONTACTS", $preloadAll = false) {
		if (empty($primaryIdentifier)) {
			return false;
		}
		self::loadCustomFields();
		if (!$preloadAll) {
			$fieldValue = getCachedData("custom_field_data", $primaryIdentifier . ":" . $customFieldCode . ":" . $customFieldTypeCode);
			if (is_array($fieldValue)) {
				return $fieldValue[0];
			}
		}
		$fieldValue = false;
		$customFieldCode = strtoupper($customFieldCode);
		if (array_key_exists(strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode), self::$iCustomFields)) {
			$row = self::$iCustomFields[strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode)];
			if (is_array($row) && empty($row)) {
				return false;
			}
			$customFieldId = $row['custom_field_id'];
			$dataType = $row['data_type'];
		} else {
			self::$iCustomFields[strtoupper($customFieldCode) . ":" . strtoupper($customFieldTypeCode)] = array();
			return $fieldValue;
		}
		if (!array_key_exists($customFieldId, $GLOBALS['gCustomFieldData'])) {
			$GLOBALS['gCustomFieldData'][$customFieldId] = array("preload" => false, "data" => array());
			if ($preloadAll) {
				$GLOBALS['gCustomFieldData'][$customFieldId]['preload'] = true;
				$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ?", $customFieldId);
				while ($dataRow = getNextRow($dataSet)) {
					switch ($dataType) {
						case "date":
							$fieldValue = $dataRow['date_data'];
							break;
						case "int":
							$fieldValue = $dataRow['integer_data'];
							break;
						case "decimal":
							$fieldValue = $dataRow['number_data'];
							break;
						case "image":
							$fieldValue = $dataRow['image_id'];
							break;
						case "file":
							$fieldValue = $dataRow['file_id'];
							break;
						case "tinyint":
							$fieldValue = ($dataRow['text_data'] ? "1" : "0");
							break;
						default:
							$fieldValue = $dataRow['text_data'];
							break;
					}
					$GLOBALS['gCustomFieldData'][$customFieldId]['data'][$dataRow['primary_identifier']] = $fieldValue;
				}
			}
		}
		if (!array_key_exists($primaryIdentifier, $GLOBALS['gCustomFieldData'][$customFieldId]['data'])) {
			if ($GLOBALS['gCustomFieldData'][$customFieldId]['preload']) {
				return false;
			}
            if (array_key_exists($primaryIdentifier,self::$iLoadedPrimaryIds)) {
	            if (!array_key_exists($customFieldId, $GLOBALS['gCustomFieldData'])) {
		            $GLOBALS['gCustomFieldData'][$customFieldId] = array("preload" => false, "data" => array());
	            }
            } else {
	            $dataSet = executeQuery("select *,(select custom_field_type_code from custom_field_types where custom_field_type_id = custom_fields.custom_field_type_id) as custom_field_type_code from " .
		            "custom_field_data join custom_fields using (custom_field_id) where primary_identifier = ?", $primaryIdentifier);
	            while ($dataRow = getNextRow($dataSet)) {
		            if (!array_key_exists($dataRow['custom_field_id'], $GLOBALS['gCustomFieldData'])) {
			            $GLOBALS['gCustomFieldData'][$dataRow['custom_field_id']] = array("preload" => false, "data" => array());
		            }
		            if (array_key_exists(strtoupper($dataRow['custom_field_code']) . ":" . strtoupper($dataRow['custom_field_type_code']), self::$iCustomFields)) {
			            $row = self::$iCustomFields[strtoupper($dataRow['custom_field_code']) . ":" . strtoupper($dataRow['custom_field_type_code'])];
			            if (is_array($row) && empty($row)) {
				            continue;
			            }
			            $thisDataType = $row['data_type'];
		            } else {
			            continue;
		            }
		            switch ($thisDataType) {
			            case "date":
				            $fieldValue = $dataRow['date_data'];
				            break;
			            case "int":
				            $fieldValue = $dataRow['integer_data'];
				            break;
			            case "decimal":
				            $fieldValue = $dataRow['number_data'];
				            break;
			            case "image":
				            $fieldValue = $dataRow['image_id'];
				            break;
			            case "file":
				            $fieldValue = $dataRow['file_id'];
				            break;
			            case "tinyint":
				            $fieldValue = ($dataRow['text_data'] ? "1" : "0");
				            break;
			            default:
				            $fieldValue = $dataRow['text_data'];
				            break;
		            }
		            $GLOBALS['gCustomFieldData'][$dataRow['custom_field_id']]['data'][$primaryIdentifier] = $fieldValue;
	            }
                self::$iLoadedPrimaryIds[$primaryIdentifier] = true;
            }
            if (!array_key_exists($primaryIdentifier, $GLOBALS['gCustomFieldData'][$customFieldId]['data'])) {
	            $GLOBALS['gCustomFieldData'][$customFieldId]['data'][$primaryIdentifier] = false;
	            return false;
            }
		}
        $fieldValue = $GLOBALS['gCustomFieldData'][$customFieldId]['data'][$primaryIdentifier];
		setCachedData("custom_field_data", $primaryIdentifier . ":" . $customFieldCode . ":" . $customFieldTypeCode, array($fieldValue));
		return $fieldValue;
	}

	/*
		Parameters can include:
			- "control_element_only" - get control element only. The default is to get the element wrapped in a form-line div
			- "classes" - array or comma separated list of classes needed by the control element
	*/

	public static function setCustomFieldData($primaryIdentifier, $customFieldCodes, $dataValue = "", $customFieldTypeCode = "CONTACTS") {
		self::loadCustomFields();
		if (!is_array($customFieldCodes)) {
			$customFieldCodes = array(array("custom_field_code" => $customFieldCodes, "data_value" => $dataValue));
		} else {
			if (count($customFieldCodes) == 2 && array_key_exists("custom_field_code", $customFieldCodes)) {
				$customFieldCodes = array($customFieldCodes);
			}
		}
		$returnValue = true;
		foreach ($customFieldCodes as $customFieldInfo) {
			if (!array_key_exists("custom_field_code", $customFieldInfo) || !array_key_exists("data_value", $customFieldInfo)) {
				$returnValue = false;
				continue;
			}
			$customFieldCode = $customFieldInfo['custom_field_code'];
			$fieldValue = $customFieldInfo['data_value'];

			$cachedValue = getCachedData("custom_field_data", $primaryIdentifier . ":" . $customFieldCode . ":" . $customFieldTypeCode);
			if (is_array($cachedValue) && $cachedValue[0] == $fieldValue) {
				continue;
			}
			removeCachedData("custom_field_data", $primaryIdentifier . ":" . $customFieldCode . ":" . $customFieldTypeCode);

			$resultSet = executeQuery("select * from custom_fields where custom_field_code = ? and client_id = ? and inactive = 0 and " .
				"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = ? and client_id = ?)",
				strtoupper($customFieldCode), $GLOBALS['gClientId'], strtoupper($customFieldTypeCode), $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$customFieldId = $row['custom_field_id'];
				$dataType = "";
				$controlSet = executeQuery("select * from custom_field_controls where custom_field_id = ? and control_name = 'data_type'", $customFieldId);
				if ($controlRow = getNextRow($controlSet)) {
					$dataType = $controlRow['control_value'];
				}
			} else {
				$returnValue = false;
				continue;
			}
			$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $customFieldId, $primaryIdentifier);
			if (!$dataRow = getNextRow($dataSet)) {
				$dataRow = array();
			}
			switch ($dataType) {
				case "date":
					$fieldName = 'date_data';
					break;
				case "int":
					$fieldName = 'integer_data';
					break;
				case "decimal":
					$fieldName = 'number_data';
					break;
				case "image_input":
				case "image":
					$fieldName = 'image_id';
					break;
				case "file":
					$fieldName = 'file_id';
					break;
				default:
					$fieldName = 'text_data';
					break;
			}
			$dataSource = new DataSource("custom_field_data");
			$dataSource->disableTransactions();
			$dataSource->setSaveOnlyPresent(true);
			if (strlen($fieldValue) > 0) {
				if (!$dataSource->saveRecord(array("name_values" => array("custom_field_id" => $customFieldId, "primary_identifier" => $primaryIdentifier, $fieldName => $fieldValue), "primary_id" => $dataRow['custom_field_data_id']))) {
					$returnValue = false;
				} else {
					setCachedData("custom_field_data", $primaryIdentifier . ":" . $customFieldCode . ":" . $customFieldTypeCode, array($fieldValue));
				}
			} else if (!empty($dataRow['custom_field_data_id'])) {
				$dataSource->deleteRecord(array("primary_id" => $dataRow['custom_field_data_id']));
			}
		}
		return $returnValue;
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function getColumn() {
		return $this->iCustomFieldControl;
	}

	public function getColumnName() {
		return $this->iColumnName;
	}

	public function setPrimaryIdentifier($primaryIdentifier) {
		$this->iPrimaryIdentifier = $primaryIdentifier;
	}

	public function addColumnControl($controlName, $controlValue) {
		$this->iAdditionalControls[$controlName] = $controlValue;
	}

	public function getColumnControl($controlName) {
		return $this->iCustomFieldControls[$controlName]['control_value'];
	}

	public function getCustomFieldId() {
		return $this->iCustomFieldRow['custom_field_id'];
	}

	public function getControl($parameters = array()) {
		if (!is_array($parameters)) {
			$parameters = array($parameters => true);
		}
		$primaryId = $parameters['primary_id'];
		$thisColumn = $this->iCustomFieldControl;
		if (!$parameters['control_element_only']) {
			$thisColumn->setControlValue("form_label", $this->iCustomFieldRow['form_label']);
		}
		$dataType = "";
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		foreach ($this->iAdditionalControls as $thisControlName => $thisControlValue) {
			$this->iCustomFieldControls[$thisControlName] = array("control_name" => $thisControlName, "control_value" => $thisControlValue);
		}
		foreach ($this->iCustomFieldControls as $controlRow) {
			if ($controlRow['control_name'] == "data_type") {
				$dataType = $controlRow['control_value'];
			}
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$choices = $thisColumn->getControlValue("choices");
		if ($this->iCustomFieldChoices === false) {
			$this->iCustomFieldChoices = array();
			$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $this->iCustomFieldId);
			while ($choiceRow = getNextRow($choiceSet)) {
				$this->iCustomFieldChoices[] = $choiceRow;
			}
		}
		foreach ($this->iCustomFieldChoices as $choiceRow) {
			$choices[$choiceRow['key_value']] = $choiceRow['description'];
		}
		$thisColumn->setControlValue("choices", $choices);
		$checkbox = ($thisColumn->getControlValue("data_type") == "tinyint");
		if (!empty($parameters['classes'])) {
			$classesText = $thisColumn->getControlValue("classes");
			$classes = explode(",", $classesText);
			if (is_array($parameters['classes'])) {
				$classes = array_merge($classes, $parameters['classes']);
			} else {
				$classes = array_merge($classes, explode(",", $parameters['classes']));
			}
			$thisColumn->setControlValue("classes", $classes);
		}
		$formLineClassesText = $thisColumn->getControlValue("form_line_classes");
		$formLineClassesArray = explode(",", str_replace(" ", ",", $formLineClassesText));
		$formLineClasses = implode(" ", $formLineClassesArray);
		if (!empty($primaryId)) {
			$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $this->iCustomFieldId, $primaryId);
			if (!$dataRow = getNextRow($dataSet)) {
				$dataRow = array();
			}
			$returnArray = array();
			switch ($dataType) {
				case "date":
					$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
					break;
				case "int":
					$fieldValue = $dataRow['integer_data'];
					break;
				case "decimal":
					$fieldValue = $dataRow['number_data'];
					break;
				case "image":
				case "image_input":
					$returnArray[$this->iColumnName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
					$returnArray[$this->iColumnName . "_view"] = array("data_value" => getImageFilename($dataRow['image_id']));
					$returnArray[$this->iColumnName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $dataRow['image_id']));
					$returnArray["remove_" . $this->iColumnName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
					$fieldValue = $dataRow['image_id'];
					break;
				case "file":
					$returnArray[$this->iColumnName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
					$returnArray[$this->iColumnName . "_download"] = array("data_value" => (empty($dataRow['file_id']) ? "" : "download.php?id=" . $dataRow['file_id']));
					$returnArray["remove_" . $this->iColumnName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
					$fieldValue = $dataRow['file_id'];
					break;
				case "tinyint":
					$fieldValue = ($dataRow['text_data'] ? "1" : "0");
					break;
				case "custom":
					$thisColumn = new DataColumn($this->iColumnName);
					if ($this->iCustomFieldControls === false) {
						$this->iCustomFieldControls = array();
						$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
						while ($controlRow = getNextRow($controlSet)) {
							$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
						}
					}
					foreach ($this->iCustomFieldControls as $controlRow) {
						$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
					}
					$controlClass = $thisColumn->getControlValue('control_class');
					$customControl = new $controlClass($thisColumn, $GLOBALS['gPageObject']);
					$returnArray[$this->iColumnName] = $customControl->getCustomDataArray(json_decode($dataRow['text_data'], true));
				default:
					$fieldValue = $dataRow['text_data'];
					break;
			}

			$thisColumn->setControlValue("initial_value", $fieldValue);
		}
		ob_start();
		if ($parameters['basic_form_line']) {
			?>
			<?php if (!$parameters['control_element_only']) { ?>
                <div class="basic-form-line <?= $formLineClasses ?>" id="_<?= $this->iColumnName ?>_row" data-check="<?= $thisColumn->getControlValue("form_line_classes") ?>" data-column_name="<?= $this->iColumnName ?>">
                <label for="<?= $this->iColumnName ?>" class="<?= ($thisColumn->getControlValue("not_null") && !$checkbox ? "required-label" : "") ?>"><?= ($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
			<?php } ?>
			<?= $thisColumn->getControl($GLOBALS['gPageObject']) ?>
			<?php if (!$parameters['control_element_only']) { ?>
                <div class='basic-form-line-messages'><span class="help-label"><?= $thisColumn->getControlValue("help_label") ?></span><span class='field-error-text'></span></div>
                </div>
			<?php } ?>
			<?php
		} else {
			?>
			<?php if (!$parameters['control_element_only']) { ?>
                <div class="form-line <?= $formLineClasses ?>" id="_<?= $this->iColumnName ?>_row" data-check="<?= $thisColumn->getControlValue("form_line_classes") ?>" data-column_name="<?= $this->iColumnName ?>">
                <label for="<?= $this->iColumnName ?>" class="<?= ($thisColumn->getControlValue("not_null") && !$checkbox ? "required-label" : "") ?>"><?= ($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
				<?php if (!empty($thisColumn->getControlValue("help_label"))) { ?>
                    <span class='help-label'><?= $thisColumn->getControlValue("help_label") ?></span>
				<?php } ?>
			<?php } ?>
			<?= $thisColumn->getControl($GLOBALS['gPageObject']) ?>
			<?php if (!$parameters['control_element_only']) { ?>
                <div class='clear-div'></div>
                </div>
			<?php } ?>
			<?php
		}
		return ob_get_clean();
	}

	public function getTemplate() {
		$thisColumn = new DataColumn($this->iColumnName);
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		foreach ($this->iCustomFieldControls as $controlRow) {
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$template = "";
		if ($thisColumn->getControlValue("data_type") == "custom") {
			$controlClass = $thisColumn->getControlValue('control_class');
			$customControl = new $controlClass($thisColumn, $GLOBALS['gPageObject']);
			$template = $customControl->getTemplate();
		}
		return $template;
	}

	public function displayData($primaryId = "", $readOnly = true) {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryIdentifier;
		}
		$fieldName = $this->iColumnName;
		$thisColumn = new DataColumn($fieldName);
		$dataType = "";
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		foreach ($this->iCustomFieldControls as $controlRow) {
			if ($controlRow['control_name'] == "data_type") {
				$dataType = $controlRow['control_value'];
			}
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$choices = $thisColumn->getControlValue("choices");
		if ($this->iCustomFieldChoices === false) {
			$this->iCustomFieldChoices = array();
			$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $this->iCustomFieldId);
			while ($choiceRow = getNextRow($choiceSet)) {
				$this->iCustomFieldChoices[] = $choiceRow;
			}
		}
		foreach ($this->iCustomFieldChoices as $choiceRow) {
			$choices[$choiceRow['key_value']] = $choiceRow['description'];
		}
		$thisColumn->setControlValue("choices", $choices);
		$checkbox = ($thisColumn->getControlValue("data_type") == "tinyint");
		$thisColumn->setControlValue("readonly", $readOnly);

		$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $this->iCustomFieldId, $primaryId);
		if (!$dataRow = getNextRow($dataSet)) {
			$dataRow = array();
		}
		$returnArray = array();
		switch ($dataType) {
			case "date":
				$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
				break;
			case "int":
				$fieldValue = $dataRow['integer_data'];
				break;
			case "decimal":
				$fieldValue = $dataRow['number_data'];
				break;
			case "image":
			case "image_input":
				$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
				$returnArray[$fieldName . "_view"] = array("data_value" => getImageFilename($dataRow['image_id']));
				$returnArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $dataRow['image_id']));
				$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				$fieldValue = $dataRow['image_id'];
				break;
			case "file":
				$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
				$returnArray[$fieldName . "_download"] = array("data_value" => (empty($dataRow['file_id']) ? "" : "download.php?id=" . $dataRow['file_id']));
				$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				$fieldValue = $dataRow['file_id'];
				break;
			case "tinyint":
				$fieldValue = ($dataRow['text_data'] ? "1" : "0");
				break;
			case "custom":
				$thisColumn = new DataColumn($this->iColumnName);
				if ($this->iCustomFieldControls === false) {
					$this->iCustomFieldControls = array();
					$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
					while ($controlRow = getNextRow($controlSet)) {
						$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
					}
				}
				foreach ($this->iCustomFieldControls as $controlRow) {
					$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
				}
				$controlClass = $thisColumn->getControlValue('control_class');
				$customControl = new $controlClass($thisColumn, $GLOBALS['gPageObject']);
				$returnArray[$fieldName] = $customControl->getCustomDataArray(json_decode($dataRow['text_data'], true));
			default:
				$fieldValue = $dataRow['text_data'];
				break;
		}

		$thisColumn->setControlValue("initial_value", $fieldValue);
		ob_start();
		?>
        <div class="form-line" id="_<?= $this->iColumnName ?>_row" data-column_name="<?= $this->iColumnName ?>">
            <label for="<?= $this->iColumnName ?>" class="<?= ($thisColumn->getControlValue("not_null") && !$checkbox ? "required-label" : "") ?>"><?= ($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
			<?= $thisColumn->getControl($this) ?>
            <div class='clear-div'></div>
        </div>
		<?php
		return ob_get_clean();
	}

	public function getDisplayData($primaryId = "", $parameters = array()) {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryIdentifier;
		}
		$fieldName = $this->iColumnName;
		$thisColumn = new DataColumn($fieldName);
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		$dataType = "";
		foreach ($this->iCustomFieldControls as $controlRow) {
			if ($controlRow['control_name'] == "data_type") {
				$dataType = $controlRow['control_value'];
			}
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}

		$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $this->iCustomFieldId, $primaryId);
		if (!$dataRow = getNextRow($dataSet)) {
			$dataRow = array();
		}
		switch ($dataType) {
			case "date":
				$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
				break;
			case "int":
				$fieldValue = $dataRow['integer_data'];
				break;
			case "decimal":
				$fieldValue = $dataRow['number_data'];
				break;
			case "image":
			case "image_input":
				$fieldValue = $dataRow['image_id'];
				break;
			case "file":
				$fieldValue = $dataRow['file_id'];
				break;
			case "tinyint":
				$fieldValue = ($dataRow['text_data'] ? "1" : "0");
				break;
			default:
				$fieldValue = $dataRow['text_data'];
				break;
		}
		$displayValue = $fieldValue;
		switch ($dataType) {
			case "image":
			case "image_input":
				$displayValue = getImageFilename($fieldValue);
				break;
			case "select":
			case "radio":
				$choices = $thisColumn->getControlValue("choices");
				if ($this->iCustomFieldChoices === false) {
					$this->iCustomFieldChoices = array();
					$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $this->iCustomFieldId);
					while ($choiceRow = getNextRow($choiceSet)) {
						$this->iCustomFieldChoices[] = $choiceRow;
					}
				}
				foreach ($this->iCustomFieldChoices as $choiceRow) {
					$choices[$choiceRow['key_value']] = $choiceRow['description'];
				}
				$displayValue = $choices[$fieldValue];
				break;
			case "file":
				$displayValue = (empty($fieldValue) ? "" : "/download.php?id=" . $fieldValue);
				break;
			case "custom":
				if (substr($fieldValue, 0, 1) == "[") {
					$fieldValueArray = json_decode($fieldValue, true);
					$displayValue = "";
					foreach ($fieldValueArray as $thisValue) {
						if ($parameters['custom_table']) {
							if (empty($displayValue)) {
								$displayValue .= "<table class='grid-table'><tr>";
								foreach ($thisValue as $fieldName => $fieldData) {
									$displayValue .= "<th>" . $fieldName . "</th>";
								}
								$displayValue .= "</tr>";
							}
							$displayValue .= "<tr>";
							foreach ($thisValue as $fieldName => $fieldData) {
								$displayValue .= "<td>" . htmlText($fieldData) . "</td>";
							}
							$displayValue .= "</tr>";
						} else {
							$thisValue = trim(jsonEncode($thisValue), "[{}]");
							$displayValue .= $thisValue . "\n";
						}
					}
					if ($parameters['custom_table'] && !empty($displayValue)) {
						$displayValue .= "</table>";
					}
				}
				break;
		}
		return $displayValue;
	}

	public function getFormLabel() {
		return $this->iCustomFieldRow['form_label'];
	}

# Static function to get a custom field. This is more efficient, because the object is simply returned if it has already been created. Typically, the custom field object
# is used in numerous places, so it is needed numerous times.

	public function getRecord($primaryId = "") {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryIdentifier;
		}
		$fieldName = $this->iColumnName;
		$thisColumn = new DataColumn($fieldName);
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		$dataType = "";
		foreach ($this->iCustomFieldControls as $controlRow) {
			if ($controlRow['control_name'] == "data_type") {
				$dataType = $controlRow['control_value'];
			}
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}

		$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $this->iCustomFieldId, $primaryId);
		if (!$dataRow = getNextRow($dataSet)) {
			$dataRow = array();
		}
		$returnArray = array();
		$returnArray['select_values'] = array();
		switch ($dataType) {
			case "date":
				$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
				break;
			case "int":
				$fieldValue = $dataRow['integer_data'];
				break;
			case "decimal":
				$fieldValue = $dataRow['number_data'];
				break;
			case "image":
			case "image_input":
				$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
				$returnArray[$fieldName . "_view"] = array("data_value" => getImageFilename($dataRow['image_id']));
				$returnArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $dataRow['image_id']));
				$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				$fieldValue = $dataRow['image_id'];
				if (!empty($fieldValue)) {
					$returnArray['select_values'][$fieldName] = array(array("key_value" => $fieldValue, "description" => getFieldFromId("description", "images", "image_id", $fieldValue)));
				}
				break;
			case "file":
				$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
				$returnArray[$fieldName . "_download"] = array("data_value" => (empty($dataRow['file_id']) ? "" : "download.php?id=" . $dataRow['file_id']));
				$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				$fieldValue = $dataRow['file_id'];
				break;
			case "tinyint":
				$fieldValue = ($dataRow['text_data'] ? "1" : "0");
				break;
			case "custom":
				$thisColumn = new DataColumn($this->iColumnName);
				if ($this->iCustomFieldControls === false) {
					$this->iCustomFieldControls = array();
					$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
					while ($controlRow = getNextRow($controlSet)) {
						$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
					}
				}
				foreach ($this->iCustomFieldControls as $controlRow) {
					if ($controlRow['control_name'] == "data_type") {
						$dataType = $controlRow['control_value'];
					}
					$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
				}
				$controlClass = $thisColumn->getControlValue('control_class');
				$customControl = new $controlClass($thisColumn, $GLOBALS['gPageObject']);
				$returnArray[$fieldName] = $customControl->getCustomDataArray(json_decode($dataRow['text_data'], true));
			default:
				$fieldValue = $dataRow['text_data'];
				break;
		}
		$displayValue = $fieldValue;
		switch ($dataType) {
			case "contact_picker":
				$displayValue = getDisplayName($fieldValue, array("include_company" => true));
				$address1 = getFieldFromId("address_1", "contacts", "contact_id", $fieldValue);
				if (!empty($address1)) {
					if (!empty($displayValue)) {
						$displayValue .= " • ";
					}
					$displayValue .= $address1;
				}
				$city = getFieldFromId("city", "contacts", "contact_id", $fieldValue);
				$state = getFieldFromId("state", "contacts", "contact_id", $fieldValue);
				if (!empty($state)) {
					if (!empty($city)) {
						$city .= ", ";
					}
					$city .= $state;
				}
				if (!empty($city)) {
					if (!empty($displayValue)) {
						$displayValue .= " • ";
					}
					$displayValue .= $city;
				}
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $fieldValue);
				if (!empty($emailAddress)) {
					if (!empty($displayValue)) {
						$displayValue .= " • ";
					}
					$displayValue .= $emailAddress;
				}

				if ($fieldValue) {
					$returnArray['select_values'][$fieldName . "_selector"] = array(array("key_value" => $fieldValue, "description" => $displayValue));
					$returnArray[$fieldName . "_selector"] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($displayValue));
				}
				break;
			case "image":
			case "image_input":
				$displayValue = getImageFilename($fieldValue);
				break;
			case "select":
			case "radio":
				$choices = $thisColumn->getControlValue("choices");
				if ($this->iCustomFieldChoices === false) {
					$this->iCustomFieldChoices = array();
					$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $this->iCustomFieldId);
					while ($choiceRow = getNextRow($choiceSet)) {
						$this->iCustomFieldChoices[] = $choiceRow;
					}
				}
				foreach ($this->iCustomFieldChoices as $choiceRow) {
					$choices[$choiceRow['key_value']] = $choiceRow['description'];
				}
				$displayValue = $choices[$fieldValue];
				break;
			case "file":
				$displayValue = (empty($fieldValue) ? "" : "/download.php?id=" . $fieldValue);
				break;
		}
		if (empty($dataRow) && empty($primaryId) && strlen($thisColumn->getControlValue("initial_value")) > 0) {
			$fieldValue = $thisColumn->getControlValue("initial_value");
		}
		if (empty($dataRow) && strlen($thisColumn->getControlValue("default_value")) > 0) {
			$fieldValue = $thisColumn->getControlValue("default_value");
		}
		if ($dataType != "custom") {
			$returnArray[$fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue), "display_value" => $displayValue);
		}
		return $returnArray;
	}

	public function saveData($nameValues, $primaryId = "", $returnOnly = false) {
		$fieldName = $this->iColumnName;
		if (empty($primaryId)) {
			$primaryId = $nameValues['primary_id'];
		}
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryIdentifier;
		}
		if (empty($primaryId)) {
			$this->iErrorMessage = "No primary ID found for custom field " . $this->iCustomFieldRow['description'];
			return false;
		}
		$dataType = "";
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		foreach ($this->iCustomFieldControls as $controlRow) {
			if ($controlRow['control_name'] == "data_type") {
				$dataType = $controlRow['control_value'];
			}
		}
		if ($dataType != "custom" && !array_key_exists($fieldName, $nameValues)) {
			return true;
		}
		$customFieldDataId = "";
		$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $this->iCustomFieldId, $primaryId);
		if ($dataRow = getNextRow($dataSet)) {
			$customFieldDataId = $dataRow['custom_field_data_id'];
		}
		$deleteRows = array();
		switch ($dataType) {
			case "date":
				$nameValues[$fieldName] = makeDateParameter($nameValues[$fieldName]);
				$updateField = "date_data";
				break;
			case "int":
				$updateField = "integer_data";
				break;
			case "decimal":
				$updateField = "number_data";
				break;
			case "file":
				if (!empty($nameValues['remove_' . $fieldName]) || (array_key_exists($fieldName . "_file", $_FILES) &&
						!empty($_FILES[$fieldName . '_file']['name']))) {
					$oldFileId = $nameValues[$fieldName];
					if (!empty($oldFileId)) {
						$nameValues[$fieldName] = "";
						$deleteRows[] = array("table_name" => "files", "key_value" => $oldFileId);
					}
				}
				if (array_key_exists($fieldName . "_file", $_FILES) && !empty($_FILES[$fieldName . '_file']['name']) && empty($nameValues['remove_' . $fieldName])) {
					$originalFilename = $_FILES[$fieldName . '_file']['name'];
					if (array_key_exists($_FILES[$fieldName . '_file']['type'], $GLOBALS['gMimeTypes'])) {
						$extension = $GLOBALS['gMimeTypes'][$_FILES[$fieldName . '_file']['type']];
					} else {
						$fileNameParts = explode(".", $_FILES[$fieldName . '_file']['name']);
						$extension = $fileNameParts[count($fileNameParts) - 1];
					}
					$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
					if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
						$maxDBSize = 1000000;
					}
					if ($_FILES[$fieldName . '_file']['size'] < $maxDBSize) {
						$fileContent = file_get_contents($_FILES[$fieldName . '_file']['tmp_name']);
						$osFilename = "";
					} else {
						$fileContent = "";
						$osFilename = "/documents/tmp." . $extension;
					}
					$fileSet = executeQuery("insert into files (file_id,client_id,description,date_uploaded," .
						"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access," .
						"sort_order,version) values (null,?,?,now(),?,?,?,?,0,0,1,0,1)", $GLOBALS['gClientId'], "Custom Data File",
						$originalFilename, $extension, $fileContent, $osFilename);
					if (!empty($fileSet['sql_error'])) {
						return getSystemMessage("basic", $fileSet['sql_error']);
					}
					$nameValues[$fieldName] = $fileSet['insert_id'];
					if (!empty($osFilename)) {
						putExternalFileContents($nameValues[$fieldName], $extension, file_get_contents($_FILES[$fieldName . '_file']['tmp_name']));
					}
				}
				$updateField = "file_id";
				break;
			case "image":
			case "image_input":
				if (!empty($nameValues['remove_' . $fieldName]) || (array_key_exists($fieldName . "_file", $_FILES) &&
						!empty($_FILES[$fieldName . '_file']['name']))) {
					$oldImageId = $nameValues[$fieldName];
					if (!empty($oldImageId)) {
						$nameValues[$fieldName] = "";
						$deleteRows[] = array("table_name" => "images", "key_value" => $oldImageId);
					}
				}
				if (array_key_exists($fieldName . "_file", $_FILES) && !empty($_FILES[$fieldName . '_file']['name']) && empty($nameValues['remove_' . $fieldName])) {
					$imageId = createImage($fieldName . "_file", array("description" => "Custom Data Image"));
					if ($imageId == false) {
						return getSystemMessage("basic");
					}
					$nameValues[$fieldName] = $imageId;
				}
				$updateField = "image_id";
				break;
			case "custom":
				$thisColumn = new DataColumn($fieldName);
				if ($this->iCustomFieldControls === false) {
					$this->iCustomFieldControls = array();
					$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
					while ($controlRow = getNextRow($controlSet)) {
						$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
					}
				}
				foreach ($this->iCustomFieldControls as $controlRow) {
					$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
				}
				$controlClass = $thisColumn->getControlValue('control_class');
				$customControl = new $controlClass($thisColumn, $GLOBALS['gPageObject']);
				$nameValues[$fieldName] = $customControl->saveData($nameValues);
			default:
				$updateField = "text_data";
				break;
		}
		if ($returnOnly) {
			return $nameValues[$fieldName];
		}
		$customFieldDataDataSource = new DataSource("custom_field_data");
		$customFieldDataDataSource->setSaveOnlyPresent(true);
		$customFieldDataDataSource->disableTransactions();
		$customFieldDataValues = array("primary_identifier" => $primaryId, "custom_field_id" => $this->iCustomFieldId, $updateField => $nameValues[$fieldName]);
		$success = true;
		if (!empty($nameValues[$fieldName]) || (is_scalar($nameValues[$fieldName]) && strlen($nameValues[$fieldName]) > 0)) {
			$success = $customFieldDataDataSource->saveRecord(array("name_values" => $customFieldDataValues, "primary_id" => $customFieldDataId));
		} else if (!empty($customFieldDataId)) {
			$success = $customFieldDataDataSource->deleteRecord(array("primary_id" => $customFieldDataId));
		}
		if (!$success) {
			$this->iErrorMessage = $customFieldDataDataSource->getErrorMessage();
			return false;
		}
		removeCachedData("custom_field_data", $primaryId . ":" . $this->iCustomFieldRow['custom_field_code'] . ":"
			. getFieldFromId("custom_field_type_code", "custom_field_types", "custom_field_type_id", $this->iCustomFieldRow["custom_field_type_id"]));

		foreach ($deleteRows as $deleteInfo) {
			$thisDataSource = new DataSource($deleteInfo['table_name']);
			$thisDataSource->deleteRecord(array("primary_id" => $deleteInfo['key_value']));
		}
		return true;
	}

	# Set custom fields

	function getData($primaryIdentifier) {
		if (empty($primaryIdentifier)) {
			$primaryIdentifier = $this->iPrimaryIdentifier;
		}
		$dataType = "";
		if ($this->iCustomFieldControls === false) {
			$this->iCustomFieldControls = array();
			$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
			while ($controlRow = getNextRow($controlSet)) {
				$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
			}
		}
		foreach ($this->iCustomFieldControls as $controlRow) {
			if ($controlRow['control_name'] == "data_type") {
				$dataType = $controlRow['control_value'];
			}
		}
		$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $this->iCustomFieldId, $primaryIdentifier);
		if (!$dataRow = getNextRow($dataSet)) {
			$dataRow = array();
		}
		switch ($dataType) {
			case "date":
				$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
				break;
			case "int":
				$fieldValue = $dataRow['integer_data'];
				break;
			case "decimal":
				$fieldValue = $dataRow['number_data'];
				break;
			case "image":
			case "image_input":
				$fieldValue = $dataRow['image_id'];
				break;
			case "file":
				$fieldValue = $dataRow['file_id'];
				break;
			case "tinyint":
				$fieldValue = ($dataRow['text_data'] ? "1" : "0");
				break;
			case "custom":
				$thisColumn = new DataColumn($this->iColumnName);
				if ($this->iCustomFieldControls === false) {
					$this->iCustomFieldControls = array();
					$controlSet = executeQuery("select control_name,control_value from custom_field_controls where custom_field_id = ?", $this->iCustomFieldId);
					while ($controlRow = getNextRow($controlSet)) {
						$this->iCustomFieldControls[$controlRow['control_name']] = $controlRow;
					}
				}
				foreach ($this->iCustomFieldControls as $controlRow) {
					$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
				}
				$controlClass = $thisColumn->getControlValue('control_class');
				$customControl = new $controlClass($thisColumn, $GLOBALS['gPageObject']);
				$fieldValue = $customControl->getCustomDataArray(json_decode($dataRow['text_data'], true));
				break;
			default:
				$fieldValue = $dataRow['text_data'];
				break;
		}
		return $fieldValue;
	}

}
