<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "EVENTTYPECSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class EventTypeCSVImportPage extends Page {

    private $iErrorMessages = array();
    private $iValidFields = array("event_type_code", "description", "detailed_description", "excerpt", "event_type_requirements", "event_type_tags", "change_days",
        "cancellation_days", "certification_type", "price","link_name","attendees","any_requirement");
    private $iValidCustomFields = array();
    private $iShowDetailedErrors = false;

    function setup() {
        $resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'EVENT_TYPES') and inactive = 0 and client_id = ? order by custom_field_code", $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            $this->iValidCustomFields[] = "custom_field-" . strtolower($row['custom_field_code']);
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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "event_types", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to event types";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from event_type_tag_links where event_type_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event types (tags)");

				$deleteSet = executeQuery("delete from event_type_requirements where event_type_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event types (requirements)");

				$deleteSet = executeQuery("delete from certification_type_requirements where event_type_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event types (certifications)");

				$deleteSet = executeQuery("delete from event_types where event_type_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event types");

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event types");

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event types");

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

				$allValidFields = array_merge($this->iValidFields, $this->iValidCustomFields);
				$requiredFields = array("event_type_code|description");
				$numericFields = array("change_days", "cancellation_days", "price","attendees");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$this->iErrorMessages = array();

                $customFields = array();
                $resultSet = executeQuery("select custom_fields.*,(select control_value from custom_field_controls where custom_field_id = custom_fields.custom_field_id and control_name = 'data_type' limit 1) data_type " .
                    " from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'EVENT_TYPES') and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $customFields[$row['custom_field_code']] = $row;
                }

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
							if(in_array($thisFieldName,$numericFields)) {
								$fieldData[$thisFieldName] = str_replace(["$",","],"",trim($thisData));
							} else {
								$fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
							}
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);

				# check for required fields and invalid data
				$certifications = array();
                $eventTypeCodes = array();
                $linkNames = array();
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
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
						}
					}
					if (!empty($thisRecord['event_type_requirements']) || !empty($thisRecord['certification_type'])) {
						$certificationSet = array_filter(array_map("trim", explode("|", $thisRecord['event_type_requirements'])));
						if (!empty(trim($thisRecord['certification_type']))) {
							$certificationSet[] = $thisRecord['certification_type'];
						}
						foreach ($certificationSet as $thisCertification) {
                            if (!array_key_exists($thisCertification, $certifications)) {
                                $certifications[$thisCertification] = "";
                            }
                        }
					}
                    $eventTypeCode = makeCode($thisRecord['event_type_code'] ?: $thisRecord['description']);
                    if(array_key_exists($eventTypeCode, $eventTypeCodes)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": event_type_code is a duplicate: " . $eventTypeCode . " (Line " . $eventTypeCodes[$eventTypeCode] .")");
                    } else {
                        $eventTypeCodes[$eventTypeCode] = $index + 2;
                    }

                    $linkName = makeCode($thisRecord['link_name'] ?: $thisRecord['description'],array("use_dash" => true, "lowercase" => true));
                    if(!empty($linkName) && array_key_exists($linkName, $linkNames)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": link_name is a duplicate: " . $linkName . " (Line " . $linkNames[$linkName] .")");
                    } else {
                        $linkNames[$linkName] = $index + 2;
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

				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'event_types',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				$this->checkSqlError($resultSet, $returnArray);
				$csvImportId = $resultSet['insert_id'];

				foreach ($certifications as $thisCertification => $certificationTypeId) {
					$certificationTypeId = getFieldFromId("certification_type_id", "certification_types", "certification_type_code", makeCode($thisCertification));
					if (empty($certificationTypeId)) {
						$certificationTypeId = getFieldFromId("certification_type_id", "certification_types", "description", $thisCertification);
					}
					if (empty($certificationTypeId)) {
						$insertSet = executeQuery("insert into certification_types (client_id, certification_type_code, description) values (?,?,?)",
							$GLOBALS['gClientId'], makeCode($thisCertification), $thisCertification);
						$this->checkSqlError($insertSet, $returnArray);
						$certificationTypeId = $insertSet['insert_id'];
					}
					$certifications[$thisCertification] = $certificationTypeId;
				}

				$insertCount = 0;
				$updateCount = 0;
				$this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
				foreach ($importRecords as $index => $thisRecord) {
					$eventTypeCode = makeCode($thisRecord['event_type_code'] ?: $thisRecord['description']);
					$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_code", $eventTypeCode);
                    $linkName = makeCode($thisRecord['link_name'] ?: $thisRecord['description'],array("use_dash" => true, "lowercase" => true));

					if (empty($eventTypeId)) {
						$resultSet = executeQuery("insert into event_types (client_id, event_type_code, description, detailed_description, excerpt, change_days, ".
                            "cancellation_days,price,attendees,link_name,any_requirement) " .
							" values (?,?,?,?,?,?, ?,?,?,?,?)", $GLOBALS['gClientId'], $eventTypeCode, $thisRecord['description'], $thisRecord['detailed_description'], $thisRecord['excerpt'],
							$thisRecord['change_days'], $thisRecord['cancellation_days'], $thisRecord['price'],$thisRecord['attendees'],$linkName,
                            (empty($thisRecord['any_requirement'])? 0 : 1));
						$this->checkSqlError($resultSet, $returnArray);
						$eventTypeId = $resultSet['insert_id'];
						$insertCount++;
						$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $eventTypeId);
						$this->checkSqlError($insertSet, $returnArray);
					} else {
                        $nameValues = array_filter($thisRecord, function ($value) {
                            return isset($value) && strlen($value) > 0;
                        });
                        unset($nameValues['event_type_code']);
                        $existingLinkName = getFieldFromId("link_name", "event_types", "event_type_id", $eventTypeId);
                        if(empty($existingLinkName) || !empty($thisRecord['link_name'])) {
                            $nameValues['link_name'] = $linkName;
                        }
                        if(array_key_exists('any_requirement',$thisRecord)) {
                            $nameValues['any_requirement'] = (empty($thisRecord['any_requirement']) ? 0 : 1);
                        }
                        $dataTable = new DataTable('event_types');
                        $dataTable->setPrimaryId($eventTypeId);
                        if(!$dataTable->saveRecord(array('primary_id'=>$eventTypeId, "name_values"=>$nameValues))) {
                            if($this->iShowDetailedErrors) {
                                $returnArray['error_message'] = $returnArray['import_error'] = $dataTable->getErrorMessage();
                            } else {
                                $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $dataTable->getErrorMessage());
                            }
                            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                            ajaxResponse($returnArray);
                        }
						$updateCount++;
					}
					# event type tags
                    if (array_key_exists('event_type_tags', $thisRecord)) {
                        $tagIds = array();
                        foreach (array_filter(array_map("trim", explode("|", $thisRecord['event_type_tags']))) as $thisTag) {
                            $tagId = getFieldFromId("event_type_tag_id", "event_type_tags", "event_type_tag_code", makeCode($thisTag));
                            if (empty($tagId)) {
                                $tagId = getFieldFromId("event_type_tag_id", "event_type_tags", "description", $thisTag);
                            }
                            if (empty($tagId)) {
                                $insertSet = executeQuery("insert into event_type_tags (client_id, event_type_tag_code, description) values (?,?,?)",
                                    $GLOBALS['gClientId'], makeCode($thisTag), $thisTag);
                                $this->checkSqlError($resultSet, $returnArray);
                                $tagId = $insertSet['insert_id'];
                            }
                            $eventTypeTagLinkId = getFieldFromId("event_type_tag_link_id", "event_type_tag_links", "event_type_id", $eventTypeId, "event_type_tag_id = ?", $tagId);
                            if (empty($eventTypeTagLinkId)) {
                                $insertSet = executeQuery("insert into event_type_tag_links (event_type_id, event_type_tag_id) values (?,?)",
                                    $eventTypeId, $tagId);
                                $this->checkSqlError($insertSet, $returnArray);
                            }
                            $tagIds[] = $tagId;
                        }
                        executeQuery("delete from event_type_tag_links where event_type_id = ?"
                            . (empty($tagIds) ? "" : "  and event_type_tag_id not in (" . implode(",", $tagIds) . ")"), $eventTypeId);
                    }

					# event type certifications
                    if (array_key_exists('event_type_requirements', $thisRecord)) {
                        $certificationTypeIds = array();
                        foreach (array_map("trim", explode("|", $thisRecord['event_type_requirements'])) as $thisCertification) {
                            if (empty($thisCertification)) {
                                continue;
                            }
                            $certificationTypeId = $certifications[$thisCertification];
                            $eventTypeRequirementId = getFieldFromId("event_type_requirement_id", "event_type_requirements", "event_type_id",
                                $eventTypeId, "certification_type_id = ?", $certificationTypeId);
                            if (empty($eventTypeRequirementId)) {
                                $insertSet = executeQuery("insert into event_type_requirements (event_type_id, certification_type_id) values (?,?)",
                                    $eventTypeId, $certificationTypeId);
                                $this->checkSqlError($insertSet, $returnArray);
                                $certificationTypeIds[] = $certificationTypeId;
                            }
                        }
                        executeQuery("delete from event_type_requirements where event_type_id = ?" .
                            (empty($certificationTypeIds) ? "" : " and certification_type_id not in (" . implode(",", $certificationTypeIds) . ")"), $eventTypeId);
                    }

					# certification type requirements
					if (!empty($thisRecord['certification_type'])) {
						$certificationTypeId = $certifications[trim($thisRecord['certification_type'])];
						$resultSet = executeQuery("insert ignore into certification_type_requirements (certification_type_id, event_type_id) values (?,?)",
							$certificationTypeId, $eventTypeId);
						$this->checkSqlError($resultSet, $returnArray);
					}

                    # event type custom fields
                    foreach ($customFields as $customFieldCode => $customFieldRow) {
                        if(array_key_exists('custom_field-' . strtolower($customFieldCode), $thisRecord)) {
                            $value = $thisRecord['custom_field-' . strtolower($customFieldCode)];
                            if($customFieldRow['data_type'] == "tinyint") {
                                $value = !empty($value) && !in_array(strtolower($value),['false','no']);
                            }
                            CustomField::setCustomFieldData($eventTypeId, $customFieldCode, $value, "EVENT_TYPES");
                        }
                    }

                }

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['response'] = "<p>" . $insertCount . " event types imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " existing event types updated.</p>";
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
                $returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'] . ":" . jsonEncode($resultSet['parameters']);
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
                    <span class="help-label">Required Field: Description.</span>
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
                <th>Undo</th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'event_types' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
        <ul>
            <li><?= implode("</li><li>", $this->iValidFields) ?></li>
        </ul>

        <div class="accordion">
            <?php if (!empty($this->iValidCustomFields)) { ?>
                <h3>Valid Custom Fields</h3>
                <!-- Has an extra wrapper div since columns CSS property doesn't work properly with accordion content's max height -->
                <div>
                    <ul>
                        <li><?= implode("</li><li>", $this->iValidCustomFields) ?></li>
                    </ul>
                </div>
            <?php } ?>
        </div>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these event types being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new EventTypeCSVImportPage();
$pageObject->displayPage();
