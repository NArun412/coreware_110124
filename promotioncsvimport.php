<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "PROMOTIONCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class PromotionCsvImportPage extends Page {


	private $iErrorMessages = array();
    private $iValidFields = array("promotion_id", "promotion_code", "start_date", "expiration_date", "description", "detailed_description",
        "publish_start_date","publish_end_date","event_start_date","event_end_date","last_ship_date","minimum_amount","requires_user",
        "discount_amount","discount_percent","maximum_usages","maximum_per_email",
        "duplicate_from_promotion_code");
    private $iRequiredFields = array("promotion_id|promotion_code", "start_date", "description|duplicate_from_promotion_code");
    private $iSubTables = array('promotion_banners',
        'promotion_files',
        'promotion_group_links',
        'promotion_purchased_product_categories',
        'promotion_purchased_product_category_groups',
        'promotion_purchased_product_departments',
        'promotion_purchased_product_manufacturers',
        'promotion_purchased_product_tags',
        'promotion_purchased_product_types',
        'promotion_purchased_products',
        'promotion_purchased_sets',
        'promotion_rewards_excluded_product_categories',
        'promotion_rewards_excluded_product_category_groups',
        'promotion_rewards_excluded_product_departments',
        'promotion_rewards_excluded_product_manufacturers',
        'promotion_rewards_excluded_product_tags',
        'promotion_rewards_excluded_product_types',
        'promotion_rewards_excluded_products',
        'promotion_rewards_excluded_sets',
        'promotion_rewards_product_categories',
        'promotion_rewards_product_category_groups',
        'promotion_rewards_product_departments',
        'promotion_rewards_product_manufacturers',
        'promotion_rewards_product_tags',
        'promotion_rewards_product_types',
        'promotion_rewards_products',
        'promotion_rewards_sets',
        'promotion_rewards_shipping_charges',
        'promotion_terms_contact_types',
        'promotion_terms_countries',
        'promotion_terms_excluded_product_categories',
        'promotion_terms_excluded_product_category_groups',
        'promotion_terms_excluded_product_departments',
        'promotion_terms_excluded_product_manufacturers',
        'promotion_terms_excluded_product_tags',
        'promotion_terms_excluded_product_types',
        'promotion_terms_excluded_products',
        'promotion_terms_excluded_sets',
        'promotion_terms_product_categories',
        'promotion_terms_product_category_groups',
        'promotion_terms_product_departments',
        'promotion_terms_product_manufacturers',
        'promotion_terms_product_tags',
        'promotion_terms_product_types',
        'promotion_terms_products',
        'promotion_terms_sets',
        'promotion_terms_user_types');
    private $iCancelForeignKeyTables = array('order_promotions','recurring_payments');
    private $iUpdateForeignKeyTables = array('shopping_carts');
    private $iBoolValues = array("1"=>1, "true"=>1,"yes"=>1, "0"=>0,"false"=>0,"no"=>0);

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "promotions", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to promotions";
					ajaxResponse($returnArray);
					break;
				}
                foreach($this->iCancelForeignKeyTables as $thisTable) {
                    $resultSet = executeQuery("select * from " . $thisTable . " where promotion_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                    if(getNextRow($resultSet)) {
                        $returnArray['error_message'] = "Unable to remove import due to use of or changes to promotions: " . $thisTable;
                        ajaxResponse($returnArray);
                        break;
                    }
                }

				$GLOBALS['gPrimaryDatabase']->startTransaction();

                foreach ($this->iUpdateForeignKeyTables as $thisTable) {
                    $updateSet = executeQuery("update " . $thisTable . " set promotion_id = null where promotion_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                    $this->checkSqlError($updateSet, $returnArray, "Unable to remove import due to use of or changes to promotions: " . $thisTable);
                }

                foreach ($this->iSubTables as $thisTable) {
                    $deleteSet = executeQuery("delete from " . $thisTable . " where promotion_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                    $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to promotions: " . $thisTable);
                }

				$deleteSet = executeQuery("delete from promotions where promotion_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to promotions");

                $deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray);

                $deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray);

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
				$numericFields = array("minimum_amount","discount_amount","discount_percent","maximum_usages","maximum_per_email");
                $dateTimeFields = array("start_date", "expiration_date", "publish_start_date","publish_end_date","event_start_date","event_end_date","last_ship_date");
                $boolFields = array("requires_user");

				$fieldNames = array();
				$importRecords = array();
                $existingPromotions = array();
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
				foreach ($importRecords as $index => $thisRecord) {
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
                    foreach ($dateTimeFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName])) {
                            $timeStamp = strtotime($thisRecord[$fieldName]);
                            if(!$timeStamp) {
                                $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a valid date or time: " . $thisRecord[$fieldName]);
                            } else {
                                $importRecords[$index][$fieldName] = date("Y-m-d H:i:s", $timeStamp);
                            }
                        }
                    }
                    foreach ($boolFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName])) {
                            if (!array_key_exists(strtolower($thisRecord[$fieldName]), $this->iBoolValues)) {
                                $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a true/false value: " . $thisRecord[$fieldName]);
                            } else {
                                $importRecords[$index][$fieldName] = $this->iBoolValues[$thisRecord[$fieldName]];
                            }
                        }
                    }

                    if (!empty($thisRecord['duplicate_from_promotion_code'])) {
						if (!array_key_exists($thisRecord['duplicate_from_promotion_code'], $existingPromotions)) {
                            $existingPromotions[$thisRecord['duplicate_from_promotion_code']] = "";
						}
					}
				}
				foreach ($existingPromotions as $thisCode => $promotionId) {
                    $promotionId = getFieldFromId("promotion_id", "promotions", "promotion_code", makeCode($thisCode));
					if (empty($promotionId)) {
                        $promotionId = getFieldFromId("promotion_id", "promotions", "description", $thisCode);
					}
					if (empty($promotionId)) {
						$this->addErrorMessage("Invalid Promotion to duplicate: " . $thisCode);
					} else {
						$existingPromotions[$thisCode] = $promotionId;
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

                $resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'promotions',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				$skippedCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
                    $promotionId = getFieldFromId("promotion_id", "promotions", "promotion_id", $thisRecord['promotion_id']);
                    $promotionId = $promotionId ?: getFieldFromId("promotion_id", "promotions", "promotion_code", $thisRecord['promotion_code']);
                    $originalPromotionId = $existingPromotions[$thisRecord['duplicate_from_promotion_code']];
                    $nameValues = $thisRecord;
                    unset($nameValues['promotion_id']);
                    unset($nameValues['duplicate_from_promotion_code']);
                    $nameValues['promotion_code'] = makeCode($thisRecord['promotion_code']);

					if (!empty($originalPromotionId)) {
                        if(empty($promotionId)) {
                            $resultSet = executeQuery("select * from promotions where promotion_id = ?", $originalPromotionId);
                            $row = getNextRow($resultSet);
                            $queryString = "";
                            $row = array_merge($row, $nameValues, array("promotion_id" => ""));
                            foreach ($row as $fieldData) {
                                $queryString .= (empty($queryString) ? "" : ",") . "?";
                            }
                            $insertSet = executeQuery("insert into promotions values (" . $queryString . ")", $row);
                            $this->checkSqlError($insertSet, $returnArray);
                            $promotionId = $insertSet['insert_id'];
                            $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $promotionId);
                            $this->checkSqlError($insertSet, $returnArray);
                            $insertCount++;
                        } else {
                            $promotionsTable = new DataTable("promotions");
                            $promotionsTable->setSaveOnlyPresent(true);
                            if(!$promotionsTable->saveRecord(array("name_values"=>$nameValues, "primary_id"=>$promotionId))) {
                                $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $promotionsTable->getErrorMessage());
                                ajaxResponse($returnArray);
                                break;
                            }
                            foreach($this->iSubTables as $thisTable) {
                                $deleteSet = executeQuery("delete from " . $thisTable . " where promotion_id = ?", $promotionId);
                                $this->checkSqlError($deleteSet,$returnArray);
                            }
                            $updateCount++;
                        }
                        foreach ($this->iSubTables as $thisTable) {
                            $resultSet = executeQuery("select * from " . $thisTable . " where promotion_id = ?", $originalPromotionId);
                            while ($row = getNextRow($resultSet)) {
                                $queryString = "";
                                foreach ($row as $fieldName => $fieldData) {
                                    if (empty($queryString)) {
                                        $row[$fieldName] = "";
                                    }
                                    $queryString .= (empty($queryString) ? "" : ",") . "?";
                                }
                                $row['promotion_id'] = $promotionId;
                                $insertSet = executeQuery("insert into " . $thisTable . " values (" . $queryString . ")", $row);
                                $this->checkSqlError($insertSet, $returnArray);
                            }
                        }
					} else {
                        // Add or update promotion row without subtables
                        $promotionsTable = new DataTable("promotions");
                        $promotionsTable->setSaveOnlyPresent(true);
                        if(!$insertId = $promotionsTable->saveRecord(array("name_values"=>$nameValues, "primary_id"=>$promotionId))) {
                            $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $promotionsTable->getErrorMessage());
                            ajaxResponse($returnArray);
                            break;
                        }
                        if(empty($promotionId)) {
                            $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $insertId);
                            $this->checkSqlError($insertSet, $returnArray);
                            $insertCount++;
                        } else {
                            $updateCount++;
                        }
					}
				}

                $GLOBALS['gPrimaryDatabase']->commitTransaction();

                $returnArray['response'] = "<p>" . $insertCount . " promotions imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " promotions updated.</p>";
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
            if($GLOBALS['gUserRow']['superuser_flag']) {
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
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>
            <p><strong>Required Fields: </strong><?= str_replace("|", " OR ", implode(", ", $this->iRequiredFields)) ?></p>

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
			$resultSet = executeQuery("select * from csv_imports where table_name = 'promotions' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
            This will result in these promotions being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new PromotionCsvImportPage();
$pageObject->displayPage();
