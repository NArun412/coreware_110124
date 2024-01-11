<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "SUBSCRIPTIONCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class ThisPage extends Page {

	var $iErrorMessages = array();
    var $iValidFields = array("old_contact_id", "contact_id", "email_address", "subscription_code", "start_date", "expiration_date", "units_remaining",
        "customer_paused", "inactive");
    var $iRequiredFields = array("subscription_code", "start_date", "contact_id|old_contact_id|email_address");

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "contact_subscriptions", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contact subscriptions #872";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from contact_subscriptions where contact_subscription_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contact subscriptions #294";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contact subscriptions #183";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $deleteSet['sql_error'];
					ajaxResponse($returnArray);
					break;
				}

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

				$allValidFields = $this->iValidFields;
                $requiredFields = $this->iRequiredFields;
				$numericFields = array("units_remaining");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$this->iErrorMessages = array();
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
							$fieldData[$thisFieldName] = trim($thisData);
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);
				$subscriptions = array();
				foreach ($importRecords as $index => $thisRecord) {
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
					if (empty($contactId) && !empty($thisRecord['contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
					}
					if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
					}
                    if (empty($contactId) && !empty($thisRecord['email_address'])) {
                        $resultSet = executeQuery("select * from contacts where email_address = ? and client_id = ?", $thisRecord['email_address'], $GLOBALS['gClientId']);
                        if($resultSet['row_count'] != 1) {
                            $this->addErrorMessage("Line " . ($index + 2) . " email address " . $thisRecord['email_address'] . " could not be matched to a contact. (" . $resultSet['row_count'] . " contacts found)");
                        } else {
                            $row = getNextRow($resultSet);
                            $contactId = $row['contact_id'];
                        }
                    }
                    if(empty($contactId)) {
                        $this->addErrorMessage("Line " . ($index + 2) . " can not be associated with a contact. contact_id, old_contact_id or unique email_address is required.");
                    } else {
                        $importRecords[$index]['contact_id'] = $contactId;
                    }

					$missingFields = "";
                    foreach ($requiredFields as $thisField) {
                        if (strpos($thisField, "|") === false) {
                            if (empty($thisRecord[$thisField])) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
                            }
                        } else {
                            $found = false;
                            foreach (explode("|", $thisField) as $orField) {
                                if (!empty($thisRecord[$orField])) {
                                    $found = true;
                                }
                            }
                            if (!$found) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . str_replace(" OR ", "|", $thisField);
                            }
                        }
                    }
					if (!empty($missingFields)) {
						$this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
					}

					foreach ($numericFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
						}
					}
					if (!empty($thisRecord['subscription_code'])) {
						if (!array_key_exists($thisRecord['subscription_code'], $subscriptions)) {
							$subscriptions[$thisRecord['subscription_code']] = "";
						}
					}
				}
				foreach ($subscriptions as $thisCode => $subscriptionId) {
					$subscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", makeCode($thisCode));
					if (empty($subscriptionId)) {
						$subscriptionId = getFieldFromId("subscription_id", "subscriptions", "description", $thisCode);
					}
					if (empty($subscriptionId)) {
						$this->addErrorMessage("Invalid Subscription: " . $thisCode);
					} else {
						$subscriptions[$thisCode] = $subscriptionId;
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

				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'contact_subscriptions',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				$skippedCount = 0;
				$contactSubscriptions = array();
				foreach ($importRecords as $index => $thisRecord) {
					$contactId = $thisRecord['contact_id'];
					if (empty($contactId)) {
						$skippedCount++;
						continue;
					}
					$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "contact_id", $contactId,
						"subscription_id = ?", $subscriptions[$thisRecord['subscription_code']]);

					if (empty($contactSubscriptionId)) {
						$resultSet = executeQuery("insert into contact_subscriptions (contact_id,subscription_id,start_date," .
							"expiration_date,units_remaining,customer_paused,inactive) values (?,?,?,?,?, ?,?)", $contactId, $subscriptions[$thisRecord['subscription_code']],
							date("Y-m-d", strtotime($thisRecord['start_date'])), (empty($thisRecord['expiration_date']) ? "" : date("Y-m-d", strtotime($thisRecord['expiration_date']))),
							$thisRecord['units_remaining'], (empty($thisRecord['customer_paused']) ? 0 : 1), (empty($thisRecord['inactive']) ? 0 : 1));
						if (!empty($resultSet['sql_error'])) {
							if (!empty($contactSubscriptions)) {
								executeQuery("delete from contact_subscriptions where contact_subscription_id in (" . implode(",", $contactSubscriptions) . ")");
							}
							executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
							executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
							$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
						$contactSubscriptionId = $resultSet['insert_id'];
						$contactSubscriptions[] = $contactSubscriptionId;
						$insertCount++;
						$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $contactSubscriptionId);
						if (!empty($insertSet['sql_error'])) {
							if (!empty($contactSubscriptions)) {
								executeQuery("delete from contact_subscriptions where contact_subscription_id in (" . implode(",", $contactSubscriptions) . ")");
							}
							executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
							executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
					} else {
						$resultSet = executeQuery("update contact_subscriptions set start_date = ?," .
							"expiration_date = ?,units_remaining = ?,customer_paused = ?,inactive = ? where contact_subscription_id = ?",
							date("Y-m-d", strtotime($thisRecord['start_date'])), (empty($thisRecord['expiration_date']) ? "" : date("Y-m-d", strtotime($thisRecord['expiration_date']))),
							$thisRecord['units_remaining'], (empty($thisRecord['customer_paused']) ? 0 : 1), (empty($thisRecord['inactive']) ? 0 : 1),
							$contactSubscriptionId);
						if (!empty($resultSet['sql_error'])) {
							if (!empty($contactSubscriptions)) {
								executeQuery("delete from contact_subscriptions where contact_subscription_id in (" . implode(",", $contactSubscriptions) . ")");
							}
							executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
							executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
							$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
						$updateCount++;
					}
				}
                if ($insertCount + $updateCount > 0) {
	                updateUserSubscriptions();
                }

				$returnArray['response'] = "<p>" . $insertCount . " contact subscriptions imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " existing contact subscriptions found.</p>";
				$returnArray['response'] .= "<p>" . $skippedCount . " contact records not found.</p>";
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

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>
            <p><strong>Required Fields: </strong><?= str_replace("|", " OR ", implode(", ", $this->iRequiredFields)) ?></p>

            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line">
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
                <th></th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'contact_subscriptions' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = $minutesSince < 48;
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
                var csvImportId = $(this).closest("tr").data("csv_import_id");
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
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function(returnArray) {
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
                if ($("#_submit_form").data("disabled") == "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
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
            This will result in these contact subscriptions being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
