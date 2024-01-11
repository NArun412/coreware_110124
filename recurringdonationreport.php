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

$GLOBALS['gPageCode'] = "RECURRINGDONATIONREPORT";
require_once "shared/startup.inc";

class RecurringDonationReportPage extends Page implements BackgroundReport {

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
		saveStoredReport(static::class);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalDonations = 0;

		if (!empty($_POST['designation_id'])) {
			$designationArray = array($_POST['designation_id']);
		} else if (!empty($_POST['designations'])) {
			$designationArray = explode(",", $_POST['designations']);
		} else {
			$designationArray = array();
		}

		$designationCodeList = "";
		foreach ($designationArray as $index => $thisDesignationId) {

			$designationCode = getFieldFromId("designation_code", "designations", "designation_id", $thisDesignationId,
				($GLOBALS['gUserRow']['full_client_access'] ? "" : "inactive = 0 and (designation_id in (select " .
					"designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ") or designation_id in (select designation_id from designation_group_links where " .
					"designation_group_id in (select designation_group_id from designation_groups where user_id = " . $GLOBALS['gUserId'] . ") or designation_group_id in " .
					"(select designation_group_id from designation_group_users where user_id = " . $GLOBALS['gUserId'] . ")))"));

			if (empty($designationCode)) {
				unset($designationArray[$index]);
			} else {
				$designationCodeList .= (empty($designationCodeList) ? "" : ", ") . $designationCode;
			}
		}
		if (empty($designationArray)) {
			$designationArray[] = "0";
		}
		$whereStatement = "";
		$displayCriteria = "";
		if (!empty($_POST['only_active'])) {
			$whereStatement = "(end_date is null or end_date > current_date)";
			$displayCriteria = "Only Active Recurring Donations";
		}

		$recurringDonationTypeTotals = array();
		$resultSet = executeReadQuery("select * from recurring_donations join contacts using (contact_id) where designation_id in (" . implode(",", $designationArray) . ")" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by last_name,first_name,business_name");

		ob_start();
		?>
        <h1>Recurring Donations Report</h1>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <p>Note: this report shows only automatic recurring gifts. Gifts received by check and one-time gifts are not included.</p>
        <table id="report_table" class="grid-table">
            <tr>
                <th>For</th>
                <th>Start Date</th>
                <th>From</th>
                <th>Project</th>
                <th>Next Billing</th>
                <th>End Date</th>
                <th>Payment Method</th>
                <th>Type</th>
                <th></th>
                <th>Amount</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				if (!array_key_exists($row['recurring_donation_type_id'], $recurringDonationTypeTotals)) {
					$recurringDonationTypeTotals[$row['recurring_donation_type_id']] = 0;
				}
				$recurringDonationTypeTotals[$row['recurring_donation_type_id']] += $row['amount'];
				if (!empty($row['anonymous_gift'])) {
					$fromDisplay = "Anonymous";
				} else {
					$fromDisplay = htmlText(getDisplayName($row['contact_id']));
					if (!empty($row['address_1'])) {
						$fromDisplay .= "<br>" . htmlText($row['address_1']);
					}
					if (!empty($row['address_2'])) {
						$fromDisplay .= "<br>" . htmlText($row['address_2']);
					}
					if (!empty($row['city'])) {
						$fromDisplay .= "<br>" . htmlText($row['city']);
					}
					if (!empty($row['state'])) {
						$fromDisplay .= ", " . htmlText($row['state']);
					}
					if (!empty($row['postal_code'])) {
						$fromDisplay .= " " . htmlText($row['postal_code']);
					}
					if (!empty($row['email_address'])) {
						$fromDisplay .= "<br>" . htmlText($row['email_address']);
					}
				}
				?>
                <tr>
                    <td><?= htmlText(getFieldFromId("description", "designations", "designation_id", $row['designation_id'])) ?></td>
                    <td><?= date("m/d/Y", strtotime($row['start_date'])) ?></td>
                    <td><?= $fromDisplay ?></td>
                    <td><?= htmlText($row['project_name']) ?></td>
                    <td><?= (empty($row['next_billing_date']) ? "" : date("m/d/Y", strtotime($row['next_billing_date']))) ?></td>
                    <td><?= (empty($row['end_date']) ? "" : date("m/d/Y", strtotime($row['end_date']))) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id'])) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", $row['recurring_donation_type_id'])) ?></td>
                    <td class="error-message"><?= (empty($row['requires_attention']) ? "" : "REQUIRES<br>ATTENTION") ?></td>
                    <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		foreach ($recurringDonationTypeTotals as $recurringDonationTypeId => $amount) {
			?>
            <p>Total for '<?= htmlText(getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", $recurringDonationTypeId)) ?>': <span class="highlighted-text"><?= number_format($amount, 2, ".", ",") ?></span></p>
			<?php
		}
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		return $returnArray;
	}

	function mainContent() {
		$designationArray = array();
		$resultSet = executeReadQuery("select * from designations where inactive = 0 and client_id = ?" .
			($GLOBALS['gUserRow']['full_client_access'] ? "" : " and (designation_id in (select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ") or " .
				"designation_id in (select designation_id from designation_group_links where designation_group_id in (select designation_group_id from designation_groups where user_id = " . $GLOBALS['gUserId'] . ") or " .
				"designation_group_id in (select designation_group_id from designation_group_users where user_id = " . $GLOBALS['gUserId'] . ")))") .
			" order by sort_order,designation_code", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designationArray[$row['designation_id']] = $row['designation_code'] . " - " . $row['description'];
		}
		?>
        <div id="report_parameters">
			<?php if (empty($designationArray)) { ?>
                <p class="error-message">There are no designations assigned to your user.</p>
			<?php } else { ?>
                <form id="_report_form" name="_report_form">

					<?php getStoredReports() ?>

					<?php if (!$GLOBALS['gUserRow']['full_client_access']) { ?>
                        <div class="basic-form-line" id="_designation_id_row">
                            <label for="designation_id" class="required-label">Designation</label>
                            <select tabindex="10" id="designation_id" name="designation_id" class="validate[required]">
                                <option value="">[Select]</option>
								<?php
								foreach ($designationArray as $designationId => $description) {
									?>
                                    <option value="<?= $designationId ?>"><?= htmlText($description) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
					<?php } else { ?>
						<?php
						$designationControl = new DataColumn("designations");
						$designationControl->setControlValue("data_type", "custom");
						$designationControl->setControlValue("include_inactive", "true");
						$designationControl->setControlValue("control_class", "MultiSelect");
						$designationControl->setControlValue("control_table", "designations");
						$designationControl->setControlValue("links_table", "designations");
						$designationControl->setControlValue("primary_table", "donations");
						$customControl = new MultipleSelect($designationControl, $this);
						?>
                        <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_designations_row">
                            <label for="designations">Designations</label>
							<?= $customControl->getControl() ?>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
					<?php } ?>

                    <div class="basic-form-line" id="_only_active_row">
                        <input tabindex="10" type="checkbox" id="only_active" name="only_active" checked="checked"><label class="checkbox-label" for="only_active">Show only active recurring donations</label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

					<?php storedReportDescription() ?>

                    <div class="basic-form-line">
                        <button tabindex="10" id="create_report">Create Report</button>
                    </div>

                </form>
			<?php } ?>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("recurringdonations.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
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
            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
            #report_table {
                margin-bottom: 20px;
            }
        </style>
		<?php
	}
}

$pageObject = new RecurringDonationReportPage();
$pageObject->displayPage();
