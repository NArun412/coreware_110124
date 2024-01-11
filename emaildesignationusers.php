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

$GLOBALS['gPageCode'] = "EMAILDESIGNATIONUSERS";
require_once "shared/startup.inc";

class EmailDesignationUsersPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "send_emails":
				if (empty($_POST['email_id'])) {
					$emailId = getFieldFromId("email_id", "emails", "email_code", "EMAIL_DESIGNATION_USER",  "inactive = 0");
				} else {
					$emailId = getFieldFromId("email_id", "emails", "email_id", $_POST['email_id'],  "inactive = 0");
				}
				if (empty($emailId)) {
					$body = "<p>You can create an account on the Donor Management System, with which you can check your past and pending giving reports. " .
						"This is a supplement to the normal giving report that you receive at each payroll. With this new program, you can see past giving reports and " .
						"see gifts that will be included in the next payroll. To create your user account, go to http://%http_host%/createdesignationuser.php</p><p>This site is SSL secure. " .
						"It is as secure as your banks website.</p><p>This will not replace your e-mailed giving reports.</p><p>PLEASE do not use spaces in your user name. If you do the system will " .
						"make a space into an underscore.</p><p>The information you will need to create this account is:</p><p>Office Code: %office_code%</p><p>Designation Code: " .
						"%designation_code%</p><p>Your Contact ID: %contact_id%</p><p>Email Address: %email_address%</p>";
					$subject = "User Account";
				} else {
					$body = $subject = "";
				}
				$reportContent = "";
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("designation_")) == "designation_" && !empty($fieldData)) {
						$resultSet = executeQuery("select * from designations where designation_id = ? and client_id = ? and inactive = 0 and " .
							"contact_id is not null and designation_id in (select designation_id from designation_email_addresses)", $fieldData, $GLOBALS['gClientId']);
						if (!$row = getNextRow($resultSet)) {
							continue;
						}
						$row['email_address'] = getFieldFromId("email_address", "designation_email_addresses", "designation_id", $row['designation_id']);
						$designationUserId = getFieldFromId("user_id", "designation_users", "designation_id", $row['designation_id']);
						if (!empty($designationUserId)) {
							continue;
						}
						$substitutions = Contact::getContact($row['contact_id']);
						$substitutions['http_host'] = $_SERVER['HTTP_HOST'];
						$substitutions['office_code'] = getFieldFromId("client_code", "clients", "client_id", $GLOBALS['gClientId']);
						$substitutions['designation_code'] = $row['designation_code'];
						$substitutions['email_address'] = $row['email_address'];
						$result = sendEmail(array("email_credential_code" => $_POST['email_credential_code'], "email_id" => $emailId,
							"subject" => $subject, "body" => $body, "email_addresses" => $row['email_address'], "substitutions" => $substitutions));
						if ($result !== true) {
							$reportContent .= "<p>Can't send email to " . $row['email_address'] . ": " . $result . "</p>";
						} else {
							$reportContent .= "<p>Email Sent to " . $row['email_address'] . "</p>";
						}
					}
				}
				$returnArray['result'] = $reportContent;
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		$resultSet = executeQuery("select count(*) from designations where inactive = 1 and client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			?>
            <p><?= $row['count(*)'] ?> designations are inactive.</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from designations where inactive = 0 and client_id = ? and designation_id in (select designation_id from designation_users)", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			?>
            <p><?= $row['count(*)'] ?> designations already have users.</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from designations where inactive = 0 and client_id = ? and designation_id not in (select designation_id from designation_users) and contact_id is null", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			?>
            <p><?= $row['count(*)'] ?> designations don't have users but aren't assigned a contact.</p>
			<?php
		}
		$resultSet = executeQuery("select count(*) from designations where inactive = 0 and client_id = ? and designation_id not in (select designation_id from designation_users) and designation_id not in (select designation_id from designation_email_addresses)", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			?>
            <p><?= $row['count(*)'] ?> designations don't have users but aren't assigned an email address.</p>
			<?php
		}
		$resultSet = executeQuery("select * from designations where designation_id not in (select designation_id from designation_email_addresses) and contact_id is not null and inactive = 0 and client_id = ? and " .
			"designation_id not in (select designation_id from designation_users)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $row['contact_id']);
			if (!empty($emailAddress)) {
				executeQuery("insert into designation_email_addresses (designation_id,email_address) values (?,?)", $row['designation_id'], $emailAddress);
			}
		}
		$resultSet = executeQuery("select * from designations where inactive = 0 and client_id = ? and designation_id not in (select designation_id from designation_users) and " .
			"contact_id is not null and designation_id in (select designation_id from designation_email_addresses)", $GLOBALS['gClientId']);
		?>
        <p><?= $resultSet['row_count'] ?> designations don't have users but have both a contact and email address. These can be sent emails.</p>
		<?php
		$designations = array();
		while ($row = getNextRow($resultSet)) {
			$designations[] = $row;
		}
		?>
        <div class="basic-form-line" id="_email_credential_code_row">
            <label for="email_credential_code">Email Account (for sending)</label>
            <select id="email_credential_code" name="email_credential_code">
                <option value="">[Use Default]</option>
				<?php
				$resultSet = executeQuery("select * from email_credentials where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['email_credential_code'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_email_id_row">
            <label for="email_id">Email</label>
            <select id="email_id" name="email_id">
                <option value="">[Use Default]</option>
				<?php
				$resultSet = executeQuery("select * from emails where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['email_id'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div id="_button_row">
            <button id="select_all">Select All</button>
            <button id="unselect_all">Unselect All</button>
            <button id="send_emails">Send Emails</button>
        </div>
        <div id="report_form">
            <form id="_edit_form" name="_edit_form">
				<?php
				foreach ($designations as $row) {
					?>
                    <p><input type="checkbox" class="designation-email" id="designation_<?= $row['designation_id'] ?>" name="designation_<?= $row['designation_id'] ?>" value="<?= $row['designation_id'] ?>"><label class="checkbox-label" for="designation_<?= $row['designation_id'] ?>"><?= $row['description'] ?></label></p>
					<?php
				}
				?>
            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#select_all", function () {
                $(".designation-email").prop("checked", true);
            });
            $(document).on("tap click", "#unselect_all", function () {
                $(".designation-email").prop("checked", false);
            });
            $(document).on("tap click", "#send_emails", function () {
                if ($(".designation-email:checked").length === 0) {
                    displayErrorMessage("No designations selected");
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_emails", $("#_edit_form").serialize(), function(returnArray) {
                    if ("result" in returnArray) {
                        $("#report_form").html(returnArray['result']);
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_button_row {
                margin-bottom: 20px;
            }
        </style>
		<?php
	}
}

$pageObject = new EmailDesignationUsersPage();
$pageObject->displayPage();
