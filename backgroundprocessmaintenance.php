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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESSMAINT";
require_once "shared/startup.inc";

class BackgroundProcessMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("repeat_rules");
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("run_now" => array("label" => getLanguageText("Run Now"), "disabled" => false)));
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("background_process_log", "background_process_notifications"));
		$this->iDataSource->addColumnControl("background_process_notifications", "data_type", "custom");
		$this->iDataSource->addColumnControl("background_process_notifications", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("background_process_notifications", "form_label", "Notifications");
		$this->iDataSource->addColumnControl("background_process_notifications", "list_table", "background_process_notifications");
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#_run_now_button", function () {
                window.open("/background/" + $("#script_filename").val());
                return false;
            });
            $(document).on("change", "#frequency", function () {
                $(".repeat-field").hide();
                const thisValue = $(this).val();
                $("." + thisValue.toLowerCase() + "-repeat").show();
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#frequency").trigger("change");
            }
        </script>
		<?php
	}

	function intervalFields() {
		?>
        <div class="basic-form-line" id="_frequency_row">
            <label for="frequency" class="required-label">Frequency</label>
            <select tabindex="10" class='field-text' name='frequency' id='frequency'>
                <option value="">[Select]</option>
                <option value="MINUTES">Every n minutes</option>
                <option value="HOURLY">Hourly</option>
                <option value="DAILY">Daily</option>
                <option value="WEEKLY">Weekly</option>
                <option value="MONTHLY">Monthly</option>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line repeat-field minutes-repeat" id="_minute_interval_row">
            <label for="minute_interval" class="required-label">Minutes Between</label>
            <input tabindex="10" class='validate[required,custom[integer],min[2]] align-right' type='text' size='4' maxlength='4' name='minute_interval' id='minute_interval' value=''/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line repeat-field monthly-repeat" id="_month_row">
            <label class="required-label">Months of the year</label>
            <table class="grid-table">
                <tr>
					<?php foreach ($GLOBALS['gMonthArray'] as $month => $description) { ?>
                        <th class="align-center"><label for="month_<?= $month ?>"><?= $description ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php foreach ($GLOBALS['gMonthArray'] as $month => $description) { ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $month ?>" name="month_<?= $month ?>" id="month_<?= $month ?>"></td>
					<?php } ?>
                </tr>
            </table>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line repeat-field monthly-repeat" id="_month_day_row">
            <label class="required-label">Day(s) of the month</label>
            <table class="grid-table">
                <tr>
					<?php for ($x = 1; $x <= 31; $x++) { ?>
                        <th class="align-center"><label for="month_day_<?= $x ?>"><?= ($x == 31 ? "Last" : $x) ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php for ($x = 1; $x <= 31; $x++) { ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $x ?>" name="month_day_<?= $x ?>" id="month_day_<?= $x ?>"></td>
					<?php } ?>
                </tr>
            </table>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line repeat-field weekly-repeat" id="_weekday_row">
            <label class="required-label">Day(s) of the week</label>
            <table class="grid-table">
                <tr>
					<?php foreach ($GLOBALS['gWeekdays'] as $weekday => $description) { ?>
                        <th class="align-center"><label for="weekday_<?= $weekday ?>"><?= $description ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php foreach ($GLOBALS['gWeekdays'] as $weekday => $description) { ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $weekday ?>" name="weekday_<?= $weekday ?>" id="weekday_<?= $weekday ?>"></td>
					<?php } ?>
                </tr>
            </table>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line repeat-field monthly-repeat weekly-repeat daily-repeat" id="_hour_of_day_row">
            <label class="required-label">Hour(s) of the day</label>
            <table class="grid-table">
                <tr>
					<?php
					for ($x = 0; $x <= 23; $x++) {
						$description = ($x == 0 ? "12" : ($x < 13 ? $x : ($x - 12))) . ($x == 0 || $x == 12 ? " " . ($x < 12 ? "am" : "pm") : "");
						?>
                        <th class="align-center"><label for="hour_<?= $x ?>"><?= $description ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php
					for ($x = 0; $x <= 23; $x++) {
						?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $x ?>" name="hour_<?= $x ?>" id="hour_<?= $x ?>"></td>

					<?php } ?>
                </tr>
            </table>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line repeat-field monthly-repeat weekly-repeat daily-repeat hourly-repeat" id="_hour_minute_row">
            <label for="hour_minute" class="required-label">Minute of the hour (0-59)</label>
            <input tabindex="10" class='validate[required,custom[integer],min[0],max[59]] align-right' type='text' size='4' maxlength='4' name='hour_minute' id='hour_minute' value=''/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

		<?php
	}

	function internalCSS() {
		?>
        <style>
			.basic-form-line th {
				padding: 0;
				margin: 0;
			}
			.basic-form-line .grid-table td {
				padding: 4px 0;
				margin: 0;
			}
			.basic-form-line th label:first-child {
				display: block;
				width: auto;
				margin: 0;
				padding: 4px 8px;
				float: none;
				text-align: center;
			}
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$repeatParts = explode(":", $returnArray['repeat_rules']['data_value']);
		$returnArray['frequency'] = array("data_value" => $repeatParts[0], "crc_value" => getCrcValue($repeatParts[0]));
		$returnArray['minute_interval'] = array("data_value" => $repeatParts[1], "crc_value" => getCrcValue($repeatParts[1]));
		$monthValues = explode(",", $repeatParts[2]);
		foreach ($GLOBALS['gMonthArray'] as $month => $description) {
			$returnArray['month_' . $month] = array("data_value" => (in_array($month, $monthValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($month, $monthValues) ? "1" : "0")));
		}
		$monthDayValues = explode(",", $repeatParts[3]);
		for ($x = 1; $x <= 31; $x++) {
			$returnArray['month_day_' . $x] = array("data_value" => (in_array($x, $monthDayValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($x, $monthDayValues) ? "1" : "0")));
		}
		$weekdayValues = explode(",", $repeatParts[4]);
		foreach ($GLOBALS['gWeekdays'] as $weekday => $description) {
			$returnArray['weekday_' . $weekday] = array("data_value" => (in_array($weekday, $weekdayValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($weekday, $weekdayValues) ? "1" : "0")));
		}
		$hourValues = explode(",", $repeatParts[5]);
		for ($x = 0; $x <= 23; $x++) {
			$returnArray['hour_' . $x] = array("data_value" => (in_array($x, $hourValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($x, $hourValues) ? "1" : "0")));
		}
		$returnArray['hour_minute'] = array("data_value" => $repeatParts[6], "crc_value" => getCrcValue($repeatParts[6]));
	}

	function beforeSaveChanges(&$dataValues) {
		$monthValues = "";
		foreach ($GLOBALS['gMonthArray'] as $month => $description) {
			if ($dataValues['month_' . $month]) {
				$monthValues .= (strlen($monthValues) == 0 ? "" : ",") . $dataValues['month_' . $month];
			}
		}
		$monthDayValues = "";
		for ($x = 1; $x <= 31; $x++) {
			if ($dataValues['month_day_' . $x]) {
				$monthDayValues .= (strlen($monthDayValues) == 0 ? "" : ",") . $dataValues['month_day_' . $x];
			}
		}
		$weekdayValues = "";
		foreach ($GLOBALS['gWeekdays'] as $weekday => $description) {
			if (strlen($dataValues['weekday_' . $weekday]) > 0) {
				$weekdayValues .= (strlen($weekdayValues) == 0 ? "" : ",") . $dataValues['weekday_' . $weekday];
			}
		}
		$hourValues = "";
		for ($x = 0; $x <= 23; $x++) {
			if (strlen($dataValues['hour_' . $x]) > 0) {
				$hourValues .= (strlen($hourValues) == 0 ? "" : ",") . $dataValues['hour_' . $x];
			}
		}
		switch ($dataValues['frequency']) {
			case "MINUTES":
				$monthValues = "";
				$monthDayValues = "";
				$weekdayValues = "";
				$hourValues = "";
				$dataValues['hour_minute'] = "";
				break;
			case "HOURLY":
				$monthValues = "";
				$monthDayValues = "";
				$weekdayValues = "";
				$hourValues = "";
				$dataValues['minute_interval'] = "";
				break;
			case "DAILY":
				$monthValues = "";
				$monthDayValues = "";
				$weekdayValues = "";
				$dataValues['minute_interval'] = "";
				break;
			case "WEEKLY":
				$monthValues = "";
				$monthDayValues = "";
				$dataValues['minute_interval'] = "";
				break;
			case "MONTHLY":
				$weekdayValues = "";
				$dataValues['minute_interval'] = "";
				break;
		}
		$dataValues['repeat_rules'] = $dataValues['frequency'] . ":" . $dataValues['minute_interval'] . ":" . $monthValues . ":" . $monthDayValues . ":" . $weekdayValues . ":" . $hourValues . ":" . $dataValues['hour_minute'];
		return true;
	}

}

$pageObject = new BackgroundProcessMaintenancePage("background_processes");
$pageObject->displayPage();
