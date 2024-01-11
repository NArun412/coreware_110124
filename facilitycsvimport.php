<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "FACILITYCSVIMPORT";
require_once "shared/startup.inc";

class FacilityCsvImportPage extends Page {

    private $iErrorMessages = array();
    private $iValidFields = array("description","detailed_description","facility_type_code","location_code","event_type_code","link_url","link_name","cost_per_hour","cost_per_day","cost_tbd",
        "maximum_capacity","square_footage","reservation_start","sort_order","uses_requirements","requires_approval","internal_use_only","notification_email_addresses","availability","daily_availability");

    private $iWeekdays = array("sun"=>0,"sunday"=>0,0=>0,
        "mon"=>1,"monday"=>1,1=>1,
        "tue"=>2,"tuesday"=>2,2=>2,
        "wed"=>3,"wednesday"=>3,3=>3,
        "thu"=>4,"thursday"=>4,4=>4,
        "fri"=>5,"friday"=>5,5=>5,
        "sat"=>6,"saturday"=>6,6=>6);

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
                $changeLogId = getFieldFromId("log_id", "change_log", "table_name", "facilities", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                if (!empty($changeLogId)) {
                    $returnArray['error_message'] = "Unable to remove import due to use of or changes to facilities";
                    ajaxResponse($returnArray);
                    break;
                }
                $GLOBALS['gPrimaryDatabase']->startTransaction();

                $deleteSet = executeQuery("delete from facility_notifications where facility_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to facilities");

                $deleteSet = executeQuery("delete from facility_availability where facility_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to facilities");

                $deleteSet = executeQuery("delete from facilities where facility_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to facilities");

                $deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to facilities");

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

                $numericFields = array("cost_per_hour","cost_per_day","maximum_capacity","square_footage","sort_order");
                $booleanFields = array("cost_tbd","uses_requirements","requires_approval","internal_use_only");
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
                            $this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
                            $this->addErrorMessage("Valid fields are: " . implode(", ", $this->iValidFields));
                        }
                    } else {
                        $fieldData = array();
                        foreach ($csvData as $index => $thisData) {
                            $thisFieldName = $fieldNames[$index];
                            if(in_array($thisFieldName, $booleanFields)) {
                                $thisData = (!empty($thisData) && !in_array(strtolower($thisData), ["no","false"]));
                            }
                            $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
                        }
                        $importRecords[] = $fieldData;
                    }
                    $count++;
                }
                fclose($openFile);

                # build lookup arrays
                $facilityTypes = array();
                $resultSet = executeQuery("select * from facility_types where client_id = ?", $GLOBALS['gClientId']);
                while($row = getNextRow($resultSet)) {
                    $facilityTypes[strtolower($row['facility_type_code'])] = $row['facility_type_id'];
                    $facilityTypes[strtolower($row['description'])] = $row['facility_type_id'];
                }

                $locations = array();
                $resultSet = executeQuery("select * from locations where client_id = ?", $GLOBALS['gClientId']);
                while($row = getNextRow($resultSet)) {
                    $locations[strtolower($row['location_code'])] = $row['location_id'];
                    $locations[strtolower($row['description'])] = $row['location_id'];
                }

                $eventTypes = array();
                $resultSet = executeQuery("select * from event_types where client_id = ?", $GLOBALS['gClientId']);
                while($row = getNextRow($resultSet)) {
                    $eventTypes[strtolower($row['event_type_code'])] = $row['event_type_id'];
                    $eventTypes[strtolower($row['description'])] = $row['event_type_id'];
                }

                # Validate data
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
                    foreach ($numericFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
                        }
                    }
                    # look up foreign keys
                    if(!empty($thisRecord['facility_type_code']) && !array_key_exists(strtolower($thisRecord['facility_type_code']), $facilityTypes)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": facility type does not exist: " . $thisRecord['facility_type_code']);
                    }
                    if(!empty($thisRecord['location_code']) && !array_key_exists(strtolower($thisRecord['location_code']), $locations)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": location does not exist: " . $thisRecord['location_code']);
                    }
                    if(!empty($thisRecord['event_type_code']) && !array_key_exists(strtolower($thisRecord['event_type_code']), $eventTypes)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": event type does not exist: " . $thisRecord['event_type_code']);
                    }
                    # extra validation
                    if(!empty($thisRecord['notification_email_addresses'])) {
                        foreach(explode("|", $thisRecord['notification_email_addresses']) as $emailAddress) {
                            if(!preg_match(VALID_EMAIL_REGEX, $emailAddress)) {
                                $this->addErrorMessage("Line " . ($index + 2) . ": invalid email address: " . $thisRecord['emailAddress']);
                            }
                        }
                    }
                    if(!empty($thisRecord['availability'])) {
                        $availability = $this->parseAvailability($thisRecord['availability']);
                        if (empty($availability)) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": availability format is invalid: " . $thisRecord['availability']);
                        }
                    }
                    if(!empty($thisRecord['daily_availability'])) {
                        [$startTime,$endTime] = explode("-", $thisRecord['daily_availability']);
                        $startTime = strtotime($startTime);
                        $endTime = strtotime($endTime);
                        if(empty($startTime) || empty($endTime)) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": daily_availability format is invalid: " . $thisRecord['daily_availability']);
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
                $updateCount = 0;
                $insertCount = 0;

                $resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'facilities',?,now(),?,?)",
                    $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
                $this->checkSqlError($resultSet,$returnArray);

                $csvImportId = $resultSet['insert_id'];
                foreach ($importRecords as $index => $thisRecord) {

                    $facilityId = getFieldFromId("facility_id","facilities","description",$thisRecord['description']);
                    $thisRecord['facility_type_id'] = $facilityTypes[strtolower($thisRecord['facility_type_code'])];
                    unset($thisRecord['facility_type_code']);
                    $thisRecord['location_id'] = $locations[strtolower($thisRecord['location_code'])];
                    unset($thisRecord['location_code']);
                    $thisRecord['event_type_id'] = $eventTypes[strtolower($thisRecord['event_type_code'])];
                    unset($thisRecord['event_type_code']);
                    $notificationEmailAddresses = explode("|",$thisRecord['notification_email_addresses']);
                    unset($thisRecord['notification_email_addresses']);
                    $availability = $this->parseAvailability($thisRecord['availability']);
                    unset($thisRecord['availability']);
                    if(empty($availability)) {
                        [$startTime,$endTime] = explode("-", $thisRecord['daily_availability']);
                        $startHour = date("H", strtotime($startTime));
                        $endHour = date("H", strtotime($endTime));
                        for($weekday = 0;$weekday <= 7; $weekday++) {
                            for ($hour = $startHour; $hour < $endHour; $hour++) {
                                if ($hour < 0 || $hour > 23) {
                                    continue;
                                }
                                $availability[] = array("weekday" => $weekday, "hour" => $hour);
                            }
                        }
                    }
                    unset($thisRecord['daily_availability']);
                    $thisRecord['cost_per_day'] = $thisRecord['cost_per_day'] ?: 0.0;
                    $thisRecord['cost_per_hour'] = $thisRecord['cost_per_hour'] ?: 0.0;

                    $dataTable = new DataTable("facilities");
                    if(empty($facilityId)) {
                        $facilityId = $dataTable->saveRecord(array("name_values"=>$thisRecord));
                        $insertCount++;
                    } else {
                        $facilityId = $dataTable->saveRecord(array("primary_id"=>$facilityId,"name_values"=>$thisRecord));
                        $updateCount++;
                    }
                    if(empty($facilityId)) {
                        $returnArray['error_message'] = $dataTable->getErrorMessage();
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        ajaxResponse($returnArray);
                    }
                    foreach($notificationEmailAddresses as $emailAddress) {
                        if(!empty(trim($emailAddress)) && empty(getRowFromId("facility_notifications", "facility_id", $facilityId,"email_address = ?", $emailAddress))) {
                            $insertSet = executeQuery("insert into facility_notifications (facility_id, email_address) values (?,?)", $facilityId, $emailAddress);
                            $this->checkSqlError($insertSet, $returnArray);
                        }
                    }
                    foreach($availability as $thisAvailability) {
                        $insertSet = executeQuery("insert into facility_availability (facility_id, weekday, hour) values (?,?,?)", $facilityId, $thisAvailability['weekday'], $thisAvailability['hour']);
                        $this->checkSqlError($insertSet, $returnArray);
                    }

                    $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $facilityId);
                    $this->checkSqlError($insertSet,$returnArray);
                }

                $GLOBALS['gPrimaryDatabase']->commitTransaction();

                $returnArray['response'] .= "<p>" . $updateCount . " existing facilities updated</p>";
                $returnArray['response'] .= "<p>" . $insertCount . " facilities created</p>";
                $returnArray['response'] .= $errorMessage;
                ajaxResponse($returnArray);
                break;
        }

    }

    function parseAvailability($availabilityString) {
        $returnArray = array();
        // format mon:9-17|tue:9-17|wed:9-12
        $availability = explode("|",$availabilityString);
        foreach($availability as $thisAvailability) {
            if (!empty(trim($thisAvailability))) {
                [$day, $hours] = explode(":", $thisAvailability);
                $weekday = $this->iWeekdays[strtolower($day)];
                [$startHour, $endHour] = explode("-", $hours);
                if (!empty($weekday) && is_numeric($startHour) && is_numeric($endHour)) {
                    $endHour = ($endHour < $startHour ? $endHour + 12 : $endHour);
                    for ($hour = $startHour; $hour <= $endHour; $hour++) {
                        if ($hour < 0 || $hour > 23) {
                            continue;
                        }
                        $returnArray[] = array("weekday"=>$weekday,"hour"=>$hour);
                    }
                }
            }
        }
        return $returnArray;
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
                    <span class="help-label">Required Field: Description.</span>
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
            $resultSet = executeQuery("select * from csv_imports where table_name = 'facilities' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
        #import_error { color: rgb(192,0,0); }
        .remove-import { cursor: pointer; }
        <?php
    }

    function hiddenElements() {
        ?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
        <ul>
            <li><?= implode("</li><li>", $this->iValidFields) ?></li>
        </ul>
            <p><strong>notification_email_addresses:</strong> separate with "|", e.g. name@example.com|name2@example.com</p>
            <p><strong>availability:</strong> Format as follows: mon:9-17|tue:9-17|wed:9-12</p>
            <p><strong>daily_availability:</strong> Use if availability is same every day. Can be human-readable, e.g. 8am - 5pm (whole hours only)</p>
        </div>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these facilities being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
        <?php
    }
}

$pageObject = new FacilityCsvImportPage();
$pageObject->displayPage();
