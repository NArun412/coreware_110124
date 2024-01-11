<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "LOCATIONCSVIMPORT";
require_once "shared/startup.inc";

class LocationCsvImportPage extends Page {

    var $iErrorMessages = array();
    private $iValidFields = array("description","location_code","contact_id","business_name","address_1","address_2","city","state","postal_code","country","email_address");
    private $iShowDetailedErrors = false;

    function setup() {
        $this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
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
                $changeLogId = getFieldFromId("log_id", "change_log", "table_name", "locations", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                if (!empty($changeLogId)) {
                    $returnArray['error_message'] = "Unable to remove import due to use of or changes to locations";
                    ajaxResponse($returnArray);
                    break;
                }
                $GLOBALS['gPrimaryDatabase']->startTransaction();

                $deleteSet = executeQuery("delete from location_credentials where location_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to locations");

                $contactIds = array();
                $resultSet = executeQuery("select contact_id from locations where location_id in (select primary_identifier from csv_import_details where csv_import_id = ?)",$csvImportId);
                while ($row = getNextRow($resultSet)) {
                    $contactIds[] = $row['contact_id'];
                }

                $deleteSet = executeQuery("delete from locations where location_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to locations");

                $deleteSet = executeQuery("delete from contacts where contact_id in (" . implode(",",$contactIds) . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to locations");

                $deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to locations");

                $deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray);

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

                $requiredFields = array("description");

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
                            if (!in_array($fieldName, $this->iValidFields)) {
                                $invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
                            }
                        }
                        if (!empty($invalidFields)) {
                            $errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . "</p>";
                            $errorMessage .= "<p>Valid fields are: " . implode(", ", $this->iValidFields) . "</p>";
                        }
                    } else {
                        $fieldData = array();
                        foreach ($csvData as $index => $thisData) {
                            $thisFieldName = $fieldNames[$index];
                            $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
                        }
                        $importRecords[] = $fieldData;
                    }
                    $count++;
                }
                fclose($openFile);

                # Validate data
                $countries = getCountryArray();
                $countryLookup = array();
                foreach($importRecords as $index => $thisRecord) {
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
                    if(!empty($thisRecord['contact_id'])) {
                        $contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
                        if(empty($contactId)) {
                            $this->addErrorMessage("Line " . ($index + 2) . " no contact found for contact_id: " . $thisRecord['contact_id']);
                        }
                    }
                    if(!empty($thisRecord['country'])) {
                        $countryId = $countryLookup($thisRecord['country']);
                        if(empty($countryId)) {
                            foreach($countries as $thisCountry) {
                                if(strtolower($thisRecord['country']) == strtolower($thisCountry['country_code']) ||
                                    strtolower($thisRecord['country']) == strtolower($thisCountry['iso_code']) ||
                                    strtolower($thisRecord['country']) == strtolower($thisCountry['country_name'])) {
                                    $countryId = $thisCountry['country_id'];
                                    break;
                                }
                            }
                        }
                        if(empty($countryId)) {
                            $errorMessage .= "<p>Line " . ($index + 2) . ": unknown country:  " . $thisRecord['country'] . "</p>";
                        } else {
                            $countryLookup[$thisRecord['country']] = $countryId;
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

                $GLOBALS['gPrimaryDatabase']->startTransaction();
                $foundCount = 0;
                $insertCount = 0;

                $resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'locations',?,now(),?,?)",
                    $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
                $this->checkSqlError($resultSet,$returnArray);

                $csvImportId = $resultSet['insert_id'];
                foreach ($importRecords as $index => $thisRecord) {

                    $locationCode = makeCode($thisRecord['location_code']) ?: makeCode($thisRecord['description']);
                    $locationId = getFieldFromId("location_id","locations","location_code",$locationCode);
                    if (!empty($locationId)) {
                        $foundCount++;
                        continue;
                    }
                    if(empty($thisRecord['contact_id'])) {
                        $businessName = $thisRecord['business_name'] ?: $GLOBALS['gClientRow']['business_name'];
                        $countryId = (empty($thisRecord['country']) ? 1000 : $countryLookup[$thisRecord['country']]);
                        $resultSet = executeQuery("insert into contacts (client_id,business_name,address_1,address_2,city,state,postal_code,country_id,email_address,date_created) " .
                            "values (?,?,?,?,?,?,?,?,?,now())",$GLOBALS['gClientId'], $businessName, $thisRecord['address_1'], $thisRecord['address_2'], $thisRecord['city'],
                            $thisRecord['state'], $thisRecord['postal_code'], $countryId, $thisRecord['email_address']);
                        $this->checkSqlError($resultSet, $returnArray);

                        $contactId = $resultSet['insert_id'];
                    } else {
                        $contactId = $thisRecord['contact_id'];
                    }

	                $dataTable = new DataTable("locations");
	                if (!$locationId = $dataTable->saveRecord(array("name_values" => array("location_code" => $locationCode, "description" => $thisRecord['description'], "contact_id" => $contactId, "user_id" => $GLOBALS['gUserId'])))) {
		                $returnArray['error_message'] = $dataTable->getErrorMessage();
		                $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		                ajaxResponse($returnArray);
                        exit;
	                }

                    $insertCount++;
                    $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $locationId);
                    $this->checkSqlError($insertSet,$returnArray);
                }

                $GLOBALS['gPrimaryDatabase']->commitTransaction();

                $returnArray['response'] .= "<p>" . $foundCount . " existing locations found</p>";
                $returnArray['response'] .= "<p>" . $insertCount . " locations created</p>";
                $returnArray['response'] .= $errorMessage;
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

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
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
            </tr>
            <?php
            $resultSet = executeQuery("select * from csv_imports where table_name = 'locations' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
            <li><?= implode("</li><li>", array_merge($this->iValidFields)) ?></li>
        </ul>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these locations being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
        <?php
    }
}

$pageObject = new LocationCsvImportPage();
$pageObject->displayPage();
