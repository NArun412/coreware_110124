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

$GLOBALS['gPageCode'] = "EMPLOYEETIMESHEETS";
require_once "shared/startup.inc";

class EmployeeTimeSheetsPage extends Page {

	function mainContent() {
		?>
        <div id="parameters">
            <form id="parameters_form">
                <div class="basic-form-line">
                    <label for="start_date" class="required-label">Start Date</label>
                    <input type="text" tabindex="10" id="start_date" name="start_date" size="12" class="field-text validate[required,custom[date]] datepicker">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line">
                    <label for="end_date">End Date</label>
                    <input type="text" tabindex="10" id="end_date" name="end_date" size="12" class="field-text validate[custom[date]] datepicker">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type" class="field-text">
                        <option value="S">Summary</option>
                        <option value="D">Detail</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line">
                    <button id="create_report">Create Report</button>
                </div>
            </form>
        </div>
        <div id="new_parameters">
            <p><a href="#" id="new_parameters_link">Search Again</a></p>
        </div>
        <div id="_report_content">
        </div>
		<?php
		return true;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				if ($_POST['end_date']) {
					$resultSet = executeReadQuery("select *,(select last_name from contacts where contact_id = " .
						"(select contact_id from users where user_id = employee_time_sheets.user_id)) last_name from employee_time_sheets where date_entered between ? and ? order by last_name,user_id,date_entered",
						$_POST['start_date'], $_POST['end_date']);
				} else {
					$resultSet = executeReadQuery("select *,(select last_name from contacts where contact_id = " .
						"(select contact_id from users where user_id = employee_time_sheets.user_id)) last_name from employee_time_sheets where date_entered >= ? order by last_name,user_id,date_entered",
						$_POST['start_date']);
				}
				$footnotes = "";
				ob_start();
				?>
                <table id="report_table" class="grid-table">
                    <tr>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Hours</th>
                    </tr>
					<?php
					$saveUserId = "";
					$rowArray = array();
					while ($row = getNextRow($resultSet)) {
						$rowArray[] = $row;
					}
					$rowArray[] = array();
					$saveDisplayName = $displayUserName = "";
					$footnotesUsed = false;
					$totalHours = 0;
					foreach ($rowArray as $row) {
						if ($row['user_id'] != $saveUserId) {
							if ($saveUserId) {
								?>
                                <tr>
                                    <td class="total-line"><?= $displayUserName ?></td>
                                    <td class="total-line highlighted-text">Total Hours<?= ($footnotesUsed ? "<span class='superscript'>*</span>" : "") ?></td>
                                    <td class="total-line align-right"><?= $totalHours ?></td>
                                </tr>
								<?php
							}
							$saveUserId = $row['user_id'];
							$saveDisplayName = $displayUserName = getUserDisplayName($saveUserId);
							$footnotesUsed = false;
							$totalHours = 0;
						}
						if (empty($row['date_entered'])) {
							continue;
						}
						if (empty($row['end_time'])) {
							$footnotes .= "<p><span class='superscript'>*</span>On " . date("m/d/Y", strtotime($row['date_entered'])) . ", " . $saveDisplayName . " did not clock out</p>";
							$footnotesUsed = true;
							continue;
						}
						try {
							$startDate = new DateTime($row['start_time']);
						} catch (Exception $e) {
							continue;
						}
						try {
							$endDate = new DateTime($row['end_time']);
						} catch (Exception $e) {
							continue;
						}
						$dateDifference = $endDate->diff($startDate);
						$hours = $dateDifference->h;
						$minutes = $dateDifference->i;
						$hours += round($minutes / 60, 2);
						if ($_POST['report_type'] == "D") {
							?>
                            <tr>
                                <td><?= $displayUserName ?></td>
                                <td><?= date("m/d/Y", strtotime($row['date_entered'])) . " " . date("g:ia", strtotime($row['start_time'])) . " - " . date("g:ia", strtotime($row['end_time'])) ?></td>
                                <td class="align-right"><?= $hours ?></td>
                            </tr>
							<?php
							$displayUserName = "";
						}
						$totalHours += $hours;
					}
					?>
                </table>
				<?php
				echo "<div id='footnotes'>" . $footnotes . "</div>";
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#new_parameters_link").click(function () {
                $("#new_parameters").hide();
                $("#_report_content").hide();
                $("#parameters").show();
            });
            $("#create_report").click(function () {
                if ($("#parameters_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#parameters_form").serialize(), function(returnArray) {
                        if ("report_content" in returnArray) {
                            $("#new_parameters").show();
                            $("#_report_content").html(returnArray['report_content']).show();
                            $("#parameters").hide();
                        }
                    });
                }
                return false;
            });
            $("#start_date").focus();
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #new_parameters {
                display: none;
            }
            #_report_content {
                display: none;
            }
            #report_table {
                min-width: 400px;
            }
            #report_table th, #report_table td {
                font-size: 16px;
                padding: 4px 10px;
            }
            #footnotes {
                margin-top: 20px;
            }
            #footnotes p {
                padding: 0;
                margin: 0;
                font-size: 14px;
            }
            .superscript {
                position: relative;
                top: -0.3em;
                font-size: 80%;
            }
            #report_table td.total-line {
                border-bottom-width: 3px;
                font-size: 18px;
                background-color: rgb(230, 230, 230);
            }
        </style>
		<?php
	}

}

$pageObject = new EmployeeTimeSheetsPage();
$pageObject->displayPage();
