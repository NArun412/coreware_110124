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

$GLOBALS['gPageCode'] = "LOCATIONCREDENTIALMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		$_SESSION['location_id'] = $_GET['location_id'];
		saveSessionData();
		$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("test_credentials" => array("icon" => "fad fa-cog", "label" => getLanguageText("Test Credentials"), "disabled" => false)));
		if (!empty($GLOBALS['gUserRow']['superuser_flag'])) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("run_next" => array("label" => getLanguageText("Run Next"), "disabled" => false)));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_field_labels":
				$returnArray['field_labels'] = array();
				$resultSet = executeQuery("select * from product_distributor_field_labels where product_distributor_id = (select product_distributor_id from locations where location_id = ?)", $_GET['location_id']);
				while ($row = getNextRow($resultSet)) {
					if (substr($row['column_name'], 0, strlen("custom_field_")) == "custom_field_") {
						$customFieldCode = substr($row['column_name'], strlen("custom_field_"));
						$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode,"PRODUCT_DISTRIBUTORS");
						$row['column_name'] = "custom_field_id_" . $customFieldId;
					}
					$returnArray['field_labels'][$row['column_name']] = array("form_label"=>$row['form_label'],"not_null"=>$row['not_null']);
				}

				ajaxResponse($returnArray);
				break;
			case "get_custom_data":
				$returnArray['location_id'] = array("data_value" => $_GET['location_id']);
				$returnArray['primary_id'] = array("data_value" => $_GET['primary_id']);
				$this->afterGetRecord($returnArray);
				unset($returnArray['location_id']);
				unset($returnArray['primary_id']);
				ajaxResponse($returnArray);
				break;
			case "test_credentials":
				$locationId = getFieldFromId("location_id", "location_credentials", "location_credential_id", $_POST['primary_id']);
				$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
				if (!$productDistributor) {
					$returnArray['error_message'] = "Distributor not found. Contact support.";
				} elseif ($productDistributor->testCredentials()) {
					$returnArray['info_message'] = "Connection to distributor works";
				} else {
					$returnArray['error_message'] = "Connection to distributor DOES NOT work";
					if (!empty($productDistributor->getErrorMessage())) {
						$returnArray['error_message'] .= " (" . $productDistributor->getErrorMessage() . ")";
					}
				}
				ajaxResponse($returnArray);
				break;
			case "run_next":
				if (!empty($GLOBALS['gUserRow']['superuser_flag'])) {
					$result = executeQuery("update location_credentials set date_last_run = '2000-01-01' where location_credential_id = ?", $_POST['primary_id']);
					if (empty($result['sql_error'])) {
						$returnArray['info_message'] = "Import will run next time background process runs";
					} else {
						$returnArray['error_message'] = getSystemMessage("basic", $result['sql_error']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_printful_token":
				$locationId = getFieldFromId("location_id", "location_credentials", "location_credential_id", $_GET['primary_id']);
				$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
				if (!is_a($productDistributor, "Printful") ) {
					$returnArray['error_message'] = "Distributor not found. Contact support.";
				} else {
					header("Location: " . $productDistributor->getAuthorizeUrl($locationId, $GLOBALS['gLinkUrl']));
				}
				ajaxResponse($returnArray);
				break;
            case "get_connection_key":
                $locationId = getFieldFromId("location_id", "location_credentials", "location_credential_id", $_GET['primary_id']);
                while($locationId) { // check conditions in a loop to break out easily
                    $productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
                    if (is_object($productDistributor) && !method_exists($productDistributor, "getConnectionKey")) {
                        $returnArray['error_message'] = "Distributor does not support automatically retrieving connection key.";
                        break;
                    }
                    $result = $productDistributor->getConnectionKey($_POST['distributor_un'], $_POST['distributor_pw']);
                    if (empty($result['connection_key'])) {
                        $returnArray['error_message'] = $result['error_message'];
                        break;
                    }
                    $returnArray['connection_key'] = $result['connection_key'];
                    break;
                }
                ajaxResponse($returnArray);
                break;

		}
	}

	function afterGetRecord(&$returnArray) {
		$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $returnArray['location_id']['data_value']);
		$returnArray['credential_notes'] = array("data_value" => makeHtml(getFieldFromId("credential_notes", "product_distributors", "product_distributor_id",
			$productDistributorId)));
		ob_start();
		$customFields = CustomField::getCustomFields("product_distributors");
		$useCustomFields = array();
		foreach ($customFields as $thisCustomField) {
			$productDistributorCustomFieldId = getFieldFromId("product_distributor_custom_field_id", "product_distributor_custom_fields", "product_distributor_id",
				$productDistributorId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($productDistributorCustomFieldId)) {
				continue;
			}
			$sequenceNumber = getFieldFromId("sequence_number", "product_distributor_custom_fields", "product_distributor_id",
				$productDistributorId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			$thisCustomField['sequence_number'] = $sequenceNumber;
			$useCustomFields[] = $thisCustomField;
		}
		usort($useCustomFields, array($this, "sortCustomFields"));
		foreach ($useCustomFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl(array("basic_form_line" => true));
		}
        $productDistributor = ProductDistributor::getProductDistributorInstance($returnArray['location_id']['data_value']);
        if (is_a($productDistributor, "Printful") ) { ?>
            <button class="enabled-button" id="_get_printful_token_button"><span class="button-text">Get Printful Token</span></button>
			<?php
		} elseif (is_object($productDistributor) && method_exists($productDistributor, "getConnectionKey")) {?>
            <button class="enabled-button" id="_get_connection_key_button"><span class="button-text">Get Connection Key</span></button>
            <?php
        }
		$returnArray['custom_data'] = array("data_value" => ob_get_clean());
		foreach ($useCustomFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("date_last_run", "readonly", true);
		$this->iDataSource->addColumnControl("date_last_run", "form_label", "Last product import");
		$this->iDataSource->addColumnControl("user_name", "code_value", false);
		$this->iDataSource->addColumnControl("user_name", "letter_case", false);
		$this->iDataSource->addColumnControl("location_id", "default_value", $_SESSION['location_id']);
		$this->iDataSource->addColumnControl("location_id", "not_editable", true);
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("product_distributor_id", "not_editable", true);
		if (!empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->setFilterWhere("location_id in (select location_id from locations where inactive = 0 and client_id = " . $GLOBALS['gClientId'] . " and user_location = 0)");
		} else {
			$this->iDataSource->setFilterWhere("location_id in (select location_id from locations where inactive = 0 and client_id = " . $GLOBALS['gClientId'] . " and (user_id = " . $GLOBALS['gUserId'] . " or (location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . ")))))");
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where inactive = 0 and product_distributor_id is not null and client_id = ?" .
			(empty($GLOBALS['gUserRow']['administrator_flag']) ? " and (user_id = " . $GLOBALS['gUserId'] . " or (location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))))" : " and user_location = 0") . " order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#location_id").change(function () {
                $("#custom_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_data&location_id=" + $(this).val() + "&primary_id=" + $("#primary_id").val(), function (returnArray) {
                        if ("custom_data" in returnArray && "data_value" in returnArray['custom_data']) {
                            $("#custom_data").html(returnArray['custom_data']['data_value']);
                            afterGetRecord(returnArray);
                        }
                        $("#credential_notes").html(returnArray['credential_notes']['data_value']);
                    });
                }
            });
            $(document).on("tap click", "#_test_credentials_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_credentials", $("#_edit_form").serialize());
                }
                return false;
            });
            $(document).on("tap click", "#_run_next_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=run_next", $("#_edit_form").serialize(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#date_last_run").val("");
                        }
                    });
                }
                return false;
            });
            $(document).on("tap click", "#_get_printful_token_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save first");
                } else {
                    window.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_printful_token&primary_id=" + $("#primary_id").val();
                }
                return false;
            });
            $(document).on("tap click", "#_get_connection_key_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save first");
                } else {
                    $("#_get_connection_key_form input").val("");
                    $('#_get_connection_key_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Get Connection Key',
                        buttons: {
                            Ok: function (event) {
                                if ($("#_get_connection_key_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_connection_key&primary_id=" + $("#primary_id").val(), $("#_get_connection_key_form").serialize(), function (returnArray) {
                                        if("connection_key" in returnArray) {
                                            $("#user_name").val(returnArray['connection_key']);
                                        }
                                    });
                                    $("#_get_connection_key_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_get_connection_key_dialog").dialog('close');
                            }
                        }
                    });
                }
                return false;
            });


        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            var dataArray = new Object();
            function afterGetRecord(returnArray) {
                $("#custom_data .datepicker").datepicker({
                    showOn: "button",
                    buttonText: "<span class='fad fa-calendar-alt'></span>",
                    constrainInput: false,
                    dateFormat: "mm/dd/y",
                    yearRange: "c-100:c+10"
                });
                $("#custom_data .required-label").append("<span class='required-tag'>*</span>");
                $("#custom_data a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                dataArray = returnArray;
                loadFieldLabels();
                setTimeout("setCustomData()", 100);
            }
            function setCustomData() {
                if ("select_values" in dataArray) {
                    for (var i in dataArray['select_values']) {
                        if (!$("#" + i).is("select")) {
                            continue;
                        }
                        $("#" + i + " option").each(function () {
                            if ($(this).data("inactive") == "1") {
                                $(this).remove();
                            }
                        });
                        for (var j in dataArray['select_values'][i]) {
                            if ($("#" + i + " option[value='" + dataArray['select_values'][i][j]['key_value'] + "']").length == 0) {
                                var inactive = ("inactive" in dataArray['select_values'][i][j] ? dataArray['select_values'][i][j]['inactive'] : "0");
                                $("#" + i).append("<option data-inactive='" + inactive + "' value='" + dataArray['select_values'][i][j]['key_value'] + "'>" + dataArray['select_values'][i][j]['description'] + "</option>");
                            }
                        }
                    }
                }
                for (var i in dataArray) {
                    if (typeof dataArray[i] == "object" && "data_value" in dataArray[i]) {
                        if ($("input[type=radio][name='" + i + "']").length > 0) {
                            $("input[type=radio][name='" + i + "']").prop("checked", false);
                            $("input[type=radio][name='" + i + "'][value='" + dataArray[i]['data_value'] + "']").prop("checked", true);
                        } else if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", dataArray[i].data_value != 0);
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", dataArray[i].data_value).css("display", (dataArray[i].data_value == "" ? "none" : "inline"));
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            for (var j in dataArray[i].data_value) {
                                addEditableListRow(i, dataArray[i]['data_value'][j]);
                            }
                        } else {
                            $("#" + i).val(dataArray[i].data_value);
                        }
                        if ("crc_value" in dataArray[i]) {
                            $("#" + i).data("crc_value", dataArray[i].crc_value);
                        } else {
                            $("#" + i).removeData("crc_value");
                        }
                    }
                }
                $(".selector-value-list").trigger("change");
                $(".multiple-dropdown-values").trigger("change");
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
                if (empty($("#location_id").val())) {
                    $("#_maintenance_form").find("div.basic-form-line").each(function () {
                        $(this).removeClass("hidden").find("label").html($(this).data("default_label"));
                    });
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_field_labels&location_id=" + $("#location_id").val(), function (returnArray) {
                        if ("field_labels" in returnArray) {
                            $("#_maintenance_form").find("div.basic-form-line").each(function () {
                                let labeledFields = ['_user_name_row', '_password_row','_customer_number_row','_distributor_source_row'];
                                if(!labeledFields.includes(this.id) && !this.id.startsWith("_custom_field_")) {
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
                                    if(!this.id.startsWith("_custom_field_")) {
                                        $(this).addClass("hidden");
                                    }
                                }
                            });
                        }
                    });
                }
            }
        </script>
		<?php
	}

	function sortCustomFields($a, $b) {
		if ($a['sequence_number'] == $b['sequence_number']) {
			return 0;
		}
		return ($a['sequence_number'] > $b['sequence_number']) ? 1 : -1;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", getFieldFromId("location_id", "location_credentials", "location_credential_id", $nameValues['primary_id']));
		$customFields = CustomField::getCustomFields("product_distributors");
		foreach ($customFields as $thisCustomField) {
			$productDistributorCustomFieldId = getFieldFromId("product_distributor_custom_field_id", "product_distributor_custom_fields", "product_distributor_id",
				$productDistributorId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($productDistributorCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		if (function_exists("_localAfterSaveLocationCredentials")) {
			_localAfterSaveLocationCredentials($nameValues);
		}
		return true;
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("product_distributors");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

    function hiddenElements() {
        ?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_get_connection_key_dialog" class="dialog-box">
            <p>Enter your login credentials for the distributor's site to retrieve your Connection Key</p>
            <form id="_get_connection_key_form">

            <div class="basic-form-line" id="_distributor_un_row">
                <label for="distributor_un">Username</label>
                <input type='text' id="distributor_un" name="distributor_un" class="validate[required]">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                <div class='clear-div'></div>
            </div>
            <div class="basic-form-line" id="_distributor_pw_row">
                <label for="distributor_pw">Password</label>
                <input type='password' id="distributor_pw" name="distributor_pw" class="validate[required]" autocomplete="chrome-off" autocomplete="off">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                <div class='clear-div'></div>
            </div>
            </form>

        </div> <!-- _get_connection_key_dialog -->
        <?php
    }
}

$pageObject = new ThisPage("location_credentials");
$pageObject->displayPage();
