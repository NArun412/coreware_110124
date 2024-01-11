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

$GLOBALS['gPageCode'] = "CREATERECURRINGDONATIONTRANSACTIONS";
require_once "shared/startup.inc";

class CreateRecurringDonationTransactionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_transactions":
				$donationBatchId = $_POST['donation_batch_id'];
				$recurringDonationTypeId = $_POST['recurring_donation_type_id'];
				$resultSet = executeQuery("select * from donation_batches where donation_batch_id = ? and client_id = ? and date_completed is null and date_posted is null", $donationBatchId, $GLOBALS['gClientId']);
				if ($resultSet['row_count'] == 0) {
					$returnArray['error_message'] = "Invalid Batch Selected";
					ajaxResponse($returnArray);
					break;
				}
				$this->iDatabase->startTransaction();
				$totalGifts = 0;
				$totalAmount = 0;
				if ($row = getNextRow($resultSet)) {
					$resultSet = executeQuery("delete from donations where donation_batch_id = ?", $donationBatchId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("select * from recurring_donations where exists (select contact_id from contacts where " .
						"client_id = ? and contact_id = recurring_donations.contact_id) and recurring_donation_type_id = ? and " .
						"(start_date is null or start_date <= now()) and (end_date is null or end_date >= now())", $GLOBALS['gClientId'],
						$recurringDonationTypeId);
					while ($row = getNextRow($resultSet)) {
						$totalGifts++;
						$totalAmount += $row['amount'];
						$donationCommitmentId = Donations::getContactDonationCommitment($row['contact_id'], $row['designation_id'], $row['donation_source_id']);
						$insertSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
							"account_id,donation_batch_id,designation_id,project_name,donation_source_id,amount,anonymous_gift,recurring_donation_id,donation_commitment_id,notes) values " .
							"(?,?,now(),?, ?,?,?,?,?, ?,?,?,?,?)", $GLOBALS['gClientId'], $row['contact_id'], $row['payment_method_id'], $row['account_id'],
							$donationBatchId, $row['designation_id'], $row['project_name'], $row['donation_source_id'], $row['amount'], $row['anonymous_gift'],
							$row['recurring_donation_id'], $donationCommitmentId, $row['notes']);
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						Donations::completeDonationCommitment($donationCommitmentId);
						Donations::processDonation($insertSet['insert_id']);
						executeQuery("update recurring_donations set next_billing_date = now() where recurring_donation_id = ?", $row['recurring_donation_id']);
					}
					$resultSet = executeQuery("update donation_batches set donation_count = ?, total_donations = ?, user_id = ? where donation_batch_id = ?",
						$totalGifts, $totalAmount, $GLOBALS['gUserId'], $donationBatchId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$this->iDatabase->commitTransaction();
				$returnArray['message_text'] = $totalGifts . " donations created totalling $" . number_format($totalAmount, 2);

				ajaxResponse($returnArray);

				break;
		}
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <p class="color-red">Donations in the batch WILL BE REMOVED. Make sure you choose the right batch.</p>
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line" id="_donation_batch_id_row">
                    <label for="donation_batch_id">Batch</label>
                    <select class="validate[required]" tabindex="10" id="donation_batch_id" name="donation_batch_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select *,(select count(*) from donations where donation_batch_id = donation_batches.donation_batch_id) donation_count " .
							"from donation_batches where client_id = ? and date_completed is null and date_posted is null order by batch_number", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option data-donation_count="<?= $row['donation_count'] ?>" value="<?= $row['donation_batch_id'] ?>"><?= htmlText($row['batch_number']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_recurring_donation_type_id_row">
                    <label for="recurring_donation_type_id">Recurring Donation Type</label>
                    <select class="validate[required]" tabindex="10" id="recurring_donation_type_id" name="recurring_donation_type_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from recurring_donation_types where manual_processing = 1 and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['recurring_donation_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <p id="message_text"></p>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Transactions</button>
                </div>

            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#donation_batch_id").change(function () {
                $("#message_text").removeClass("error-message").removeClass("info-message").html("");
                const donationCount = $("#donation_batch_id option:selected").data("donation_count");
                if (donationCount > 0) {
                    $("#message_text").html(donationCount + " transaction" + (donationCount === 1 ? "" : "s") + " will be removed.").addClass("error-message");
                }
            });
            $(document).on("tap click", "#create_report", function () {
                $("#message_text").removeClass("error-message").removeClass("info-message").html("");
                if ($("#_report_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_transactions", $("#_report_form").serialize(), function(returnArray) {
                        if ("message_text" in returnArray) {
                            $("#message_text").html(returnArray['message_text']).addClass("info-message");
                            $("#create_report").remove();
                            $("#donation_batch_id").prop("disabled", true);
                            $("#recurring_donation_type_id").prop("disabled", true);
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #message_text {
                font-size: 16px;
                font-weight: bold;
            }
        </style>
		<?php
	}
}

$pageObject = new CreateRecurringDonationTransactionsPage();
$pageObject->displayPage();
