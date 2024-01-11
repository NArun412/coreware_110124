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

$GLOBALS['gPageCode'] = "DONATIONCOMMITMENTREPORT";
require_once "shared/startup.inc";

class DonationCommitmentReportPage extends Page implements BackgroundReport {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$returnArray = self::getReportContent();
				if (array_key_exists("report_export", $returnArray)) {
					if (is_array($returnArray['export_headers'])) {
						foreach ($returnArray['export_headers'] as $thisHeader) {
							header($thisHeader);
						}
					}
					echo $returnArray['report_export'];
				} else {
					echo jsonEncode($returnArray);
				}
				exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		if (empty($_POST['designation_id']) && !$GLOBALS['gUserRow']['full_client_access'] && !$GLOBALS['gUserRow']['superuser_flag']) {
			$returnArray['error_message'] = "Designation Required";
			ajaxResponse($returnArray);
		}
		saveStoredReport(static::class);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalDonations = 0;

		$displayCriteria = "";

		if (!empty($_POST['contact_id'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donor ID is " . $_POST['contact_id'];
		}

		if (!empty($_POST['donation_commitment_type_id'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation Commitment Type is " . getFieldFromId("description", "donation_commitment_types", "donation_commitment_type_id", $_POST['donation_commitment_type_id']);
		}

		$detailReport = $_POST['report_type'] == "detail";
		ob_start();

		$donationArray = array();
		?>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table class="grid-table">
            <tr>
                <th>Donor ID</th>
                <th>Donor Name</th>
                <th>Commitment Type</th>
                <th>Started</th>
                <th>Amount</th>
                <th>Gifts</th>
                <th>Donations</th>
                <th>Remaining</th>
                <th>Recurring</th>
            </tr>
			<?php
			$totalGifts = 0;
			$totalAmount = 0;
			$totalCommitted = 0;
			$resultSet = executeReadQuery("select * from donation_commitments join contacts using (contact_id) where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!empty($_POST['donation_commitment_type_id']) && $row['donation_commitment_type_id'] != $_POST['donation_commitment_type_id']) {
					continue;
				}
				if (!empty($_POST['designation_id']) && $row['designation_id'] != $_POST['designation_id']) {
					continue;
				}
				$recurringDonationId = getFieldFromId("recurring_donation_id", "recurring_donations", "contact_id", $row['contact_id'],
					"(end_date is null or end_date > current_date)" . (empty($_POST['designation_id']) ? "" : " and designation_id = " . makeParameter($_POST['designation_id'])));
				$totalCommitted += $row['amount'];
				$parameters = array($row['donation_commitment_id'], $row['contact_id']);
				if (!empty($_POST['designation_id'])) {
					$parameters[] = $_POST['designation_id'];
				}
				$donationArray = array();
				$donationSet = executeReadQuery("select * from donations where donation_commitment_id = ? and contact_id = ? and " .
					(empty($_POST['designation_id']) ? "" : "designation_id = ? and ") . "associated_donation_id is null order by donation_id", $parameters);
				$giftCount = 0;
				$donationTotal = 0;
				while ($donationRow = getNextRow($donationSet)) {
					$giftCount++;
					$totalGifts++;
					$donationTotal += $donationRow['amount'];
					$totalAmount += $donationRow['amount'];
					$donationArray[] = $donationRow;
				}
				?>
                <tr>
					<?php if (canAccessPageCode("CONTACTMAINT")) { ?>
                        <td><a href="/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['contact_id'] ?>"><?= $row['contact_id'] ?></a></td>
					<?php } else { ?>
                        <td><?= $row['contact_id'] ?></td>
					<?php } ?>
                    <td><?= htmlText(getDisplayName($row['contact_id'])) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "donation_commitment_types", "donation_commitment_type_id", $row['donation_commitment_type_id'])) ?></td>
                    <td><?= (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date']))) ?></td>
                    <td class="align-right"><?= number_format($row['amount'], 2, ".", ",") ?></td>
                    <td class="align-right"><?= $giftCount ?></td>
                    <td class="align-right"><?= number_format($donationTotal, 2, ".", ",") ?></td>
                    <td class="align-right"><?= number_format(max(0, $row['amount'] - $donationTotal), 2, ".", ",") ?></td>
                    <td><?= (empty($recurringDonationId) ? "" : "YES") ?></td>
                </tr>
				<?php
				if ($detailReport) {
					?>
                    <tr>
                        <td colspan="3"></td>
                        <td colspan="6">
                            <table>
                                <tr>
                                    <th>Donation Date</th>
                                    <th>Designation</th>
                                    <th class="align-right">Amount</th>
                                </tr>
								<?php
								if (empty($donationArray)) {
									?>
                                    <tr>
                                        <td colspan="3">No Donations Yet</td>
                                    </tr>
									<?php
								}
								foreach ($donationArray as $donationRow) {
									?>
                                    <tr>
                                        <td><?= date("m/d/Y", strtotime($donationRow['donation_date'])) ?></td>
                                        <td><?= htmlText(getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id'])) ?></td>
                                        <td><?= number_format($donationRow['amount'], 2, ".", ",") ?></td>
                                    </tr>
									<?php
								}
								?>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <th>Donor ID</th>
                        <th>Donor Name</th>
                        <th>Commitment Type</th>
                        <th>Started</th>
                        <th>Amount</th>
                        <th>Gifts</th>
                        <th>Donations</th>
                        <th>Remaining</th>
                    </tr>
					<?php
				}
			}
			?>
            <tr>
                <td colspan="4">Report Total</td>
                <td class="align-right"><?= number_format($totalCommitted, 2, ".", ",") ?></td>
                <td class="align-right"><?= $totalGifts ?></td>
                <td class="align-right"><?= number_format($totalAmount, 2, ".", ",") ?></td>
                <td class="align-right"><?= number_format(max(0, $totalCommitted - $totalAmount), 2, ".", ",") ?></td>
                <td></td>
            </tr>
        </table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		$returnArray['report_title'] = "Donation Commitment Report";
		return $returnArray;
	}

	function mainContent() {
		echo $this->getPageData("content");
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_contact_id_row">
                    <label for="contact_id">Donor ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="contact_id" name="contact_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_id_row">
                    <label for="designation_id" class="required-label">Designation</label>
                    <select tabindex="10" id="designation_id" name="designation_id" class="">
						<?php if ($GLOBALS['gUserRow']['full_client_access'] || $GLOBALS['gUserRow']['superuser_flag']) { ?>
                            <option value="">[All]</option>
						<?php } else { ?>
                            <option value="">[Select]</option>
						<?php } ?>
						<?php
						$resultSet = executeReadQuery("select * from designations where client_id = ? and inactive = 0" . ($GLOBALS['gUserRow']['full_client_access'] || $GLOBALS['gUserRow']['superuser_flag'] ? "" : " and " .
								"designation_id in (select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ")"), $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_donation_commitment_type_id_row">
                    <label for="donation_commitment_type_id">Donation Commitment Type</label>
                    <select tabindex="10" id="donation_commitment_type_id" name="donation_commitment_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from donation_commitment_types where client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['donation_commitment_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
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
		echo $this->getPageData("after_form_content");
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                        if ("report_content" in returnArray) {
                            $("#report_parameters").hide();
                            $("#_report_title").html(returnArray['report_title']).show();
                            $("#_report_content").html(returnArray['report_content']).show();
                            $("#_button_row").show();
                            $("html, body").animate({ scrollTop: 0 }, "slow");
                        }
                    });
                }
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

            #_report_content table td {
                font-size: 13px;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

            .total-line {
                font-weight: bold;
                font-size: 15px;
            }

            .grid-table td.border-bottom {
                border-bottom: 2px solid rgb(0, 0, 0);
            }
        </style>
		<?php
	}
}

$pageObject = new DonationCommitmentReportPage();
$pageObject->displayPage();
