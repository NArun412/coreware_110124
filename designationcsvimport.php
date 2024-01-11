<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "DESIGNATIONCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gSkipCorestoreContactUpdate'] = true;

class DesignationCsvImportPage extends Page {

	var $iValidFields = array("contact_id", "title", "first_name", "middle_name", "last_name", "business_name", "suffix", "address_1", "address_2",
		"city", "state", "postal_code", "country", "email_address", "phone_number", "designation_code", "description", "inactive", "link_name", "designation_type_code");

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "designations", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to designations";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from designation_email_addresses where designation_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to designations";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from designations where designation_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to designations";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to designations";
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
			case "select_designations":
				$pageId = $GLOBALS['gAllPageCodes']["DESIGNATIONMAINT"];
				$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Designations selected in Designation Maintenance program";
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
				$missingFields = "";
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

				$allValidFields = $this->iValidFields;
				$requiredFields = array("designation_code");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$errorMessage = "";
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
							$errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . "</p>";
							$errorMessage .= "<p>Valid fields are: " . implode(", ", $allValidFields) . "</p>";
						}
					} else {
						$fieldData = array();
						$dataFound = false;
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							$fieldData[$thisFieldName] = trim($thisData);
							if ($thisFieldName != "country" && !empty($fieldData[$thisFieldName])) {
								$dataFound = true;
							}
						}
						if ($dataFound) {
							$importRecords[] = $fieldData;
						}
					}
					$count++;
				}
				fclose($openFile);
				foreach ($importRecords as $index => $thisRecord) {
					if (empty($thisRecord['country'])) {
						$countryId = 1000;
					} else {
						$countryId = getFieldFromId("country_id", "countries", "country_name", $thisRecord['country']);
						if (empty($countryId)) {
							$countryId = getFieldFromId("country_id", "countries", "country_code", $thisRecord['country']);
						}
					}
					if (empty($countryId)) {
						$errorMessage .= "<p>Invalid Country: " . $thisRecord['country'] . "</p>";
					}
					$importRecords[$index]['country_id'] = $countryId;
					foreach ($requiredFields as $thisField) {
						if (empty($thisRecord[$thisField])) {
							$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
						}
					}
				}
				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'designations',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$designationId = getFieldFromId("designation_id", "designations", "designation_code", makeCode($thisRecord['designation_code']));
					$originalContactId = "";
					if (!empty($designationId)) {
						$originalContactId = getFieldFromId("contact_id", "designations", "designation_id", $designationId);
					}
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
					if (empty($contactId) && !empty($thisRecord['contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
					}
					if (empty($contactId)) {
						$contactId = $originalContactId;
					}

					if (array_key_exists("first_name", $thisRecord) || array_key_exists("last_name", $thisRecord) || array_key_exists("email_address", $thisRecord)) {
						if (empty($contactId)) {
							$resultSet = executeQuery("select contact_id from contacts where client_id = ? and first_name <=> ? and middle_name <=> ? and last_name <=> ? and address_1 <=> ? and " .
								"city <=> ? and state <=> ? and postal_code <=> ? and country_id = ? and email_address <=> ?", $GLOBALS['gClientId'], $thisRecord['first_name'], $thisRecord['middle_name'],
								$thisRecord['last_name'], $thisRecord['address_1'], $thisRecord['city'], $thisRecord['state'], $thisRecord['postal_code'], $thisRecord['country_id'], $thisRecord['email_address']);
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
							}
						}

						if (!empty($originalContactId) && $originalContactId != $contactId) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = "Contact ID found for designation code '" . $thisRecord['designation_code'] . "' not the same as was already set in designation";
							ajaxResponse($returnArray);
							break;
						}

						if (empty($contactId)) {
							$contactDataTable = new DataTable("contacts");
							if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("title" => $thisRecord['title'], "first_name" => $thisRecord['first_name'],
								"middle_name" => $thisRecord['middle_name'], "last_name" => $thisRecord['last_name'], "suffix" => $thisRecord['suffix'],
								"business_name" => $thisRecord['business_name'], "address_1" => $thisRecord['address_1'], "address_2" => $thisRecord['address_2'], "city" => $thisRecord['city'], "state" => $thisRecord['state'],
								"postal_code" => $thisRecord['postal_code'], "email_address" => $thisRecord['email_address'], "country_id" => $thisRecord['country_id'])))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $contactDataTable->getErrorMessage();
								ajaxResponse($returnArray);
								break;
							}
						} else {
							$nameValues = array();
							$updateFields = array("title", "first_name", "middle_name", "last_name", "business_name", "suffix", "address_1", "address_2", "city", "state", "postal_code", "country", "email_address");
							foreach ($updateFields as $fieldName) {
								if (!empty($thisRecord[$fieldName])) {
									$nameValues[$fieldName] = $thisRecord[$fieldName];
								}
							}
							$dataTable = new DataTable("contacts");
							$dataTable->setPrimaryId($contactId);
							if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $dataTable->getErrorMessage();
								ajaxResponse($returnArray);
								break;
							}
						}
						if (!empty($thisRecord['phone_number'])) {
							if ($thisRecord['country_id'] == 1000) {
								$thisRecord['phone_number'] = formatPhoneNumber($thisRecord['phone_number']);
							}
							$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $thisRecord['phone_number']);
						}
					}

					if (empty($thisRecord['description']) && !empty($contactId)) {
						$thisRecord['description'] = getDisplayName($contactId);
					}
					if (empty($thisRecord['description'])) {
						$thisRecord['description'] = $thisRecord['designation_code'];
					}
					if (empty($designationId)) {
						if (empty($thisRecord['link_name'])) {
							$linkName = makeCode($thisRecord['description'], array("lowercase" => true, "use_dash" => true));
						} else {
							$linkName = makeCode($thisRecord['link_name'], array("lowercase" => true, "use_dash" => true));
						}
						$linkName = str_replace(".", "", $linkName);
						$designationTypeId = getFieldFromId("designation_type_id", "designation_types", "designation_type_code", $thisRecord['designation_type_code']);
						if (empty($designationTypeId)) {
							$designationTypeId = $_POST['designation_type_id'];
						}
						$insertSet = executeQuery("insert into designations (client_id,designation_code,description,link_name,designation_type_id,date_created,contact_id,inactive) values (?,?,?,?,?,current_date,?,?)",
							$GLOBALS['gClientId'], makeCode($thisRecord['designation_code']), $thisRecord['description'], $linkName, $designationTypeId, $contactId, (empty($thisRecord['inactive']) ? 0 : 1));
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$insertCount++;
						$designationId = $insertSet['insert_id'];
					} else {
						$insertSet = executeQuery("update designations set description = ?,contact_id = ? where designation_id = ? and client_id = ?",
							$thisRecord['description'], $contactId, $designationId, $GLOBALS['gClientId']);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$updateCount++;
					}
					if (!empty($thisRecord['email_address'])) {
						$designationEmailAddressId = getFieldFromId("designation_email_address_id", "designation_email_addresses", "designation_id", $designationId, "email_address = ?", $thisRecord['email_address']);
						if (empty($designationEmailAddressId)) {
							$insertSet = executeQuery("insert into designation_email_addresses (designation_id,email_address) values (?,?)",
								$designationId, $thisRecord['email_address']);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . ":" . $designationId . ":" . $thisRecord['email_address'];
								ajaxResponse($returnArray);
								break;
							}
						}
					}

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $designationId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " designations imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " designations updated.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>

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

                <div class="basic-form-line" id="_designation_type_id_row">
                    <label for="designation_type_id" class="required-label">Designation Type</label>
                    <select tabindex="10" class="validate[required]" id="designation_type_id" name="designation_type_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from designation_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div id="import_error"></div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th></th>
				<?php if (canAccessPage("DESIGNATIONMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'designations' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
					<?php if (canAccessPage("DESIGNATIONMAINT")) { ?>
                        <td><span class='far fa-check-square select-designations'></span></td>
					<?php } ?>
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
            $(document).on("click", ".select-designations", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_contacts&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
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
            .select-designations {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these designations being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new DesignationCsvImportPage();
$pageObject->displayPage();
