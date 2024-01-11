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

$GLOBALS['gPageCode'] = "SCRAPEDEMAILMAINT";
require_once "shared/startup.inc";
require_once "classes/fetch/autoload.php";

class ThisPage extends Page {
	var $iThreshold = 20;
	var $iRowsPerPage = 5;

	function setup() {
		$valuesArray = Page::getPagePreferences();
		if (empty($valuesArray['rows_per_page'])) {
			$valuesArray['rows_per_page'] = $this->iRowsPerPage;
			Page::setPagePreferences($valuesArray);
		}
		$this->iRowsPerPage = $valuesArray['rows_per_page'];
		$this->iThreshold = $this->iRowsPerPage * 4;
		$resultSet = executeQuery("select * from scraped_emails where client_id = ?", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] < $this->iThreshold) {
			$this->getMoreEmails();
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "list", "delete"));
		}
	}

	function getMoreEmails() {
		$emailCredentialRow = getRowFromId("email_credentials", "email_credential_code", "TOUCHPOINTS");
		if (empty($emailCredentialRow)) {
			return false;
		}
		$server = new Server($emailCredentialRow['pop_host'], $emailCredentialRow['pop_port']);
		$server->setAuthentication((empty($emailCredentialRow['pop_user_name']) ? $emailCredentialRow['smtp_user_name'] : $emailCredentialRow['pop_user_name']),
			(empty($emailCredentialRow['pop_password']) ? $emailCredentialRow['smtp_password'] : $emailCredentialRow['pop_password']));
		$securitySetting = (empty($emailCredentialRow['pop_security_setting']) ? $emailCredentialRow['security_setting'] : $emailCredentialRow['pop_security_setting']);
		if (!empty($securitySetting) && $securitySetting != "none") {
			$server->setFlag($securitySetting);
		}
		try {
			$messages = $server->getMessages($this->iThreshold);
		} catch (Exception $error) {
			return false;
		}
		foreach ($messages as $message) {
			$addressTypes = array('to', 'from', 'cc', 'bcc');
			$emailAddresses = array();
			foreach ($addressTypes as $addressType) {
				$emails = $message->getAddresses($addressType);
				foreach ($emails as $thisEmailAddress) {
					if (!array_key_exists($thisEmailAddress, $emailAddresses)) {
						$emailAddresses[$thisEmailAddress] = array("type" => $addressType, "email_address" => $thisEmailAddress);
					}
				}
			}

			$subject = $message->getSubject();
			if (empty($subject)) {
				$subject = "No Subject";
			}
			$subject = getFirstPart($subject, 250, true, true);
			$messageDate = date("Y-m-d", $message->getDate());
			$messageBody = $message->getMessageBody();
			if (!empty($messageBody)) {
				$insertSet = executeQuery("insert into scraped_emails (client_id,subject,content,date_created) values (?,?,?,?)", $GLOBALS['gClientId'], $subject, $messageBody, $messageDate);
				if (!empty($insertSet['sql_error'])) {
					continue;
				}
				$scrapedEmailId = $insertSet['insert_id'];
				if (!empty($scrapedEmailId)) {
					foreach ($emailAddresses as $thisEmailAddress) {
						$insertSet = executeQuery("insert into scraped_email_addresses (scraped_email_id,email_type,email_address) values (?,?,?)", $scrapedEmailId, $thisEmailAddress['type'], $thisEmailAddress['email_address']);
					}
				}
			}
			$message->delete();
			$server->expunge();
		}
		return true;
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_rows_per_page":
				$valuesArray = Page::getPagePreferences();
				$valuesArray['rows_per_page'] = $_GET['rows_per_page'];
				Page::setPagePreferences($valuesArray);
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		$count = 0;
		?>
        <p>Displaying <?= $this->iRowsPerPage ?> at a time. Be sure to check each one and select whether it will just be deleted, skipped, or create a Touchpoint.</p>
        <table id="scraped_emails" class="grid-table">
            <tr>
                <th>Action</th>
                <th>Contact</th>
                <th>Email Addresses</th>
                <th>Subject</th>
                <th>Content</th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from scraped_emails where client_id = ? order by scraped_email_id limit " . $this->iRowsPerPage, $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$contactIds = array();
				$alternateContactIds = array();
				$adminContactIds = array();
				$allEmailList = array();
				$emailSet = executeQuery("select * from scraped_email_addresses where scraped_email_id = ?", $row['scraped_email_id']);
				while ($emailRow = getNextRow($emailSet)) {
					$allEmailList[] = $emailRow;
					$contactSet = executeQuery("select * from contacts where email_address = ?", $emailRow['email_address']);
					while ($contactRow = getNextRow($contactSet)) {
						$adminUserRow = Contact::getUserFromContactId($contactRow['contact_id']);
                        if (empty($adminUserRow['administrator_flag'])) {
	                        $adminUserId = false;
                        } else {
	                        $adminUserId = $adminUserRow['user_id'];
                        }
						if (empty($adminUserId)) {
							if (!array_key_exists($contactRow['contact_id'], $contactIds)) {
								$contactIds[$contactRow['contact_id']] = getDisplayName($contactRow['contact_id']);
							}
						} else {
							if (!array_key_exists($contactRow['contact_id'], $adminContactIds)) {
								$adminContactIds[$contactRow['contact_id']] = getDisplayName($contactRow['contact_id']);
							}
						}
					}
					$contactSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_emails where email_address = ?)", $emailRow['email_address']);
					while ($contactRow = getNextRow($contactSet)) {
						$adminUserRow = Contact::getContactUserId($contactRow['contact_id']);
                        if (empty($adminUserRow['administrator_flag'])) {
	                        $adminUserId = false;
                        } else {
	                        $adminUserId = $adminUserRow['user_id'];
                        }
						if (empty($adminUserId)) {
							if (!array_key_exists($contactRow['contact_id'], $contactIds)) {
								$contactIds[$contactRow['contact_id']] = getDisplayName($contactRow['contact_id']);
							}
						} else {
							if (!array_key_exists($contactRow['contact_id'], $adminContactIds)) {
								$adminContactIds[$contactRow['contact_id']] = getDisplayName($contactRow['contact_id']);
							}
						}
					}
				}
				if (count($contactIds) == 0) {
					$contactIds = $alternateContactIds;
				}
				$saveTaskCount = 0;
				$useContactId = "";
				$contactIdValues = array();
				foreach ($contactIds as $contactId => $displayName) {
					$contactIdValues[] = array("key_value" => $contactId, "description" => $displayName);
				}
				foreach ($adminContactIds as $contactId => $displayName) {
					$contactIdValues[] = array("key_value" => $contactId, "description" => $displayName);
				}
				foreach ($contactIds as $contactId => $displayName) {
					$taskCount = 0;
					if (count($contactIds) > 1) {
						$countSet = executeQuery("select count(*) from tasks where contact_id = ?", $contactId);
						$countRow = getNextRow($countSet);
						$taskCount = $countRow['count(*)'];
					}
					if (empty($useContactId) || $taskCount > $saveTaskCount) {
						$useContactId = $contactId;
						$saveTaskCount = $taskCount;
					}
				}
				if (empty($useContactId)) {
					foreach ($adminContactIds as $contactId => $displayName) {
						$taskCount = 0;
						if (count($contactIds) > 1) {
							$countSet = executeQuery("select count(*) from tasks where contact_id = ?", $contactId);
							$countRow = getNextRow($countSet);
							$taskCount = $countRow['count(*)'];
						}
						if (empty($useContactId) || $taskCount > $saveTaskCount) {
							$useContactId = $contactId;
							$saveTaskCount = $taskCount;
						}
					}
				}
				$takeActionOption = (empty($useContactId) ? "delete" : "create");
				$count++;
				?>
                <tr class="scraped-email">
                    <td>
                        <input type="hidden" id="scraped_email_id_<?= $count ?>" name="scraped_email_id_<?= $count ?>" value="<?= $row['scraped_email_id'] ?>">
                        <input type="hidden" class="email-row-number" id="row_number_<?= $count ?>" name="row_number_<?= $count ?>" value="<?= $count ?>">
                        <select tabindex="10" class='email-action' id="action_<?= $count ?>" name="action_<?= $count ?>">
                            <option value="create"<?= ($takeActionOption == "create" ? " selected" : "") ?>>Create Touchpoint</option>
                            <option value="delete"<?= ($takeActionOption == "delete" ? " selected" : "") ?>>Delete This Email</option>
                            <option value="skip">Skip This Email for now</option>
                        </select>
                    </td>
                    <td>
                        <select tabindex="10" class="contact-id" id="contact_id_<?= $count ?>" name="contact_id_<?= $count ?>">
                            <option value="">[None]</option>
							<?php
							foreach ($contactIdValues as $contactIdInfo) {
								?>
                                <option value="<?= $contactIdInfo['key_value'] ?>"<?= ($useContactId == $contactIdInfo['key_value'] ? " selected" : "") ?>><?= htmlText($contactIdInfo['description']) ?></option>
								<?php
							}
							?>
                        </select>
                    </td>
                    <td>
                        <table class="email-list">
							<?php
							foreach ($allEmailList as $emailRow) {
								?>
                                <tr>
                                    <td><?= $emailRow['email_type'] ?></td>
                                    <td><?= $emailRow['email_address'] ?></td>
                                </tr>
								<?php
							}
							?>
                        </table>
                    </td>
                    <td><input tabindex="10" type="text" class="email-subject validate[required]" id="subject_<?= $count ?>" name="subject_<?= $count ?>" value="<?= htmlText($row['subject']) ?>"></td>
                    <td><textarea tabindex="10" class="email-content validate[required]" id="content_<?= $count ?>" name="content_<?= $count ?>"><?= htmlText($row['content']) ?></textarea></td>
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
			<?php
			$taskTypeCodes = array("EMAIL_SENT", "EMAIL_RECEIVED", "CONTACT_TASK");
			foreach ($taskTypeCodes as $thisTaskTypeCode) {
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", $thisTaskTypeCode, "inactive = 0 and task_type_id in (select task_type_id from task_type_attributes where " .
					"task_attribute_id in (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK'))");
				if (!empty($taskTypeId)) {
					break;
				}
			}
			if (empty($taskTypeId)) {
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "client_id", $GLOBALS['gClientId'], "inactive = 0 and task_type_id in (select task_type_id from task_type_attributes where " .
					"task_attribute_id in (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK'))");
			}
			if (empty($taskTypeId)) {
			?>
            displayErrorMessage("No Task Types for Contacts");
			<?php
			}
			?>
            $("#_page_number_controls").html("<label>Emails Per Page</label> <input tabindex='10' type='text' class='align-right' id='_rows_per_page' value='<?= $this->iRowsPerPage ?>'>");
            $(document).on("change", "#_rows_per_page", function () {
                var rowsPerPage = $(this).val();
                if (empty(rowsPerPage) || !$.isNumeric(rowsPerPage) || rowsPerPage < 5 || rowsPerPage > 20) {
                    $(this).val("<?= $this->iRowsPerPage ?>");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=set_rows_per_page&rows_per_page=" + $(this).val(), $("#_edit_form").serialize());
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function displayFormHeader() {
                $(".page-heading").html("<?= htmlText($GLOBALS['gPageRow']['description']) ?>");
                $("#_edit_form").prepend($("#_page_hidden_elements_content").html());
                $("#_page_hidden_elements_content").html("");
                $(".page-buttons,.page-form-buttons").html($("#_page_buttons_content").html());
                $("#_page_buttons_content").html("");
                $(".page-list-control").hide();
                $(".page-controls").show();
            }
            function saveChanges(afterFunction, regardlessFunction) {
                $(".contact-id").each(function () {
                    if ($(this).closest("tr").find(".email-action").val() == "create" && empty($(this).val())) {
                        $(this).validationEngine("showPrompt", "Required");
                    }
                });
                if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=save_changes", $("#_edit_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            enableButtons($("#_save_button"));
                        } else {
                            setTimeout(function () {
                                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                            }, 2000);
                        }
                    }, function(returnArray) {
                        regardlessFunction();
                    });
                }
            }
            function getRecord(primaryId) {
                return;
            }
        </script>
		<?php
		return true;
	}

	function saveChanges() {
		$returnArray = array();
		$nameValues = $_POST;
		$createCount = 0;
		$deleteCount = 0;
		$skipCount = 0;
		$errorCount = 0;
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("row_number_")) == "row_number_") {
				$rowNumber = substr($fieldName, strlen("row_number_"));
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $nameValues['contact_id_' . $rowNumber]);
				if (empty($contactId)) {
					$errorCount++;
					continue;
				}
				$scrapedEmailRow = getRowFromId("scraped_emails", "scraped_email_id", $nameValues['scraped_email_id_' . $rowNumber]);
				if (empty($scrapedEmailRow)) {
					$errorCount++;
					continue;
				}
				switch ($nameValues['action_' . $rowNumber]) {
					case "create":
						$scrapedEmailRow['subject'] = $nameValues['subject_' . $rowNumber];
						$scrapedEmailRow['content'] = $nameValues['content_' . $rowNumber];
						$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
						$scrapedEmailAddressRow = getRowFromId("scraped_email_addresses", "scraped_email_id", $nameValues['scraped_email_id_' . $rowNumber], "email_address = ?", $emailAddress);
						if ($scrapedEmailAddressRow['email_type'] == "from") {
							$taskTypeCodes = array("EMAIL_RECEIVED", "CONTACT_TASK", "TOUCHPOINT");
						} else {
							$taskTypeCodes = array("EMAIL_SENT", "CONTACT_TASK", "TOUCHPOINT");
						}
						foreach ($taskTypeCodes as $thisTaskTypeCode) {
							$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", $thisTaskTypeCode, "inactive = 0 and task_type_id in (select task_type_id from task_type_attributes where " .
								"task_attribute_id in (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK'))");
							if (!empty($taskTypeId)) {
								break;
							}
						}
						if (empty($taskTypeId)) {
							$taskTypeId = getFieldFromId("task_type_id", "task_types", "client_id", $GLOBALS['gClientId'], "inactive = 0 and task_type_id in (select task_type_id from task_type_attributes where " .
								"task_attribute_id in (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK'))");
						}
						$resultSet = executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task) values " .
							"(?,?,?,?,now(),?,1)", $GLOBALS['gClientId'], $contactId, $scrapedEmailRow['subject'], $scrapedEmailRow['content'], $taskTypeId);
						if (!empty($resultSet['sql_error'])) {
							$errorCount++;
							continue;
						}
						$createCount++;
					case "delete":
						executeQuery("delete from scraped_email_addresses where scraped_email_id = ?", $scrapedEmailRow['scraped_email_id']);
						executeQuery("delete from scraped_emails where scraped_email_id = ?", $scrapedEmailRow['scraped_email_id']);
						$deleteCount++;
						break;
					case "skip":
						$scrapedEmails = new DataTable("scraped_emails");
						$oldScrapedEmailId = $scrapedEmailRow['scraped_email_id'];
						unset($scrapedEmailRow['scraped_email_id']);
						$scrapedEmailId = $scrapedEmails->saveRecord(array("name_values" => $scrapedEmailRow, "primary_id" => ""));
						executeQuery("update scraped_email_addresses set scraped_email_id = ? where scraped_email_id = ?", $scrapedEmailId, $oldScrapedEmailId);
						executeQuery("delete from scraped_email_addresses where scraped_email_id = ?", $oldScrapedEmailId);
						executeQuery("delete from scraped_emails where scraped_email_id = ?", $oldScrapedEmailId);
						$skipCount++;
						break;
				}
			}
		}
		$returnArray['info_message'] = $createCount . " Touchpoints created, " . $deleteCount . " emails deleted, " . $skipCount . " emails skipped" . ($errorCount == 0 ? "" : ", " . $errorCount . " errors");
		ajaxResponse($returnArray);
	}

	function internalCSS() {
		?>
        #content { height: 600px; width: 1000px; }
        div.scraped-email { display: flex; margin: 20px 0; border-bottom: 1px solid rgb(200,200,200); padding: 20px 0; }
        table.email-list td { border: none; }
        table.email-list th { border: none; }
        input.email-subject { width: 250px; }
        textarea.email-content { width: 400px; height: 200px; }
        #_rows_per_page { width: 100px; }
		<?php
	}
}

$pageObject = new ThisPage("scraped_emails");
$pageObject->displayPage();
