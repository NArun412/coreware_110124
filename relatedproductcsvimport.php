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

$GLOBALS['gPageCode'] = "RELATEDPRODUCTSCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class RelatedProductCsvImportPage extends Page {

	var $iValidFields = array("upc_code", "related_upc_code", "related_product_type_code");
	var $iRequiredFields = array("upc_code", "related_upc_code");

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "related_products", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: change log";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from related_products where related_product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: related_products";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products";
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
						foreach ($fieldNames as $index => $fieldName) {
							if (!in_array($fieldName, $this->iValidFields)) {
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . " <a class='valid-fields-trigger'>View valid fields</a></p>";
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
				$relatedProductTypeCodes = array();
				$processCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$processCount++;
					$upcCode = ProductCatalog::makeValidUPC(trim($thisRecord['upc_code'], " \t'"));
					$relatedUpcCode = ProductCatalog::makeValidUPC(trim($thisRecord['related_upc_code'], " \t'"));
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
                    $associatedProductId = getFieldFromId("product_id", "product_data", "upc_code", $relatedUpcCode);
					if (empty($productId)) {
						$errorMessage .= "<p>Line " . ($index + 2) . ": product does not exist</p>";
					}
					if (empty($associatedProductId)) {
						$errorMessage .= "<p>Line " . ($index + 2) . ": related product does not exist</p>";
					}
                    $importRecords[$index]['product_id'] = $productId;
                    $importRecords[$index]['associated_product_id'] = $associatedProductId;

					if (!empty($thisRecord['related_product_type_code'])) {
						if (!array_key_exists($thisRecord['related_product_type_code'], $relatedProductTypeCodes)) {
							$relatedProductTypeCodes[$thisRecord['related_product_type_code']] = "";
						}
					}
				}
				foreach ($relatedProductTypeCodes as $thisCode => $unitId) {
					$relatedProductTypeId = getFieldFromId("related_product_type_id", "related_product_types", "related_product_type_code", makeCode($thisCode));
					if (empty($relatedProductTypeId)) {
						$relatedProductTypeId = getFieldFromId("unit_id", "units", "description", $thisCode);
					}
					if (empty($relatedProductTypeId)) {
						$errorMessage .= "<p>Invalid Related Product Type: " . $thisCode . "</p>";
					} else {
						$relatedProductTypeCodes[$thisCode] = $relatedProductTypeId;
					}
				}

				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'related_products',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $resultSet['sql_error'] : "");
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$lineNumber = 0;
				$insertCount = 0;
				$updateCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$lineNumber++;
//					$upcCode = ProductCatalog::makeValidUPC(trim($thisRecord['upc_code'], " \t'"));
//					$relatedUpcCode = ProductCatalog::makeValidUPC(trim($thisRecord['related_upc_code'], " \t'"));
//					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
//					$relatedProductId = getFieldFromId("product_id", "product_data", "upc_code", $relatedUpcCode);
                    $productId = $thisRecord['product_id'];
                    $associatedProductId = $thisRecord['associated_product_id'];

					$relatedProductTypeId = $relatedProductTypeCodes[$thisRecord['related_product_type_code']];
					$relatedProductId = getFieldFromId("related_product_id", "related_products", "product_id", $productId,
						"associated_product_id = ? and related_product_type_id <=> ?", $associatedProductId, $relatedProductTypeId);
					if (empty($relatedProductId)) {
						$insertSet = executeQuery("insert into related_products (product_id,associated_product_id,related_product_type_id) values (?,?,?)",
                            $productId, $associatedProductId, $relatedProductTypeId);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$relatedProductId = $insertSet['insert_id'];
						$insertCount++;
					}

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $relatedProductId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " Related Products imported.</p>";
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
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <span class="help-label">Required Fields: Product Code/UPC and Description.</span>
                    <a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
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
			$resultSet = executeQuery("select * from csv_imports where table_name = 'related_products' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = $minutesSince < 120;
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
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
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

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these relationships being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->

        <div id="_valid_fields_dialog" title="Valid Fields">
            <ul>
                <li><?= implode("</li><li>", $this->iValidFields) ?></li>
            </ul>
        </div>
		<?php
	}
}

$pageObject = new RelatedProductCsvImportPage();
$pageObject->displayPage();
