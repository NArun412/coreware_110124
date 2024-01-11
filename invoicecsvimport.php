<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "INVOICECSVIMPORT";
require_once "shared/startup.inc";

class ThisPage extends Page {

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "invoices", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to invoices";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from invoice_details where invoice_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to invoices";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from invoices where invoice_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to invoices";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to invoices";
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
			case "select_invoices":
				$pageId = $GLOBALS['gAllPageCodes']["INVOICEMAINT"];
				$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Invoices selected in Invoices Maintenance program";
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

				$allValidFields = array("invoice_date", "invoice_number", "date_due", "contact_id", "old_contact_id", "invoice_type_code", "date_completed", "billing_terms_code", "description", "detailed_description", "amount", "unit_code", "unit_price");
				$requiredFields = array("invoice_number", "amount", "description");
				$numericFields = array("amount", "unit_price");

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
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							$fieldData[$thisFieldName] = trim($thisData);
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);
				$billingTerms = array();
				$invoiceTypes = array();
				$units = array();
				foreach ($importRecords as $index => $thisRecord) {

					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
					if (empty($contactId) && !empty($thisRecord['contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
					}
					if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
					}
					if (empty($contactId)) {
						$errorMessage .= "<p>Line " . $index . ": Contact not found</p>";
					}
					$missingFields = "";

					foreach ($requiredFields as $thisField) {
						if (empty($thisRecord[$thisField])) {
							$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
						}
					}
					if (!empty($missingFields)) {
						$errorMessage .= "<p>Line " . $index . " has missing fields: " . $missingFields . "</p>";
					}

					foreach ($numericFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$errorMessage .= "<p>Line " . $index . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName] . "</p>";
						}
					}
					if (!empty($thisRecord['billing_terms_code'])) {
						if (!array_key_exists($thisRecord['billing_terms_code'], $billingTerms)) {
							$billingTerms[$thisRecord['billing_terms_code']] = "";
						}
					}
					if (!empty($thisRecord['invoice_type_code'])) {
						if (!array_key_exists($thisRecord['invoice_type_code'], $invoiceTypes)) {
							$invoiceTypes[$thisRecord['invoice_type_code']] = "";
						}
					}
					if (!empty($thisRecord['unit_code'])) {
						if (!array_key_exists($thisRecord['unit_code'], $units)) {
							$units[$thisRecord['unit_code']] = "";
						}
					}
				}
				foreach ($billingTerms as $thisType => $billingTermsId) {
					$billingTermsId = getFieldFromId("billing_terms_id", "billing_terms", "billing_terms_code", makeCode($thisType));
					if (empty($billingTermsId)) {
						$billingTermsId = getFieldFromId("billing_terms_id", "billing_terms", "description", $thisType);
					}
					if (empty($billingTermsId)) {
						$errorMessage .= "<p>Invalid Billing Terms: " . $thisType . "</p>";
					} else {
						$billingTerms[$thisType] = $billingTermsId;
					}
				}
				foreach ($invoiceTypes as $thisType => $invoiceTypeId) {
					$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "invoice_type_code", makeCode($thisType));
					if (empty($invoiceTypeId)) {
						$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "description", $thisType);
					}
					if (empty($invoiceTypeId)) {
						$errorMessage .= "<p>Invalid Invoice Type: " . $thisType . "</p>";
					} else {
						$invoiceTypes[$thisType] = $invoiceTypeId;
					}
				}
				foreach ($units as $thisType => $unitId) {
					$unitId = getFieldFromId("unit_id", "units", "unit_code", makeCode($thisType));
					if (empty($unitId)) {
						$unitId = getFieldFromId("unit_id", "units", "description", $thisType);
					}
					if (empty($unitId)) {
						$errorMessage .= "<p>Invalid Unit: " . $thisType . "</p>";
					} else {
						$units[$thisType] = $unitId;
					}
				}
				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'invoices',?,now(),?,?)",
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
					if (empty($thisRecord['invoice_date'])) {
						$thisRecord['invoice_date'] = date("Y-m-d");
					}
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
					if (empty($contactId) && !empty($thisRecord['contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
					}
					if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
					}
					if (empty($contactId)) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = "Line " . ($index + 2) . ": Contact not found";
						ajaxResponse($returnArray);
						break;
					}

					if (!empty($thisRecord['invoice_number'])) {
						$invoiceId = getFieldFromId("invoice_id", "invoices", "contact_id", $contactId, "invoice_number = ?", $thisRecord['invoice_number']);
					} else {
						$invoiceId = getFieldFromId("invoice_id", "invoices", "contact_id", $contactId, "invoice_date = ? and date_completed <=> ? and billing_terms_id <=> ?",
							makeDateParameter($thisRecord['invoice_date']), makeDateParameter($thisRecord['date_completed']), $billingTerms[$thisRecord['billing_terms_code']]);
					}

					if (empty($invoiceId)) {
						$insertSet = executeQuery("insert into invoices (client_id,invoice_number,contact_id,invoice_type_id,invoice_date,date_due,date_completed,billing_terms_id) values (?,?,?,?,?, ?,?,?)",
							$GLOBALS['gClientId'], $thisRecord['invoice_number'], $contactId, $invoiceTypes[$thisRecord['invoice_type_code']], makeDateParameter($thisRecord['invoice_date']), makeDateParameter($thisRecord['date_due']), makeDateParameter($thisRecord['date_completed']), $billingTerms[$thisRecord['billing_terms_code']]);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$invoiceId = $insertSet['insert_id'];
						$insertCount++;
					}
					if (!array_key_exists("unit_price", $thisRecord)) {
						$thisRecord['unit_price'] = $thisRecord['amount'];
						$thisRecord['amount'] = 1;
					}
					executeQuery("insert into invoice_details (invoice_id,detail_date,description,detailed_description,amount,unit_id,unit_price) values (?,?,?,?,?,?,?)",
						$invoiceId, makeDateParameter($thisRecord['invoice_date']), $thisRecord['description'], $thisRecord['detailed_description'], $thisRecord['amount'], $units[$thisRecord['unit_code']], $thisRecord['unit_price']);

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $invoiceId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " Invoices imported.</p>";
				ajaxResponse($returnArray);
				break;
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
				<?php if (canAccessPage("INVOICEMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'invoices' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
					<?php if (canAccessPage("INVOICEMAINT")) { ?>
                        <td><span class='far fa-check-square select-invoices'></span></td>
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
            $(document).on("click", ".select-invoices", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_invoices&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
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
        #import_error { color: rgb(192,0,0); }
        .remove-import { cursor: pointer; }
        .select-invoices { cursor: pointer; }
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these invoices being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
