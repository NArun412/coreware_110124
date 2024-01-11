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

$GLOBALS['gPageCode'] = "MERCHANTACCOUNTMAINT";
require_once "shared/startup.inc";

class MerchantAccountMaintenancePage extends Page {
	var $iSearchFields = array("description", "link_url");

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$showBusinessName = !empty($this->getPageTextChunk("show_business_name"));
			if ($showBusinessName) {
				$whereStatement = "(merchant_accounts.merchant_account_id in (select merchant_account_id from ffl_merchant_accounts where federal_firearms_licensee_id in " .
					"(select federal_firearms_licensee_id from federal_firearms_licensees where contact_id in (select contact_id from contacts where business_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%") . "))))";
				$whereStatement .= " or merchant_service_id in (select merchant_service_id from merchant_services where description like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%") . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
				}
				$this->iDataSource->addFilterWhere($whereStatement);
			}
			$showEmailAddress = !empty($this->getPageTextChunk("show_email_address"));
			if ($showEmailAddress) {
				$whereStatement = "(merchant_accounts.merchant_account_id in (select merchant_account_id from ffl_merchant_accounts where federal_firearms_licensee_id in " .
					"(select federal_firearms_licensee_id from federal_firearms_licensees where contact_id in (select contact_id from contacts where email_address like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%") . "))))";
				$whereStatement .= " or merchant_service_id in (select merchant_service_id from merchant_services where description like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%") . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
				}
				$this->iDataSource->addFilterWhere($whereStatement);
			}
			$this->iDataSource->setFilterText($filterText);
		}
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("account_key");
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("test_merchant_account" => array("icon" => "fad fa-cog", "label" => getLanguageText("Test Merchant Account"), "disabled" => false)));
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("set_default", "Set Default Account");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_field_labels":
				$returnArray['field_labels'] = array();
				$resultSet = executeQuery("select * from merchant_service_field_labels where merchant_service_id = ?", $_GET['merchant_service_id']);
				while ($row = getNextRow($resultSet)) {
					if (substr($row['column_name'], 0, strlen("custom_field_")) == "custom_field_") {
						$customFieldCode = substr($row['column_name'], strlen("custom_field_"));
						$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode, "MERCHANT_ACCOUNTS");
						$row['column_name'] = "custom_field_id_" . $customFieldId;
					}
					$returnArray['field_labels'][$row['column_name']] = array("form_label" => $row['form_label'], "not_null" => $row['not_null']);
				}
				ajaxResponse($returnArray);
				break;
			case "test_merchant_account":
				$eCommerce = eCommerce::getEcommerceInstance($_GET['merchant_account_id']);
				if (!$eCommerce) {
					$returnArray['error_message'] = "Merchant Service not found. Contact support.";
				} elseif ($eCommerce->testConnection()) {
					$returnArray['info_message'] = "Connection to Merchant Account works";
				} else {
					$returnArray['error_message'] = "Connection to Merchant Account DOES NOT work";
					$response = $eCommerce->getResponse();
					$error = $eCommerce->getErrorMessage() ?: $response['response_reason_text'];
					if (!empty($error)) {
						$returnArray['error_message'] .= " (" . $error . ")";
					}
				}
				ajaxResponse($returnArray);
				break;
            case "set_default":
                $merchantAccountRows = array();
                $resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
                $defaultMerchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_code", "DEFAULT");
                while ($row = getNextRow($resultSet)) {
                    $merchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_id", $row['primary_identifier']);
                    if(!empty($merchantAccountRow) && $merchantAccountRow['merchant_account_id'] != $defaultMerchantAccountRow['merchant_account_id']) {
                        $merchantAccountRows[] = $merchantAccountRow;
                    }
                }
                if(count($merchantAccountRows) != 1) {
                    $returnArray['error_message'] = "You must select only one merchant account to set as DEFAULT.";
                    ajaxResponse($returnArray);
                }
                $otherMerchantAccountRow = $merchantAccountRows[0];
                $defaultMerchantAccountRow['merchant_account_code'] = makeCode($defaultMerchantAccountRow['description']);
                $existingMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", $defaultMerchantAccountRow['merchant_account_code']);
                $counter = 0;
                while($existingMerchantAccountId) {
                    $defaultMerchantAccountRow['merchant_account_code'] = makeCode($defaultMerchantAccountRow['description']) . "_" . ++$counter;
                    $existingMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", $defaultMerchantAccountRow['merchant_account_code']);
                }
                $dataTable = new DataTable("merchant_accounts");
                $dataTable->setSaveOnlyPresent(true);
                $dataTable->saveRecord(["primary_id"=>$defaultMerchantAccountRow['merchant_account_id'],"name_values"=>$defaultMerchantAccountRow]);
                $otherMerchantAccountRow['merchant_account_code'] = "DEFAULT";
                $dataTable->saveRecord(["primary_id"=>$otherMerchantAccountRow['merchant_account_id'],"name_values"=>$otherMerchantAccountRow]);
                executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
                $returnArray['info_message'] = "Default merchant account swapped successfully.";
                ajaxResponse($returnArray);
        }
	}

	function massageDataSource() {
		$showBusinessName = !empty($this->getPageTextChunk("show_business_name"));
		$this->iDataSource->getPrimaryTable()->setSubtables(array("ffl_merchant_accounts"));
		if ($showBusinessName) {
			$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts join federal_firearms_licensees using (contact_id) where federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_merchant_accounts where merchant_account_id = merchant_accounts.merchant_account_id)");
			$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
			$this->iDataSource->addColumnControl("business_name", "form_label", "Business Name");
		}

		$showEmailAddress = !empty($this->getPageTextChunk("show_email_address"));
		if ($showEmailAddress) {
			$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts join federal_firearms_licensees using (contact_id) where federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_merchant_accounts where merchant_account_id = merchant_accounts.merchant_account_id)");
			$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
			$this->iDataSource->addColumnControl("email_address", "form_label", "Email");
		}

		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("account_key", "data_type", "password");
			$this->iDataSource->addColumnControl("account_key", "show_data", true);
		}

		if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->setFilterWhere("merchant_account_id in (select merchant_account_id from ffl_merchant_accounts where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))");

			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "data_type", "select");
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "form_label", "FFL");
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "get_choices", "fflChoices");
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "not_null", true);
			$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "not_editable", true);
			$this->iDataSource->addColumnControl("ach_merchant_account", "data_type", "tinyint");
			$this->iDataSource->addColumnControl("ach_merchant_account", "form_label", "For ACH Transactions");
		} else {
			$this->iDataSource->addColumnControl("ach_merchant_account", "data_type", "hidden");
        }

		$this->iDataSource->addColumnControl("link_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("link_url", "css-width", "500px");
		$this->iDataSource->addColumnControl("merchant_service_id", "not_editable", true);

	}

	function mainContent() {
		if ($GLOBALS['gUserRow']['administrator_flag']) {
			?>
            <h3 class='red-text'>Warning: DO NOT change the "default" merchant account.</h3>
            <p class='help-label red-text'>To switch merchant gateways, change only the Code field and add a NEW merchant account with the code DEFAULT.<br>
                Changing the credentials of an existing merchant account will make it impossible to refund or capture outstanding transactions.</p>
			<?php
		}
		return false;
	}

	function fflChoices($showInactive = false) {
		$fflChoices = array();
		$resultSet = executeQuery("select * from federal_firearms_licensees join contacts using (contact_id) where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = ?)", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$fflName = (empty($row['business_name']) ? $row['licensee_name'] : $row['business_name']);
			$fflChoices[$row['federal_firearms_licensee_id']] = array("key_value" => $row['federal_firearms_licensee_id'], "description" => $fflName, "inactive" => ($row['inactive'] == 1));
		}
		freeResult($resultSet);
		return $fflChoices;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			if (!empty($nameValues['federal_firearms_licensee_id'])) {
				executeQuery("insert ignore into ffl_merchant_accounts (federal_firearms_licensee_id,merchant_account_id,ach_merchant_account) values (?,?,?)", $nameValues['federal_firearms_licensee_id'], $nameValues['primary_id'], (empty($nameValues['ach_merchant_account']) ? 0 : 1));
			}
		}
		$customFields = CustomField::getCustomFields("merchant_accounts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		if (function_exists("_localAfterSaveMerchantAccount")) {
			_localAfterSaveMerchantAccount($nameValues);
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			$federalFirearmsLicenseeId = getFieldFromId("federal_firearms_licensee_id", "ffl_merchant_accounts", "merchant_account_id", $returnArray['primary_id']['data_value']);
			$returnArray['federal_firearms_licensee_id'] = array("data_value" => $federalFirearmsLicenseeId, "crc_value" => getCrcValue($federalFirearmsLicenseeId));
            if (!empty($federalFirearmsLicenseeId)) {
	            $achMerchantAccount = getFieldFromId("ach_merchant_account", "ffl_merchant_accounts", "merchant_account_id", $returnArray['primary_id']['data_value']);
	            $returnArray['ach_merchant_account'] = array("data_value" => $achMerchantAccount, "crc_value" => getCrcValue($achMerchantAccount));
            }
		}
		$customFields = CustomField::getCustomFields("merchant_accounts");
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

	function addCustomFields() {
		$customFields = CustomField::getCustomFields("merchant_accounts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl(array("basic_form_line" => true));
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#merchant_service_id").change(function () {
                loadFieldLabels();
            });
            $(document).on("tap click", "#_test_merchant_account_button", function () {
                if (changesMade() || empty($("#primary_id").val())) {
                    displayErrorMessage("Save changes first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_merchant_account&merchant_account_id=" + $("#primary_id").val());
                }
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                loadFieldLabels();
            }

            function loadFieldLabels() {
                $("#_maintenance_form").find("div.basic-form-line").each(function () {
                    if (empty($(this).data("default_label"))) {
                        if ($(this).find("input[type=checkbox]").length > 0) {
                            $(this).find("label").not(".checkbox-label").remove();
                            $(this).data("default_label", $(this).find("label.checkbox-label").html());
                        } else {
                            $(this).data("default_label", $(this).find("label").html());
                        }
                    }
                });
                if (empty($("#merchant_service_id").val())) {
                    $("#_maintenance_form").find("div.basic-form-line").each(function () {
                        $(this).removeClass("hidden").find("label").html($(this).data("default_label"));
                    });
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_field_labels&merchant_service_id=" + $("#merchant_service_id").val(), function (returnArray) {
                        if ("field_labels" in returnArray) {
                            $("#_maintenance_form").find("div.basic-form-line").each(function () {
                                let labeledFields = ['_account_login_row', '_account_key_row', '_merchant_identifier_row', '_link_url_row'];
                                if (!labeledFields.includes(this.id) && !this.id.startsWith('_custom_field')) {
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
                                            $(this).find("input").attr("class", "validate[required]");
                                        } else {
                                            $(this).find("label").find("span.required-tag").remove();
                                            $(this).find("input").attr("class", "");
                                        }
                                    }
                                } else {
                                    $(this).addClass("hidden");
                                }
                            });
                        }
                    });
                }
            }

            function customActions(actionName) {
                if (actionName === "set_default") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function (returnArray) {
                        getDataList();
                    });
                    return true;
                }
            }
        </script>
		<?php
	}
}

$pageObject = new MerchantAccountMaintenancePage("merchant_accounts");
$pageObject->displayPage();
