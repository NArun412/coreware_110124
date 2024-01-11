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

$GLOBALS['gPageCode'] = "CONTACTEXPORT";
require_once "shared/startup.inc";

class ContactExportPage extends Page {

    function executePageUrlActions() {
        switch ($_GET['url_action']) {
            case "create_report":
                header("Content-Type: text/csv");
                header("Content-Disposition: attachment; filename=\"contacts.csv\"");
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');

                $useImportFormat = !empty($_POST['use_import_format']);
                if($useImportFormat) {
                    $csvHeaders = array("contact_id", "title", "first_name", "middle_name", "last_name", "suffix", "preferred_first_name",
                        "alternate_name", "business_name", "job_title", "salutation", "address_1", "address_2", "city", "state",
                        "postal_code", "country", "email_address", "web_page", "date_created", "contact_type_code", "birthdate", "phone_numbers", "phone_descriptions",
                        "user_name", "subscription_code", "subscription_start_date", "subscription_expiration_date", "shares_membership_contact_id", "old_contact_id", "alternate_old_contact_id");
                } else {
                    $csvHeaders = array("Contact ID", "Title", "First Name", "Middle Name", "Last Name", "Suffix", "Preferred First Name",
                        "Alternate Name", "Business Name", "Job Title", "Salutation", "Address 1", "Address 2", "City", "State",
                        "Postal Code", "Country", "Email Address", "Web Page", "Date Created", "Contact Type", "Birthdate", "Phone 1", "Phone 2", "Phone 3",
                        "Phone 4", "Username", "Subscription Code", "Start Date", "Expiration Date", "Shares Membership Contact ID", "Retired Contact ID", "Retired Contact ID", "Retired Contact ID");
                }

                $contactIdentifierTypes = array();
                $resultSet = executeQuery("select * from contact_identifier_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $csvHeaders[] = ($useImportFormat ? "contact_identifier-" . strtolower($row['contact_identifier_type_code']) : $row['description']);
                    $contactIdentifierTypes[] = $row['contact_identifier_type_id'];
                }
                $customFields = array();
                $resultSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = " .
                    "(select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $csvHeaders[] = ($useImportFormat ? "custom_field-" . strtolower($row['custom_field_code']) : $row['description']);
                    $customFields[] = array("custom_field_id"=>$row['custom_field_id'],"data_type"=>getFieldFromId("control_value", "custom_field_controls",
                    "custom_field_id", $row['custom_field_id'], "control_name = 'data_type'"));
                }
                echo createCsvRow($csvHeaders);

                $contactTypes = array();
                $resultSet = executeQuery("select * from contact_types where client_id = ?", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $contactTypes[$row['contact_type_id']] = $row['description'];
                }

                $subscriptions = array();
                $resultSet = executeQuery("select * from subscriptions where client_id = ?",$GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $subscriptions[$row['subscription_id']] = $row['description'];
                }

                $extraWhere = "";
                if(!empty($_POST['only_active_subscriptions'])) {
                    $extraWhere = "and contact_id in (select contact_id from contact_subscriptions where inactive = 0 and start_date <= current_date and expiration_date >= current_date)";
                }
                $contactCount = 0;
                $resultSet = executeQuery("select *,(select group_concat(retired_contact_identifier) from contact_redirect where contact_id = contacts.contact_id) retired_contact_identifiers," .
                    "(select group_concat(replace(phone_number,',','') separator '|') from phone_numbers where contact_id = contacts.contact_id) phone_numbers, " .
                    "(select group_concat(replace(description,',','') separator '|') from phone_numbers where contact_id = contacts.contact_id) phone_descriptions, " .
                    "(select group_concat(concat_ws('||',contact_identifier_type_id,identifier_value)) from contact_identifiers where contact_id = contacts.contact_id) contact_identifiers, " .
                    "(select contact_id from contacts cr where cr.contact_id = (select contact_id from relationships where relationship_type_id = " .
                    "(select relationship_type_id from relationship_types where relationship_type_code = 'SHARES_MEMBERSHIP') and related_contact_id = contacts.contact_id)) shares_membership_contact_id from " .
                    "contacts left outer join users using (contact_id) left outer join contact_subscriptions using (contact_id) where " .
                    "contacts.contact_id not in (select contact_id from product_manufacturers) and " .
                    "contacts.contact_id not in (select contact_id from federal_firearms_licensees) and " .
                    "contacts.contact_id not in (select contact_id from locations) and " .
                    "(last_name is not null or first_name is not null or address_1 is not null or email_address is not null or contacts.contact_id in (select contact_id from phone_numbers)) and " .
                    "contacts.client_id = ? " . $extraWhere ." order by contacts.contact_id", $GLOBALS['gClientId']);
                $customFieldData = array();
                $customFieldDataSet = executeQuery("select * from custom_field_data where custom_field_id in (select custom_field_id from custom_fields where client_id = ? and custom_field_type_id = " .
                    "(select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS'))", $GLOBALS['gClientId']);
                while($customFieldDataRow = getNextRow($customFieldDataSet)) {
                    if(is_array($customFieldData[$customFieldDataRow['primary_identifier']])) {
                        $customFieldData[$customFieldDataRow['primary_identifier']][$customFieldDataRow['custom_field_id']] = $customFieldDataRow;
                    } else {
                        $customFieldData[$customFieldDataRow['primary_identifier']] = array($customFieldDataRow['custom_field_id'] => $customFieldDataRow);
                    }
                }
                while ($row = getNextRow($resultSet)) {
                    $csvData = array();
                    $csvData[] = $row['contact_id'];
                    $csvData[] = $row['title'];
                    $csvData[] = $row['first_name'];
                    $csvData[] = $row['middle_name'];
                    $csvData[] = $row['last_name'];
                    $csvData[] = $row['suffix'];
                    $csvData[] = $row['preferred_first_name'];
                    $csvData[] = $row['alternate_name'];
                    $csvData[] = $row['business_name'];
                    $csvData[] = $row['job_title'];
                    $csvData[] = $row['salutation'];
                    $csvData[] = $row['address_1'];
                    $csvData[] = $row['address_2'];
                    $csvData[] = $row['city'];
                    $csvData[] = $row['state'];
                    $csvData[] = $row['postal_code'];
                    $csvData[] = ($row['country_id'] == 1000 ? "US" : getFieldFromId("country_name", "countries", "country_id", $row['country_id']));
                    $csvData[] = $row['email_address'];
                    $csvData[] = $row['web_page'];
                    $csvData[] = $row['date_created'];
                    $csvData[] = $contactTypes[$row['contact_type_id']];
                    $csvData[] = $row['birthdate'];
                    if($useImportFormat) {
                        $csvData[] = $row['phone_numbers'];
                        $csvData[] = $row['phone_descriptions'];
                    } else {
                        $phoneNumbers = explode(",", $row['phone_numbers']);
                        $count = 0;
                        foreach ($phoneNumbers as $phoneNumber) {
                            $count++;
                            $csvData[] = $phoneNumber;
                            if ($count >= 4) {
                                break;
                            }
                        }
                        while ($count < 4) {
                            $csvData[] = "";
                            $count++;
                        }
                    }
                    $csvData[] = $row['user_name'];
                    $csvData[] = $subscriptions[$row['subscription_id']];
                    $csvData[] = $row['start_date'];
                    $csvData[] = $row['expiration_date'];
                    $csvData[] = $row['shares_membership_contact_id'];
                    $retiredContactIds = explode(",",$row['retired_contact_identifiers']);
                    $count = 0;
                    $maxRetiredContactIds = ($useImportFormat ? 2 : 3);
                    foreach ($retiredContactIds as $retiredContactId) {
                        $count++;
                        $csvData[] = $retiredContactId;
                        if ($count >= $maxRetiredContactIds) {
                            break;
                        }
                    }
                    while ($count < $maxRetiredContactIds) {
                        $csvData[] = "";
                        $count++;
                    }
                    $contactIdentifiers = array();
                    $parts = explode(",",$row['contact_identifiers']);
                    foreach ($parts as $thisPart) {
                        if (empty($thisPart)) {
                            continue;
                        }
                        $thisParts = explode("||",$thisPart);
                        if(empty($contactIdentifiers[$thisParts[0]])) {
                            $contactIdentifiers[$thisParts[0]] = $thisParts[1];
                        } elseif(!$useImportFormat) {
                            $contactIdentifiers[$thisParts[0]] .= "," . $thisParts[1];
                        }
                    }
                    foreach ($contactIdentifierTypes as $contactIdentifierTypeId) {
                        $csvData[] = $contactIdentifiers[$contactIdentifierTypeId];
                    }
                    foreach ($customFields as $thisCustomField) {
                        $fieldValue = "";
                        if(array_key_exists($row['contact_id'], $customFieldData) && array_key_exists($thisCustomField['custom_field_id'], $customFieldData[$row['contact_id']])) {
                            $dataRow = $customFieldData[$row['contact_id']][$thisCustomField['custom_field_id']];
                            switch ($thisCustomField['data_type']) {
                                case "date":
                                    $fieldValue = $dataRow['date_data'];
                                    break;
                                case "int":
                                    $fieldValue = $dataRow['integer_data'];
                                    break;
                                case "decimal":
                                    $fieldValue = $dataRow['number_data'];
                                    break;
                                case "image":
                                    $fieldValue = $dataRow['image_id'];
                                    break;
                                case "file":
                                    $fieldValue = $dataRow['file_id'];
                                    break;
                                case "tinyint":
                                    $fieldValue = ($dataRow['text_data'] ? "1" : "0");
                                    break;
                                default:
                                    $fieldValue = $dataRow['text_data'];
                                    break;
                            }
                        }
                        $csvData[] = $fieldValue;
                    }
                    $contactCount++;
                    echo createCsvRow($csvData);
                }
                exit;
        }
    }

    function mainContent() {
        ?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line" id="_only_active_subscriptions_row">
                    <input tabindex="10" type="checkbox" id="only_active_subscriptions" name="only_active_subscriptions"><label class="checkbox-label" for="only_active_subscriptions">Only include contacts with active subscriptions</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_use_import_format_row">
                    <input tabindex="10" type="checkbox" id="use_import_format" name="use_import_format"><label class="checkbox-label" for="use_import_format">Use Import Format</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="new_parameters_button">Search Again</button>
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
        <?php
        return true;
    }

    function onLoadJavascript() {
        ?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("contact_export.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report", function () {
                $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#new_parameters_button", function () {
                $("#report_parameters").show();
                $("#_report_title").hide();
                $("#_report_content").hide();
                $("#_button_row").hide();
                return false;
            });
        </script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_report_content {
                display: none;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
        <?php
    }
}

$pageObject = new ContactExportPage();
$pageObject->displayPage();
