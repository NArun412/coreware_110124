<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "TAXCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gSkipCorestoreContactUpdate'] = true;

class DesignationCsvImportPage extends Page {

	var $iValidFields = array("state", "postal_code", "tax_rate");
	var $iIgnoreFields = array("TaxRegionName", "StateRate", "EstimatedCountyRate", "EstimatedCityRate", "EstimatedSpecialRate", "RiskLevel");
	var $iConvertFields = array("ZipCode" => "postal_code", "EstimatedCombinedRate" => "tax_rate");

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
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
				$requiredFields = array("tax_rate");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$errorMessage = "";
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							if (in_array($thisName, $this->iIgnoreFields)) {
								$fieldNames[] = $thisName;
								continue;
							}
							if (array_key_exists($thisName, $this->iConvertFields)) {
								$thisName = $this->iConvertFields[$thisName];
							}
							$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
						}
						$invalidFields = "";
						foreach ($fieldNames as $fieldName) {
							if (!in_array($fieldName, $allValidFields) && !in_array($fieldName, $this->iIgnoreFields)) {
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
							if (in_array($thisFieldName, $this->iIgnoreFields)) {
								continue;
							}
							$fieldData[$thisFieldName] = trim($thisData);
							$dataFound = true;
						}
						if ($dataFound) {
							$importRecords[] = $fieldData;
						}
					}
					$count++;
				}
				fclose($openFile);
				foreach ($importRecords as $index => $thisRecord) {
					foreach ($requiredFields as $thisField) {
						if (empty($thisRecord[$thisField])) {
							$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
						}
					}
					if (empty($thisRecord['state']) && empty($thisRecord['postal_code'])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . "State or Postal Code";
					}
					if (!is_numeric($thisRecord['tax_rate'])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . "Tax rate must be numeric";
					}
				}
				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'state_tax_rates',?,now(),?,?)",
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
				$deleteCount = 0;
                $skipCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
                    if ($thisRecord['tax_rate'] < .4) {
	                    $thisRecord['tax_rate'] = $thisRecord['tax_rate'] * 100;
                    }
					$postalCodeRate = (!empty($thisRecord['postal_code']));
					if ($postalCodeRate) {
						$taxRateId = getFieldFromId("postal_code_tax_rate_id", "postal_code_tax_rates", "postal_code", $thisRecord['postal_code'], "product_category_id is null and product_department_id is null and country_id = 1000");
					} else {
						$taxRateId = getFieldFromId("state_tax_rate_id", "state_tax_rates", "state", $thisRecord['state'], "product_category_id is null and product_department_id is null and country_id = 1000");
					}
					if ($thisRecord['tax_rate'] == 0) {
						if (!empty($taxRateId)) {
							if ($postalCodeRate) {
								executeQuery("delete from postal_code_tax_rates where postal_code_tax_rate_id = ?", $taxRateId);
							} else {
								executeQuery("delete from state_tax_rates where state_tax_rate_id = ?", $taxRateId);
							}
							$deleteCount++;
						} else {
							$skipCount++;
						}
						continue;
					}

					if (empty($taxRateId)) {
						if ($postalCodeRate) {
							$insertSet = executeQuery("insert into postal_code_tax_rates (client_id,postal_code,flat_rate,tax_rate,country_id) values (?,?,0,?,1000)",
								$GLOBALS['gClientId'], $thisRecord['postal_code'], $thisRecord['tax_rate']);
						} else {
							$insertSet = executeQuery("insert into state_tax_rates (client_id,state,flat_rate,tax_rate,country_id) values (?,?,0,?,1000)",
								$GLOBALS['gClientId'], $thisRecord['state'], $thisRecord['tax_rate']);
						}
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$insertCount++;
						$taxRateId = $insertSet['insert_id'];
					} else {
						if ($postalCodeRate) {
							$insertSet = executeQuery("update postal_code_tax_rates set tax_rate = ? where postal_code_tax_rate_id = ?", $thisRecord['tax_rate'], $taxRateId);
						} else {
							$insertSet = executeQuery("update state_tax_rates set tax_rate = ? where state_tax_rate_id = ?", $thisRecord['tax_rate'], $taxRateId);
						}
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$updateCount++;
					}
					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $taxRateId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " tax rates imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " tax rates updated.</p>";
				$returnArray['response'] .= "<p>" . $deleteCount . " tax rates removed.</p>";
				$returnArray['response'] .= "<p>" . $skipCount . " tax rates skipped.</p>";
				ajaxResponse($returnArray);
				break;
            case "clear_tax_rates":
                $GLOBALS['gPrimaryDatabase']->startTransaction();
                $queries = ["delete from state_tax_rates where client_id = ?",
                    "delete from postal_code_tax_rates where client_id = ?",
                    "delete from csv_import_details where csv_import_id in (select csv_import_id from csv_imports where table_name = 'state_tax_rates' and client_id = ?)",
                    "delete from csv_imports where table_name = 'state_tax_rates' and client_id = ?"];
                foreach($queries as $thisQuery) {
                    $deleteSet = executeQuery($thisQuery, $GLOBALS['gClientId']);
                    if (!empty($deleteSet['sql_error'])) {
                        $returnArray['error_message'] = $deleteSet['sql_error'];
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        ajaxResponse($returnArray);
                        break;
                    }
                }
                $GLOBALS['gPrimaryDatabase']->commitTransaction();
                $returnArray['info_message'] = "All tax rates successfully cleared.";
                ajaxResponse($returnArray);
                break;
        }
	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <p><strong>IMPORTANT:</strong> Before running this import, make sure of which states you need to collect sales tax for.
                <em>Many retailers only need to collect tax in their home state.</em>
                In most states, at least $100,000 of annual sales to customers in that state are required to establish a sales tax nexus.
                See <a target="_blank" href="https://help.coreware.com/support/solutions/articles/73000572699-sales-tax-by-state-economic-nexus-laws">this article</a> for details.</p>

            <p>To clear all existing tax rates and start over, click below.</p>
            <div class="form-line">
                <button tabindex="10" id="_clear_tax_rates">Remove all existing tax rates</button>
            </div>

            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>

            <p>State tax tables can be downloaded <a href='https://www.avalara.com/taxrates/en/download-tax-tables.html'>here</a>. These downloads can be imported without change on this page.</p>

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

                <div id="import_error"></div>

                <div class="form-line">
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
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name in ('postal_code_tax_rates','state_tax_rates') and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
            $(document).on("click", "#_clear_tax_rates", function () {
                $('#_confirm_clear_tax_rates_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 400,
                    title: 'Clear All Tax Rates',
                    buttons:
                        [
                            {
                                text: "Yes",
                                click: function () {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clear_tax_rates", function (returnArray) {
                                    });
                                    $("#_confirm_clear_tax_rates_dialog").dialog('close');
                                },
                            },
                            {
                                text: "Cancel",
                                click: function () {
                                    $("#_confirm_clear_tax_rates_dialog").dialog('close');
                                }
                            }
                        ]
                });
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
        <div id="_confirm_clear_tax_rates_dialog" class="dialog-box">
            All existing tax rates will be deleted. <strong>This cannot be undone.</strong> Are you sure?
        </div> <!-- _confirm_clear_tax_rates_dialog -->

        <?php
	}
}

$pageObject = new DesignationCsvImportPage();
$pageObject->displayPage();
