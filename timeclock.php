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

$GLOBALS['gPageCode'] = "TIMECLOCK";
require_once "shared/startup.inc";

class ThisPage extends Page {

	var $iCheckInTime = "";

	function mainContent() {
		$this->iCheckInTime = "";
		$resultSet = executeQuery("select * from employee_time_sheets where user_id = ? and date_entered = current_date and end_time is null",
			$GLOBALS['gUserId']);
		if ($row = getNextRow($resultSet)) {
			$this->iCheckInTime = date("g:ia", strtotime($row['start_time']));
		}
		?>
        <p id="clock_in_time"><?= ($this->iCheckInTime ? "Clocked in at " . $this->iCheckInTime : "") ?></p>
        <div id="button_div">
            <button id="clock_in">Clock In</button>
            <button id="clock_out">Clock Out</button>
        </div>
		<?php
		return true;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "clock_in":
				$resultSet = executeQuery("select * from employee_time_sheets where user_id = ? and date_entered = current_date and end_time is null",
					$GLOBALS['gUserId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = "Employee is already clocked in for today.";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("insert into employee_time_sheets (user_id,date_entered,start_time) values (?,now(),now())",
					$GLOBALS['gUserId']);
				$this->iCheckInTime = "";
				$resultSet = executeQuery("select * from employee_time_sheets where user_id = ? and date_entered = current_date and end_time is null",
					$GLOBALS['gUserId']);
				if ($row = getNextRow($resultSet)) {
					$this->iCheckInTime = date("g:ia", strtotime($row['start_time']));
				}
				if ($this->iCheckInTime) {
					$returnArray['clock_in_time'] = "Clocked in at " . $this->iCheckInTime . "</p>";
				} else {
					$returnArray['clock_in_time'] = "";
				}
				ajaxResponse($returnArray);
				break;
			case "clock_out":
				$resultSet = executeQuery("select * from employee_time_sheets where user_id = ? and date_entered = current_date and end_time is null",
					$GLOBALS['gUserId']);
				if (!$row = getNextRow($resultSet)) {
					$returnArray['error_message'] = "Employee has not been clocked in today.";
					ajaxResponse($returnArray);
					break;
				} else {
					executeQuery("update employee_time_sheets set end_time = now() where employee_time_sheet_id = ?",
						$row['employee_time_sheet_id']);
				}
				$this->iCheckInTime = "";
				$resultSet = executeQuery("select * from employee_time_sheets where user_id = ? and date_entered = current_date and end_time is null",
					$GLOBALS['gUserId']);
				if ($row = getNextRow($resultSet)) {
					$this->iCheckInTime = date("g:ia", strtotime($row['start_time']));
				}
				if ($this->iCheckInTime) {
					$returnArray['clock_in_time'] = "Clocked in at " . $this->iCheckInTime . "</p>";
				} else {
					$returnArray['clock_in_time'] = "";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#clock_in").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clock_in", function(returnArray) {
                    $("#clock_in").css("visibility", "hidden");
                    $("#clock_out").css("visibility", "visible");
                    if ("clock_in_time" in returnArray) {
                        $("#clock_in_time").html(returnArray['clock_in_time']);
                    }
                });
            });
            $("#clock_out").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clock_out", function(returnArray) {
                    $("#clock_out").css("visibility", "hidden");
                    $("#clock_in").css("visibility", "visible");
                    if ("clock_in_time" in returnArray) {
                        $("#clock_in_time").html(returnArray['clock_in_time']);
                    }
                });
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #clock_in_time {
                text-align: center;
                font-size: 20px;
                height: 26px;
            }
            #button_div {
                margin: 0 auto;
                width: 600px;
            }
            #button_div button {
                width: 250px;
                height: 60px;
                border-radius: 10px;
                margin: 10px 20px;
            }
            #button_div .ui-button-text {
                font-size: 28px;
                font-weight: normal;
            }
            <?php if ($this->iCheckInTime) { ?>
            #clock_in {
                visibility: hidden;
            }
            <?php } else { ?>
            #clock_out {
                visibility: hidden;
            }
            <?php } ?>
        </style>
		<?php
	}

}

$pageObject = new ThisPage("");
$pageObject->displayPage();
