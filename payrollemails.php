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

$GLOBALS['gPageCode'] = "PAYROLLEMAILS";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add", "save"));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case ("sendemails"):
				$returnArray['designation_id'] = $_POST['designation_id'];

				$resultSet = executeQuery("select * from designations where designation_id = ? and client_id = ?",
					$_POST['designation_id'], $GLOBALS['gClientId']);
				if (!$designationRow = getNextRow($resultSet)) {
					$returnArray['error_message'] = "Designation not found";
					ajaxResponse($returnArray);
					break;
				}

				$ccAddress = getNotificationEmails("COPY_PAYROLL");
				$resultSet = executeQuery("select * from pay_periods where pay_period_id = ? and client_id = ?", $_POST['pay_period_id'], $GLOBALS['gClientId']);
				if (!$payPeriodRow = getNextRow($resultSet)) {
					$returnArray['error_message'] = "Pay Period does not exist";
					ajaxResponse($returnArray);
					break;
				}

				$emailAddresses = array();
				$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $designationRow['designation_id']);
				while ($emailRow = getNextRow($emailSet)) {
					$emailAddresses[] = $emailRow['email_address'];
				}
				if (empty($emailAddresses)) {
					$returnArray['error_message'] = "No email address for designation " . $designationRow['designation_code'] . " (" . $designationRow['description'] . ")";
					ajaxResponse($returnArray);
					break;
				}
				$includeBackouts = getPreference("INCLUDE_BACKOUTS_GIVING_REPORT");
				$includeNotes = getPreference("INCLUDE_NOTES_GIVING_REPORT");
				$resultSet = executeQuery("select donation_id from donations where designation_id = ? and " .
					"client_id = ? and " . ($includeBackouts ? "" : "associated_donation_id is null and ") . "pay_period_id = ? order by donation_id",
					$designationRow['designation_id'], $GLOBALS['gClientId'], $_POST['pay_period_id']);
				$donationArray = array();
				while ($row = getNextRow($resultSet)) {
					$donationArray[] = $row['donation_id'];
				}
				$givingReport = "Giving Report<br/>\n";
				if (!empty($_POST['email_introduction'])) {
					$givingReport .= makeHtml($_POST['email_introduction']);
				}
				$givingReport .= $designationRow['designation_code'] . " - " . $designationRow['description'] . "<br/>\n";
				$givingReport .= date("M j, Y") . "<br/>\n";
				$givingReport .= "----------------------------------<br/>\n";
				$reportTotal = 0;
				$totalFees = 0;
				foreach ($donationArray as $donationId) {
					$resultSet1 = executeQuery("select * from donations where client_id = ? and donation_id = ?", $GLOBALS['gClientId'], $donationId);
					$donationRow = getNextRow($resultSet1);
					if (empty($donationRow['donation_fee'])) {
						$donationRow['donation_fee'] = 0;
					}
					$resultSet1 = executeQuery("select * from contacts where contact_id = " . $donationRow['contact_id']);
					$contactRow = getNextRow($resultSet1);
					if ($donationRow['anonymous_gift']) {
						$givingReport .= "Anonymous Gift: $" . number_format($donationRow['amount'], 2) . "<br/>\n";
						if (!empty($donationRow['project_name'])) {
							$givingReport .= "Given for: " . $donationRow['project_name'] . "<br>\n";
						}
						$givingReport .= "*** Given on " . date("F j, Y", strtotime($donationRow['donation_date'])) . "<br/>\n";
						$givingReport .= "Given by " . getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']) .
							", Administration fee is $" . number_format($donationRow['donation_fee'], 2) . ", Net gift amount is $" .
							number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) . "<br/>\n";
					} else {
						$lines = array();
						$lines[] = $contactRow['contact_id'];
						$lines[] = $contactRow['first_name'] . " " . $contactRow['last_name'];
						$lines[] = $contactRow['business_name'];
						$lines[] = $contactRow['address_1'];
						$lines[] = $contactRow['address_2'];
						$lines[] = $contactRow['city'] . (empty($contactRow['state']) ? "" : ", " . $contactRow['state']) . " " . $contactRow['postal_code'];
						if ($contactRow['country_id'] != 1000) {
							$lines[] = getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']);
						}
						$lines[] = $contactRow['email_address'];
						$phoneSet = executeQuery("select * from phone_numbers where contact_id = ?", $contactRow['contact_id']);
						while ($phoneRow = getNextRow($phoneSet)) {
							$lines[] = $phoneRow['phone_number'] . " " . $phoneRow['description'];
						}
						foreach ($lines as $line) {
							$line = trim($line);
							if (!empty($line)) {
								$givingReport .= $line . "<br/>\n";
							}
						}
						if (!empty($donationRow['project_name'])) {
							$givingReport .= "Given for: " . $donationRow['project_name'] . "<br>\n";
						}
						$givingReport .= "*** Given on " . date("F j, Y", strtotime($donationRow['donation_date'])) . ", Receipt #" . $donationId . ", $" .
							number_format($donationRow['amount'], 2) . " USD<br/>\n";
						$givingReport .= "Given by " . getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']) .
							", Administration fee is $" . number_format($donationRow['donation_fee'], 2) . ", Net gift amount is $" .
							number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) . "<br/>\n";
					}
					if ($includeNotes && !empty($donationRow['notes'])) {
						$givingReport .= "Notes: " . htmlText($donationRow['notes']) . "<br/>\n";
					}
					if (!empty($donationRow['recurring_donation_id'])) {
						$givingReport .= "Recurring: " . getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", getFieldFromId("recurring_donation_type_id", "recurring_donations", "recurring_donation_id", $donationRow['recurring_donation_id'])) . "<br>\n";
					}
					$reportTotal += $donationRow['amount'];
					$totalFees += $donationRow['donation_fee'];
					$givingReport .= "----------------------------------<br/>\n";
				}
				$givingReport .= "Total Gifts: $" . number_format($reportTotal, 2) . ", Total Fees: $" . number_format($totalFees, 2) .
					", Net Total: $" . number_format($reportTotal - $totalFees, 2) . "<br/>\n";

				$resultSet = executeQuery("select *,(select description from fund_accounts where fund_account_id = fund_account_details.fund_account_id) fund_description " .
					"from fund_account_details where designation_id = ? " .
					"and pay_period_id = ? order by fund_description", $designationRow['designation_id'], $_POST['pay_period_id']);
				if ($resultSet['row_count'] > 0) {
					$givingReport .= "<br/>\nFund Account Deductions<br/>\n";
				}
				$totalFund = 0;
				while ($row = getNextRow($resultSet)) {
					$givingReport .= $row['fund_description'] . " - $" . number_format($row['amount'], 2) . "<br/>\n";
					$totalFund += $row['amount'];
				}
				if ($totalFund > 0) {
					$givingReport .= "Donations: $" . number_format($reportTotal - $totalFees, 2) . ", Total Funds: $" . number_format($totalFund, 2) .
						", Net after funds: $" . number_format($reportTotal - $totalFees - $totalFund, 2) . "<br/>\n";
				}

				$feeMessage = Donations::getFeeMessage($designationRow['designation_id']);
				if (!empty($feeMessage)) {
					$givingReport .= "<br/>\nFee Schedule:<br/>\n" . $feeMessage;
				}

				$datePaidOut = date("m/d/Y", strtotime(getFieldFromId("date_paid_out", "pay_periods", "pay_period_id", $_POST['pay_period_id'])));
				$errorMessage = sendEmail(array("email_credential_code" => "designation_notification", "email_credential_id" => $_POST['email_credential_id'], "subject" => "Giving Report for " . $designationRow['designation_code'] . " for " . $datePaidOut, "body" => $givingReport, "email_addresses" => $emailAddresses, "cc_address" => $ccAddress));
				if ($errorMessage !== true) {
					$returnArray['result_text'] .= "Email to " . implode(",", $emailAddresses) . " for designation " . $designationRow['designation_code'] . " (" . $designationRow['description'] . ") not sent: " . $errorMessage . "\n";
				} else {
					$returnArray['result_text'] = "Email successfully sent to " . implode(",", $emailAddresses) . " for designation " . $designationRow['designation_code'] . " (" . $designationRow['description'] . ")";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['header_message'] = array("data_value" => "Payroll was run on " .
			date("m/d/Y", strtotime($returnArray['date_created']['data_value'])) . " by " . getUserDisplayName($returnArray['user_id']['data_value']));
		ob_start();
		?>
            <div class='basic-form-line'>
                <label>Email Sending Account</label>
                <select id="email_credential_id" name="email_credential_id">
                    <option id="">[Default]</option>
	                <?php
	                $resultSet = executeQuery("select * from email_credentials where client_id = ? order by description", $GLOBALS['gClientId']);
	                while ($row = getNextRow($resultSet)) {
		                ?>
                        <option id="<?= $row['email_credential_id'] ?>"<?= ($row['email_credential_code'] == "DESIGNATION_NOTIFICATION" ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
		                <?php
	                }
	                ?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
		<?php
		$resultSet = executeQuery("select designation_id, designation_code, description from designations where " .
			"designation_id in (select designation_id from donations where pay_period_id = ?) and client_id = ? order by description", $returnArray['primary_id']['data_value'], $GLOBALS['gClientId']);
		$totalCount = $resultSet['row_count'];
		?>
        <p class="highlighted-text"><?= $totalCount ?> designation<?= ($totalCount == 1 ? "" : "s") ?> found</p>
		<?php
		while ($row = getNextRow($resultSet)) {
			$emailAddresses = "";
			$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $row['designation_id']);
			while ($emailRow = getNextRow($emailSet)) {
				$emailAddresses .= (empty($emailAddresses) ? "" : ",") . $emailRow['email_address'];
			}
			if (empty($emailAddresses)) {
				continue;
			}
			?>
            <p id="cell_designation_<?= $row['designation_id'] ?>"><input class="designation-checkbox" type="checkbox" name="designation_<?= $row['designation_id'] ?>" id="designation_<?= $row['designation_id'] ?>" value="<?= $row['designation_id'] ?>"><label id="label_designation_<?= $row['designation_id'] ?>" for="designation_<?= $row['designation_id'] ?>" class="checkbox-label"><?= htmlText($row['designation_code'] . " - " . $row['description']) ?> (<?= $emailAddresses ?>)</label></p>
			<?php
		}
		$returnArray['designation_list'] = array("data_value" => ob_get_clean());
	}

	function internalCSS() {
		?>
        <style>
            #header_message {
                font-size: 18px;
                font-weight: bold;
                color: rgb(120, 150, 185);
                margin-bottom: 10px;
            }

        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#select_all", function () {
                $(".designation-checkbox").prop("checked", true);
                return false;
            });
            $(document).on("tap click", "#select_none", function () {
                $(".designation-checkbox").prop("checked", false);
                return false;
            });
            $(document).on("tap click", "#send_emails", function () {
                disableButtons($("#select_all"));
                disableButtons($("#select_none"));
                disableButtons($("#send_emails"));
                $(".designation-checkbox").prop("disabled", true);
                sendEmails();
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function sendEmails() {
                $(".designation-checkbox").each(function () {
                    if ($(this).prop("checked")) {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=sendemails", { designation_id: $(this).val(), pay_period_id: $("#primary_id").val(), email_introduction: $("#email_introduction").val() }, function(returnArray) {
                            if ("result_text" in returnArray) {
                                $("#cell_designation_" + returnArray['designation_id']).html(returnArray['result_text']);
                                sendEmails();
                            }
                        });
                        return false;
                    } else {
                        $("#cell_" + $(this).attr("id")).remove();
                    }
                });
            }
        </script>
		<?php
	}

}

$pageObject = new ThisPage("pay_periods");
$pageObject->displayPage();
