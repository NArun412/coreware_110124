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

$GLOBALS['gPageCode'] = "PETITIONENTRYFORM";
require_once "shared/startup.inc";

$petitionTypeCode = $_GET['code'];
if (empty($petitionTypeCode)) {
	$petitionTypeCode = $_POST['petition_type_code'];
}
$petitionTypeId = getFieldFromId("petition_type_id","petition_types","petition_type_code",$petitionTypeCode,
	"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
	($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
if (empty($petitionTypeId)) {
	header("Location: /");
	exit;
}

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
		case "save_changes":
			if (!empty($_POST['_add_hash'])) {
				$resultSet = $this->iDatabase->executeQuery("select * from add_hashes where add_hash = ?",$_POST['_add_hash']);
				if ($row = $this->iDatabase->getNextRow($resultSet)) {
					$returnArray['error_message'] = "This form has already been saved";
					ajaxResponse($returnArray);
					break;
				}
			}
			if (empty($_POST['petition_type_id'])) {
				$_POST['petition_type_id'] = getFieldFromId("petition_type_id","petition_types","petition_type_code",$_POST['petition_type_code']);
			}
			$petitionTypeId = getFieldFromId("petition_type_id","petition_types","petition_type_id",$_POST['petition_type_id'],
				"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
			if (empty($petitionTypeId)) {
				$returnArray['error_message'] = "Invalid Form";
				ajaxResponse($returnArray);
				break;
			}
			$resultSet = executeQuery("select * from petition_types where petition_type_id = ?",$petitionTypeId);
			$petitionTypeRow = getNextRow($resultSet);

			$this->iDatabase->startTransaction();
			if (!empty($_POST['_add_hash'])) {
				executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())",$_POST['_add_hash']);
			}
			$contactFields = array("first_name","last_name","city","state","postal_code","country_id","email_address");

			if ($petitionTypeRow['create_contact']) {
				$resultSet = executeQuery("select contact_id from contacts where client_id = ? and email_address = ? and first_name = ? and last_name = ?",
					$GLOBALS['gClientId'],$_POST['email_address'],$_POST['first_name'],$_POST['last_name']);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
				} else {
					$contactTable = new DataTable("contacts");
					$parameterArray = array("date_created"=>date("Y-m-d"));

					foreach ($contactFields as $fieldName) {
						$parameterArray[$fieldName] = $_POST[$fieldName];
					}
					if (empty($parameterArray['country_id'])) {
						$parameterArray['country_id'] = "1000";
					}
					$sourceId = getFieldFromId("source_id","sources","source_id",$_COOKIE['source_id'],"inactive = 0");
					if (empty($sourceId)) {
						$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
					}
					$parameterArray['source_id'] = $sourceId;
					$contactId = $contactTable->saveRecord(array("name_values"=>$parameterArray));
					if (!$contactId) {
						$returnArray['error_message'] = getSystemMessage("basic",$contactTable->getErrorMessage());
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (!empty($_POST['phone_number'])) {
					$phoneNumberId = getFieldFromId("phone_number_id","phone_numbers","contact_id",$contactId,"phone_number = ?",$_POST['phone_number']);
					if (empty($phoneNumberId)) {
						executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)",$contactId,$_POST['phone_number']);
					}
				}
				if (!empty($petitionTypeRow['mailing_list_id']) && !empty($_POST['mailing_list_id_' . $petitionTypeRow['mailing_list_id']])) {
					$contactMailingListId = getFieldFromId("contact_mailing_list_id","contact_mailing_lists","contact_id",$contactId,"mailing_list_id = ?",$petitionTypeRow['mailing_list_id']);
					if (empty($contactMailingListId)) {
						executeQuery("insert into contact_mailing_lists (contact_id,mailing_list_id,date_opted_in,ip_address) values (?,?,now(),?)",$contactId,$petitionTypeRow['mailing_list_id'],$_SERVER['REMOTE_ADDR']);
					}
				}
				if (!empty($petitionTypeRow['category_id'])) {
					$contactCategoryId = getFieldFromId("contact_category_id","contact_categories","contact_id",$contactId,"category_id = ?",$petitionTypeRow['category_id']);
					if (empty($contactCategoryId)) {
						executeQuery("insert into contact_categories (contact_id,category_id) values (?,?)",$contactId,$petitionTypeRow['category_id']);
					}
				}
				$_POST['full_name'] = getDisplayName($contactId);
			} else {
				$contactId = "";
			}

			$resultSet = executeQuery("select petition_signature_id from petition_signatures where contact_id = ? and petition_type_id = ?",$contactId,$petitionTypeId);
			if ($resultSet['row_count'] == 0) {
				$petitionSignatureDataSource = new DataSource("petition_signatures");
				$petitionSignatureId = $petitionSignatureDataSource->saveRecord(array("name_values"=>array("petition_type_id"=>$petitionTypeId,"full_name"=>$_POST['full_name'],"contact_id"=>$contactId,"date_created"=>date("Y-m-d"))));
				if (empty($petitionSignatureId)) {
					$returnArray['error_message'] = $petitionSignatureDataSource->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$customFields = CustomField::getCustomFields("petitions");
				foreach ($customFields as $thisCustomField) {
					$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
					if (!$customField->saveData(array_merge($_POST,array("primary_id"=>$petitionSignatureId)))) {
						$returnArray['error_message'] = $customField->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
			} else {
				$returnArray['error_message'] = "You've already signed the petition. Thank you!";
				$this->iDatabase->rollbackTransaction();
				ajaxResponse($returnArray);
				break;
			}
			addActivityLog("Submitted petition '" . $petitionTypeRow['description'] . "'");

			$notificationEmailAddresses = array();
			$substitutions = $_POST;
			$substitutions['date_submitted'] = date("m/d/Y");
			$resultSet = executeQuery("select * from petition_type_emails where petition_type_id = ?",$petitionTypeRow['petition_type_id']);
			while ($row = getNextRow($resultSet)) {
				$notificationEmailAddresses[] = $row['email_address'];
			}

			if (!empty($notificationEmailAddresses)) {
				$body = "<style>p { padding-bottom: 0; margin: 0; } .label-line { padding-top: 20px; font-weight: bold; } .plain-text { font-weight: normal; }</style><p>A petition (" . $petitionTypeRow['description'] . ") was filled out and submitted.</p>";
				foreach ($_POST as $fieldName => $formFieldData) {
					$body .= "<p class='label-line'>" . str_replace("_"," ",$fieldName) . ":</p><p>" . $formFieldData . "</p>\n";
				}
				$emailParameters = array("subject"=>"Petition Filled Out","body"=>$body,"email_addresses"=>$notificationEmailAddresses,"html_already"=>true);
				$emailResult = sendEmail($emailParameters);
			}

			$this->iDatabase->commitTransaction();
			$responseContent = PlaceHolders::massageContent($petitionTypeRow['response_content'], $_POST);
			$returnArray['response'] = $responseContent;
			ajaxResponse($returnArray);
			break;
		}
	}

	function mainContent() {
		$petitionTypeCode = $_GET['code'];
		$petitionTypeId = getFieldFromId("petition_type_id","petition_types","petition_type_code",$petitionTypeCode,
			"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
			($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		$resultSet = executeQuery("select * from petition_types where petition_type_id = ? and client_id = ?",$petitionTypeId,$GLOBALS['gClientId']);
		$petitionTypeRow = getNextRow($resultSet);
		if (!$petitionTypeRow) {
			echo "<h1>Petition not found</h1>";
			return true;
		}
		echo $this->iPageData['content'];
?>
<div id="_form_div">
<form id="_edit_form" name="_edit_form" enctype='multipart/form-data'>
<input type="hidden" id="petition_type_id" name="petition_type_id" value="<?= $petitionTypeRow['petition_type_id'] ?>" />
<input type="hidden" id="petition_type_code" name="petition_type_code" value="<?= $petitionTypeRow['petition_type_code'] ?>" />
<input type="hidden" name="_add_hash" id="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>" />
<?php
		if (empty($petitionTypeRow['create_contact'])) {
?>
<?= createFormControl("petition_signatures","full_name",array("form_label"=>"Your Name","not_null"=>"true")) ?>
<?php
		} else {
?>
<?= createFormControl("contacts","first_name",array("form_label"=>"First Name","not_null"=>"true")) ?>
<?= createFormControl("contacts","last_name",array("form_label"=>"Last Name","not_null"=>"true")) ?>
<?= createFormControl("contacts","city",array("form_label"=>"City","not_null"=>"true")) ?>
<?= createFormControl("contacts","state",array("form_label"=>"State","not_null"=>"false")) ?>

<div class="form-line" id="_state_select_row">
	<label for="state_select" class="">State</label>
	<select tabindex="10" id="state_select" name="state_select" class="validate[required]" data-conditional-required="$('#country_id').val() == 1000">
		<option value="">[Select]</option>
<?php
	foreach (getStateArray() as $stateCode => $state) {
?>
		<option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>

<?= createFormControl("contacts","postal_code",array("form_label"=>"Postal Code","not_null"=>"true")) ?>
<?= createFormControl("contacts","country_id",array("form_label"=>"Country","not_null"=>"true","initial_value"=>"1000")) ?>
<?= createFormControl("phone_numbers","phone_number",array("form_label"=>"Phone","not_null"=>"false")) ?>
<?= createFormControl("contacts","email_address",array("form_label"=>"Email","not_null"=>"true")) ?>
<?php
			if (!empty($petitionTypeRow['mailing_list_id'])) {
?>
<div class="form-line">
	<input tabindex="10" checked="checked" type='checkbox' id="mailing_list_id_<?= $petitionTypeRow['mailing_list_id'] ?>" name="mailing_list_id_<?= $petitionTypeRow['mailing_list_id'] ?>">
	<label class="checkbox-label" for="mailing_list_id_<?= $petitionTypeRow['mailing_list_id'] ?>"><?= htmlText(getFieldFromId("description","mailing_lists","mailing_list_id",$petitionTypeRow['mailing_list_id'])) ?></label>
	<div class='clear-div'></div>
</div>
<?php
			}
			$resultSet = executeQuery("select * from petition_type_custom_fields where petition_type_id = ? order by sequence_number",$petitionTypeRow['petition_type_id']);
			while ($row = getNextRow($resultSet)) {
				$customField = CustomField::getCustomField($row['custom_field_id']);
				echo $customField->getControl();
			}
		}
?>
<p id="_error_message" class="error-message"></p>
<p id="_submit_paragraph"><button tabindex="10" id="_submit_form">Submit</button></p>
</form>
</div>
<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function hiddenElements() {
?>
<iframe id="_post_iframe" name="post_iframe"></iframe>
<?php
	}

	function internalCSS() {
?>
#_submit_paragraph { text-align: center; }
<?php
	}

	function onLoadJavascript() {
?>
<script>
$("#country_id").change(function() {
	if ($(this).val() == "1000") {
		$("#_state_row").hide();
		$("#_state_select_row").show();
	} else {
		$("#_state_row").show();
		$("#_state_select_row").hide();
	}
}).trigger("change");
$("#state_select").change(function() {
	$("#state").val($(this).val());
})
$(document).on("tap click","#_submit_form",function() {
	if ($("#_submit_form").data("disabled") == "true") {
		return false;
	}
	if ($("#_edit_form").validationEngine("validate")) {
		if (typeof beforeSubmit == "function") {
			if (!beforeSubmit()) {
				return false;
			}
		}
		disableButtons($("#_submit_form"));
		$("body").addClass("waiting-for-ajax");
		$("#_edit_form").attr("action","<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_changes").attr("method","POST").attr("target","post_iframe").submit();
		$("#_post_iframe").off("load");
		$("#_post_iframe").on("load",function() {
			$("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
			var returnText = $(this).contents().find("body").html();
			const returnArray = processReturn(returnText);
			if (returnArray === false) {
				enableButtons($("#_submit_form"));
				return;
			}
			if (!("error_message" in returnArray)) {
				if ("response" in returnArray) {
					$("#_form_div").html(returnArray['response']);
				}
				if (typeof afterSubmitForm == "function") {
					afterSubmitForm(returnArray);
				}
			} else {
				enableButtons($("#_submit_form"));
			}
		});
	} else {
		displayErrorMessage("Some information is missing. Please check the form and try again.");
	}
	return false;
});
</script>
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
