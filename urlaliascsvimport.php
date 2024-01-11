<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "URLALIASCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class UrlAliasCsvImportPage extends Page {

	var $iValidFields = array("link_name", "link_url", "domain_name", "permanent_redirect");

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "url_alias", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to url_alias";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from url_alias where url_alias_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to url_alias";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to url_alias";
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
			case "select_url_alias":
				$pageId = $GLOBALS['gAllPageCodes']["URLALIASMAINT"];
				$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "url_alias selected in url_alias Maintenance program";
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
				$requiredFields = array("link_name", "link_url");

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
				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'url_alias',?,now(),?,?)",
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
				$skipCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$linkName = strtolower(trim($thisRecord['link_name'], "/"));
					$linkUrl = strtolower(trim($thisRecord['link_url'], "/"));
					if ($linkName == $linkUrl) {
						$skipCount++;
						continue;
					}
					$urlAliasId = getFieldFromId("url_alias_id", "url_alias", "link_name", $linkName);

					if (empty($urlAliasId)) {
						$insertSet = executeQuery("insert into url_alias (client_id,link_name,link_url,domain_name,permanent_redirect) values (?,?,?,?,?)",
							$GLOBALS['gClientId'], $linkName, $linkUrl, $thisRecord['domain_name'], (empty($thisRecord['permanent_redirect']) ? 0 : 1));
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$insertCount++;
						$urlAliasId = $insertSet['insert_id'];
					} else {
						$insertSet = executeQuery("update url_alias set link_url = ?,domain_name = ?,permanent_redirect = ? where url_alias_id = ? and client_id = ?",
							$linkUrl, $thisRecord['domain_name'], (empty($thisRecord['permanent_redirect']) ? 0 : 1), $urlAliasId, $GLOBALS['gClientId']);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$updateCount++;
					}

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $urlAliasId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " URL aliases imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " URL aliases updated.</p>";
				$returnArray['response'] .= "<p>" . $skipCount . " URL aliases skipped because link name is the same as link URL.</p>";
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
                <th></th>
				<?php if (canAccessPage("URLALIASMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'url_alias' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
					<?php if (canAccessPage("URLALIASMAINT")) { ?>
                        <td><span class='far fa-check-square select-url-alias'></span></td>
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
            $(document).on("click", ".select-url-alias", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_contacts&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
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
            .select-url-alias {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these url_alias being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new UrlAliasCsvImportPage();
$pageObject->displayPage();
