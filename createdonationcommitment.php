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

$GLOBALS['gPageCode'] = "CREATEDONATIONCOMMITMENT";
require_once "shared/startup.inc";

class CreateDonationCommitmentPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_commitment":
				$this->iDatabase->startTransaction();
				$resultSet = executeQuery("select * from contacts where client_id = ? and last_name = ? and postal_code = ? and email_address = ? and contact_id in (select contact_id from donations where designation_id = ?)",
					$GLOBALS['gClientId'], $_POST['last_name'], $_POST['postal_code'], $_POST['email_address'], $_POST['designation_id']);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
				}
				if (empty($contactId)) {
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "middle_name" => $_POST['middle_name'], "last_name" => $_POST['last_name'],
						"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
						"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'])))) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $contactDataTable->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (!empty($_POST['phone_number'])) {
					$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $_POST['phone_number']);
					if (empty($phoneNumberId)) {
						$resultSet = executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Primary')",
							$contactId, $_POST["phone_number"]);
						if (!empty($resultSet['sql_error'])) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$resultSet = executeQuery("insert into donation_commitments (contact_id,donation_commitment_type_id,designation_id,start_date,amount) values (?,?,?,?,?)",
					$contactId, $_POST["donation_commitment_type_id"], $_POST['designation_id'], makeDateParameter($_POST['start_date']), $_POST['amount']);
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$donationCommitmentId = $resultSet['insert_id'];
				executeQuery("update donations set donation_commitment_id = ? where donation_date >= ? and designation_id = ? and donation_commitment_id is null",
					$donationCommitmentId, $_POST['start_date'], $_POST['designation_id']);
				$this->iDatabase->commitTransaction();
				$returnArray['info_message'] = "Commitment Created";
				ajaxResponse($returnArray);
				break;
		}
	}

	function commitmentForm() {
		?>
        <form id="_edit_form">
			<?php
			echo createFormControl("contacts", "first_name", array("not_null" => true));
			echo createFormControl("contacts", "middle_name", array("not_null" => false));
			echo createFormControl("contacts", "last_name", array("not_null" => true));
			echo createFormControl("contacts", "business_name", array("not_null" => false));
			echo createFormControl("contacts", "address_1", array("not_null" => false));
			echo createFormControl("contacts", "address_2", array("not_null" => false));
			echo createFormControl("contacts", "city", array("not_null" => false));
			?>
            <div class="form-line" id="_city_select_row">
                <label for="city_select" class="">City</label>
                <select tabindex="10" class="" name="city_select" id="city_select">
                    <option value="">[None]</option>
                </select>
                <div class='clear-div'></div>
            </div>
			<?php
			echo createFormControl("contacts", "state", array("not_null" => false));
			echo createFormControl("contacts", "postal_code", array("not_null" => false));
			echo createFormControl("contacts", "country_id", array("not_null" => true, "initial_value" => 1000));
			echo createFormControl("contacts", "email_address", array("not_null" => false));
			echo createFormControl("phone_numbers", "phone_number", array("form_label" => "Primary Phone", "not_null" => false));
			?>
            <div class="form-line" id="_designation_id_row">
                <label for="designation_id" class="required-label">Designation</label>
                <select tabindex="10" id="designation_id" name="designation_id" class="validate[required]">
					<?php
					$resultSet = executeQuery("select * from designations where client_id = ? and inactive = 0 and " .
						"designation_id in (select designation_id from designation_users where user_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
					if ($resultSet['row_count'] != 1) {
						?>
                        <option value="">[Select]</option>
						<?php
					}
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='clear-div'></div>
            </div>
            <div class="form-line" id="_donation_commitment_type_id_row">
                <label for="donation_commitment_type_id" class="required-label">Commitment Type</label>
                <select tabindex="10" id="donation_commitment_type_id" name="donation_commitment_type_id" class="validate[required]">
					<?php
					$resultSet = executeQuery("select * from donation_commitment_types where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
					if ($resultSet['row_count'] != 1) {
						?>
                        <option value="">[Select]</option>
						<?php
					}
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['donation_commitment_type_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='clear-div'></div>
            </div>
			<?php
			echo createFormControl("donation_commitments", "start_date", array("not_null" => false, "initial_value" => date("m/d/Y")));
			echo createFormControl("donation_commitments", "amount", array("not_null" => true));
			?>
        </form>
        <p>
            <button id="submit_form" tabindex="10">Create Commitment</button>
        </p>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#submit_form").click(function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#submit_form").addClass("hidden");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_commitment", $("#_edit_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            $("#submit_form").removeClass("hidden");
                        } else {
                            setTimeout(function () {
                                document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                            }, 2000);
                        }
                    });
                }
            });
            $("#postal_code").blur(function () {
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
            $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
            $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
            $("#_city_select_row").hide();
            $("#_city_row").show();
            setTimeout(function () {
                $("#first_name").focus();
            }, 500);
        </script>
		<?php
	}
}

$pageObject = new CreateDonationCommitmentPage();
$pageObject->displayPage();
