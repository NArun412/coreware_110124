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

$GLOBALS['gPageCode'] = "POTENTIALDUPLICATES";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class ThisPage extends Page {

	function mainContent() {
		?>
        <h2>Criteria for duplicate search</h2>
        <form id="_edit_form" class="long-label">
            <div class="basic-form-line" id="_remove_existing_row">
                <label for="remove_existing"></label>
                <input type="checkbox" value="1" checked id="remove_existing" name="remove_existing"><label for="remove_existing" class="checkbox-label">Remove Existing Potential Duplicates</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_include_permanently_skipped_row">
                <label for="include_permanently_skipped"></label>
                <input type="checkbox" value="1" id="include_permanently_skipped" name="include_permanently_skipped"><label for="include_permanently_skipped" class="checkbox-label">Include duplicates that were permanently skipped</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_contact_ids_row">
                <label for="contact_id_1">Contact IDs</label>
                <input class="field-text validate[custom[integer]] align-right" type="text" maxlength="10" size="10" id="contact_id_1" name="contact_id_1"/>
                <input class="field-text validate[custom[integer]] align-right" type="text" maxlength="10" size="10" id="contact_id_2" name="contact_id_2"/>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_date_created_row">
                <label for="date_created">Limit to contacts created since</label>
                <input class="field-text validate[custom[date]] datepicker" type="text" maxlength="12" size="10" id="date_created" name="date_created"/>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_ignore_deleted_row">
                <label for="ignore_deleted"></label>
                <input type="checkbox" value="1" id="ignore_deleted" name="ignore_deleted"><label for="ignore_deleted" class="checkbox-label">Ignore archived contacts</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_only_selected_row">
                <label for="only_selected"></label>
                <input type="checkbox" value="1" id="only_selected" name="only_selected"><label for="only_selected" class="checkbox-label">Limit to selected contacts</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_only_users_row">
                <label for="only_users"></label>
                <input type="checkbox" value="1" id="only_users" name="only_users"><label for="only_users" class="checkbox-label">Limit to users</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_use_phonetics_row">
                <label for="use_phonetics"></label>
                <input type="checkbox" value="1" id="use_phonetics" name="use_phonetics"><label for="use_phonetics" class="checkbox-label">Use phonetics on name</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <h3>Field(s) and optional search text to use for finding duplicates</h3>
            <div class="basic-form-line" id="_first_name_row">
                <label for="first_name"></label>
                <input type="checkbox" value="1" id="first_name" name="first_name">
                <label for="first_name" class="checkbox-label fixed-length">First Name</label>
                <input type="text" size="40" id="first_name_text" name="first_name_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_last_name_row">
                <label for="last_name"></label>
                <input type="checkbox" value="1" id="last_name" name="last_name">
                <label for="last_name" class="checkbox-label fixed-length">Last Name</label>
                <input type="text" size="40" id="last_name_text" name="last_name_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_business_name_row">
                <label for="business_name"></label>
                <input type="checkbox" value="1" id="business_name" name="business_name">
                <label for="business_name" class="checkbox-label fixed-length">Business Name</label>
                <input type="text" size="40" id="business_name_text" name="business_name_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_email_address_row">
                <label for="email_address"></label>
                <input type="checkbox" value="1" checked id="email_address" name="email_address">
                <label for="email_address" class="checkbox-label fixed-length">Email</label>
                <input type="text" size="40" id="email_address_text" name="email_address_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_address_1_row">
                <label for="address_1"></label>
                <input type="checkbox" value="1" id="address_1" name="address_1">
                <label for="address_1" class="checkbox-label fixed-length">Street Address</label>
                <input type="text" size="40" id="address_1_text" name="address_1_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_city_row">
                <label for="city"></label>
                <input type="checkbox" value="1" id="city" name="city">
                <label for="city" class="checkbox-label fixed-length">City</label>
                <input type="text" size="40" id="city_text" name="city_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_state_row">
                <label for="state"></label>
                <input type="checkbox" value="1" id="state" name="state">
                <label for="state" class="checkbox-label fixed-length">State</label>
                <input type="text" size="40" id="state_text" name="state_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_postal_code_row">
                <label for="postal_code"></label>
                <input type="checkbox" value="1" id="postal_code" name="postal_code">
                <label for="postal_code" class="checkbox-label fixed-length">Postal Code</label>
                <input type="text" size="40" id="postal_code_text" name="postal_code_text" class="field-text">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_birthdate_row">
                <label for="birthdate"></label>
                <input type="checkbox" value="1" id="birthdate" name="birthdate">
                <label for="birthdate" class="checkbox-label fixed-length">Birthdate</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <p>
                <button id="submit_form">Find Duplicates</button>
                <button id="process_duplicates">Process Duplicates</button>
            </p>
            <p id="results"></p>
        </form>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#process_duplicates", function () {
                document.location = "/duplicateprocessing.php";
                return false;
            });
            $(document).on("tap click", "#submit_form", function () {
                if (!($("#first_name").prop("checked") || $("#last_name").prop("checked") || $("#business_name").prop("checked") ||
                    $("#email_address").prop("checked") || $("#address_1").prop("checked") ||
                    $("#city").prop("checked") || $("#postal_code").prop("checked") || $("#birthdate").prop("checked") || $("#state").prop("checked") ||
                    ($("#contact_id_1").val() != "" && $("#contact_id_2").val() != ""))) {
                    displayErrorMessage("Nothing Selected");
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=find_dups", $("#_edit_form").serialize(), function(returnArray) {
                    if ("results" in returnArray) {
                        $("#results").html(returnArray['results']);
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "find_dups":
				$queryText = (empty($GLOBALS['gUserRow']['superuser_flag']) ? "contact_id not in (select contact_id from users where superuser_flag = 1) and " : "") . "client_id = ?";
				$queryParameters = array($GLOBALS['gClientId']);
                $queryText .= " and contact_id not in (select contact_id from clients)";
				if (!empty($_POST['date_created'])) {
					$_POST['date_created'] = date("Y-m-d", strtotime($_POST['date_created']));
					$queryText .= ($queryText ? " and " : "") . "date_created >= ?";
					$queryParameters[] = $_POST['date_created'];
				}
				if ($_POST['ignore_deleted']) {
					$queryText .= ($queryText ? " and " : "") . "deleted = 0";
				}
				if ($_POST['only_selected']) {
					$queryText .= ($queryText ? " and " : "") . "contact_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)";
					$queryParameters[] = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
					$queryParameters[] = $GLOBALS['gUserId'];
				}
				if ($_POST['only_users']) {
					$queryText .= ($queryText ? " and " : "") . "contact_id in (select contact_id from users)";
				}
				if (!empty($_POST['contact_id_1']) && !empty($_POST['contact_id_2'])) {
					$queryText .= ($queryText ? " and " : "") . "contact_id in (?,?)";
					$queryParameters[] = $_POST['contact_id_1'];
					$queryParameters[] = $_POST['contact_id_2'];
				}
				$fieldsArray = array("first_name", "last_name", "business_name", "email_address", "address_1", "city", "state", "postal_code", "birthdate");
				foreach ($fieldsArray as $fieldName) {
					if ($_POST[$fieldName]) {
						if (empty($_POST[$fieldName . "_text"])) {
							$queryText .= ($queryText ? " and " : "") . $fieldName . " is not null";
						} else {
							$queryText .= ($queryText ? " and " : "") . $fieldName . " like ?";
							$queryParameters[] = $_POST[$fieldName . "_text"] . "%";
						}
					}
				}
				if ($_POST['remove_existing']) {
					executeQuery("delete from potential_duplicates where user_id = ?", $GLOBALS['gUserId']);
				}
				$dupArray = array();
				$resultSet = executeQuery("select * from contacts where " . $queryText, $queryParameters);
				while ($row = getNextRow($resultSet)) {
					$dupKey = "";
					if ($_POST['use_phonetics']) {
						$row['first_name'] = str_ireplace(array('a', 'e', 'i', 'o', 'u'), '', $row['first_name']);
						$row['last_name'] = str_ireplace(array('a', 'e', 'i', 'o', 'u'), '', $row['last_name']);
						$row['business_name'] = str_ireplace(array('a', 'e', 'i', 'o', 'u'), '', $row['business_name']);
					}
					foreach ($fieldsArray as $fieldName) {
						if ($_POST[$fieldName]) {
							$dupKey .= strtolower((empty($_POST[$fieldName . "_text"]) ? $row[$fieldName] : substr($row[$fieldName], 0, strlen($_POST[$fieldName . "_text"])))) . "|";
						}
					}
					$contactId = $row['contact_id'];
					if (array_key_exists($dupKey, $dupArray)) {
						$dupArray[$dupKey][] = $contactId;
					} else {
						$dupArray[$dupKey] = array($contactId);
					}
				}
				$dupIdArray = array();
				$dupCount = 0;
				foreach ($dupArray as $dupKey => $contactArray) {
					if (count($contactArray) > 1) {
						$maxIndex = count($contactArray) - 1;
						$startIndex = 0;
						while ($startIndex < $maxIndex) {
							$nextIndex = $startIndex + 1;
							while ($nextIndex <= $maxIndex) {
								if (!in_array($contactArray[$startIndex], $dupIdArray)) {
									$dupIdArray[] = $contactArray[$startIndex];
								}
								if (!in_array($contactArray[$nextIndex], $dupIdArray)) {
									$dupIdArray[] = $contactArray[$nextIndex];
								}
								$permanentlySkipped = 0;
								if (empty($_POST['include_permanently_skipped'])) {
									$checkSet = executeQuery("select * from duplicate_exclusions where client_id = ? and ((contact_id = ? and duplicate_contact_id = ?) or " .
										"(duplicate_contact_id = ? and contact_id = ?))", $GLOBALS['gClientId'], $contactArray[$startIndex], $contactArray[$nextIndex],
										$contactArray[$startIndex], $contactArray[$nextIndex]);
									$permanentlySkipped = $checkSet['row_count'];
								}
								if ($permanentlySkipped == 0) {
									$resultSet = executeQuery("insert into potential_duplicates (client_id,contact_id,duplicate_contact_id,user_id) values " .
										"(?,?,?,?)", $GLOBALS['gClientId'], $contactArray[$startIndex], $contactArray[$nextIndex], $GLOBALS['gUserId']);
									$dupCount++;
								}
								$nextIndex++;
							}
							$startIndex++;
						}
					}
				}
				$dupIdCount = count($dupIdArray);
				$returnArray['results'] = $dupCount . " potential duplicates among " . $dupIdCount . " contacts found.";
				ajaxResponse($returnArray);
				break;
		}
	}

	function internalCSS() {
		?>
        <style>
            .basic-form-line label.checkbox-label.fixed-length {
                width: 150px;
                border-bottom: 1px solid rgb(200, 200, 200);
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
