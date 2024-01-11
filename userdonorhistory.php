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

$GLOBALS['gPageCode'] = "USERDONORHISTORY";
require_once "shared/startup.inc";

class UserDonorHistoryPage extends Page implements BackgroundReport {

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

		processPresetDates($_POST['preset_dates'], "donation_date_from", "donation_date_to");

		$exportOutput = ($_POST['report_type'] == "export");
		$designationId = getFieldFromId("designation_id", "designations", "designation_id", $_POST['designation_id'],
			($GLOBALS['gUserRow']['full_client_access'] ? "" : "inactive = 0 and (designation_id in (select " .
				"designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ") or designation_id in (select designation_id from designation_group_links where " .
				"designation_group_id in (select designation_group_id from designation_groups where user_id = " . $GLOBALS['gUserId'] . ") or designation_group_id in " .
				"(select designation_group_id from designation_group_users where user_id = " . $GLOBALS['gUserId'] . ")))"));
		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId'], $designationId);
		$displayCriteria = "";

		if (!empty($_POST['donation_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_date >= ?";
			$parameters[] = makeDateParameter($_POST['donation_date_from']);
		}
		if (!empty($_POST['donation_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_date <= ?";
			$parameters[] = makeDateParameter($_POST['donation_date_to']);
		}
		if (!empty($_POST['donation_date_from']) && !empty($_POST['donation_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation date is between " . date("m/d/Y", strtotime($_POST['donation_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['donation_date_to']));
		} else {
			if (!empty($_POST['donation_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Donation date is on or after " . date("m/d/Y", strtotime($_POST['donation_date_from']));
			} else {
				if (!empty($_POST['donation_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Donation date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
		}

		ob_start();

		$resultSet = executeReadQuery("select * from donations join contacts using (contact_id) where donations.client_id = ? and designation_id = ? and associated_donation_id is null and anonymous_gift = 0" .
			(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by last_name,first_name,business_name,donations.contact_id,donation_date", $parameters);

		if (!$exportOutput) {
			?>
            <h1>Donor History Report</h1>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
            <tr>
                <th>Donor ID</th>
                <th>Donor</th>
                <th>Count</th>
                <th>Total</th>
            </tr>
			<?php
		} else {
			echo "\"Contact ID\",\"First Name\",\"Last Name\",\"Business Name\",\"Address\",\"Address2\",\"City\",\"State\",\"Postal Code\",\"Email Address\",\"Country\",\"Phone\",\"Phone\",\"Phone\",\"Phone\",\"Phone\",\"Total Gifts\",\"Total Amount\"\r\n";
		}
		$saveContact = "";
		$saveRow = array();
		$saveCount = 0;
		$saveAmount = 0;
		while ($row = getNextRow($resultSet)) {
			if ($row['contact_id'] != $saveContact) {
				if (!empty($saveContact)) {
					if ($exportOutput) {
						echo '"' . $saveContact . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("first_name", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("last_name", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("business_name", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("address_1", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("address_2", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("city", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("state", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("postal_code", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("email_address", "contacts", "contact_id", $saveContact)) . '",';
						echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", getFieldFromId("country_id", "contacts", "contact_id", $saveContact))) . '",';
						$phoneCount = 0;
						$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveContact);
						while ($phoneRow = getNextRow($phoneSet)) {
							echo '"' . str_replace('"', '""', $phoneRow['phone_number'] . " " . $phoneRow['description']) . '",';
							$phoneCount++;
							if ($phoneCount >= 5) {
								break;
							}
						}
						while ($phoneCount < 5) {
							echo '"",';
							$phoneCount++;
						}
						echo '"' . str_replace('"', '""', $saveCount) . '",';
						echo '"' . str_replace('"', '""', number_format($saveAmount, 2)) . '"' . "\r\n";
					} else {
						$contactInfo = getDisplayName($saveRow['contact_id']);
						if (!empty($saveRow['address_1'])) {
							$contactInfo .= "<br/>" . $saveRow['address_1'];
						}
						if (!empty($saveRow['address_2'])) {
							$contactInfo .= "<br/>" . $saveRow['address_2'];
						}
						if (!empty($saveRow['city'])) {
							$contactInfo .= "<br/>" . $saveRow['city'] . (empty($saveRow['state']) ? "" : ", " . $saveRow['state']) . (empty($saveRow['postal_code']) ? "" : " " . $saveRow['postal_code']);
						}
						$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveRow['contact_id']);
						while ($phoneRow = getNextRow($phoneSet)) {
							$contactInfo .= "<br/>" . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
						}
						if (!empty($saveRow['email_address'])) {
							$contactInfo .= "<br/>" . $saveRow['email_address'];
						}
						?>
                        <tr>
                            <td><?= $saveContact ?></td>
                            <td><?= $contactInfo ?></td>
                            <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                            <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                        </tr>
						<?php
					}
				}
				$saveContact = $row['contact_id'];
				$saveRow = $row;
				$saveCount = 0;
				$saveAmount = 0;
			}
			$saveCount++;
			$saveAmount += $row['amount'];
		}
		if (!empty($saveContact)) {
			if ($exportOutput) {
				echo '"' . $saveContact . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("first_name", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("last_name", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("business_name", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("address_1", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("address_2", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("city", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("state", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("postal_code", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("email_address", "contacts", "contact_id", $saveContact)) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", getFieldFromId("country_id", "contacts", "contact_id", $saveContact))) . '",';
				$phoneCount = 0;
				$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveContact);
				while ($phoneRow = getNextRow($phoneSet)) {
					echo '"' . str_replace('"', '""', $phoneRow['phone_number'] . " " . $phoneRow['description']) . '",';
					$phoneCount++;
					if ($phoneCount >= 5) {
						break;
					}
				}
				while ($phoneCount < 5) {
					echo '"",';
					$phoneCount++;
				}
				echo '"' . str_replace('"', '""', $saveCount) . '",';
				echo '"' . str_replace('"', '""', number_format($saveAmount, 2)) . '"' . "\r\n";
			} else {
				$contactInfo = getDisplayName($saveRow['contact_id']);
				if (!empty($saveRow['address_1'])) {
					$contactInfo .= "<br/>" . $saveRow['address_1'];
				}
				if (!empty($saveRow['address_2'])) {
					$contactInfo .= "<br/>" . $saveRow['address_2'];
				}
				if (!empty($saveRow['city'])) {
					$contactInfo .= "<br/>" . $saveRow['city'] . (empty($saveRow['state']) ? "" : ", " . $saveRow['state']) . (empty($saveRow['postal_code']) ? "" : " " . $saveRow['postal_code']);
				}
				$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveRow['contact_id']);
				while ($phoneRow = getNextRow($phoneSet)) {
					$contactInfo .= "<br/>" . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
				}
				if (!empty($saveRow['email_address'])) {
					$contactInfo .= "<br/>" . $saveRow['email_address'];
				}
				?>
                <tr>
                    <td><?= $saveContact ?></td>
                    <td><?= $contactInfo ?></td>
                    <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                    <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                </tr>
				<?php
			}
		}
		if (!$exportOutput) {
			?>
            </table>
			<?php
		}
		$reportContent = ob_get_clean();
		if ($exportOutput) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"donorhistory.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['report_export'] = $reportContent;
			$returnArray['filename'] = "donorhistory.csv";
		} else {
			$returnArray['report_content'] = $reportContent;
		}
		return $returnArray;
	}

	function mainContent() {
		$designationArray = array();
		$resultSet = executeReadQuery("select * from designations where inactive = 0 and client_id = ?" .
			($GLOBALS['gUserRow']['full_client_access'] ? "" : " and (designation_id in " .
				"(select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ") or designation_id in (select designation_id from designation_group_links where " .
				"designation_group_id in (select designation_group_id from designation_groups where user_id = " . $GLOBALS['gUserId'] . ") or designation_group_id in " .
				"(select designation_group_id from designation_group_users where user_id = " . $GLOBALS['gUserId'] . ")))") .
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

                    <div class="form-line" id="_report_type_row">
                        <label for="report_type">Report Type</label>
                        <select tabindex="10" id="report_type" name="report_type">
                            <option value="printable">Printable</option>
                            <option value="export">Export</option>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_designation_id_row">
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
                        <div class='clear-div'></div>
                    </div>

					<?php getPresetDateOptions() ?>

                    <div class="form-line preset-date-custom" id="_donation_date_row">
                        <label for="donation_date_from">Donation Date: From</label>
                        <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_from" name="donation_date_from">
                        <label class="second-label">Through</label>
                        <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_to" name="donation_date_to">
                        <div class='clear-div'></div>
                    </div>

					<?php storedReportDescription() ?>

                    <div class="form-line">
                        <label></label>
                        <button tabindex="10" id="create_report">Create Report</button>
                        <div class='clear-div'></div>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("donorhistory.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
                    if (reportType == "export" || reportType == "summaryexport") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
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
        </style>
		<?php
	}
}

$pageObject = new UserDonorHistoryPage();
$pageObject->displayPage();
