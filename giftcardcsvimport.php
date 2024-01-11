<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "GIFTCARDCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class GiftCardCSVImportPage extends Page {

	var $iErrorMessages = array();

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_import":
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				if (empty($csvImportId)) {
					$returnArray['error_message'] = "Invalid CSV Import";
					ajaxResponse($returnArray);
					break;
				}
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "gift_cards", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to gift cards";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from gift_card_log where description = 'Created by CSV import' and gift_card_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to gift cards (gift card log)");

				$deleteSet = executeQuery("delete from gift_cards where gift_card_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to gift cards");

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to gift cards");

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to gift cards");

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);
				break;
			case "import_csv":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

				$allValidFields = array("gift_card_number", "description", "balance", "notes", "user", "inactive", "pin", "gift_card_pin");
				$requiredFields = array("gift_card_number", "description", "balance");
				$numericFields = array("balance");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$this->iErrorMessages = array();

				# parse file and check for invalid fields
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
						}
						$invalidFields = "";
						foreach ($fieldNames as $fieldName) {
							if (!in_array($fieldName, $allValidFields)) {
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
							$this->addErrorMessage("Valid fields are: " . implode(", ", $allValidFields));
						}
					} else {
						$fieldData = array();
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							if ($thisFieldName == "pin") {
								$thisFieldName = "gift_card_pin";
							}
							$fieldData[$thisFieldName] = trim($thisData);
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);

				# check for required fields and invalid data
				$users = array();
				$giftCardNumbers = array();
				foreach ($importRecords as $index => $thisRecord) {
					if ($_POST['add_default_description'] && empty($thisRecord['description'])) {
						$importRecords[$index]['description'] = $thisRecord['description'] = "Imported Gift Card";
					}
					$missingFields = "";
					foreach ($requiredFields as $thisField) {
						if (empty($thisRecord[$thisField])) {
							$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
						}
					}
					if (!empty($missingFields)) {
						$this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
					}

					foreach ($numericFields as $fieldName) {
						$fieldValue = str_replace(",", "", $thisRecord[$fieldName]);
						if (!empty($fieldValue) && !is_numeric($fieldValue) && !is_float($fieldValue)) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
						}
					}
					$giftCardNumber = substr(makeCode($thisRecord['gift_card_number'], ["allow_dash"=>true]), 0, 30);
					if (in_array($giftCardNumber, $giftCardNumbers)) {
						$this->addErrorMessage("Line " . ($index + 2) . ": Duplicate gift card number (limited to 30 characters): " . $giftCardNumber);
					}
					if (!empty($thisRecord['user'])) {
						if (!array_key_exists($thisRecord['user'], $users)) {
							$userName = str_replace(" ", ".", $thisRecord['user']);
							$userId = getFieldFromId("user_id", "users", "user_name", $userName);
							if (empty($userId)) {
								$userId = getFieldFromId("user_id", "users", "contact_id", getFieldFromId("contact_id", "contacts", "email_address", $thisRecord['user']));
							}
							if (empty($userId)) {
								$nameParts = explode(" ", $thisRecord['user']);
								$firstName = array_shift($nameParts);
								if (count($nameParts) > 1) {
									$middleName = array_shift($nameParts);
								}
								$lastName = implode(" ", $nameParts);
								if (empty($middleName)) {
									$userId = getFieldFromId("user_id", "users", "contact_id", getFieldFromId("contact_id", "contacts", "first_name", $firstName, "last_name = ?", $lastName));
								} else {
									$userId = getFieldFromId("user_id", "users", "contact_id", getFieldFromId("contact_id", "contacts", "first_name", $firstName, "last_name = ? and middle_name = ?", $lastName, $middleName));
								}
							}
							if (!empty($userId)) {
								$users[$thisRecord['user']] = $userId;
							} else {
								$this->addErrorMessage("Line " . ($index + 2) . ": user not found: " . $thisRecord['user']);
							}
						}
					}
				}

				if (!empty($this->iErrorMessages)) {
					$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
					foreach ($this->iErrorMessages as $thisMessage => $count) {
						$returnArray['import_error'] .= "<p>" . $count . ": " . $thisMessage . "</p>";
					}
					ajaxResponse($returnArray);
					break;
				}

				# do import
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'gift_cards',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				$this->checkSqlError($resultSet, $returnArray);
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$giftCardNumber = makeCode($thisRecord['gift_card_number'], ["allow_dash"=>true]);
                    $giftCardId = GiftCard::lookupGiftCard($giftCardNumber);

					if (empty($giftCardId)) {
						$resultSet = executeQuery("insert into gift_cards (client_id, gift_card_number, gift_card_pin, description, balance, notes, user_id, inactive) values (?,?,?,?,?,?,?,?)",
							$GLOBALS['gClientId'], $giftCardNumber, $thisRecord['gift_card_pin'], $thisRecord['description'], $thisRecord['balance'], $thisRecord['notes'], $users[$thisRecord['user']],
							(empty($thisRecord['inactive']) ? 0 : 1));
						$this->checkSqlError($resultSet, $returnArray);
						$giftCardId = $resultSet['insert_id'];
						$resultSet = executeQuery("insert into gift_card_log (gift_card_id, description, log_time, amount) values (?,?,now(),?)",
							$giftCardId, "Created by CSV import", $thisRecord['balance']);
						$this->checkSqlError($resultSet, $returnArray);
						$insertCount++;
						$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $giftCardId);
						$this->checkSqlError($insertSet, $returnArray);
					} else {
						$resultSet = executeQuery("update gift_cards set description = ?, balance = ?, notes = ?, user_id = ?, inactive = ? where gift_card_id = ?",
							$thisRecord['description'], $thisRecord['balance'], $thisRecord['notes'], $users[$thisRecord['user']], (empty($thisRecord['inactive']) ? 0 : 1), $giftCardId);
						$this->checkSqlError($resultSet, $returnArray);
						$resultSet = executeQuery("insert into gift_card_log (gift_card_id, description, log_time, amount) values (?,?,now(),?)",
							$giftCardId, "Updated by CSV import", $thisRecord['balance']);
						$this->checkSqlError($resultSet, $returnArray);
						$updateCount++;
					}
					$giftCard = new GiftCard($giftCardId);
					coreSTORE::giftCardNotification($giftCardNumber, "import", "CSV Import", $thisRecord['balance']);
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['response'] = "<p>" . $insertCount . " gift cards imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " existing gift cards updated.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

	function addErrorMessage($errorMessage) {
		if (array_key_exists($errorMessage, $this->iErrorMessages)) {
			$this->iErrorMessages[$errorMessage]++;
		} else {
			$this->iErrorMessages[$errorMessage] = 1;
		}
	}

	function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
		if (!empty($resultSet['sql_error'])) {
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'];
			} else {
				$returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
			}
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			ajaxResponse($returnArray);
		}
	}


	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_add_default_description_row">
                    <input type="checkbox" tabindex="10" id="add_default_description" name="add_default_description" value="1">
                    <label class="checkbox-label" for="add_default_description">Fill in missing descriptions(instead of failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

                <div id="import_error"></div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th>Undo</th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'gift_cards' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 120 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row" data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function (returnArray) {
                                if ("csv_import_id" in returnArray) {
                                    $("#csv_import_id_" + returnArray['csv_import_id']).remove();
                                }
                            });
                            $("#_confirm_undo_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_undo_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_submit_form", function () {
                if ($("#_submit_form").data("disabled") === "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($("#_submit_form"));
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #import_error {
                color: rgb(192, 0, 0);
            }

            .remove-import {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these gift cards being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new GiftCardCSVImportPage();
$pageObject->displayPage();
