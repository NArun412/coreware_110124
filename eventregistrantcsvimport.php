<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "EVENTREGISTRANTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class EventRegistrantCsvImportPage extends Page {

    var $iErrorMessages = array();
    var $iValidFields = array("event_id", "contact_id", "event", "location_code", "dates", "start_date", "start_time", "first_name", "last_name", "email_address", "order_id",
        "registration_time", "check_in_time", "date_completed", "status", "failed", "certification_type", "expiration_date", "notes","product_code", "certificate_title");
    private $iRequiredFields = array("event_id|event|product_code", "event_id|start_date|dates|product_code", "contact_id|email_address");

    private $iShowDetailedErrors = false;
    private $iProgramLogId = false;

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
                $changeLogId = getFieldFromId("log_id", "change_log", "table_name", "event_registrants", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                if (!empty($changeLogId)) {
                    $returnArray['error_message'] = "Unable to remove import due to use of or changes to event registrants";
                    ajaxResponse($returnArray);
                    break;
                }
                $GLOBALS['gPrimaryDatabase']->startTransaction();

                $resultSet = executeQuery("select contacts.contact_id, event_type_id, start_date, end_date from event_registrants join contacts  using (contact_id) join events using (event_id)" .
                    "where event_registrant_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                while ($row = getNextRow($resultSet)) {
                    $dateCompleted = $row['end_date'] ?: $row['start_date'];
                    $deleteSet = executeQuery("delete from contact_certifications where contact_id = ? and date_issued = ?", $row['contact_id'], $dateCompleted);
                    $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event registrants (certifications)");
                    $deleteSet = executeQuery("delete from contact_event_types where contact_id = ? and event_type_id = ? and date_completed = ?", $row['contact_id'],
                        $row['event_type_id'], $dateCompleted);
                    $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event registrants (completion)");
                }

                $deleteSet = executeQuery("delete from event_registrants where event_registrant_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event registrants");

                $deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to event registrants");

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

                $numericFields = array("event_id", "contact_id", "order_id");
                $dateTimeFields = array("start_date", "start_time", "date_completed");

                $fieldNames = array();
                $importRecords = array();
                $count = 0;
                $this->iErrorMessages = array();
                # parse file and check for invalid fields
                while ($csvData = fgetcsv($openFile)) {
                    if (empty($csvData)) {
                        continue;
                    }
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

                $this->addResult("Event registrants import: " . count($importRecords) . " rows found");

                # Build lookup arrays
                $orderIdContacts = array();
                $orderResult = executeQuery("select order_id, contact_id from orders where client_id = ?", $GLOBALS['gClientId']);
                while($orderRow = getNextRow($orderResult)) {
                    $orderIdContacts[$orderRow['order_id']] = $orderRow['contact_id'];
                }

                # check for required fields and invalid data
                $locations = array();
                $contactIds = array();
                $contactRows = array();
                $eventIds = array();
                $certificationTypes = array();
                $attendanceStatusRows = array();
                foreach ($importRecords as $index => $thisRecord) {
                    $missingFields = "";
                    foreach ($this->iRequiredFields as $thisField) {
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
                    foreach ($dateTimeFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && strtotime($thisRecord[$fieldName]) == false) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a valid date or time: " . $thisRecord[$fieldName]);
                        }
                    }
                    if (!empty($thisRecord['dates'])) {
                        $dateParts = explode(" to ", $thisRecord['dates']);
                        if (count($dateParts) != 2 || strtotime($dateParts[0]) == false || strtotime($dateParts[1]) == false) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Dates must include a valid start date/time and a valid end date/time separated by ' to ': " . $thisRecord['dates']);
                        }
                    }
                    if (!empty($thisRecord['contact_id'])) {
                        $contactRow = Contact::getContact($thisRecord['contact_id']);
                        if (!empty($contactRow)) {
                            $contactKey = $contactRow['first_name'] . "|" . $contactRow['last_name'] . "|" . $contactRow['email_address'];
                            $contactIds[$contactKey] = $thisRecord['contact_id'];
                            $contactRow[$thisRecord['contact_id']] = $contactRow;
                        } else {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Contact ID not found: " . $thisRecord['contact_id']);
                        }
                    } else {
                        $contactKey = $thisRecord['first_name'] . "|" . $thisRecord['last_name'] . "|" . $thisRecord['email_address'];
                        $contactId = $contactIds[$contactKey];
                        if (empty($contactId)) {
                            $contactRow = getRowFromId("contacts", "email_address", $thisRecord['email_address'],
                                "(? is null or first_name = ?) and (? is null or last_name = ?)", $thisRecord['first_name'], $thisRecord['first_name'], $thisRecord['last_name'], $thisRecord['last_name']);
                            $contactRow = $contactRow ?: getRowFromId("contacts", "email_address", $thisRecord['email_address']);
                            if (!empty($contactRow)) {
                                $contactIds[$contactKey] = $contactRow['contact_id'];
                                $contactRows[$contactRow['contact_id']] = $contactRow;
                            } else {
                                $this->addErrorMessage("Line " . ($index + 2) . ": Contact not found: " . $contactKey);
                            }
                        } else {
                            $contactRow = $contactRows[$contactId];
                        }
                    }
                    if(!empty($thisRecord['order_id'])) {
                        if(!array_key_exists($thisRecord['order_id'], $orderIdContacts)) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Order ID not found: " . $thisRecord['order_id']);
                        } elseif(empty($_POST['allow_contacts_not_matching_order']) && $orderIdContacts[$thisRecord['order_id']] != $contactRow['contact_id']) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Order ID does not match contact: " . $thisRecord['order_id']);
                        }
                    }
                    $importRecords[$index]['contact_key'] = $contactKey;
                    $locationId = '';
                    if (!empty($thisRecord['location_code'])) {
                        $locationId = $locations[$thisRecord['location_code']];
                        if (empty($locationId)) {
                            $locationId = getFieldFromId("location_id", "locations", "location_code", $thisRecord['location_code']);
                        }
                        if (empty($locationId)) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Location does not exist: " . $thisRecord['location_code']);
                        } else {
                            $locations[$thisRecord['location_code']] = $locationId;
                        }
                    }
                    $eventKey = "";
                    if (!empty($thisRecord['event_id'])) {
                        $eventRow = getRowFromId("events", "event_id", $thisRecord['event_id']);
                        if (!empty($eventRow)) {
                            $eventKey = $eventRow['description'] . "|" . $eventRow['start_date'] . "|" . $eventRow['location_id'];
                            $eventIds[$eventKey] = $thisRecord['event_id'];
                        } else {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Event ID not found: " . $thisRecord['event_id']);
                        }
                    } elseif(!empty($thisRecord['product_code'])){
                        $eventSet = executeQuery("select * from events where product_id = (select product_id from products where product_code = ?)", makeCode($thisRecord['product_code']));
                        if($eventRow = getNextRow($eventSet)) {
                            $eventKey = $eventRow['description'] . "|" . $eventRow['start_date'] . "|" . $eventRow['location_id'];
                            $eventIds[$eventKey] = $eventRow['event_id'];
                        } else {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Event not found for product code: " . $thisRecord['product_code']);
                        }
                    } else {
                        if (empty($thisRecord['start_date'])) {
                            $dateParts = explode(" to ", $thisRecord['dates']);
                            $startDate = date("Y-m-d", strtotime($dateParts[0]));
                        } else {
                            $startDate = date("Y-m-d", strtotime($thisRecord['start_date']));
                        }
                        $eventKey = $thisRecord['event'] . "|" . $startDate . "|" . $locationId;
                        $eventId = $eventIds[$eventKey];
                        if (empty($eventId)) {
                            $eventId = getFieldFromId("event_id", "events", "description", $thisRecord['event'], "start_date = ? and (? is null or location_id = ?)",
                                $startDate, $locationId, $locationId);
                        }
                        if (!empty($eventId)) {
                            $eventIds[$eventKey] = $eventId;
                        } else {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Event not found: " . $eventKey);
                        }
                    }
                    if(empty($eventKey)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Event not found: " . implode("|", $thisRecord));
                    } else {
                        $importRecords[$index]['event_key'] = $eventKey;
                    }
                    if(!empty($thisRecord['status'])) {
                        $attendanceStatusRow = $attendanceStatusRows[$thisRecord['status']];
                        if(empty($attendanceStatusRow)) {
                            $attendanceStatusRow = getRowFromId("event_attendance_statuses", "event_attendance_status_code", $thisRecord['status']);
                        }
                        if(empty($attendanceStatusRow)) {
                            $attendanceStatusRow = getRowFromId("event_attendance_statuses", "description", $thisRecord['status']);
                        }
                        if(!empty($attendanceStatusRow)) {
                            $attendanceStatusRows[$thisRecord['status']] = $attendanceStatusRow;
                        } else {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Attendance Status not found: " . $thisRecord['status']);
                        }
                    }
                    if (!empty($thisRecord['certification_type'])) {
                        $certificationTypeId = getFieldFromId("certification_type_id", "certification_types", "certification_type_code", makeCode($thisRecord['certification_type']));
                        if (empty($certificationTypeId)) {
                            $certificationTypeId = getFieldFromId("certification_type_id", "certification_types", "description", $thisRecord['certification_type']);
                        }
                        if (!empty($certificationTypeId)) {
                            $certificationTypes[$thisRecord['certification_type']] = $certificationTypeId;
                        } else {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Certification Type not found: " . $thisRecord['certification_type']);
                        }
                    }
                }

                if (!empty($this->iErrorMessages)) {
                    $returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
                    foreach ($this->iErrorMessages as $thisMessage => $count) {
                        $returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
                    }
                    $this->addResult($returnArray['import_error']);
                    $logEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $this->iProgramLogId);
                    sendEmail(["subject"=>"Event Registrant Import Error", "body"=>$logEntry, "email_address"=>$GLOBALS['gUserRow']['email_address']]);
                    ajaxResponse($returnArray);
                    break;
                }

                $eventTypeRows = array();
                $resultSet = executeQuery("select * from event_types where client_id = ?", $GLOBALS['gClientId']);
                while($row = getNextRow($resultSet)) {
                    $eventTypeRows[$row['event_type_id']] = $row;
                }

                $this->addResult("starting import for " . count($importRecords) . " records");
                # do import
                $GLOBALS['gPrimaryDatabase']->startTransaction();

                $resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'event_registrants',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
                $this->checkSqlError($resultSet, $returnArray);
                $csvImportId = $resultSet['insert_id'];

                $insertCount = 0;
                $updateCount = 0;
                $processCount = 0;
                $newRegistrantIds = array();
                $this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
                foreach ($importRecords as $index => $thisRecord) {
                    $processCount++;
                    if($processCount % 1000 == 0) {
                        $this->addResult($processCount . " records processed");
                    }

                    $contactId = $thisRecord['contact_id'] ?: $contactIds[$thisRecord['contact_key']];
                    $eventId = $thisRecord['event_id'];
                    $eventId = $eventId ?: $eventIds[$thisRecord['event_key']];

                    $eventRow = getRowFromId("events", "event_id", $eventId);

                    # create or update registration
                    $eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "contact_id", $contactId, "event_id = ?", $eventId);
                    $registrationTime = $thisRecord['registration_time'] ?: date("Y-m-d H:i:s");
                    $checkinTime = $thisRecord['check_in_time'] ?: null;
                    $attendanceStatusRow = $attendanceStatusRows[$thisRecord['status']];
                    if(strlen($thisRecord['failed']) == 0) {
                        $thisRecord['failed'] = $attendanceStatusRow['incomplete'];
                    }
                    if (empty($eventRegistrantId) || in_array($eventRegistrantId, $newRegistrantIds)) {
                        $resultSet = executeQuery("insert into event_registrants (event_id, contact_id, event_attendance_status_id, registration_time, check_in_time, order_id) values (?,?,?,?,?,?)",
                            $eventId, $contactId, $attendanceStatusRow['event_attendance_status_id'], $registrationTime, $checkinTime, $thisRecord['order_id']);
                        $this->checkSqlError($resultSet, $returnArray);
                        $eventRegistrantId = $resultSet['insert_id'];
                        $newRegistrantIds[] = $eventRegistrantId;
                        $insertCount++;
                        $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $eventRegistrantId);
                        $this->checkSqlError($insertSet, $returnArray);
                    } else {
                        $resultSet = executeQuery("update event_registrants set event_attendance_status_id = ?, registration_time = ?, check_in_time = ? where event_registrant_id = ?",
                            $attendanceStatusRow['event_attendance_status_id'], $registrationTime, $checkinTime, $eventRegistrantId);
                        $this->checkSqlError($resultSet, $returnArray);
                        $updateCount++;
                    }

                    # create or update event results
                    $eventRow['end_date'] = (strtotime($eventRow['end_date']) <= 0 ? "" : $eventRow['end_date']);
                    $pastEvent = strtotime($eventRow['end_date'] ?: $eventRow['start_date']) < time();
                    $dateCompleted = ($thisRecord['date_completed'] ?: ($eventRow['end_date'] ?: $eventRow['start_date']));
                    if ($pastEvent && (!empty($thisRecord['date_completed']) || !empty($attendanceStatusRow) || !empty($thisRecord['failed']))) {
                        $eventTypeId = $eventRow['event_type_id'];
                        $contactEventTypeId = getFieldFromId("contact_event_type_id", "contact_event_types", "contact_id", $contactId,
                            "event_type_id = ?", $eventTypeId);
                        $failed = empty($thisRecord['failed']) ? 0 : 1;
                        if (!$failed && !empty($attendanceStatusRow['fragment_id'])) {
                            $dateFormat = getPreference("EVENT_EMAIL_DATE_FORMAT") ?: "m/d/Y";
                            $substitutions = array_merge($eventTypeRows[$eventTypeId], $contactRows[$contactId]);
                            $substitutions['full_name'] = getDisplayName($contactId);
                            $substitutions['event_date'] = date($dateFormat, strtotime($dateCompleted));
                            $expirationDays = CustomField::getCustomFieldData($eventTypeId, "CERTIFICATE_EXPIRATION_DAYS", "EVENT_TYPES");
                            if(is_numeric($expirationDays) && $expirationDays > 0) {
                                $substitutions['expiration_date'] = date($dateFormat, strtotime($dateCompleted . " +" . $expirationDays . " days"));
                            } else {
                                $substitutions['expiration_date'] = "";
                            }
                            // Certificate title is for legacy event types that will not be offered again
                            $substitutions['description'] = $thisRecord['certificate_title'] ?: $eventTypeRows[$eventTypeId]['description'];
                            $fileId = outputPDF(false, array("substitutions" => $substitutions, "create_file" => true, "fragment_id" => $attendanceStatusRow['fragment_id'],
                                "filename" => "certificate.pdf", "description" => "Certificate for " . $substitutions['description'] . " on " . $substitutions['event_date']));
                            executeQuery("update event_registrants set file_id = ? where event_registrant_id = ?", $fileId, $eventRegistrantId);
                        } else {
                            $fileId = "";
                        }

                        if(empty($contactEventTypeId)) {
                            $resultSet = executeQuery("insert into contact_event_types (contact_id, event_type_id, date_completed, failed, file_id, notes) values (?,?,?,?,?,?)",
                                $contactId, $eventTypeId, $dateCompleted, $failed, $fileId, $thisRecord['notes']);
                        } else {
                            $resultSet = executeQuery("update contact_event_types set date_completed = ?, failed = ?, file_id = ?, notes = ? where contact_id = ? and event_type_id = ?",
                                $dateCompleted, $failed, $fileId, $thisRecord['notes'], $contactId, $eventTypeId);
                        }
                        $this->checkSqlError($resultSet, $returnArray);

                        # create or update certifications if any
                        $certificationTypeId = getFieldFromId("certification_type_id", "certification_type_requirements", "event_type_id", $eventTypeId);
                        if (!$failed && !empty($certificationTypeId)) {
                            $contactCertificationId = getFieldFromId("contact_certification_id", "contact_certifications", "contact_id", $contactId,
                                "certification_type_id = ?", $certificationTypeId);
                            $expirationTime = strtotime($thisRecord['expiration_date']);
                            $expirationDate = !empty($expirationTime) ? date("Y-m-d", $expirationTime) : null;
                            if (empty($contactCertificationId)) {
                                $resultSet = executeQuery("insert into contact_certifications (contact_id, certification_type_id, date_issued, expiration_date, notes) values (?,?,?,?,?)",
                                    $contactId, $certificationTypeId, $dateCompleted, $expirationDate, $thisRecord['notes']);
                            } else {
                                $resultSet = executeQuery("update contact_certifications set date_issued = ?, expiration_date = ?, notes = ? where contact_id = ? and certification_type_id = ?",
                                    $dateCompleted, $expirationDate, $thisRecord['notes'], $contactId, $certificationTypeId);
                            }
                            $this->checkSqlError($resultSet, $returnArray);
                        }
                    }
                }

                $GLOBALS['gPrimaryDatabase']->commitTransaction();
                $returnArray['response'] = "<p>" . $insertCount . " event registrants imported.</p>";
                $returnArray['response'] .= "<p>" . $updateCount . " existing event registrants updated.</p>";
                $this->addResult(strip_tags($returnArray['response']));
                $logEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $this->iProgramLogId);
                sendEmail(["subject"=>"Event Registrant Import Results", "body"=>$logEntry, "email_address"=>$GLOBALS['gUserRow']['email_address']]);
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

    private function addResult($message) {
        $this->iProgramLogId = addProgramLog(numberFormat( (getMilliseconds() - $GLOBALS['gStartTime']) / 1000, 2) . ": " . $message, $this->iProgramLogId);
        error_log($GLOBALS['gClientRow']['client_code'] . " : " . date("m/d/Y H:i:s") . " : " . (is_array($message) ? json_encode($message) : $message) . "\n", 3,
            "/var/www/html/cache/import.log");
    }

    function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
        if (!empty($resultSet['sql_error'])) {
            if($this->iShowDetailedErrors) {
                $returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'];
            } else {
                $returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
            }
            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
            $this->addResult($returnArray['error_message']);
            $logEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $this->iProgramLogId);
            sendEmail(["subject"=>"Event Registrant Import Error", "body"=>$logEntry, "email_address"=>$GLOBALS['gUserRow']['email_address']]);
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

                <div class="basic-form-line" id="_allow_contacts_not_matching_order_row">
                    <input type="checkbox" tabindex="10" id="allow_contacts_not_matching_order" name="allow_contacts_not_matching_order" value="1"><label
                            class="checkbox-label" for="allow_contacts_not_matching_order">Allow order ID for contacts other than the contact for the order (instead of
                        failing)</label>
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
            $resultSet = executeQuery("select * from csv_imports where table_name = 'event_registrants' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
                var csvImportId = $(this).closest("tr").data("csv_import_id");
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
                <li><?= implode("</li><li>", $this->iValidFields) ?></li>
            </ul>
            <h3>Required Fields</h3>
            <ul>
                <?php
                foreach ($this->iRequiredFields as $thisField) {
                    echo "<li>" . str_replace("|", " OR ", $thisField) . "</li>";
                }
                ?>
            </ul>
            <p>For legeacy events with no currently used event type, certificate_title will replace the description of the event type on the certificate PDF.</p>
        </div>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these event registrants and any related certifications being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
        <?php
    }
}

$pageObject = new EventRegistrantCsvImportPage();
$pageObject->displayPage();
