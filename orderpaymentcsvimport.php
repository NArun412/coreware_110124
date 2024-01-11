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

$GLOBALS['gPageCode'] = "ORDERPAYMENTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class OrderPaymentCsvImportPage extends Page {

    protected $iErrorMessages = array();
    private $iValidFields = array("order_number", "payment_time", "payment_method", "amount", "tax_charge", "shipping_charge", "handling_charge", "authorization_code",
        "transaction_identifier", "reference_number", "notes", "contact_id", "email_address", "full_name", "account_id", "account_number");
    private $iContactIdentifierFields = array();

    private $iShowDetailedErrors = false;

    function setup() {
        $this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
        $resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            $this->iContactIdentifierFields[] = "contact_identifier-" . strtolower($row['contact_identifier_type_code']);
        }
    }

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "order_payments", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to order payments: change log";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

                $tempTableName = "temp_account_ids_" . getRandomString(10);
                executeQuery("create table " . $tempTableName . "(account_id int not null,primary key (account_id))");
                executeQuery("insert into " . $tempTableName . "(account_id) (select account_id from order_payments where order_payment_id in (select primary_identifier from csv_import_details where csv_import_id = ?))", $csvImportId);

                $deleteSet = executeQuery("delete from order_payments where order_payment_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to order payments");

                $deleteSet = executeQuery("delete from accounts where account_id in (select account_id from " . $tempTableName  .")");
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to order payments: accounts");

                executeQuery("drop table " . $tempTableName);

                $deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to order payments");

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import");

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);

				break;

			case "import_file":
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

				// Build lookup arrays

				$userEmailResult = executeQuery("select contact_id, user_id, email_address from contacts join users using (contact_id) where contacts.client_id = ?", $GLOBALS['gClientId']);
				$userEmails = array();
				while ($row = getNextRow($userEmailResult)) {
					$userEmails[$row['email_address']]['contact_id'] = $row['contact_id'];
					$userEmails[$row['email_address']]['user_id'] = $row['user_id'];
				}
				freeResult($userEmailResult);

				$contactEmailResult = executeQuery("select contact_id, email_address from contacts where client_id = ?", $GLOBALS['gClientId']);
				$contactEmails = array();
				while ($row = getNextRow($contactEmailResult)) {
					$contactEmails[$row['email_address']] = $row['contact_id'];
				}
				freeResult($contactEmailResult);

				// Load and validate data
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");
                $contactIdentifierTypes = array();
                $allValidFields = array_merge($this->iValidFields, $this->iContactIdentifierFields);
                $requiredFields = array("order_number", "payment_time", "payment_method", "amount");
                $numericFields = array("order_number", "amount",  "tax_charge", "shipping_charge", "handling_charge", "contact_id", "account_id");
                $dateTimeFields = array("payment_time");


                $resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $allValidFields[] = "contact_identifier-" . strtolower($row['contact_identifier_type_code']);
                    $contactIdentifierTypes[$row['contact_identifier_type_code']] = $row['contact_identifier_type_id'];
                }

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
                            // strip non printable characters - preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $thisData)
                            $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
                        }
                        if(empty(array_filter($fieldData))) {
                            continue;
                        }
                        $importRecords[] = $fieldData;
                    }
                    $count++;
                }
                fclose($openFile);

                # check for required fields and invalid data
                $paymentMethods = array();
                foreach ($importRecords as $index => $thisRecord) {
                    $missingFields = "";
                    foreach ($requiredFields as $thisField) {
                        if (strpos($thisField, "|") !== false) {
                            $alternateRequiredFields = explode("|", $thisField);
                            $found = false;
                            foreach ($alternateRequiredFields as $thisAlternate) {
                                $found = $found ?: !empty($thisRecord[$thisAlternate]);
                            }
                            if (!$found) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . str_replace("|", " or ", $thisField);
                            }
                        } else {
                            if (empty($thisRecord[$thisField])) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
                            }
                        }
                    }
                    if (!empty($missingFields)) {
                        $this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
                    }

                    foreach ($numericFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName] + 0) && !is_numeric($thisRecord[$fieldName] + 0)) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
                        }
                    }
                    foreach ($dateTimeFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && strtotime($thisRecord[$fieldName]) == false) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a valid date or time: " . $thisRecord[$fieldName]);
                        }
                    }
                    if (!empty($thisRecord['payment_method'])) {
                        if (empty($paymentMethods[$thisRecord['payment_method']])) {
                            $paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", makeCode($thisRecord['payment_method']));
                            if (empty($paymentMethodId)) {
                                $paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "replace(payment_method_code,'_','')", makeCode($thisRecord['payment_method']));
                            }
                            if (empty($paymentMethodId)) {
                                $paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "description", $thisRecord['payment_method']);
                            }
                            if (empty($paymentMethodId)) {
                                $this->addErrorMessage("Line " . ($index + 2) . ": Payment Method does not exist: " . $thisRecord['payment_method']);
                            } else {
                                $paymentMethods[$thisRecord['payment_method']] = getRowFromId("payment_methods", "payment_method_id", $paymentMethodId);
                            }
                        }
                    }
                }

                if (!empty($this->iErrorMessages)) {
                    $returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
                    foreach ($this->iErrorMessages as $thisMessage => $count) {
                        $returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
                    }
                    ajaxResponse($returnArray);
                    break;
                }

                # order specific validation

                $ordersArray = array();
                $missingSkipCount = 0;
                foreach ($importRecords as $index => $thisRecord) {
                    //"Insert into order_payments (order_id, payment_method_id, account_id, invoice_id, reference_number, amount, authorization_code, transaction_identifier, notes) "

                    $orderNumber = $thisRecord['order_number'];
                    if(!array_key_exists($orderNumber, $ordersArray)) {
                        $orderRow = getRowFromId("orders", "order_number", $orderNumber);
                        if(empty($orderRow)) {
                            if(empty($_POST['skip_orders_not_found'])) {
                                $this->addErrorMessage("Order " . $orderNumber . " not found.");
                            } else {
                                $missingSkipCount++;
                                continue;
                            }
                        }
                        $ordersArray[$orderNumber] = $orderRow;
                    }

                    $contactEmail = $thisRecord['email_address'];
                    $contactId = $thisRecord['contact_id'];
                    $userId = "";
                    if(empty($contactId)) {
                        if (!empty($contactEmail)) {
                            if (array_key_exists($contactEmail, $userEmails)) {
                                $contactId = $userEmails[$contactEmail]['contact_id'];
                                $userId = $userEmails[$contactEmail]['user_id'];
                            } elseif (array_key_exists($contactEmail, $contactEmails)) {
                                $contactId = $contactEmails[$contactEmail];
                            }
                        }
                    }
                    if(empty($contactId)) {
                        foreach($contactIdentifierTypes as $contactIdentifierTypeCode => $contactIdentifierTypeId) {
                            $fieldName = 'contact_identifier-'. strtolower($contactIdentifierTypeCode);
                            if(!empty($thisRecord[$fieldName])) {
                                $contactId = getFieldFromId("contact_id", "contact_identifiers", "identifier_value",
                                    $thisRecord[$fieldName], "contact_identifier_type_id = ?", $contactIdentifierTypeId);
                                if(!empty($contactId)) {
                                    break;
                                }
                            }
                        }
                    }

                    if (empty($contactId)) {
                        if(empty($_POST['skip_contacts_not_found'])) {
                            $this->addErrorMessage("Order " . $orderNumber . ": Contact Not found.");
                        } else {
                            $thisRecord['contact_id'] = $ordersArray[$orderNumber]['contact_id'];
                        }
                    } else {
                        $thisRecord['contact_id'] = $contactId;
                        $thisRecord['user_id'] = $userId;
                    }

                    $ordersArray[$orderNumber]['order_payments'][] = $thisRecord;
                }

                if (!empty($this->iErrorMessages)) {
                    $returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
                    foreach ($this->iErrorMessages as $thisMessage => $count) {
                        $returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
                    }
                    ajaxResponse($returnArray);
                    break;
                }

				if (!empty($_POST['validate_only'])) {
					$returnArray['response'] = "File validated successfully. No errors found.";
					ajaxResponse($returnArray);
					break;
				}

				// Import data
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'order_payments',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
                $this->checkSqlError($resultSet, $returnArray);
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				foreach ($ordersArray as $thisOrder) {
                    foreach($thisOrder['order_payments'] as $thisPayment) {
                        $paymentMethodId = $paymentMethods[$thisPayment['payment_method']]['payment_method_id'];
                        $orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_id", $thisOrder['order_id'],
                        "payment_method_id = ? and amount = ?",$paymentMethodId, $thisPayment['amount']);

                        $paymentTime = date("Y-m-d H:i:s", strtotime($thisPayment['payment_time']));

                        if(empty($orderPaymentId)) {
                            $contactId = $thisPayment['contact_id'] ?: $thisOrder['contact_id'];
                            $accountNumber = (empty($thisPayment['account_number']) ? "" : "XXXX-" . substr($thisPayment['account_number'],-4));
                            $accountId = getFieldFromId("account_id", "accounts", "account_id", $thisOrder['account_id'] , "contact_id = ?", $contactId);
                            if(empty($accountId)) {
                                $accountId = getFieldFromId("account_id", "accounts", "contact_id", $contactId, "account_number = ?", $accountNumber);
                            }
                            if(empty($accountId)) {
                                $accountLabel = $paymentMethods[$thisPayment['payment_method']]['description'] . (empty($accountNumber) ? "" : " - " . $accountNumber);
                                $fullName = $thisPayment['full_name'] ?: getDisplayName($contactId);
                                $insertSet = executeQuery("insert into accounts (contact_id, account_label, payment_method_id, full_name, account_number, inactive) values (?,?,?,?,?,1)",
                                    $contactId, $accountLabel, $paymentMethodId, $fullName, $accountNumber);
                                $this->checkSqlError($insertSet, $returnArray);
                                $accountId = $insertSet['insert_id'];
                            }

                            $insertSet = executeQuery("insert into order_payments (order_id, payment_time, payment_method_id, account_id, reference_number, amount, " .
                                "authorization_code, transaction_identifier, notes) values (?,?,?,?,?,?,?,?,?)", $thisOrder['order_id'], $paymentTime,
                                $paymentMethodId, $accountId, $thisPayment['reference_number'], $thisPayment['amount'],
                                $thisPayment['authorization_code'], $thisPayment['transaction_identifier'], $thisPayment['notes']);
                            $this->checkSqlError($insertSet, $returnArray);
                            $orderPaymentId = $insertSet['insert_id'];
                            $insertCount++;
                            $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $orderPaymentId);
                            $this->checkSqlError($insertSet, $returnArray);
                        } else {
                            $resultSet = executeQuery("update order_payments set payment_time = ?, reference_number = ?, authorization_code = ?, transaction_identifier = ?, notes = ? " .
                                "where order_payment_id = ?", $paymentTime, $thisPayment['reference_number'], $thisPayment['authorization_code'], $thisPayment['transaction_identifier'],
                                $thisPayment['notes'], $orderPaymentId);
                            $this->checkSqlError($resultSet, $returnArray);
                            $updateCount++;
                        }
                    }
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " Payments imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " existing payments updated.</p>";
                if(!empty($_POST['skip_orders_not_found'])) {
                    $returnArray['response'] .= "<p>" . $missingSkipCount . " payments for missing orders skipped.</p>";
                }
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
            if($this->iShowDetailedErrors) {
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

                <div class="basic-form-line" id="_description_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_orders_not_found_row">
                    <input type="checkbox" tabindex="10" id="skip_orders_not_found" name="skip_orders_not_found" value="1"><label
                            class="checkbox-label" for="skip_orders_not_found">Skip payments for orders that cannot be found (instead of
                        failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_contacts_not_found_row">
                    <input type="checkbox" tabindex="10" id="skip_contacts_not_found" name="skip_contacts_not_found" value="1"><label
                            class="checkbox-label" for="skip_contacts_not_found">Use Order contact for contacts that cannot be found (instead of
                        failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_validate_only">
                    <input type="checkbox" tabindex="10" id="validate_only" name="validate_only" value="1"><label
                            class="checkbox-label" for="validate_only">Validate Data only (do not import)</label>
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
                <th></th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'order_payments' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 120 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row"
                    data-csv_import_id="<?= $row['csv_import_id'] ?>">
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
                const $submitForm = $("#_submit_form");
                const $editForm = $("#_edit_form");
                const $postIframe = $("#_post_iframe");
                if ($submitForm.data("disabled") === "true") {
                    return false;
                }
                if ($editForm.validationEngine("validate")) {
                    $("#import_error").html("");
                    disableButtons($submitForm);
                    $("body").addClass("waiting-for-ajax");
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_file").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
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
                        enableButtons($submitForm);
                    });
                }
                return false;
            });
            $(document).on("tap click", ".valid-fields-trigger", function () {
                $("#_valid_fields_dialog").dialog({
                    modal: true,
                    resizable: true,
                    width: 1000,
                    title: 'Valid Fields',
                    buttons: {
                        Close: function (event) {
                            $("#_valid_fields_dialog").dialog('close');
                        }
                    }
                });
            });
            $("#_valid_fields_dialog .accordion").accordion({
                active: false,
                heightStyle: "content",
                collapsible: true
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
            #_valid_fields_dialog .ui-accordion-content {
                max-height: 200px;
            }

            #_valid_fields_dialog > ul {
                columns: 3;
                padding-bottom: 1rem;
            }

            #_valid_fields_dialog .ui-accordion ul {
                columns: 2;
            }

            #_valid_fields_dialog ul li {
                padding-right: 20px;
            }
        </style>
        <?php
    }

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
        <p>To support refunds for imported orders, account_id and/or account_number must be included after running Account CSV import.
            If the account record does not exist, the payment will be created with an inactive account record not linked to the merchant gateway.</p>
        <ul>
            <li><?= implode("</li><li>", $this->iValidFields) ?></li>
        </ul>

        <div class="accordion">
            <?php if (!empty($this->iContactIdentifierFields)) { ?>
                <h3>Valid Contact Identifiers</h3>
                <!-- Has an extra wrapper div since columns CSS property doesn't work properly with accordion content's max height -->
                <div>
                    <ul>
                        <li><?= implode("</li><li>", $this->iContactIdentifierFields) ?></li>
                    </ul>
                </div>
            <?php } ?>
        </div>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these payments being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new OrderPaymentCsvImportPage();
$pageObject->displayPage();
