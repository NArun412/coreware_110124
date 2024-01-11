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

$GLOBALS['gPageCode'] = "CONTACTMAINT";
require_once "shared/startup.inc";

class ContactMaintenancePage extends Page {
    var $iSearchFields = array("first_name", "last_name", "business_name", "address_1", "city", "postal_code", "email_address", "alternate_name");
    var $iContactTypeId = "";
    var $iMailingLists = array();
    var $iCategories = array();
    var $iCustomTabs = array();
    var $iDonationBatchId = "";

    function executePageUrlActions() {
        $returnArray = array();
        switch ($_GET['url_action']) {
            case "invite_create_user":
                $emailAddress = $_POST['email_address'];
                $contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
                $emailId = getFieldFromId("email_id", "emails", "email_code", "EMAIL_CONTACT_USER", "inactive = 0");
                if (!empty($emailAddress) && !empty($contactId) && !empty($emailId)) {
                    $substitutions = Contact::getContact($contactId);
                    if (empty($substitutions['hash_code'])) {
                        $hashCode = md5(uniqid(mt_rand(), true) . $substitutions['first_name'] . $substitutions['last_name'] . $substitutions['contact_id'] . $substitutions['email_address'] . $substitutions['date_created']);
                        executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $contactId);
                        $substitutions['hash_code'] = $hashCode;
                    }
                    $substitutions['http_host'] = $_SERVER['HTTP_HOST'];
                    $substitutions['site_code'] = getFieldFromId("client_code", "clients", "client_id", $GLOBALS['gClientId']);
                    sendEmail(array("email_id" => $emailId, "email_addresses" => $emailAddress, "substitutions" => $substitutions));
                    $returnArray['info_message'] = "Email Sent";
                } else {
                    $returnArray['error_message'] = "Email NOT sent";
                }
                ajaxResponse($returnArray);
                break;
            case "check_for_duplicate":
                $emailAddress = $_POST['email_address'];
                if (!empty($emailAddress)) {
                    $contactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address']);
                    if (!empty($contactId)) {
                        $returnArray['duplicate_message'] = "Another contact (" . getDisplayName($contactId, array("include_company" => true)) . ") has this email address.";
                    }
                }
                ajaxResponse($returnArray);
                break;
            case "merge_selected":
                $selectedCount = 0;
                $resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
                if ($row = getNextRow($resultSet)) {
                    $selectedCount = $row['count(*)'];
                }
                if ($selectedCount != 2) {
                    $returnArray['error_message'] = "Exactly and only two contacts must be selected";
                }
                ajaxResponse($returnArray);
                break;
            case "select_by_radius":
                $postalCodeRow = getRowFromId("postal_codes", "postal_code", $_POST['central_postal_code'], "country_id = 1000 and latitude is not null and longitude is not null");
                if (empty($postalCodeRow)) {
                    $returnArray['error_message'] = "Geopoint information missing for central postal code";
                    ajaxResponse($returnArray);
                    break;
                }
                executeQuery("delete from selected_rows where user_id = ? and page_id = ? " .
                    "and primary_identifier in (select contact_id from contacts where client_id = ? and country_id = 1000 and postal_code in (select postal_code from postal_codes where " .
                    "country_id = 1000 and latitude is not null and longitude is not null and (3958 * 3.14159265 * " .
                    "sqrt((" . $postalCodeRow['latitude'] . " - latitude) * (" . $postalCodeRow['latitude'] . " - latitude) + cos(" . $postalCodeRow['latitude'] .
                    " / 57.29578) * cos(latitude / 57.29578) * (" . $postalCodeRow['longitude'] . " - longitude) * (" . $postalCodeRow['longitude'] .
                    " - longitude)) / 180) < ?))", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $GLOBALS['gClientId'], $_POST['radius_miles']);
                executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) " .
                    "select ?,?,contact_id from contacts where client_id = ? and country_id = 1000 and postal_code in (select postal_code from postal_codes where " .
                    "country_id = 1000 and latitude is not null and longitude is not null and (3958 * 3.14159265 * " .
                    "sqrt((" . $postalCodeRow['latitude'] . " - latitude) * (" . $postalCodeRow['latitude'] . " - latitude) + cos(" . $postalCodeRow['latitude'] .
                    " / 57.29578) * cos(latitude / 57.29578) * (" . $postalCodeRow['longitude'] . " - longitude) * (" . $postalCodeRow['longitude'] .
                    " - longitude)) / 180) < ?)", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $GLOBALS['gClientId'], $_POST['radius_miles']);
                ajaxResponse($returnArray);
                break;
            case "add_to_category":
                $contactIds = array();
                $resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
                while ($row = getNextRow($resultSet)) {
                    $contactIds[] = $row['primary_identifier'];
                }
                $categoryId = getFieldFromId("category_id", "categories", "category_id", $_POST['category_id']);
                if (!empty($contactIds) && !empty($categoryId)) {
                    $thisDataSource = new DataSource("contact_categories");
                    foreach ($contactIds as $contactId) {
                        $contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "contact_id", $contactId,
                            "category_id = ?", $categoryId);
                        if (empty($contactCategoryId)) {
                            $thisDataSource->saveRecord(array("name_values" => array("contact_id" => $contactId, "category_id" => $categoryId), "primary_id" => ""));
                        }
                    }
                }
                executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
                ajaxResponse($returnArray);
                break;
            case "remove_from_category":
                $contactIds = array();
                $resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
                while ($row = getNextRow($resultSet)) {
                    $contactIds[] = $row['primary_identifier'];
                }
                $categoryId = getFieldFromId("category_id", "categories", "category_id", $_POST['category_id']);
                if (!empty($contactIds) && !empty($categoryId)) {
                    $thisDataSource = new DataSource("contact_categories");
                    foreach ($contactIds as $contactId) {
                        $contactCategoryId = getFieldFromId('contact_category_id', 'contact_categories', 'category_id', $categoryId,
                            "contact_id = ?", $contactId);
                        if (!empty($contactCategoryId)) {
                            $thisDataSource->deleteRecord(array("primary_id" => $contactCategoryId));
                        }
                    }
                }
                ajaxResponse($returnArray);
                break;
            case "regenerate_certificates":
                $contactRow = Contact::getContact($_POST['contact_id']);
                if(empty($contactRow)) {
                    $returnArray['error_message'] = "Contact not found.";
                    ajaxResponse($returnArray);
                }
                $result = Events::generateEventCertificates(["contact_row"=>$contactRow, "regenerate"=>true]);
                if(!is_array($result)) {
                    $returnArray['error_message'] = "An error occurred and no certificates were generated.";
                } else {
                    $returnArray['info_message'] = "Certificates regenerated for " . count($result) . " events.";
                }
                ajaxResponse($returnArray);
                break;
        }
    }

    function setup() {
        if (!empty($_GET['clear_filter'])) {
            if (!empty($_GET['primary_id'])) {
                executeQuery("insert into user_preferences (user_id,preference_id,preference_qualifier,preference_value) values (?,?,'CONTACTMAINT',?)",
                    $GLOBALS['gUserId'], getFieldFromId("preference_id", "preferences", "preference_code", "MAINTENANCE_FILTER_TEXT"), $_GET['primary_id']);
            }
        }
        $this->iDonationBatchId = getFieldFromId("donation_batch_id", "donation_batches", "donation_batch_id", $_COOKIE['donation_batch_id'], "date_completed is null");
        if (function_exists("contactMaintenanceCustomTabs")) {
            $this->iCustomTabs = contactMaintenanceCustomTabs();
        }
        if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
            if (userHasAttribute("NO_CONTACT_EXPORT", $GLOBALS['gUserId'], false) || hasPageCapability("NO_CONTACT_EXPORT")) {
                $this->iTemplateObject->getTableEditorObject()->canExport(false);
            }
            $columnList = array("first_name", "last_name", "salutation", "business_name", "company_id", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address", "phone_number", "date_created", "contact_type_id", "date_due", "responsible_user_display", "alternate_email_addresses", "all_phone_numbers", "contact_categories", "birthdate", "notes");
            if (clientUsesSubsystem("CORE_DMS") && canAccessPageSection("donations")) {
                $columnList[] = "years_as_donor";
                $columnList[] = "number_gifts";
                $columnList[] = "total_donations";
                $columnList[] = "last_gift_amount";
                $columnList[] = "last_gift_date";
                $columnList[] = "largest_gift";
                $columnList[] = "largest_gift_date";
            }

            if (clientUsesSubsystem("CORE_DMS") && canAccessPageSection("orders")) {
                $columnList[] = "number_orders";
                $columnList[] = "total_orders";
                $columnList[] = "last_order_amount";
                $columnList[] = "last_order_date";
            }

            $customFields = CustomField::getCustomFields("contacts");
            foreach ($customFields as $thisCustomField) {
                $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);

                $dataType = $customField->getColumn()->getControlValue("data_type");
                switch ($dataType) {
                    case "date":
                        $fieldName = "date_data";
                        break;
                    case "bigint":
                    case "int":
                        $fieldName = "integer_data";
                        break;
                    case "decimal":
                        $fieldName = "number_data";
                        break;
                    case "image":
                    case "image_input":
                    case "file":
                    case "custom":
                    case "custom_control":
                        $fieldName = "";
                        break;
                    default:
                        $fieldName = "text_data";
                        break;
                }
                if (empty($fieldName)) {
                    continue;
                }
                $columnList[] = $customField->getColumn()->getControlValue("column_name");
            }
            $this->iTemplateObject->getTableEditorObject()->addIncludeListColumn($columnList);
            $this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address", "phone_number", "date_created", "contact_type_id", "date_due"));
            $this->iTemplateObject->getTableEditorObject()->addIncludeSearchColumn($this->iSearchFields);
            $this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("activity" => array("icon" => "fad fa-file-medical-alt", "label" => getLanguageText("Activity"), "disabled" => false)));
            $this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("delete" => array("icon" => "fad fa-archive", "label" => getLanguageText("Archive"), "disabled" => false)));
            $this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
            $filters = array();
            $filters['hide_deleted'] = array("form_label" => "Hide Archived", "where" => "deleted = 0", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);

            $fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
            if (!empty($fflRequiredProductTagId) && !empty(getPreference("SHOW_FFL_IN_CONTACTS"))) {
                $filters['hide_ffl'] = array("form_label" => "Hide FFL contacts", "where" => "contact_id not in (select contact_id from federal_firearms_licensees)", "data_type" => "tinyint");
            }
            $filters['show_tasks'] = array("form_label" => "Response Required", "where" => "contact_id in (select contact_id from tasks where contact_id is not null and date_completed is null and date_due is not null)", "data_type" => "tinyint");
            $filters['from_date'] = array("form_label" => "Earliest Date Created", "where" => "date_created >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
            $filters['to_date'] = array("form_label" => "Latest Date Created", "where" => "date_created <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
            $filters['responsible_user_id'] = array("form_label" => "My Contacts", "where" => "responsible_user_id = " . $GLOBALS['gUserId'], "data_type" => "tinyint");

            $resultSet = executeQuery("select count(*) from designations where client_id = ?", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                if ($row['count(*)'] > 0) {
                    $filters['has_designation'] = array("form_label" => "Has a designation", "where" => "contacts.contact_id in (select contact_id from designations where contact_id is not null)", "data_type" => "tinyint");
                }
            }
            $resultSet = executeQuery("select * from categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
            if ($resultSet['row_count'] > 0) {
                $filters['category_header'] = array("form_label" => "Categories", "data_type" => "header");
                while ($row = getNextRow($resultSet)) {
                    $filters['category_' . $row['category_id']] = array("form_label" => $row['description'], "where" => "contact_id in (select contact_id from contact_categories where category_id = " . $row['category_id'] . ")", "data_type" => "tinyint");
                }
            }
            $resultSet = executeQuery("select * from form_definitions where client_id = ? order by description", $GLOBALS['gClientId']);
            if ($resultSet['row_count'] > 0) {
                $formDefinitions = array();
                while ($row = getNextRow($resultSet)) {
                    $formDefinitions[$row['form_definition_id']] = $row['description'];
                }
                $filters['form_filled_out'] = array("form_label" => "Filled Out Form", "data_type" => "select", "where" => "contacts.contact_id in (select contact_id from forms where form_definition_id = %key_value%)", "choices" => $formDefinitions);
            }
            $resultSet = executeQuery("select * from mailing_lists where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
            if ($resultSet['row_count'] > 0) {
                $filters['mailing_list_header'] = array("form_label" => "Mailing Lists", "data_type" => "header");
                while ($row = getNextRow($resultSet)) {
                    $filters['mailing_list_' . $row['mailing_list_id']] = array("form_label" => $row['description'], "where" => "contact_id in (select contact_id from contact_mailing_lists where date_opted_out is null and mailing_list_id = " . $row['mailing_list_id'] . ")", "data_type" => "tinyint");
                }
            }
            $contactTypes = array();
            $resultSet = executeQuery("select * from contact_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $contactTypes[$row['contact_type_id']] = $row['description'];
            }
            if (!empty($contactTypes)) {
                $filters['contact_type'] = array("form_label" => "Contact Type", "where" => "contact_type_id = %key_value%", "data_type" => "select", "choices" => $contactTypes);
            }

            $countries = array();
            $resultSet = executeQuery("select * from countries order by sort_order,country_name");
            while ($row = getNextRow($resultSet)) {
                $countries[$row['country_id']] = $row['country_name'];
            }
            if (!empty($countries)) {
                $filters['country_id'] = array("form_label" => "Country", "where" => "country_id = %key_value%", "data_type" => "select", "choices" => $countries);
            }

            $regions = getCachedData("regions", "regions");
            if (!is_array($regions)) {
                $regions = array();
                $resultSet = executeQuery("select *,(select group_concat(country_id) from region_countries where region_id = regions.region_id) country_list from regions where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $whereStatement = "";
                    $countrySet = executeQuery("select * from region_countries where region_id = ?", $row['region_id']);
                    $countryIds = array();
                    while ($countryRow = getNextRow($countrySet)) {
                        $countryIds[] = $countryRow['country_id'];
                    }
                    if (!empty($countryIds)) {
                        $whereStatement .= (empty($whereStatement) ? "" : " and ") . "country_id in (" . implode(",", $countryIds) . ")";
                    }
                    $stateSet = executeQuery("select * from region_states where region_id = ?", $row['region_id']);
                    $stateIds = array();
                    while ($stateRow = getNextRow($stateSet)) {
                        $stateIds[] = $stateRow['state'];
                    }
                    if (!empty($stateIds)) {
                        $stateString = "";
                        foreach ($stateIds as $state) {
                            $stateString .= (empty($stateString) ? "" : ",") . '"' . $state . '"';
                        }
                        $whereStatement .= (empty($whereStatement) ? "" : " and ") . "state in (" . $stateString . ")";
                    }
                    if (!empty($whereStatement)) {
                        $regions[$whereStatement] = $row['description'];
                    }
                }
                setCachedData("regions", "regions", $regions, 2);
            }
            if (!empty($regions)) {
                $filters['region'] = array("form_label" => "Region", "where" => "%key_value%", "data_type" => "select", "choices" => $regions, "conjunction" => "and");
            }
            $filters['has_subscription'] = array("form_label" => "Has Active Subscription", "where" => "contact_id in (select contact_id from contact_subscriptions where inactive = 0 and (expiration_date is null or expiration_date > current_date))", "data_type" => "tinyint");
            $filters['no_account'] = array("form_label" => "No Payment Method on File", "where" => "contact_id not in (select contact_id from accounts where inactive = 0 and account_token is not null)", "data_type" => "tinyint");

            $this->iTemplateObject->getTableEditorObject()->addFilters($filters);
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("select_by_radius", "Select by Distance from Zip");
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("map_selected", "Map Selected Contacts");
            $resultSet = executeQuery("select count(*) from donation_commitment_types where client_id = ?", $GLOBALS['gClientId']);
            if ($row = getNextRow($resultSet)) {
                if ($row['count(*)'] == 0) {
                    $this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("donation_commitments"));
                }
            }
            if (canAccessPageCode("DUPLICATEPROCESSING")) {
                $this->iTemplateObject->getTableEditorObject()->addCustomAction("merge_selected", "Merge Selected Contacts");
            }
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("add_to_category", "Add Selected Contacts to Category");
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_from_category", "Remove Selected Contacts from Category");
        }
    }

    function massageDataSource() {
        $this->iDataSource->addColumnControl("deleted", "data_type", "hidden");
        $this->iDataSource->addColumnControl("redirect_list", "data_type", "hidden");

        $this->iDataSource->addColumnControl("contact_event_types", "data_type", "custom");
        $this->iDataSource->addColumnControl("contact_event_types", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("contact_event_types", "form_label", "Completed Classes");
        $this->iDataSource->addColumnControl("contact_event_types", "list_table", "contact_event_types");

        $this->iDataSource->addColumnControl("contact_certifications", "data_type", "custom");
        $this->iDataSource->addColumnControl("contact_certifications", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("contact_certifications", "form_label", "Certifications");
        $this->iDataSource->addColumnControl("contact_certifications", "list_table", "contact_certifications");

        $this->iDataSource->addColumnControl("address_1", "classes", "autocomplete-address");

        $this->iDataSource->addColumnControl("contact_identifiers", "form_label", "Identifiers");
        $this->iDataSource->addColumnControl("contact_identifiers", "data_type", "custom");
        $this->iDataSource->addColumnControl("contact_identifiers", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("contact_identifiers", "list_table", "contact_identifiers");
        $this->iDataSource->addColumnControl("contact_identifiers", "list_table_controls", array("image_id" => array("data_type" => "image_input")));

        $this->iDataSource->addColumnControl("contact_subscriptions", "form_label", "Subscriptions");
        $this->iDataSource->addColumnControl("contact_subscriptions", "data_type", "custom");
        $this->iDataSource->addColumnControl("contact_subscriptions", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("contact_subscriptions", "list_table", "contact_subscriptions");
        $this->iDataSource->addColumnControl("contact_subscriptions", "sort_order", "start_date");
        $this->iDataSource->addColumnControl("contact_subscriptions", "reverse_sort", true);

        $this->iDataSource->addColumnControl("accounts", "data_type", "custom");
        $this->iDataSource->addColumnControl("accounts", "control_class", "FormList");
        $this->iDataSource->addColumnControl("accounts", "filter_where", "inactive = 0");
        $this->iDataSource->addColumnControl("accounts", "list_table", "accounts");
        $this->iDataSource->addColumnControl("accounts", "no_delete", "true");
        $this->iDataSource->addColumnControl("accounts", "column_list", "account_label,payment_method_id,full_name,account_number,credit_limit,credit_terms,inactive");
        $this->iDataSource->addColumnControl("accounts", "list_table_controls",
            array("credit_terms" => array("help_label" => "Number of days"), "address_id" => array("data_type" => "hidden", "readonly" => true), "payment_method_id" => array("not_editable" => "true"),
                "account_number" => array("not_editable" => "true"), "account_token" => array("readonly" => "true"), "full_name" => array("not_editable" => "true")));

        $this->iDataSource->addColumnControl("email_address", "form_label", "Primary Email Address");
        $this->iDataSource->addColumnControl("contact_emails", "form_label", "Other Email Addresses");
        $this->iDataSource->addColumnControl("contact_emails", "data_type", "custom");
        $this->iDataSource->addColumnControl("contact_emails", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("contact_emails", "list_table", "contact_emails");
        $this->iDataSource->addColumnControl("contact_emails", "list_table_controls", array("description" => array("inline-width" => "150px"), "email_address" => array("inline-width" => "250px")));
        $this->iDataSource->addColumnControl("phone_numbers", "form_label", "Phone Numbers");
        $this->iDataSource->addColumnControl("phone_numbers", "data_type", "custom");
        $this->iDataSource->addColumnControl("phone_numbers", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("phone_numbers", "list_table", "phone_numbers");
        $this->iDataSource->addColumnControl("phone_numbers", "list_table_controls", array("description" => array("inline-width" => "150px"), "phone_number" => array("inline-width" => "150px")));

        $this->iDataSource->addColumnControl("alternate_email_addresses", "form_label", "Alternate Email");
        $this->iDataSource->addColumnControl("alternate_email_addresses", "data_type", "varchar");
        $this->iDataSource->addColumnControl("alternate_email_addresses", "select_value", "select group_concat(email_address) from contact_emails where contact_id = contacts.contact_id");

        $this->iDataSource->addColumnControl("all_phone_numbers", "form_label", "Phone Numbers");
        $this->iDataSource->addColumnControl("all_phone_numbers", "data_type", "varchar");
        $this->iDataSource->addColumnControl("all_phone_numbers", "select_value", "select group_concat(concat_ws('-',phone_number,description)) from phone_numbers where contact_id = contacts.contact_id");

        $this->iDataSource->addColumnControl("contact_categories", "form_label", "Categories");
        $this->iDataSource->addColumnControl("contact_categories", "data_type", "varchar");
        $this->iDataSource->addColumnControl("contact_categories", "select_value", "select group_concat(category_code) from categories join contact_categories using (category_id) where contact_id = contacts.contact_id");

        if (clientUsesSubsystem("CORE_DMS") && canAccessPageSection("donations")) {
            $this->iDataSource->addColumnControl("total_donations", "form_label", "Total Donations");
            $this->iDataSource->addColumnControl("total_donations", "data_type", "decimal");
            $this->iDataSource->addColumnControl("total_donations", "decimal_places", "2");
            $this->iDataSource->addColumnControl("total_donations", "select_value", "select coalesce(sum(amount),0) from donations where contact_id = contacts.contact_id");

            $this->iDataSource->addColumnControl("years_as_donor", "form_label", "Years As Donor");
            $this->iDataSource->addColumnControl("years_as_donor", "data_type", "int");
            $this->iDataSource->addColumnControl("years_as_donor", "select_value", "select coalesce(count(distinct(year(donation_date))),0) from donations where associated_donation_id is null and designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = contacts.contact_id");

            $this->iDataSource->addColumnControl("number_gifts", "form_label", "Total Gifts");
            $this->iDataSource->addColumnControl("number_gifts", "data_type", "int");
            $this->iDataSource->addColumnControl("number_gifts", "select_value", "select count(*) from donations where associated_donation_id is null and designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = contacts.contact_id");

            $this->iDataSource->addColumnControl("last_gift_amount", "form_label", "Last Gift");
            $this->iDataSource->addColumnControl("last_gift_amount", "data_type", "decimal");
            $this->iDataSource->addColumnControl("last_gift_amount", "decimal_places", "2");
            $this->iDataSource->addColumnControl("last_gift_amount", "select_value", "select amount from donations where associated_donation_id is null and designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = contacts.contact_id order by donation_id desc limit 1");

            $this->iDataSource->addColumnControl("last_gift_date", "form_label", "Last Gift Date");
            $this->iDataSource->addColumnControl("last_gift_date", "data_type", "date");
            $this->iDataSource->addColumnControl("last_gift_date", "select_value", "select donation_date from donations where associated_donation_id is null and designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = contacts.contact_id order by donation_id desc limit 1");

            $this->iDataSource->addColumnControl("largest_gift", "form_label", "Largest Gift");
            $this->iDataSource->addColumnControl("largest_gift", "data_type", "decimal");
            $this->iDataSource->addColumnControl("largest_gift", "decimal_places", "2");
            $this->iDataSource->addColumnControl("largest_gift", "select_value", "select amount from donations where associated_donation_id is null and designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = contacts.contact_id order by amount desc limit 1");

            $this->iDataSource->addColumnControl("largest_gift_date", "form_label", "Largest Gift Date");
            $this->iDataSource->addColumnControl("largest_gift_date", "data_type", "date");
            $this->iDataSource->addColumnControl("largest_gift_date", "select_value", "select donation_date from donations where associated_donation_id is null and designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = contacts.contact_id order by amount desc limit 1");
        }

        if (clientUsesSubsystem("CORE_DMS") && canAccessPageSection("orders")) {
            $this->iDataSource->addColumnControl("total_orders", "form_label", "Orders Total");
            $this->iDataSource->addColumnControl("total_orders", "data_type", "decimal");
            $this->iDataSource->addColumnControl("total_orders", "decimal_places", "2");
            $this->iDataSource->addColumnControl("total_orders", "select_value",
                "select coalesce(sum(shipping_charge) + sum(tax_charge) + sum(handling_charge) + (select sum(sale_price * quantity) from order_items where order_id in (select order_id from orders where contact_id = contacts.contact_id)),0) from orders where contact_id = contacts.contact_id");

            $this->iDataSource->addColumnControl("number_gifts", "form_label", "Orders");
            $this->iDataSource->addColumnControl("number_gifts", "data_type", "int");
            $this->iDataSource->addColumnControl("number_gifts", "select_value", "select count(*) from orders where contact_id = contacts.contact_id");

            $this->iDataSource->addColumnControl("last_order_amount", "form_label", "Last Order");
            $this->iDataSource->addColumnControl("last_order_amount", "data_type", "decimal");
            $this->iDataSource->addColumnControl("last_order_amount", "decimal_places", "2");
            $this->iDataSource->addColumnControl("last_order_amount", "select_value", "select shipping_charge + tax_charge + handling_charge + (select sum(sale_price * quantity) from order_items where order_id = orders.order_id) from orders where contact_id = contacts.contact_id order by order_id desc limit 1");

            $this->iDataSource->addColumnControl("last_order_date", "form_label", "Last Order Date");
            $this->iDataSource->addColumnControl("last_order_date", "data_type", "date");
            $this->iDataSource->addColumnControl("last_order_date", "select_value", "select order_time from orders where contact_id = contacts.contact_id order by donation_id desc limit 1");
        }

        $this->iDataSource->addColumnControl("responsible_user_display", "data_type", "varchar");
        $this->iDataSource->addColumnControl("responsible_user_display", "select_value", "select concat_ws(' ',first_name,last_name) from contacts ca where contact_id = (select contact_id from users where user_id = contacts.responsible_user_id)");
        $this->iDataSource->addColumnControl("responsible_user_display", "form_label", "Responsible User");

        $this->iDataSource->addColumnControl("contact_files", "data_type", "custom");
        $this->iDataSource->addColumnControl("contact_files", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("contact_files", "form_label", "Files");
        $this->iDataSource->addColumnControl("contact_files", "list_table", "contact_files");
        $this->iDataSource->addColumnControl("contact_files", "column_list", "description,file_id");
        $minimumPasswordLength = getPreference("minimum_password_length");
        if (empty($minimumPasswordLength)) {
            $minimumPasswordLength = 10;
        }
        $this->iDataSource->addColumnControl("contact_pw", "validation_classes", "custom[pciPassword,minSize[" . $minimumPasswordLength . "]]");
        if (getPreference("PCI_COMPLIANCE")) {
            $noPasswordRequirements = false;
        } else {
            $noPasswordRequirements = getPreference("no_password_requirements");
        }
        if ($noPasswordRequirements) {
            $this->iDataSource->addColumnControl("contact_pw", "classes", "no-password-requirements");
        }

        $this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
        $this->iDataSource->addColumnControl("recurring_donations", "no_add", "true");
        $this->iDataSource->addColumnControl("recurring_donations", "no_delete", "true");
        $this->iDataSource->addColumnControl("responsible_user_id", "data_type", "user_picker");
        $this->iDataSource->addColumnControl("contact_pw", "help_label", "will be reset by user");
        $this->iDataSource->addColumnControl("date_due", "select_value", "select min(date_due) from tasks where contact_id = contacts.contact_id and date_completed is null and date_due is not null");
        $this->iDataSource->addColumnControl("date_due", "data_type", "date");
        $this->iDataSource->addColumnControl("date_due", "form_label", "Response");
        $this->iDataSource->addColumnControl("phone_number", "select_value", "select phone_number from phone_numbers where contact_id = contacts.contact_id limit 1");
        $this->iDataSource->addColumnControl("phone_number", "data_type", "varchar");
        $this->iDataSource->addColumnControl("phone_number", "form_label", "Phone");

        $filterWhere = "";
        if (empty(getPreference("SHOW_LOCATIONS_IN_CONTACTS"))) {
            $filterWhere .= (empty($filterWhere) ? "" : " and ") . "contact_id not in (select contact_id from locations)";
        }
        if (empty(getPreference("SHOW_FFL_IN_CONTACTS"))) {
            $filterWhere .= (empty($filterWhere) ? "" : " and ") . "contact_id not in (select contact_id from federal_firearms_licensees)";
        }
        if (empty(getPreference("SHOW_MANUFACTURERS_IN_CONTACTS"))) {
            $filterWhere .= (empty($filterWhere) ? "" : " and ") . "contact_id not in (select contact_id from product_manufacturers)";
        }
        if (!$GLOBALS['gUserRow']['administrator_flag'] || hasPageCapability("ONLY_RESPONSIBLE")) {
            $filterWhere .= (empty($filterWhere) ? "" : " and ") . "responsible_user_id = " . $GLOBALS['gUserId'];
        }
        if (empty($GLOBALS['gUserRow']['superuser_flag'])) {
            $filterWhere .= (empty($filterWhere) ? "" : " and ") . "contact_id not in (select contact_id from users where superuser_flag = 1) and contact_id not in (select contact_id from clients)";
        }

        if (!$GLOBALS['gUserRow']['full_client_access']) {
            $categoryIds = array();
            $resultSet = executeQuery("select category_id from categories where user_group_id is not null and user_group_id not in " .
                "(select user_group_id from user_group_members where user_id = ?)", $GLOBALS['gUserId']);
            while ($row = getNextRow($resultSet)) {
                $categoryIds[] = $row['category_id'];
            }
            if (!empty($categoryIds)) {
                $filterWhere .= (empty($filterWhere) ? "" : " and ") . "contact_id not in (select contact_id from contact_categories where category_id in (" . implode(",", $categoryIds) . "))";
            }
        }

        if (!empty($filterWhere)) {
            $this->iDataSource->setFilterWhere($filterWhere);
        }

        $this->iDataSource->setSaveOnlyPresent(true);
        $this->iDataSource->addColumnLikeColumn("contact_un", "users", "user_name");
        $this->iDataSource->addColumnControl("contact_un", "form_label", "User Name");
        $this->iDataSource->addColumnControl("contact_un", "not_null", "false");
        $this->iDataSource->addColumnControl("contact_un", "classes", "allow-dash");
        if (canAccessPageCode("USERMAINT")) {
            $this->iDataSource->addColumnControl("contact_un", "help_label", "Click to open User record");
        }
        $this->iDataSource->addColumnLikeColumn("contact_pw", "users", "password");
        $this->iDataSource->addColumnControl("contact_pw", "not_null", "false");
        $this->iDataSource->addColumnControl("hash_code", "data_type", "hidden");

        $this->iDataSource->addColumnControl("donation_commitments", "form_label", "Donation Commitments");
        $this->iDataSource->addColumnControl("donation_commitments", "data_type", "custom");
        $this->iDataSource->addColumnControl("donation_commitments", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("donation_commitments", "list_table", "donation_commitments");

        $customFields = CustomField::getCustomFields("contacts");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);

            $dataType = $customField->getColumn()->getControlValue("data_type");
            switch ($dataType) {
                case "date":
                    $fieldName = "date_data";
                    break;
                case "bigint":
                case "int":
                    $fieldName = "integer_data";
                    break;
                case "decimal":
                    $fieldName = "number_data";
                    break;
                case "image":
                case "image_input":
                case "file":
                case "custom":
                case "custom_control":
                    $fieldName = "";
                    break;
                default:
                    $fieldName = "text_data";
                    break;
            }
            if (empty($fieldName)) {
                continue;
            }

            $this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "select_value",
                "select " . $fieldName . " from custom_field_data where primary_identifier = contacts.contact_id and custom_field_id = " . $thisCustomField['custom_field_id']);
            $this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "data_type", $dataType);
            $this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "form_label", $customField->getColumn()->getControlValue("form_label"));
        }

    }

    function internalCSS() {
        ?>
        <style>
            <?php
			if (function_exists("contactMaintenanceCSS")) {
				contactMaintenanceCSS();
			}
	?>
            .contact-subscription, .recurring-payment {
                cursor: pointer;
            }

            .contact-subscription:hover, .recurring-payment:hover {
                background-color: rgb(240, 240, 160);
            }

            #simulate_user_wrapper {
                margin-top: 10px;
            }

            #regenerate_certificates_wrapper {
                margin: 10px 0 10px 0;
            }

            #simulate_user_wrapper button, #regenerate_certificates_wrapper button {
                margin: 0;
            }

            #duplicate_message {
                font-size: .8rem;
                color: rgb(15, 180, 50);
            }

            #contact_un:read-only {
                cursor: pointer;
            }

            #_maintenance_form input#custom_field_group_link {
                width: 90%;
                max-width: 90%;
            }

            .basic-form-line p label:first-child {
                display: block;
                margin-top: 5px;
                width: 100%;
                text-align: left;
                padding-bottom: 0;
                padding-right: 0;
                float: none;
            }

            #_contact_display {
                font-size: 1rem;
                font-weight: bold;
                color: rgb(80, 80, 80);
            }

            #_contact_left_column {
                width: 52%;
                float: left;
            }

            #_contact_right_column {
                width: 47%;
                float: right;
            }

            .touchpoint-select {
                width: 130px;
            }

            .category-table td:first-child {
                padding-left: 10px;
                width: 400px;
            }

            #_summary {
                display: flex;
            }

            #_summary div {
                flex: 1 1 auto;
            }

            #_summary_image_id img {
                max-height: 200px;
                max-width: 200px;
            }

            #deleted_display p {
                font-size: 1.2rem;
                color: rgb(192, 0, 0);
                font-weight: bold;
            }

            .reverse-donation {
                cursor: pointer;
            }

            #age {
                margin-left: 40px;
            }

            .donation-receipt, .view-donation-receipt {
                cursor: pointer;
            }

            .grid-table {
                margin-bottom: 20px;
            }

            #_maintenance_form input {
                max-width: 350px;
            }

            #_name_table {
                max-width: 100%;
                display: flex;
            }

            #_name_table div {
                flex: 1 1 auto;
                padding-right: 5px;
            }

            #_name_table input {
                width: 100%;
            }

            .help-desk-row, .email-log-row {
                cursor: pointer;
            }

            .help-desk-row:hover, .email-log-row:hover {
                background-color: rgb(240, 240, 160);
            }

            @media only screen and (max-width: 1200px) {
                #_main_content p#_contact_display {
                    font-size: 1.2rem;
                }
            }

            @media only screen and (max-width: 1000px) {
                #_main_content p#_contact_display {
                    font-size: 1.4rem;
                }

                #_summary {
                    display: block;
                }

                #_name_table {
                    display: block;
                }

                #_contact_left_column, #_contact_right_column {
                    width: 100%;
                    float: none;
                }
            }
        </style>
        <?php
    }

    function taskTypeChoices($showInactive = false) {
        $taskTypeChoices = array();
        $resultSet = executeQuery("select * from task_types where task_type_id in (select task_type_id from task_type_attributes " .
            "where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK')) and " .
            "client_id = ?", $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            if (empty($row['inactive']) || $showInactive) {
                $taskTypeChoices[$row['task_type_id']] = array("key_value" => $row['task_type_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "data-assigned_user_id" => $row['user_id']);
            }
        }
        return $taskTypeChoices;
    }

    function generateSummary() {
        ?>
        <div id="deleted_display"></div>
        <div id="_summary">
            <?php
            $summaryLayout = $this->getFragment("contacts_summary_layout");
            if (empty($summaryLayout)) {
                ob_start();
                ?>
                <div id="_column_1">

                    <div class="basic-form-line" id="_summary_primary_id_row">
                        <label class="summary-label">Contact ID</label>
                        <p class="summary-text summary-text-wrapper" id="_summary_primary_id"></p>
                    </div>

                    <div class="basic-form-line" id="_summary_date_created_row">
                        <label class="summary-label">Created</label>
                        <p class="summary-text summary-text-wrapper" id="_summary_date_created"></p>
                    </div>

                    <div class="basic-form-line" id="_summary_name_row">
                        <label class="summary-label">Name</label>
                        <p class="summary-text-wrapper"><span class="summary-text" id="_summary_title"></span> <span
                                    class="summary-text" id="_summary_first_name"></span> <span class="summary-text"
                                                                                                id="_summary_middle_name"></span>
                            <span
                                    class="summary-text" id="_summary_last_name"></span> <span class="summary-text"
                                                                                               id="_summary_suffix"></span>
                        </p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_business_name_row">
                        <label class="summary-label">Company</label>
                        <p class="summary-text summary-text-wrapper" id="_summary_business_name"></p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_address_row">
                        <label class="summary-label">Address</label>
                        <p class="summary-text-wrapper"><span class="summary-text empty-chunk"
                                                              id="_summary_address_1"></span><br><span
                                    class="summary-text empty-chunk" id="_summary_city"></span> <span
                                    class="summary-text empty-chunk"
                                    id="_summary_state"></span> <span
                                    class="summary-text empty-chunk" id="_summary_postal_code"></span><br><span
                                    class="summary-text empty-chunk" id="_summary_country_id"></span></p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_email_address_row">
                        <label class="summary-label">Email Address</label>
                        <p class="summary-text-wrapper"><span class="summary-text empty-chunk"
                                                              id="_summary_email_address"></span></p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_phone_numbers_phone_numbers">
                        <label class="summary-label">Phone</label>
                        <p class="summary-text-wrapper">
                            <span class="summary-text empty-chunk" id="_summary_phone_numbers_phone_number-1"></span>
                            <span class="summary-text empty-chunk" id="_summary_phone_numbers_description-1"></span><br>
                            <span class="summary-text empty-chunk" id="_summary_phone_numbers_phone_number-2"></span>
                            <span class="summary-text empty-chunk" id="_summary_phone_numbers_description-2"></span><br>
                            <span class="summary-text empty-chunk" id="_summary_phone_numbers_phone_number-3"></span>
                            <span class="summary-text empty-chunk" id="_summary_phone_numbers_description-3"></span><br>
                        </p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_hash_code_row">
                        <label class="summary-label">Contact Code</label>
                        <p class="summary-text-wrapper"><span class="summary-text empty-chunk"
                                                              id="_summary_hash_code"></span></p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_user_id">
                        <label class="summary-label">Assigned To</label>
                        <p class="summary-text summary-text-wrapper" id="_summary_responsible_user_id"></p>
                    </div>

                </div>
                <div id="_column_2">

                    <div class="basic-form-line" id="_summary_image_id_row">
                        <div id="_summary_image_id"></div>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_notes_row">
                        <label class="summary-label">Notes</label>
                        <p class="summary-text summary-text-wrapper" id="_summary_notes"></p>
                    </div>

                    <div class="basic-form-line empty-hide" id="_summary_redirect_list">
                        <label class="summary-label">Previous IDs</label>
                        <p class="summary-text summary-text-wrapper" id="_summary_redirect_list"></p>
                    </div>
                </div>
                <?php
                $summaryLayout = ob_get_clean();
            }
            echo $summaryLayout;
            ?>
        </div>
        <?php
    }

    function onLoadJavascript() {
        ?>
        <script>
            const $tabbedForm = $(".tabbed-form");
            <?php if (canAccessPageCode("RECURRINGPAYMENTMAINT")) { ?>
            $(document).on("click", ".recurring-payment", function () {
                const recurringPaymentId = $(this).data("recurring_payment_id");
                window.open("/recurringpaymentmaintenance.php?clear_filter=true&url_page=show&primary_id=" + recurringPaymentId);
            });
            <?php } ?>
            <?php if (canAccessPageCode("CONTACTSUBSCRIPTIONMAINT")) { ?>
            $(document).on("click", ".contact-subscription", function () {
                const contactSubscriptionId = $(this).data("contact_subscription_id");
                window.open("/contactsubscriptionmaintenance.php?clear_filter=true&url_page=show&primary_id=" + contactSubscriptionId);
            });
            <?php } ?>
            $(document).on("click", "#invite_create_user", function () {
                const emailAddress = $("#email_address").val();
                if (empty(emailAddress)) {
                    displayErrorMessage("No email address");
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=invite_create_user", {
                    email_address: emailAddress,
                    contact_id: $("#primary_id").val()
                });
                return false;
            });
            $(document).on("click", "#regenerate_certificates", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=regenerate_certificates", {
                    contact_id: $("#primary_id").val()
                });
                return false;
            });
            $(document).on("click", "#simulate_user", function () {
                goToLink("/simulateuser.php?url_action=simulate_user&user_id=" + $("#user_id").val());
                return false;
            });
            $("#email_address").change(function () {
                $("#duplicate_message").html();
                if (empty($("#primary_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_for_duplicate", {email_address: $(this).val()}, function (returnArray) {
                        if ("duplicate_message" in returnArray) {
                            $("#duplicate_message").html(returnArray['duplicate_message']);
                        }
                    });
                }
            });
            <?php if (canAccessPageCode("HELPDESKDASHBOARD")) { ?>
            $(document).on("click", ".help-desk-row", function () {
                const helpDeskEntryId = $(this).data("help_desk_entry_id");
                window.open("/help-desk-dashboard?id=" + helpDeskEntryId);
            });
            <?php } ?>
            <?php if (canAccessPageCode("EMAILLOG")) { ?>
            $(document).on("click", ".email-log-row", function () {
                const emailLogId = $(this).data("email_log_id");
                window.open("/emaillog.php?url_page=show&primary_id=" + emailLogId);
            });
            <?php } ?>
            <?php if (canAccessPageCode("USERMAINT")) { ?>
            $(document).on("click", "#contact_un", function () {
                const userId = $("#user_id").val();
                if (!empty(userId)) {
                    window.open("/usermaintenance.php?clear_filter=true&url_page=show&primary_id=" + userId);
                }
            });
            <?php } ?>
            <?php
            if (!empty($this->iCustomTabs)) {
            foreach ($this->iCustomTabs as $customTabInfo) {
            ?>
            $("#main_tabs").append("<li><a href='#<?= $customTabInfo['tab_id'] ?>'><?= $customTabInfo['tab_label'] ?></a></li>");
            $("#main_tab_panel").append("<div id='<?= $customTabInfo['tab_id'] ?>'></div>");
            <?php
            }
            ?>
            const $activeTab = $("#_active_tab");
            $tabbedForm.tabs("destroy");
            const activeTab = ($activeTab.length > 0 ? $activeTab.val() - 0 : 0);
            $tabbedForm.tabs({
                active: activeTab,
                beforeActivate: function (event, ui) {
                    $("#_edit_form").validationEngine("hideAll");
                },
                activate: function (event, ui) {
                    if ("scriptFilename" in window) {
                        $("body").addClass("no-waiting-for-ajax");
                        loadAjaxRequest(scriptFilename + "?ajax=true&url_action=select_tab&tab_index=" + $(this).tabs("option", "active"));
                    }
                },
                cookie: {expires: 120}
            });
            <?php
            }
            if (!empty($this->iDonationBatchId)) {
            ?>
            $(document).on("click", ".reverse-donation", function (event) {
                const donationId = $(this).closest(".donation-row").data("donation_id");
                const $reverseDonationIds = $("#reverse_donation_ids");
                let reverseDonationIds = $reverseDonationIds.val();
                reverseDonationIds += (empty(reverseDonationIds) ? "" : ",") + donationId;
                $reverseDonationIds.val(reverseDonationIds);
                $(this).closest("td").html("Added to batch <?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $this->iDonationBatchId) ?> as backout");
            });
            <?php
            }
            if (canAccessPageCode("USERMAINT")) {
            ?>
            $("#contact_un").change(function () {
                const $userNameMessage = $("#_user_name_message");
                if (!empty($(this).val())) {
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val(), function (returnArray) {
                        $userNameMessage.removeClass("info-message").removeClass("error-message");
                        if ("info_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['info_user_name_message']).addClass("info-message");
                        }
                        if ("error_user_name_message" in returnArray) {
                            $userNameMessage.html(returnArray['error_user_name_message']).addClass("error-message");
                            $("#contact_un").val("").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $userNameMessage.val("");
                }
            });
            <?php
            }
            if (canAccessPageCode("SENDEMAIL")) {
            ?>
            $(document).on("click", ".donation-receipt", function () {
                const donationId = $(this).closest("tr").data("donation_id");
                window.open("/sendemail.php?contact_id=" + $("#primary_id").val() + "&donation_id=" + donationId);
            });
            <?php
            }
            if (canAccessPageCode("DONATIONMAINT")) {
            ?>
            $(document).on("click", ".view-donation-receipt", function () {
                const donationId = $(this).closest("tr").data("donation_id");
                window.open("/viewdonationreceipt.php?donation_id=" + donationId);
            });
            <?php
            }
            ?>
            $(document).on("tap click", "#_activity_button", function () {
                const $activityLog = $('#_activity_log');
                loadAjaxRequest("/useractivitylog.php?ajax=true&contact_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("activity_log" in returnArray) {
                        $activityLog.html(returnArray['activity_log']);
                    } else {
                        $activityLog.html("<p>No Activity Found</p>");
                    }
                    $activityLog.dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 600,
                        title: 'User Activity',
                        buttons: {
                            Close: function (event) {
                                $activityLog.dialog('close');
                            }
                        }
                    });
                });
                return false;
            });
            $tabbedForm.tabs({
                beforeActivate: function (event, ui) {
                    if (ui.newPanel.attr("id") === "summary_tab") {
                        generateSummary();
                    }
                }
            });
            $(document).on("blur", "#state", function () {
                if ($("#country_id").val() === "1000") {
                    $(this).val($(this).val().toUpperCase());
                }
            });
            $(document).on("blur", "#postal_code", function () {
                const $city = $("#city");
                const $countryId = $("#country_id");
                const $postalCode = $("#postal_code");
                $city.add("#state").prop("readonly", $countryId.val() === "1000" && !empty($postalCode.val()));
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" && !empty($postalCode.val()) ? "9999" : "10"));
                if ($countryId.val() === "1000") {
                    $postalCode.data("city_hide", "_city_row").data("city_select_hide", "_city_select_row");
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                const $city = $("#city");
                const $countryId = $("#country_id");
                const $postalCode = $("#postal_code");
                $city.add("#state").prop("readonly", $countryId.val() === "1000" && !empty($postalCode.val()));
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" && !empty($postalCode.val()) ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($countryId.val() === "1000") {
                    $postalCode.data("city_hide", "_city_row").data("city_select_hide", "_city_select_row");
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
            $(document).on("blur", ".addresses-postal-code", function () {
                if ($(this).closest(".form-list-item").find(".addresses-country-id").val() === "1000") {
                    const cityHide = $(this).closest(".form-list-item").find(".addresses-city").closest(".basic-form-line").attr("id");
                    const citySelectHide = $(this).closest(".form-list-item").find(".addresses-city-select").closest(".basic-form-line").attr("id");
                    const cityField = $(this).closest(".form-list-item").find(".addresses-city").attr("id");
                    const citySelectField = $(this).closest(".form-list-item").find(".addresses-city-select").attr("id");
                    const stateField = $(this).closest(".form-list-item").find(".addresses-state").attr("id");
                    $(this).data("city_hide", cityHide).data("city_select_hide", citySelectHide).data("state_field", stateField).data("city_field", cityField).data("city_select_field", citySelectField);
                    validatePostalCode($(this).attr("id"));
                }
            });
            $(document).on("change", ".addresses-country-id", function () {
                $(".addresses-postal-code").trigger("blur");
            });
            $(document).on("change", ".addresses-country-id", function () {
                $(this).closest(".form-list-item").find(".addresses-city").prop("readonly", $(this).val() === "1000").attr("tabindex", "10").show();
                $(this).closest(".form-list-item").find(".addresses-state").prop("readonly", $(this).val() === "1000").attr("tabindex", "10");
                $(this).closest(".form-list-item").find(".addresses-city-select").closest(".basic-form-line").hide();
                if ($(this).val() === "1000") {
                    $(this).closest(".form-list-item").find(".addresses-city").attr("tabindex", "9000");
                    $(this).closest(".form-list-item").find(".addresses-state").attr("tabindex", "9000");
                    const cityHide = $(this).closest(".form-list-item").find(".addresses-city").closest(".basic-form-line").attr("id");
                    const citySelectHide = $(this).closest(".form-list-item").find(".addresses-city-select").closest(".basic-form-line").attr("id");
                    const cityField = $(this).closest(".form-list-item").find(".addresses-city").attr("id");
                    const citySelectField = $(this).closest(".form-list-item").find(".addresses-city-select").attr("id");
                    const stateField = $(this).closest(".form-list-item").find(".addresses-state").attr("id");
                    $(this).closest(".form-list-item").find(".addresses-postal-code").data("city_hide", cityHide).data("city_select_hide", citySelectHide).data("state_field", stateField).data("city_field", cityField).data("city_select_field", citySelectField);
                }
            });
            $(document).on("change", ".addresses-city-select", function () {
                $(this).closest(".form-list-item").find(".addresses-city").val($(this).val());
                $(this).closest(".form-list-item").find(".addresses-state").val($(this).find("option:selected").data("state"));
            });
            $("#first_name").add("#last_name").add("#business_name").change(function () {
                displayContact();
            });
            $("#salutation").focus(function () {
                setSalutation();
            });
            $("#birthdate").change(function () {
                calculateAge();
            });
            $("#custom_field_group_id").change(function () {
                const $customFieldGroupLink = $("#custom_field_group_link");
                const $primaryId = $("#primary_id");
                let linkUrl = "Save Contact First";
                $customFieldGroupLink.val("");
                if (!empty($(this).val())) {
                    if (!empty($primaryId.val())) {
                        const groupLinkUrl = $(this).find("option:selected").data("link_url");
                        linkUrl = (groupLinkUrl.indexOf("http") >= 0 ? "" : "https://") + groupLinkUrl + (groupLinkUrl.indexOf("?") >= 0 ? "&" : "?") + "code=" + $(this).find("option:selected").data("code") + "&id=" + $primaryId.val() + "&hash=" + $("#hash_code").val();
                    }
                    $customFieldGroupLink.val(linkUrl);
                }
            });
            $(document).on("click", "#custom_field_group_link", function () {
                $(this).select();
            });
            $(document).on("change", ".task-type", function () {
                const userId = $(this).find("option:selected").data("assigned_user_id");
                if (!empty(userId)) {
                    $(this).closest("tr").find(".assigned-user").val(userId);
                } else {
                    $(this).closest("tr").find(".assigned-user").val("");
                }
            });
            $(document).on("click", ".custom-field-group", function () {
                window.open("/contactcustomdata.php?url_page=show&primary_id=" + $("#primary_id").val() + "&custom_field_group_id=" + $(this).data("custom_field_group_id"));
                return false;
            });
        </script>
        <?php
    }

    function javascript() {
        ?>
        <script>
            <?php
            if (function_exists("contactMaintenanceJavascript")) {
                contactMaintenanceJavascript();
            }
            ?>

            function customActions(actionName) {
                if (actionName === "map_selected") {
                    window.open("/contactmap.php");
                    return true;
                }
                if (actionName === "select_by_radius") {
                    const $selectByRadiusForm = $("#_select_by_radius_form");
                    const $selectByRadiusDialog = $("#_select_by_radius_dialog");
                    $selectByRadiusDialog.dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 600,
                        title: 'Select By Radius',
                        buttons: {
                            Save: function (event) {
                                if ($selectByRadiusForm.validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_by_radius", $selectByRadiusForm.serialize(), function (returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                                        }
                                    });
                                    $selectByRadiusDialog.dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $selectByRadiusDialog.dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "add_to_category") {
                    const $addToCategoryForm = $("#_add_to_category_form");
                    const $addToCategoryDialog = $("#_add_to_category_dialog");
                    $addToCategoryDialog.dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 600,
                        title: 'Add To Category',
                        buttons: {
                            Save: function (event) {
                                if ($addToCategoryForm.validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_to_category", $addToCategoryForm.serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $addToCategoryDialog.dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $addToCategoryDialog.dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "remove_from_category") {
                    const $removeFromCategoryForm = $("#_remove_from_category_form");
                    $('#_remove_from_category_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 600,
                        title: 'Remove From Category',
                        buttons: {
                            Save: function (event) {
                                if ($removeFromCategoryForm.validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_from_category", $removeFromCategoryForm.serialize());
                                    $("#_remove_from_category_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_remove_from_category_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                <?php if (canAccessPageCode("DUPLICATEPROCESSING")) { ?>
                if (actionName === "merge_selected") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_selected", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            goToLink("/duplicateprocessing.php?selected=true&return=contacts");
                        }
                    });
                    return true;
                }
                <?php } ?>
                return false;
            }

            function recurringGiftTitle(listName) {
                const $listName = $("#" + listName);
                let titleText = (empty($listName.find("select[id^='recurring_donations_recurring_donation_type_id-']").find("option:selected").val()) ? "" : $listName.find("select[id^='recurring_donations_recurring_donation_type_id-']").find("option:selected").text());
                let extraText = $listName.find("input[id^='recurring_donations_amount-']").val();
                if (!empty(extraText)) {
                    titleText += (empty(titleText) ? "" : ", ") + extraText;
                }
                extraText = $listName.find("input[id^='recurring_donations_start_date-']").val();
                if (!empty(extraText)) {
                    titleText += (empty(titleText) ? "" : ", Started ") + extraText;
                }
                extraText = $listName.find("input[id^='recurring_donations_end_date-']").val();
                if (!empty(extraText)) {
                    titleText += (empty(titleText) ? "" : ", Ends ") + extraText;
                } else {
                    titleText += (empty(titleText) ? "" : ", No End");
                }
                return titleText;
            }

            function setSalutation() {
                const $salutation = $("#salutation");
                const $title = $("#title");
                const $lastName = $("#last_name");
                const $firstName = $("#first_name");
                if (!empty($salutation.val())) {
                    return;
                }
                if ($title.val() === "Mrs" && !empty($lastName.val())) {
                    $salutation.val($title.val() + " " + $lastName.val());
                    return;
                }
                if (($title.val().indexOf(" and ") > 0 || $title.val().indexOf(" & ") > 0) && !($firstName.val().indexOf(" and ") > 0 || $firstName.val().indexOf(" & ") > 0)) {
                    $salutation.val($title.val() + " " + $lastName.val());
                    return;
                }
                if ($firstName.val().length > 1) {
                    $salutation.val($firstName.val());
                    return;
                }
                if (!empty($title.val())) {
                    $salutation.val($title.val() + " " + $lastName.val());
                    return;
                }
                if ($firstName.val().length === 1) {
                    $salutation.val("Friend");
                }
            }

            function displayContact() {
                let displayName = $("#first_name").val();
                const $lastName = $("#last_name");
                const $businessName = $("#business_name");
                displayName += (empty(displayName) || empty($lastName.val()) ? "" : " ") + $lastName.val();
                displayName += (empty(displayName) || empty($businessName.val()) ? "" : ", ") + $businessName.val();
                $("#_contact_display").html(displayName);
            }

            function generateSummary() {
                const $summary = $("#_summary");
                $summary.find(".summary-text").each(function () {
                    const fieldName = $(this).attr("id").replace("_summary_", "");
                    if (empty(fieldName)) {
                        return false;
                    }
                    let thisValue = "";
                    if ($("#" + fieldName + "_selector").length > 0) {
                        const $fieldNameSelector = $("#" + fieldName + "_selector option:selected");
                        thisValue = $fieldNameSelector.val();
                        if (!empty(thisValue)) {
                            thisValue = $fieldNameSelector.text();
                        }
                    } else if ($("#" + fieldName).is("select")) {
                        thisValue = $("#" + fieldName + " option:selected").text();
                    } else if ($("#" + fieldName).is("input[type=checkbox]")) {
                        if ($("#" + fieldName).prop("checked")) {
                            thisValue = "Yes";
                        } else {
                            thisValue = "No";
                        }
                    } else if ($("#" + fieldName).length > 0) {
                        thisValue = $("#" + fieldName).val();
                    }
                    if (!empty(thisValue)) {
                        thisValue = thisValue.trim();
                    }
                    $(this).html(thisValue);
                });
                $summary.find(".empty-hide").each(function () {
                    let fieldValue = $(this).find(".summary-text-wrapper").html();
                    if (!empty(fieldValue)) {
                        fieldValue = fieldValue.trim();
                    }
                    if (empty(fieldValue)) {
                        $(this).addClass("hidden");
                    } else {
                        $(this).removeClass("hidden");
                    }
                });
                const $summaryEmailAddress = $("#_summary_email_address");
                const $emailAddress = $("#email_address");
                if ($summaryEmailAddress.length > 0 && !empty($summaryEmailAddress.html())) {
                    $summaryEmailAddress.html("<a href='mailto:" + $emailAddress.val() + "'>" + $emailAddress.val() + "</a>");
                }
                const $summaryImageId = $("#_summary_image_id");
                const $imageId = $("#image_id");
                if ($summaryImageId.length > 0) {
                    if (!empty($imageId.val())) {
                        $summaryImageId.html("<img alt='Contact Image' src='/getimage.php?id=" + $imageId.val() + "'>");
                    } else {
                        $summaryImageId.html("");
                    }
                }
            }

            function afterDeleteRecord(returnArray) {
                if (returnArray['deleted'] === "1") {
                    return false;
                } else {
                    getRecord($("#primary_id").val());
                    return true;
                }
            }

            function calculateAge() {
                const today = new Date();
                const birthDate = new Date($("#birthdate").val());
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age < 1 || age > 100) {
                    age = "";
                }
                $("#calculated_age").html(age);
            }

            function afterGetRecord(returnArray) {
                if (empty($("#birthdate").val())) {
                    $("#calculated_age").html("");
                } else {
                    calculateAge();
                }
                <?php if (canAccessPageCode("USERMAINT")) { ?>
                const $contactUn = $("#contact_un");
                $contactUn.prop("readonly", !empty($contactUn.val()));
                <?php } ?>
                displayContact();
                const $city = $("#city");
                const $countryId = $("#country_id");
                const $postalCode = $("#postal_code");
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" && !empty($postalCode.val()) ? "9999" : "10"));
                $city.add("#state").prop("readonly", $countryId.val() === "1000" && !empty($postalCode.val()));
                $("#_city_select_row").hide();
                $("#_city_row").show();
                <?php if (canAccessPageSection("addresses")) { ?>
                displayAddresses();
                $("#addresses_country_id").val("1000");
                $("#addresses_city").add("#addresses_state").prop("readonly", true);
                $("#addresses_city_select").hide();
                $("#addresses_city").show();
                $("#_addresses_fields").hide();
                <?php } ?>
                generateSummary();
                $("#_addresses_form_list .addresses-city-select").closest(".basic-form-line").hide();
                if ($("#deleted").val() === "1") {
                    $("#deleted_display").html("<p>This contact is archived!</p>");
                    $("#_delete_button").find(".button-text").html("Unarchive");
                } else {
                    $("#deleted_display").html("");
                    $("#_delete_button").find(".button-text").html("Archive");
                }
                if ($("#primary_id").val() === "") {
                    $(".custom-field-group").hide();
                } else {
                    $(".custom-field-group").show();
                }
                if (returnArray['contact_pw']['data_value'] == 'sso') {
                    $("#_contact_pw_non_sso").addClass("hidden").find("input").prop("disabled", true);
                    $("#_contact_pw_sso").removeClass("hidden");
                    returnArray['contact_pw']['data_value'] = '';
                    <?php if(getPreference("SSO_MAKE_USER_CONTACT_INFO_READONLY")) {?>
                    $("#email_address").prop("readonly", true);
                    $("#first_name").prop("readonly", true);
                    $("#last_name").prop("readonly", true);
                    <?php } ?>
                } else {
                    $("#_contact_pw_non_sso").removeClass("hidden").find("input").prop("disabled", false);
                    $("#_contact_pw_sso").addClass("hidden");
                    <?php if(getPreference("SSO_MAKE_USER_CONTACT_INFO_READONLY")) {?>
                    $("#email_address").prop("readonly", false);
                    $("#first_name").prop("readonly", false);
                    $("#last_name").prop("readonly", false);
                    <?php } ?>
                }
            }
            <?php if (canAccessPageSection("addresses")) { ?>
            function displayAddresses() {
                $(".address-row").remove();
                let addressesArray;
                try {
                    addressesArray = JSON.parse($("#addresses_rows").val());
                } catch (e) {
                    addressesArray = [];
                }
                for (const i in addressesArray) {
                    displayAddressLine(i);
                }
            }

            function displayAddressLine(index) {
                let addressesArray;
                try {
                    addressesArray = JSON.parse($("#addresses_rows").val());
                } catch (e) {
                    addressesArray = [];
                }
                if (index in addressesArray) {
                    let addressLine = "";
                    if (!empty(addressesArray[index]['address_1'])) {
                        addressLine += (empty(addressLine) ? "" : " &bull; ") + addressesArray[index]['address_1'];
                    }
                    if (!empty(addressesArray[index]['address_2'])) {
                        addressLine += (empty(addressLine) ? "" : " &bull; ") + addressesArray[index]['address_2'];
                    }
                    if (!empty(addressesArray[index]['city'])) {
                        addressLine += (empty(addressLine) ? "" : " &bull; ") + addressesArray[index]['city'];
                    }
                    if (!empty(addressesArray[index]['state'])) {
                        addressLine += (empty(addressLine) ? "" : " &bull; ") + addressesArray[index]['state'];
                    }
                    if (!empty(addressesArray[index]['postal_code'])) {
                        addressLine += (empty(addressLine) ? "" : " &bull; ") + addressesArray[index]['postal_code'];
                    }
                    if (!empty(addressesArray[index]['country_id'])) {
                        addressLine += (empty(addressLine) ? "" : " &bull; ") + $("#country_id option[value='" + addressesArray[index]['country_id'] + "']").text();
                    }
                    if (!empty(addressesArray[index]['address_label'])) {
                        addressLine = addressesArray[index]['address_label'] + ": " + addressLine;
                    }
                    if ($("#_address_row_" + index).length === 0) {
                        $("#_addresses_list").append("<tr class='address-row' id='_address_row_" + index + "' data-index='" + index + "'><td id='_address_data_" + index + "'>" + addressLine + "</td><td class='delete-address'><img alt='Delete Icon' src='images/delete.gif' /></td></tr>");
                    } else {
                        $("#_address_data_" + index).html(addressLine);
                    }
                }
            }
            <?php } ?>
        </script>
        <?php
    }

    function beforeSaveRecurringDonation(&$nameValues) {
        if (empty($nameValues['primary_id']) && !$GLOBALS['gUserRow']['administrator_flag'] || hasPageCapability("ONLY_RESPONSIBLE")) {
            $nameValues['user_id'] = $GLOBALS['gUserId'];
        }
        unset($nameValues['account_id']);
        return true;
    }

    function massageRecurringDonation(&$returnArray) {
        if (array_key_exists("account_id", $returnArray)) {
            $accountId = $returnArray['account_id']['data_value'];
            $returnArray['account_id']['data_value'] = getFieldFromId("account_label", "accounts", "account_id", $accountId);
            if (empty($returnArray['account_id']['data_value'])) {
                $returnArray['account_id']['data_value'] = getFieldFromId("account_number", "accounts", "account_id", $accountId);
            }
        }
    }

    function beforeSaveChanges(&$nameValues) {
        if (!empty($nameValues['primary_id'])) {
            $this->iContactTypeId = getFieldFromId("contact_type_id", "contacts", "contact_id", $nameValues['primary_id']);
            $resultSet = executeQuery("select mailing_list_id from contact_mailing_lists where contact_id = ? and date_opted_out is null", $nameValues['primary_id']);
            while ($row = getNextRow($resultSet)) {
                $this->iMailingLists[] = $row['mailing_list_id'];
            }
            $resultSet = executeQuery("select category_id from contact_categories where contact_id = ?", $nameValues['primary_id']);
            while ($row = getNextRow($resultSet)) {
                $this->iCategories[] = $row['category_id'];
            }
        } else {
            $nameValues['date_created'] = date("m/d/Y");
        }
        return true;
    }

    function afterSaveDone($nameValues) {
        $resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 1 and account_token is not null", $nameValues['primary_id']);
        while ($row = getNextRow($resultSet)) {
            $merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
            $merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $row['contact_id'], "merchant_account_id = ?", $merchantAccountId);
            if (!empty($merchantIdentifier)) {
                $eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
                if ($eCommerce && $eCommerce->hasCustomerDatabase()) {
                    $eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $row['account_token'], "account_id" => $row['account_id']));
                }
            }
            executeQuery("update accounts set account_token = null where account_id = ?", $row['account_id']);
        }

        if (!empty($this->iDonationBatchId) && !empty($nameValues['reverse_donation_ids'])) {
            foreach (explode(",", $nameValues['reverse_donation_ids']) as $donationId) {
                $resultSet = executeQuery("select * from donations where donation_id = ? and contact_id = ? and " .
                    "associated_donation_id is null and donation_id not in (select associated_donation_id from " .
                    "donations where associated_donation_id = ?)", $donationId, $nameValues['primary_id'], $donationId);
                if ($row = getNextRow($resultSet)) {
                    $insertSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id,reference_number,account_id," .
                        "donation_batch_id,designation_id,project_name,donation_source_id,amount,anonymous_gift,donation_fee,recurring_donation_id," .
                        "associated_donation_id,receipted_contact_id,notes) values (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?)", $GLOBALS['gClientId'],
                        $nameValues['primary_id'], getFieldFromId("batch_date", "donation_batches", "donation_batch_id", $this->iDonationBatchId),
                        $row['payment_method_id'], $row['reference_number'], $row['account_id'], $this->iDonationBatchId, $row['designation_id'],
                        $row['project_name'], $row['donation_source_id'], ($row['amount'] * -1), $row['anonymous_gift'], ($row['donation_fee'] * -1),
                        $row['recurring_donation_id'], $row['donation_id'], $row['receipted_contact_id'], $row['notes']);
                    Donations::processDonation($insertSet['insert_id']);
                    executeQuery("update donation_batches set user_id = ? where user_id is null and donation_batch_id = ?", $GLOBALS['gUserId'], $this->iDonationBatchId);
                }
            }
        }
        if (!empty($nameValues['user_id']) || !empty($nameValues['contact_un'])) {
            $GLOBALS['gChangeLogNotes'] = "Updating User Subscriptions from Contact Maintenance";
            updateUserSubscriptions($nameValues['primary_id']);
            $GLOBALS['gChangeLogNotes'] = "";
        }
    }

    function afterSaveChanges($nameValues, $actionPerformed) {
        $customFields = CustomField::getCustomFields("contacts");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            if (!$customField->saveData($nameValues)) {
                return $customField->getErrorMessage();
            }
        }
        if (canAccessPageSection("member")) {
            $resultSet = executeQuery("select * from mailing_lists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $updateNecessary = false;
                $contactMailingListValues = array("contact_id" => $nameValues['primary_id'], "mailing_list_id" => $row['mailing_list_id']);
                $contactSet = executeQuery("select * from contact_mailing_lists where contact_id = ? and mailing_list_id = ?",
                    $nameValues['primary_id'], $row['mailing_list_id']);
                if ($contactRow = getNextRow($contactSet)) {
                    if (empty($nameValues['mailing_list_id_' . $row['mailing_list_id']])) {
                        $contactMailingListValues['date_opted_out'] = date("m/d/Y");
                    } else {
                        $contactMailingListValues['date_opted_out'] = "";
                    }
                    $updateNecessary = true;
                } else {
                    if (!empty($nameValues['mailing_list_id_' . $row['mailing_list_id']])) {
                        $contactMailingListValues['date_opted_in'] = date("m/d/Y");
                        $updateNecessary = true;
                    }
                }
                if ($updateNecessary) {
                    $thisDataSource = new DataSource("contact_mailing_lists");
                    $thisDataSource->setSaveOnlyPresent(true);
                    $thisDataSource->saveRecord(array("name_values" => $contactMailingListValues, "primary_id" => $contactRow['contact_mailing_list_id']));
                }
            }
            $resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 1 and client_id = ? " .
                "order by sort_order,description", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $deleteSet = executeQuery("select * from contact_categories where category_id in (select category_id from categories where category_group_id = ?) and " .
                    "contact_id = ?", $row['category_group_id'], $nameValues['primary_id']);
                while ($row1 = getNextRow($deleteSet)) {
                    $thisDataSource = new DataSource("contact_categories");
                    $thisDataSource->deleteRecord(array("primary_id" => $row1['contact_category_id']));
                }
                if (!empty($nameValues['category_group_id_' . $row['category_group_id']])) {
                    $thisDataSource = new DataSource("contact_categories");
                    $thisDataSource->saveRecord(array("name_values" => array("contact_id" => $nameValues['primary_id'], "category_id" => $nameValues['category_group_id_' . $row['category_group_id']]), "primary_id" => ""));
                }
            }
            $resultSet = executeQuery("select * from categories where (category_group_id is null or category_group_id not in (select category_group_id from " .
                "category_groups where inactive = 0 and choose_only_one = 1 and client_id = ?)) and inactive = 0 and client_id = ?",
                $GLOBALS['gClientId'], $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $resultSet1 = executeQuery("select * from contact_categories where category_id = ? and contact_id = ?",
                    $row['category_id'], $nameValues['primary_id']);
                if ($row1 = getNextRow($resultSet1)) {
                    if (empty($nameValues['category_id_' . $row['category_id']])) {
                        $thisDataSource = new DataSource("contact_categories");
                        $thisDataSource->deleteRecord(array("primary_id" => $row1['contact_category_id']));
                    }
                } else {
                    if (!empty($nameValues['category_id_' . $row['category_id']])) {
                        $thisDataSource = new DataSource("contact_categories");
                        $thisDataSource->saveRecord(array("name_values" => array("contact_id" => $nameValues['primary_id'], "category_id" => $row['category_id']), "primary_id" => ""));
                    }
                }
            }
        }
        if (!empty($nameValues['user_id']) && !empty($nameValues['contact_pw'])) {
            $passwordSalt = getRandomString(64);
            $password = hash("sha256", $nameValues['user_id'] . $passwordSalt . $nameValues['contact_pw']);
            executeQuery("update users set password = ?,password_salt = ?,last_password_change = now()," .
                "force_password_change = 1 where user_id = ? and contact_id = ?", $password, $passwordSalt,
                $nameValues['user_id'], $nameValues['primary_id']);
        }
        if (empty($nameValues['user_id']) && !empty($nameValues['contact_un']) && !empty($nameValues['contact_pw'])) {
            $passwordSalt = getRandomString(64);
            $password = hash("sha256", $passwordSalt . $nameValues['contact_pw']);

            $nameValues['contact_un'] = makeCode($nameValues['contact_un'], array("lowercase" => true));
            $checkUserId = getFieldFromId("user_id", "users", "user_name", $nameValues['contact_un'], "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
            if (!empty($checkUserId)) {
                return "User name is unavailable. Choose another";
            }

            $usersTable = new DataTable("users");
            if ($userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $nameValues['primary_id'], "user_name" => strtolower($nameValues['contact_un']),
                "password_salt" => $passwordSalt, "password" => $password, "date_created" => date("Y-m-d H:i:s"))))) {
                $password = hash("sha256", $userId . $passwordSalt . $nameValues['contact_pw']);
                executeQuery("update users set password = ?,force_password_change = 1 where user_id = ?", $password, $userId);
            }
        }
        return true;
    }

    function deleteRecord() {
        $returnArray = array();
        executeQuery("update contacts set deleted = " . ($_POST['deleted'] == 1 ? "0" : "1") . " where client_id = ? and contact_id = ?", $GLOBALS['gClientId'], $_POST['primary_id']);
        if ($_POST['deleted'] == "1") {
            $returnArray['deleted'] = "0";
            $returnArray['info_message'] = getLanguageText("Contact successfully undeleted");
        } else {
            $returnArray['deleted'] = "1";
        }
        ajaxResponse($returnArray);
    }

    function afterGetRecord(&$returnArray) {
        $returnArray['duplicate_message'] = array("data_value" => "");
        $redirectList = "";
        $resultSet = executeQuery("select * from contact_redirect where contact_id = ?", $returnArray['primary_id']['data_value']);
        while ($row = getNextRow($resultSet)) {
            $redirectList .= (empty($redirectList) ? "" : "<br>") . $row['retired_contact_identifier'];
        }
        $returnArray['redirect_list'] = array("data_value" => $redirectList);

        $subscriptionLinks = "";
        $resultSet = executeQuery("select * from recurring_payments where contact_id = ?", $returnArray['primary_id']['data_value']);
        if ($resultSet['row_count'] > 0) {
            ob_start();
            ?>
            <div class='basic-form-line'>
                <label>Recurring Payment Links</label>
                <table class='grid-table'>
                    <tr>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>Next Billing Date</th>
                        <th>End Date</th>
                        <th>Requires Attention</th>
                        <th>Customer Paused</th>
                    </tr>
                    <?php
                    while ($row = getNextRow($resultSet)) {
                        ?>
                        <tr class='recurring-payment' data-recurring_payment_id='<?= $row['recurring_payment_id'] ?>'>
                            <td><?= htmlText(getFieldFromId("description", "recurring_payment_types", "recurring_payment_type_id", $row['recurring_payment_type_id'])) ?></td>
                            <td><?= (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date']))) ?></td>
                            <td><?= (empty($row['next_billing_date']) ? "" : date("m/d/Y", strtotime($row['next_billing_date']))) ?></td>
                            <td><?= (empty($row['end_date']) ? "" : date("m/d/Y", strtotime($row['end_date']))) ?></td>
                            <td><?= (empty($row['requires_attention']) ? "" : "YES") ?></td>
                            <td><?= (empty($row['customer_paused']) ? "" : "YES") ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </div>
            <?php
            $subscriptionLinks .= ob_get_clean();
        }
        $resultSet = executeQuery("select * from contact_subscriptions join subscriptions using (subscription_id) where contact_id = ?", $returnArray['primary_id']['data_value']);
        if ($resultSet['row_count'] > 0) {
            ob_start();
            ?>
            <div class='basic-form-line'>
                <label>Subscription Links</label>
                <table class='grid-table'>
                    <tr>
                        <th>Subscription</th>
                        <th>Start Date</th>
                        <th>Expires</th>
                        <th>Customer Paused</th>
                        <th>Inactive</th>
                    </tr>
                    <?php
                    while ($row = getNextRow($resultSet)) {
                        ?>
                        <tr class='contact-subscription'
                            data-contact_subscription_id='<?= $row['contact_subscription_id'] ?>'>
                            <td><?= htmlText($row['description']) ?></td>
                            <td><?= (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date']))) ?></td>
                            <td><?= (empty($row['expiration_date']) ? "" : date("m/d/Y", strtotime($row['expiration_date']))) ?></td>
                            <td><?= (empty($row['customer_paused']) ? "" : "YES") ?></td>
                            <td><?= (empty($row['inactive']) ? "" : "YES") ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </div>
            <?php
            $subscriptionLinks .= ob_get_clean();
        }
        $returnArray['subscription_links'] = array("data_value" => $subscriptionLinks);

        ob_start();
        $resultSet = executeQuery("select * from help_desk_entries where contact_id = ? order by help_desk_entry_id desc", $returnArray['primary_id']['data_value']);
        if ($resultSet['row_count'] == 0) {
            echo "<p>No Help Desk Tickets</p>";
        } else {
            ?>
            <table class="grid-table">
                <tr>
                    <th>Ticket #</th>
                    <th>Date Submitted</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Date Closed</th>
                </tr>
                <?php
                while ($row = getNextRow($resultSet)) {
                    ?>
                    <tr class="help-desk-row" data-help_desk_entry_id="<?= $row['help_desk_entry_id'] ?>">
                        <td><?= $row['help_desk_entry_id'] ?></td>
                        <td><?= date("m/d/Y", strtotime($row['time_submitted'])) ?></td>
                        <td><?= htmlText($row['description']) ?></td>
                        <td><?= htmlText(getFieldFromId("description", "help_desk_statuses", "help_desk_status_id", $row['help_desk_status_id'])) ?></td>
                        <td><?= (empty($row['time_closed']) ? "" : date("m/d/Y", strtotime($row['time_closed']))) ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        }
        $returnArray['help_desk_entries'] = array("data_value" => ob_get_clean());

        ob_start();
        $resultSet = executeQuery("select * from email_log where contact_id = ? order by time_submitted desc", $returnArray['primary_id']['data_value']);
        if ($resultSet['row_count'] == 0) {
            echo "<p>No Emails</p>";
        } else {
            ?>
            <table class="grid-table">
                <tr>
                    <th>Date Sent</th>
                    <th>Subject</th>
                </tr>
                <?php
                while ($row = getNextRow($resultSet)) {
                    $subject = false;

                    # try to get the actual subject used in the email. Otherwise, use the subject from the email record
                    if (!empty($row['parameters']) && startsWith($row['parameters'], "{")) {
                        $parameters = json_decode($row['parameters']);
                        if (is_array($parameters) && array_key_exists("subject", $parameters)) {
                            $subject = $parameters['subject'];
                        }
                    }
                    if (empty($subject)) {
                        $subject = getFieldFromId("subject", "emails", "email_id", $row['email_id']);
                    }
                    ?>
                    <tr class="email-log-row" data-email_log_id="<?= $row['email_log_id'] ?>">
                        <td><?= date("m/d/Y", strtotime($row['time_submitted'])) ?></td>
                        <td><?= htmlText($subject) ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        }
        $returnArray['emails_sent'] = array("data_value" => ob_get_clean());

        ob_start();
        $resultSet = executeQuery("select * from relationships where related_contact_id = ?", $returnArray['primary_id']['data_value']);
        if ($resultSet['row_count'] > 0) {
            ?>
            <h2>Reverse Relationships</h2>
            <p>These relationships are only editable in the other contact.</p>
            <?php
            while ($row = getNextRow($resultSet)) {
                ?>
                <h4>This contact is
                    the <?= getFieldFromId("description", "relationship_types", "relationship_type_id", $row['relationship_type_id']) ?>
                    of <a href="/contactmaintenance.php?url_page=show&primary_id=<?= $row['contact_id'] ?>"
                          target="_blank"><?= getDisplayName($row['contact_id']) ?></a></h4>
                <?php
            }
        }
        $returnArray['reverse_relationships'] = array("data_value" => ob_get_clean());

        $returnArray['reverse_donation_ids'] = array("data_value" => "", "crc_value" => getCrcValue(""));
        $formsTab = "";
        $resultSet = executeQuery("select * from forms where contact_id = ? and form_definition_id in (select form_definition_id from " .
            "form_definitions where user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = ?))",
            $returnArray['primary_id']['data_value'], $GLOBALS['gUserId']);
        while ($row = getNextRow($resultSet)) {
            $description = (empty($row['description']) ? getFieldFromId("description", "form_definitions", "form_definition_id", $row['form_definition_id']) : $row['description']);
            $formsTab .= "<p>" . $description . ", submitted on " . date("m/d/Y", strtotime($row['date_created'])) . ", <a href='/displayform.php?form_id=" . $row['form_id'] . "' target='_blank'>View Form</a></p>";
        }
        $returnArray['forms_tab'] = array("data_value" => $formsTab);
        $returnArray['custom_field_group_link'] = array("data_value" => "");
        $returnArray['custom_field_group_id'] = array("data_value" => "");
        if (empty($returnArray['hash_code']['data_value'])) {
            $hashCode = md5(uniqid(mt_rand(), true) . $returnArray['first_name']['data_value'] . $returnArray['last_name']['data_value'] . $returnArray['primary_id']['data_value'] . $returnArray['email_address']['data_value'] . $returnArray['date_created']['data_value']);
            executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $returnArray['primary_id']['data_value']);
            $returnArray['hash_code'] = array("data_value" => $hashCode, "crc_value" => getCrcValue($hashCode));
        }
        if (is_array($this->iCustomTabs)) {
            foreach ($this->iCustomTabs as $customTabInfo) {
                if (function_exists($customTabInfo['get_record'])) {
                    $customTabInfo['get_record']($returnArray);
                }
            }
        }
        if (canAccessPageSection("addresses")) {
            $addressesRows = array();
            $resultSet = executeQuery("select * from addresses where contact_id = ? and inactive = 0", $returnArray['primary_id']['data_value']);
            while ($row = getNextRow($resultSet)) {
                $addressesRows[] = $row;
            }
            $returnArray['addresses_rows'] = array("data_value" => jsonEncode($addressesRows), "crc_value" => getCrcValue(jsonEncode($addressesRows)));
        }
        $customFields = CustomField::getCustomFields("contacts");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            $customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
            if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
                $returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
            }
            $returnArray = array_merge($returnArray, $customFieldData);
        }
        if (canAccessPageSection("member")) {
            $resultSet = executeQuery("select * from mailing_lists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $contactSet = executeQuery("select * from contact_mailing_lists where contact_id = ? and mailing_list_id = ? and date_opted_out is null",
                    $returnArray['primary_id']['data_value'], $row['mailing_list_id']);
                if ($contactRow = getNextRow($contactSet)) {
                    $returnArray['mailing_list_id_' . $row['mailing_list_id']] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
                } else {
                    $returnArray['mailing_list_id_' . $row['mailing_list_id']] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
                }
            }
            $resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 1 and client_id = ? " .
                "order by sort_order,description", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $resultSet1 = executeQuery("select * from contact_categories where category_id in (select category_id from categories where " .
                    "category_group_id = ?) and contact_id = ?", $row['category_group_id'], $returnArray['primary_id']['data_value']);
                if ($row1 = getNextRow($resultSet1)) {
                    $returnArray['category_group_id_' . $row['category_group_id']] = array("data_value" => $row1['category_id'], "crc_value" => getCrcValue($row1['category_id']));
                } else {
                    $returnArray['category_group_id_' . $row['category_group_id']] = array("data_value" => "", "crc_value" => getCrcValue(""));
                }
            }
            $resultSet = executeQuery("select * from categories where (category_group_id is null or category_group_id not in (select category_group_id from " .
                "category_groups where inactive = 0 and choose_only_one = 1 and client_id = ?)) and inactive = 0 and client_id = ?",
                $GLOBALS['gClientId'], $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $resultSet1 = executeQuery("select * from contact_categories where category_id = ? and contact_id = ?",
                    $row['category_id'], $returnArray['primary_id']['data_value']);
                if ($row1 = getNextRow($resultSet1)) {
                    $returnArray['category_id_' . $row['category_id']] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
                } else {
                    $returnArray['category_id_' . $row['category_id']] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
                }
            }
        }
        if (canAccessPageSection("orders")) {
            $canManuallyAddOrder = canAccessPageCode("ADDORDER");
            ob_start();
            ?>
            <?php if ($canManuallyAddOrder && !empty($returnArray['primary_id']['data_value'])) { ?>
                <p><a href='/add-order?contact_id=<?= $returnArray['primary_id']['data_value'] ?>'>Add Order</a></p>
            <?php } ?>
            <table class='grid-table'>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Amount</th>
                    <th>Order Time</th>
                    <th>Status</th>
                    <th>Completed</th>
                    <th>Deleted</th>
                </tr>
                <?php
                $resultSet = executeQuery("select *,(select sum(quantity * sale_price) from order_items where order_id = orders.order_id) total_amount," .
                    "(select description from order_status where order_status_id = orders.order_status_id) order_status from orders " .
                    "where contact_id = ? order by order_time desc", $returnArray['primary_id']['data_value']);
                while ($row = getNextRow($resultSet)) {
                    ?>
                    <tr class='order-row'>
                        <td><?= (canAccessPageCode("ORDERDASHBOARD") ? "<a target='_blank' href='/order-dashboard?clear_filter=true&url_page=show&primary_id=" . $row['order_id'] . "'>" : "") . $row['order_id'] . (canAccessPageCode("ORDERDASHBOARD") ? "</a>" : "") ?></td>
                        <td><?= $row['full_name'] ?></td>
                        <td><?= number_format($row['total_amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_change'], 2) ?></td>
                        <td><?= date("m/d/Y g:ia", strtotime($row['order_time'])) ?></td>
                        <td><?= $row['order_status'] ?></td>
                        <td><?= (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed']))) ?></td>
                        <td><?= (empty($row['deleted']) ? "" : "YES") ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
            $returnArray['orders_tab'] = array("data_value" => ob_get_clean());
        }
        $hasCompletedEvents = false;
        if (canAccessPageSection("events")) {
            ob_start();
            ?>
            <table class='grid-table header-sortable'>
                <tr>
                    <th>ID</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Start Date</th>
                </tr>
                <?php
                $resultSet = executeQuery("select *,(select event_attendance_status_id from event_registrants where event_id = events.event_id and contact_id = ? order by event_registrant_id desc limit 1) event_attendance_status_id from events " .
                    "where contact_id = ? or event_id in (select event_id from event_registrants where contact_id = ?) order by start_date desc", $returnArray['primary_id']['data_value'], $returnArray['primary_id']['data_value'], $returnArray['primary_id']['data_value']);
                while ($row = getNextRow($resultSet)) {
                    $locationDescription = getFieldFromId("description", "locations", "location_id", $row['location_id']);
                    if (empty($locationDescription)) {
                        $facilitySet = executeQuery("select description from facilities where facility_id in (select facility_id from event_facilities where event_id = ?)", $row['event_id']);
                        if ($facilityRow = getNextRow($facilitySet)) {
                            $locationDescription = $facilityRow['description'];
                        }
                    }
                    ?>
                    <tr class='event-row'>
                        <td><?= (canAccessPageCode("EVENTMAINT") ? "<a target='_blank' href='/eventmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $row['event_id'] . "'>" : "") . $row['event_id'] . (canAccessPageCode("EVENTMAINT") ? "</a>" : "") ?></td>
                        <td><?= $row['description'] ?></td>
                        <td><?= getFieldFromId("description", "event_types", "event_type_id", $row['event_type_id']) ?></td>
                        <td><?= $locationDescription ?></td>
                        <td><?= htmlText(getFieldFromId("description", "event_attendance_statuses", "event_attendance_status_id", $row['event_attendance_status_id'])) ?></td>
                        <td><?= date("m/d/Y", strtotime($row['start_date'])) ?></td>
                    </tr>
                    <?php
                    $hasCompletedEvents = true;
                }
                ?>
            </table>
            <?php
            $returnArray['completed_events'] = array("data_value" => ob_get_clean());
        }
        $returnArray['regenerate_certificates_wrapper'] = array('data_value' => "");
        if ($hasCompletedEvents && canAccessPageCode("EVENTREGISTRANTSTATUSMAINT")) {
            $resultSet = executeQuery("select count(*) from event_attendance_statuses where fragment_id is not null and client_id = ?", $GLOBALS['gClientId']);
            if($row = getNextRow($resultSet)) {
                if($row['count(*)'] > 0) {
                    $returnArray['regenerate_certificates_wrapper']['data_value'] = <<< EOT
<div class='basic-form-line-messages'>
    <span class='help-label'>If certificates for completed classes have incorrect data (such as contact name), click here to regenerate them.</span>
    <button id='regenerate_certificates'>Regenerate Class Certificates</button>
</div>
EOT;
                }
            }
        }
        if (canAccessPageSection("donations")) {
        ob_start();
        $year = date("Y");
        $pastGiving = array();
        for ($x = 4; $x >= 0; $x--) {
            $giftCount = 0;
            $totalAmount = 0;
            $resultSet = executeQuery("select count(*),sum(amount) from donations where designation_id in (select designation_id from designations where not_tax_deductible = 0) and contact_id = ? and donation_date between ? and ?",
                $returnArray['primary_id']['data_value'], ($year - $x) . "-01-01", ($year - $x) . "-12-31");
            if ($row = getNextRow($resultSet)) {
                $giftCount = $row['count(*)'];
                $totalAmount = $row['sum(amount)'];
            }
            $pastGiving[($year - $x)] = array("gift_count" => $giftCount, "total_amount" => $totalAmount);
        }
        if (!empty($this->iDonationBatchId)) {
            $batchNumber = getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $this->iDonationBatchId);
            ?>
            <p>Batch Number <?= $batchNumber ?> is open and can have backout transactions added to it.</p>
            <?php
        }
        ?>
        <table class='grid-table'>
            <tr>
                <th class='highlighted-text'>Past Tax-Deductible Giving</th>
                <?php
                foreach ($pastGiving as $year => $giftInfo) {
                    ?>
                    <th class='align-right'><?= $year ?></th>
                    <?php
                }
                ?>
            </tr>
            <tr>
                <th>Number of Gifts</th>
                <?php
                foreach ($pastGiving as $year => $giftInfo) {
                    ?>
                    <td class='align-right'><?= $giftInfo['gift_count'] ?></td>
                    <?php
                }
                ?>
            </tr>
            <tr>
                <th>Total Amount</th>
                <?php
                foreach ($pastGiving as $year => $giftInfo) {
                    ?>
                    <td class='align-right'><?= number_format($giftInfo['total_amount'], 2) ?></td>
                    <?php
                }
                ?>
            </tr>
        </table>
        <?php
        $year = date("Y");
        $pastGiving = array();
        $totalGifts = 0;
        for ($x = 4; $x >= 0; $x--) {
            $giftCount = 0;
            $totalAmount = 0;
            $resultSet = executeQuery("select count(*),sum(amount) from donations where designation_id in (select designation_id from designations where not_tax_deductible = 1) and contact_id = ? and donation_date between ? and ?",
                $returnArray['primary_id']['data_value'], ($year - $x) . "-01-01", ($year - $x) . "-12-31");
            if ($row = getNextRow($resultSet)) {
                $giftCount = $row['count(*)'];
                $totalAmount = $row['sum(amount)'];
            }
            $pastGiving[($year - $x)] = array("gift_count" => $giftCount, "total_amount" => $totalAmount);
            $totalGifts += $giftCount;
        }
        if ($totalGifts > 0) {
            ?>
            <table class='grid-table'>
                <tr>
                    <th class='highlighted-text'>Past NON Tax-Deductible Payments</th>
                    <?php
                    foreach ($pastGiving as $year => $giftInfo) {
                        ?>
                        <th class='align-right'><?= $year ?></th>
                        <?php
                    }
                    ?>
                </tr>
                <tr>
                    <th>Number of Gifts</th>
                    <?php
                    foreach ($pastGiving as $year => $giftInfo) {
                        ?>
                        <td class='align-right'><?= $giftInfo['gift_count'] ?></td>
                        <?php
                    }
                    ?>
                </tr>
                <tr>
                    <th>Total Amount</th>
                    <?php
                    foreach ($pastGiving as $year => $giftInfo) {
                        ?>
                        <td class='align-right'><?= number_format($giftInfo['total_amount'], 2) ?></td>
                        <?php
                    }
                    ?>
                </tr>
            </table>
        <?php } ?>
        <h3>Only the last 30 donations are displayed</h3>
        <table class='grid-table'>
            <tr>
                <th>ID</th>
                <th>When</th>
                <th>Method</th>
                <th>Account</th>
                <th>Ref #</th>
                <th>Batch</th>
                <th>For</th>
                <th>Recur</th>
                <th>Notes</th>
                <th>Receipt</th>
                <th>Amount</th>
                <?php if (!empty($this->iDonationBatchId)) { ?>
                    <th></th>
                <?php } ?>
                <?php if (canAccessPageCode("SENDEMAIL")) { ?>
                    <th></th>
                <?php } ?>
                <?php if (canAccessPageCode("DONATIONMAINT")) { ?>
                    <th></th>
                <?php } ?>
            </tr>
            <?php
            $resultSet = executeQuery("select * from donations where contact_id = ? order by donation_date desc limit 100", $returnArray['primary_id']['data_value']);
            while ($row = getNextRow($resultSet)) {
                $dateCompleted = getFieldFromId("date_completed", "donation_batches", "donation_batch_id", $row['donation_batch_id']);
                $associatedDonationId = getFieldFromId("donation_id", "donations", "associated_donation_id", $row['donation_id']);
                $backout = (empty($associatedDonationId) && empty($row['associated_donation_id']) && (empty($row['donation_batch_id']) || !empty($dateCompleted)));
                $account = getFieldFromId("account_label", "accounts", "account_id", $row['account_id']);
                if (empty($account)) {
                    $account = getFieldFromId("account_number", "accounts", "account_id", $row['account_id']);
                }
                ?>
                <tr class='donation-row' data-donation_id='<?= $row['donation_id'] ?>'>
                    <td><?= $row['donation_id'] ?></td>
                    <td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
                    <td><?= getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) ?></td>
                    <td><?= $account ?></td>
                    <td><?= $row['reference_number'] ?></td>
                    <td><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></td>
                    <td><?= getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']) ?>
                        - <?= getFieldFromId("description", "designations", "designation_id", $row['designation_id']) ?></td>
                    <td><?= (empty($row['recurring_donation_id']) ? "" : "YES") ?></td>
                    <td><?= $row['notes'] ?></td>
                    <td><?= (empty($row['receipted_contact_id']) ? "" : getDisplayName($row['receipted_contact_id'])) ?></td>
                    <td class='align-right'><?= number_format($row['amount'], 2) ?></td>
                    <?php if (!empty($this->iDonationBatchId)) { ?>
                        <td class='align-center'><?= ($backout ? "<span class='fa fa-undo reverse-donation' title='Backout this donation'></span>" : "") ?></td>
                    <?php } ?>
                    <?php if (canAccessPageCode("SENDEMAIL")) { ?>
                        <td class='align-center'><span class='fad fa-paper-plane donation-receipt'
                                                       title="Resend Receipt"></span></td>
                    <?php } ?>
                    <?php if (canAccessPageCode("DONATIONMAINT")) { ?>
                        <td class='align-center'><span class='fad fa-print view-donation-receipt'
                                                       title="View Receipt"></span></td>
                    <?php } ?>
                </tr>
                <?php
            }
            $returnArray['donations_tab_contents'] = array("data_value" => ob_get_clean());
            }
        $userId = Contact::getContactUserId($returnArray['primary_id']['data_value']);
        $returnArray['create_contact_user'] = array("data_value" => "");
        if (empty($userId) && !empty($returnArray['primary_id']['data_value'])) {
            $emailId = getFieldFromId("email_id", "emails", "email_code", "EMAIL_CONTACT_USER", "inactive = 0");
            if (!empty($emailId) && !empty($returnArray['email_address']['data_value'])) {
                $returnArray['create_contact_user']['data_value'] = "<p><button id='invite_create_user'>Invite to create user</button></p>";
            }
        }

        if (canAccessPageCode("USERMAINT")) {
            $returnArray['user_id'] = array("data_value" => $userId);
            $userRow = getRowFromId("users", "contact_id", $returnArray['primary_id']['data_value']);
            $userName = $userRow['user_name'];
            $returnArray['contact_un'] = array("data_value" => $userName, "crc_value" => getCrcValue($userName));
            if (startsWith($userRow['password'], "SSO_")) {
                $returnArray['contact_pw'] = array("data_value" => "sso", "crc_value" => getCrcValue(""));
            } else {
                $returnArray['contact_pw'] = array("data_value" => "", "crc_value" => getCrcValue(""));
            }
            $loyaltyPrograms = "";
            $resultSet = executeQuery("select * from loyalty_programs join loyalty_program_points using (loyalty_program_id) where user_id = ?", $userId);
            while ($row = getNextRow($resultSet)) {
                if (empty($loyaltyPrograms)) {
                    $loyaltyPrograms .= "<h3>Loyalty Program Points</h3>";
                }
                $loyaltyPrograms .= "<p>" . $row['description'] . ": " . $row['point_value'] . "</p>";
            }
            $returnArray['loyalty_points'] = array("data_value" => $loyaltyPrograms);
        }
        $returnArray['simulate_user_wrapper'] = array('data_value' => "");
        if (canAccessPageCode("SIMULATEUSER") && !empty($userId)) {
            $allowAdministratorLogin = getPreference("allow_administrator_login");
            if ($GLOBALS['gUserRow']['superuser_flag'] || $allowAdministratorLogin) {
                $query = ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0 and full_client_access = 0" .
                    (empty($GLOBALS['gUserRow']['full_client_access']) ? " and administrator_flag = 0" : ""));
                $resultSet = executeQuery("select * from users where user_id = ? and inactive = 0 and client_id = ?" .
                    (empty($query) ? "" : " and " . $query), $userId, $GLOBALS['gClientId']);
                if ($row = getNextRow($resultSet)) {
                    $returnArray['simulate_user_wrapper']['data_value'] = "<button id='simulate_user'>Simulate User</button>";
                }
            }
        }
    }

    function addCustomFieldsBeforeAddress() {
        $this->addCustomFields("contacts_before_address");
    }

    function addCustomFields($customFieldGroupCode = "") {
        $customFieldGroupCode = strtoupper($customFieldGroupCode);
        if (empty($customFieldGroupCode)) {
            $resultSet = executeQuery("select * from custom_field_groups where link_url is not null and inactive = 0 and " .
                "client_id = ? and custom_field_group_id in (select custom_field_group_id from custom_field_group_links " .
                "where custom_field_id in (select custom_field_id from custom_fields where custom_field_type_id = " .
                "(select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS'))) order by sort_order,description", $GLOBALS['gClientId']);
            if ($resultSet['row_count'] > 0) {
                ?>
                <div class="basic-form-line" id="_custom_field_group_id_row">
                    <p><label for="custom_field_group_id" class="">Custom Field Group</label></p>
                    <select id="custom_field_group_id" name="custom_field_group_id">
                        <option value="">[Select]</option>
                        <?php
                        while ($row = getNextRow($resultSet)) {
                            ?>
                            <option data-link_url="<?= str_replace('"', '', $row['link_url']) ?>"
                                    data-code="<?= strtolower($row['custom_field_group_code']) ?>"
                                    value="<?= $row['custom_field_group_id'] ?>"><?= htmlText($row['description']) ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_custom_field_group_link_row">
                    <input id="custom_field_group_link" name="custom_field_group_link" readonly="readonly" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>
                <hr>
                <?php
            }
        }
        $customFields = CustomField::getCustomFields("contacts", $customFieldGroupCode);
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            echo $customField->getControl(array("basic_form_line" => true));
        }
        $usedCustomFieldGroupCodes = array("contacts_before_address", "contacts_after_address");
        if (empty($customFieldGroupCode)) {
            $resultSet = executeQuery("select * from custom_field_groups where client_id = ? and inactive = 0 and custom_field_group_id in (select custom_field_group_id from custom_field_group_links " .
                "where custom_field_id in (select custom_field_id from custom_fields where custom_field_type_id = " .
                "(select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS'))) order by sort_order,description", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                if (!in_array(strtolower($row['custom_field_group_code']), $usedCustomFieldGroupCodes)) {
                    if (canAccessPageCode("CONTACTCUSTOMDATA")) {
                        ?>
                        <h3><a class="custom-field-group" href="#"
                               data-custom_field_group_id="<?= $row['custom_field_group_id'] ?>"><?= htmlText($row['description']) ?></a>
                        </h3>
                        <?php
                    } else {
                        echo "<h3>" . htmlText($row['description']) . "</h3>";
                    }
                    $this->addCustomFields($row['custom_field_group_code']);
                }
            }
        }
    }

    function addCustomFieldsAfterAddress() {
        $this->addCustomFields("contacts_after_address");
    }

    function membershipFields() {
    $resultSet = executeQuery("select * from mailing_lists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
    if ($resultSet['row_count'] > 0) {
        ?>
        <h3>Mailing Lists</h3>
        <table>
            <?php
            while ($row = getNextRow($resultSet)) {
                ?>
                <div class="basic-form-line" id="_mailing_list_id_<?= $row['mailing_list_id'] ?>_row">
                    <label for="mailing_list_id_<?= $row['mailing_list_id'] ?>"></label>
                    <input tabindex="10" class="" type="checkbox" value="1"
                           name="mailing_list_id_<?= $row['mailing_list_id'] ?>"
                           id="mailing_list_id_<?= $row['mailing_list_id'] ?>"><label class="checkbox-label"
                                                                                      for="mailing_list_id_<?= $row['mailing_list_id'] ?>"><?= htmlText($row['description']) ?></label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>
                <?php
            }
            ?>
        </table>
        <?php
    }
    $resultSet = executeQuery("select count(*) from categories where inactive = 0 and (category_group_id is null or " .
        "category_group_id in (select category_group_id from category_groups where inactive = 0 and client_id = ?)) and " .
        "client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
    if ($row = getNextRow($resultSet)) {
        $rowCount = $row['count(*)'];
    } else {
        $rowCount = 0;
    }
    if ($rowCount > 0) {
        ?>
        <h3>Categories</h3>
        <?php
    }
    $resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 1 and client_id = ? " .
        "order by sort_order,description", $GLOBALS['gClientId']);
    if ($resultSet['row_count'] > 0) {
    while ($row = getNextRow($resultSet)) {
        $thisColumn = new DataColumn("category_group_id_" . $row['category_group_id']);
        $thisColumn->setControlValue("data_type", "select");
        $thisColumn->setControlValue("form_label", $row['description']);
        $choices = array();
        $resultSet1 = executeQuery("select * from categories where inactive = 0 and category_group_id = ? order by sort_order,description", $row['category_group_id']);
        while ($row1 = getNextRow($resultSet1)) {
            $choices[$row1['category_id']] = array("key_value" => $row1['category_id'], "description" => $row1['description'], "inactive" => $row1['inactive'] == 1);
        }
        if (empty($choices)) {
            continue;
        }
        $thisColumn->setControlValue("choices", $choices);
        ?>
        <div class="basic-form-line" id="_category_group_id_<?= $row['category_group_id'] ?>_row">
            <label for="category_group_id_<?= $row['category_group_id'] ?>"><?= htmlText($row['description']) ?></label>
            <?= $thisColumn->getControl($this) ?>
            <div class='basic-form-line-messages'><span class="help-label"></span><span
                        class='field-error-text'></span></div>
        </div>
        <?php
    }
    ?>
</table>
<?php
}
$resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 0 and client_id = ? " .
    "and category_group_id in (select category_group_id from categories where inactive = 0 and client_id = ?) " .
    "order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
    ?>
    <h3><?= htmlText($row['description']) ?></h3>
    <div class="category-table">
        <?php
        $resultSet1 = executeQuery("select * from categories where category_group_id = ? and inactive = 0 and " .
            "client_id = ?", $row['category_group_id'], $GLOBALS['gClientId']);
        while ($row1 = getNextRow($resultSet1)) {
            ?>
            <div class="basic-form-line">
                <label></label>
                <input tabindex="10" class="" type="checkbox" value="1"
                       name="category_id_<?= $row1['category_id'] ?>"
                       id="category_id_<?= $row1['category_id'] ?>"><label class="checkbox-label"
                                                                           for="category_id_<?= $row1['category_id'] ?>"><?= htmlText($row1['description']) ?></label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span
                            class='field-error-text'></span></div>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}
$resultSet = executeQuery("select * from categories where category_group_id is null and inactive = 0 and client_id = ? " .
    "order by sort_order,description", $GLOBALS['gClientId']);
if ($resultSet['row_count'] > 0) {
    ?>
    <h3>General Categories</h3>
    <div class="category-table">
        <?php
        while ($row = getNextRow($resultSet)) {
            ?>
            <div class="basic-form-line">
                <label></label>
                <input tabindex="10" class="" type="checkbox" value="1"
                       name="category_id_<?= $row['category_id'] ?>"
                       id="category_id_<?= $row['category_id'] ?>"><label class="checkbox-label"
                                                                          for="category_id_<?= $row['category_id'] ?>"><?= htmlText($row['description']) ?></label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span
                            class='field-error-text'></span></div>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}
}

    function filterTextProcessing($filterText) {
        if (!empty($filterText)) {
            $parts = explode(" ", $filterText);
            if (count($parts) == 2) {
                $whereStatement = "(first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
                    " and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")";
                foreach ($this->iSearchFields as $fieldName) {
                    $whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
                }
            } elseif (count($parts) == 3) {
                $whereStatement = "(first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
                    " and middle_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") .
                    " and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[2] . "%") . ")";
                foreach ($this->iSearchFields as $fieldName) {
                    $whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
                }
            } else {
                $phoneSearchText = "";
                for ($x = 0; $x < strlen($filterText); $x++) {
                    if (is_numeric(substr($filterText, $x, 1))) {
                        $phoneSearchText .= substr($filterText, $x, 1);
                    }
                }
                $whereStatement = "";
                if (!empty($phoneSearchText) && is_numeric($phoneSearchText) && (strlen($phoneSearchText) == 10 || (strlen($phoneSearchText) == 11 && startsWith($phoneSearchText, "1")))) {
                    $whereStatement = "contact_id in (select contact_id from phone_numbers where phone_number = " . $GLOBALS['gPrimaryDatabase']->makeParameter(formatPhoneNumber($phoneSearchText)) . ")";
                }
                $whereStatement .= (empty($whereStatement) ? "" : " or ") . "exists (select contact_id from contact_redirect where client_id = " . $GLOBALS['gClientId'] .
                    " and contact_id = contacts.contact_id and retired_contact_identifier = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")" .
                    (!is_numeric($filterText) ? "" : " or contacts.contact_id = " . $GLOBALS['gPrimaryDatabase']->makeNumberParameter($filterText));
                foreach ($this->iSearchFields as $fieldName) {
                    if ($fieldName != "contacts.contact_id" && $fieldName != "contact_id") {
                        $whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
                    }
                }
                $whereStatement .= " or contact_id in (select primary_identifier from custom_field_data where text_data like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%") .
                    " and custom_field_id in (select custom_field_id from custom_fields where client_id = " . $GLOBALS['gClientId'] . " and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')))";
                $whereStatement .= " or contact_id in (select contact_id from contact_identifiers where identifier_value like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%") . ")";
            }
            $this->iDataSource->addFilterWhere($whereStatement);
        }
    }

    function checkTask(&$saveDataArray) {
        if (empty($saveDataArray['description']) && empty($saveDataArray['detailed_description']) && empty($saveDataArray['assigned_user_id']) &&
            empty($saveDataArray['task_type_id']) && empty($saveDataArray['date_due']) && empty($saveDataArray['date_completed'])) {
            if (!empty($saveDataArray['task_id'])) {
                echo "Delete";
                executeQuery("delete from tasks where task_id = ?", $saveDataArray['task_id']);
            }
            return false;
        }
        return true;
    }

    function hiddenElements() {
        ?>
        <div id="_add_to_category_dialog" class="dialog-box">
            <p>Add Selected Contacts to this category</p>
            <form id="_add_to_category_form">
                <div class="basic-form-line" id="_contact_category_id_row">
                    <label for="category_id">Contact Category</label>
                    <select id="category_id" name="category_id" class="validate[required]">
                        <option value="">[Select]</option>
                        <?php
                        $resultSet = executeQuery("select * from categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            ?>
                            <option value="<?= $row['category_id'] ?>"><?= htmlText($row['description']) ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>
            </form>
        </div>

        <div id="_remove_from_category_dialog" class="dialog-box">
            <p>Remove Selected Contacts from this category</p>
            <form id="_remove_from_category_form">
                <div class="basic-form-line" id="_category_id_row">
                    <label for="category_id">Contact Category</label>
                    <select id="category_id" name="category_id" class="validate[required]">
                        <option value="">[Select]</option>
                        <?php
                        $resultSet = executeQuery("select * from categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            ?>
                            <option value="<?= $row['category_id'] ?>"><?= htmlText($row['description']) ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>
            </form>
        </div>

        <div id="_select_by_radius_dialog" class="dialog-box">
            <form id="_select_by_radius_form">
                <p>Select contacts within the radius of a zip code (US only).</p>
                <div class="basic-form-line" id="_central_postal_code_row">
                    <label for="central_postal_code">Zip Code</label>
                    <input id="central_postal_code" name="central_postal_code" class="validate[required]" size="6"
                           maxlength="5">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_radius_miles_row">
                    <label for="radius_miles">Radius (miles)</label>
                    <input id="radius_miles" name="radius_miles"
                           class="validate[required,custom[integer],min[1]] align-right" size="4" maxlength="4">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span
                                class='field-error-text'></span></div>
                </div>

            </form>
        </div>

        <div id="_activity_log" class="dialog-box">
        </div>
        <?php
    }

    function hasRelationshipTypes() {
        $resultSet = executeQuery("select count(*) from relationship_types where client_id = ?", $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            if ($row['count(*)'] > 0) {
                return true;
            }
        }
        return false;
    }

    function hasDonations() {
        $resultSet = executeQuery("select count(*) from donations where client_id = ?", $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            if ($row['count(*)'] > 0) {
                return true;
            }
        }
        $resultSet = executeQuery("select count(*) from donation_commitment_types where client_id = ?", $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            if ($row['count(*)'] > 0) {
                return true;
            }
        }
        return false;
    }

    function hasRecurringDonations() {
        $resultSet = executeQuery("select count(*) from recurring_donation_types where client_id = ?", $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            if ($row['count(*)'] > 0) {
                return true;
            }
        }
        return false;
    }

    function hasPaymentMethods() {
        $resultSet = executeQuery("select count(*) from payment_methods where client_id = ?", $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            if ($row['count(*)'] > 0) {
                return true;
            }
        }
        return false;
    }

    function hasSubscriptions() {
        $resultSet = executeQuery("select count(*) from subscriptions where client_id = ?", $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            if ($row['count(*)'] > 0) {
                return true;
            }
        }
        return false;
    }

    function jqueryTemplates() {
        $customFields = CustomField::getCustomFields("contacts");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            echo $customField->getTemplate();
        }
    }
}

$pageObject = new ContactMaintenancePage("contacts");
$pageObject->displayPage();
