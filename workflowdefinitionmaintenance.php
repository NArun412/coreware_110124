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

$GLOBALS['gPageCode'] = "WORKFLOWDEFINITIONMAINT";
require_once "shared/startup.inc";

class WorkFlowDefinitionMaintenancePage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("work_flow_details","work_flow_user_access"));
	}

	function supplementaryContent() {
?>
<div class="form-line" id="_repeating_row">
	<label></label>
	<input tabindex="10" type="checkbox" id="repeating" name="repeating" value="1" /><label for="repeating" class="checkbox-label">Is Repeating</label>
	<div class='clear-div'></div>
</div>

<div id="repeating_rules">

<div class="form-line" id="_start_date_row">
	<label for="start_date">Start Date</label>
	<input tabindex="10" class="validate[custom[date]] datepicker" type="text" size="12" id="start_date" name="start_date" />
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_end_date_row">
	<label for="end_date">End Date</label>
	<input tabindex="10" class="validate[custom[date]] datepicker" type="text" size="12" id="end_date" name="end_date" />
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_frequency_row">
	<label for="frequency">Frequency</label>
	<select tabindex="10" class="validate[required]" name='frequency' id='frequency'>
		<option value="">[Select]</option>
		<option value="DAILY">Daily</option>
		<option value="WEEKLY">Weekly</option>
		<option value="MONTHLY">Monthly</option>
		<option value="YEARLY">Yearly</option>
		<option value="AFTER">After Completion of previous instance</option>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_interval_row">
	<label for="interval">Interval</label>
	<input tabindex="10" class='validate[required,custom[integer],min[1]] align-right' type='text' size='4' maxlength='4' name='interval' id='interval' value='' />
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_units_row">
	<label for="interval">After interval units</label>
	<select tabindex="10" name='units' id='units'>
		<option value="DAY">Days</option>
		<option value="WEEK">Weeks</option>
		<option value="MONTH">Months</option>
		<option value="YEAR">Years</option>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_bymonth_row">
	<label for="bymonth">Months</label>
	<table id='bymonth_table'>
	<tr>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='1' name='bymonth_1' id='bymonth_1' /><label for="bymonth_1" class="checkbox-label">January</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='4' name='bymonth_4' id='bymonth_4' /><label for="bymonth_4" class="checkbox-label">April</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='7' name='bymonth_7' id='bymonth_7' /><label for="bymonth_7" class="checkbox-label">July</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='10' name='bymonth_10' id='bymonth_10' /><label for="bymonth_10" class="checkbox-label">October</label></td>
	</tr>
	<tr>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='2' name='bymonth_2' id='bymonth_2' /><label for="bymonth_2" class="checkbox-label">February</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='5' name='bymonth_5' id='bymonth_5' /><label for="bymonth_5" class="checkbox-label">May</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='8' name='bymonth_8' id='bymonth_8' /><label for="bymonth_8" class="checkbox-label">August</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='11' name='bymonth_11' id='bymonth_11' /><label for="bymonth_11" class="checkbox-label">November</label></td>
	</tr>
	<tr>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='3' name='bymonth_3' id='bymonth_3' /><label for="bymonth_3" class="checkbox-label">March</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='6' name='bymonth_6' id='bymonth_6' /><label for="bymonth_6" class="checkbox-label">June</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='9' name='bymonth_9' id='bymonth_9' /><label for="bymonth_9" class="checkbox-label">September</label></td>
		<td><input tabindex="10" class='bymonth-month validate[minCheckbox[1]]' type='checkbox' rel='bymonth-month' value='12' name='bymonth_12' id='bymonth_12' /><label for="bymonth_12" class="checkbox-label">December</label></td>
	</tr>
	</table>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_byday_row">
	<label for="byday">Days</label>
<table id="byday_weekly_table">
<tr>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='SUN' name='byday_sun' id='byday_sun' /><label for="byday_sun" class="checkbox-label">Sunday</label></td>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='MON' name='byday_mon' id='byday_mon' /><label for="byday_mon" class="checkbox-label">Monday</label></td>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='TUE' name='byday_tue' id='byday_tue' /><label for="byday_tue" class="checkbox-label">Tuesday</label></td>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='WED' name='byday_wed' id='byday_wed' /><label for="byday_wed" class="checkbox-label">Wednesday</label></td>
</tr>
<tr>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='THU' name='byday_thu' id='byday_thu' /><label for="byday_thu" class="checkbox-label">Thursday</label></td>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='FRI' name='byday_fri' id='byday_fri' /><label for="byday_fri" class="checkbox-label">Friday</label></td>
	<td><input tabindex="10" class='byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='SAT' name='byday_sat' id='byday_sat' /><label for="byday_sat" class="checkbox-label">Saturday</label></td>
</tr>
</table>
<table id="byday_monthly_table">
<?php for ($x=1;$x<=5;$x++) { ?>
<tr class="byday-monthly-row">
	<td><select tabindex="10" class='ordinal-day' id='ordinal_day_<?= $x ?>' name='ordinal_day_<?= $x ?>'>
		<option value="">[Select]</option>
		<option value="1">1st</option>
		<option value="2">2nd</option>
		<option value="3">3rd</option>
		<option value="4">4th</option>
		<option value="5">5th</option>
		<option value="6">6th</option>
		<option value="7">7th</option>
		<option value="8">8th</option>
		<option value="9">9th</option>
		<option value="10">10th</option>
		<option value="11">11th</option>
		<option value="12">12th</option>
		<option value="13">13th</option>
		<option value="14">14th</option>
		<option value="15">15th</option>
		<option value="16">16th</option>
		<option value="17">17th</option>
		<option value="18">18th</option>
		<option value="19">19th</option>
		<option value="20">20th</option>
		<option value="21">21st</option>
		<option value="22">22nd</option>
		<option value="23">23rd</option>
		<option value="24">24th</option>
		<option value="25">25th</option>
		<option value="26">26th</option>
		<option value="27">27th</option>
		<option value="28">28th</option>
		<option value="29">29th</option>
		<option value="30">30th</option>
		<option value="31">31st</option>
		<option value="-">Last</option>
	</select></td>
	<td><select tabindex="10" class='weekday-select' id='weekday_<?= $x ?>' name='weekday_<?= $x ?>'>
		<option value="">Day of the Month</option>
		<option value="SUN">Sunday</option>
		<option value="MON">Monday</option>
		<option value="TUE">Tuesday</option>
		<option value="WED">Wednesday</option>
		<option value="THU">Thursday</option>
		<option value="FRI">Friday</option>
		<option value="SAT">Saturday</option>
	</select></td>
</tr>
<?php } ?>
</table>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_count_row">
	<label for="count">Number of occurrences</label>
	<input tabindex="10" class="validate[custom[integer]] min[1]" type="text" size="6" id="count" name="count" />
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_due_after_row">
	<label for="due_after">Days until due</label>
	<input tabindex="10" class="validate[custom[integer]] min[1]" type="text" size="6" id="due_after" name="due_after" />
	<div class='clear-div'></div>
</div>

</div>

<h2>Steps<input type="hidden" id="detail_list" name="detail_list" value=""/></h2>

<table id="detail_table" class="grid-table">
<tbody id="detail_rows">
</tbody>
</table>
<p><button id="add_detail" tabindex="10">Add a step</button></p>
<?php
	}

	function onLoadJavascript() {
?>
        <script>
$("#frequency").change(function() {
	$("#bymonth_row").hide();
	$("#byday_row").hide();
	$("#byday_weekly_table").hide();
	$("#byday_monthly_table").hide();
	$(".byday-monthly-row").hide();
	$("#_units_row").hide();
	$("#count_row").show();
    const thisValue = $(this).val();
    if (thisValue === "WEEKLY") {
		$("#byday_row").show();
		$("#byday_weekly_table").show();
	} else if (thisValue === "MONTHLY") {
		$("#byday_row").show();
		$("#byday_monthly_table").show();
		$(".byday-monthly-row").each(function() {
			$(this).show();
			if (empty($(this).find(".ordinal-day").val())) {
				return false;
			}
		});
	} else if (thisValue === "YEARLY") {
		$("#bymonth_row").show();
		$("#byday_row").show();
		$("#byday_monthly_table").show();
		$(".byday-monthly-row").each(function() {
			$(this).show();
			if (empty($(this).find(".ordinal-day").val())) {
				return false;
			}
		});
	} else if (thisValue === "AFTER") {
		$("#_units_row").show();
		$("#count_row").val("").hide();
	}
});
$(".ordinal-day").change(function() {
	if (!empty($(this).val())) {
		$(".ordinal-day").each(function() {
			if (!$(this).is(":visible")) {
				$(this).closest(".byday-monthly-row").show();
				return false;
			} else if (empty($(this).val())) {
				return false;
			}
		});
	}
});
$("#repeating").change(function() {
	if ($(this).is(":checked")) {
		$("#start_date_label").addClass("required-label");
		$("#start_date_label").append("<span class='required-tag'>*</span>");
		$("#repeating_rules").show();
		$("#date_due").hide();
		$("#date_due").next(".ui-datepicker-trigger").hide();
		$("#due_days").show();
		$("#date_due_label").html("Due after X days");
	} else {
		$("#start_date_label").removeClass("required-label");
		$("#start_date_row").find(".required-tag").remove();
		$("#repeating_rules").hide();
		$("#date_due").show();
		$("#date_due").next(".ui-datepicker-trigger").show();
		$("#due_days").hide();
		$("#date_due_label").html("Due");
	}
});
$("#wfd_task_type_id").change(function() {
    const userId = $("#wfd_task_type_id option:selected").data("user_id");
    const userGroupId = $("#wfd_task_type_id option:selected").data("user_group_id");
    if (!empty(userId) && empty($("#wfd_user_id").val()) {
		$("#wfd_user_id").val(userId);
	}
	if (!empty(userGroupId) && empty($("#wfd_user_group_id").val())) {
		$("#wfd_user_group_id").val(userGroupId);
	}
});
$(document).on("tap click","#add_detail",function() {
	$("#_detail_form").clearForm();
	$("#wfd_action").val("T");
	$("#task_list_div").html("");
    const detailList = JSON.parse($("#detail_list").val());
    for (const i in detailList) {
        const checkbox = "<p><input class='validate[minCheckbox[1]]' rel='selected-tasks' type='checkbox' value='1' id='wfd_selected_task_" + detailList[i]['wfd_work_flow_detail_code'] + "' name='wfd_selected_task_" +
            detailList[i]['wfd_work_flow_detail_code'] + "' /><label for='wfd_selected_task_" + detailList[i]['wfd_work_flow_detail_code'] +
            "' class='checkbox-label'>" + detailList[i]['wfd_description'] + "</label></p>";
        $("#task_list_div").append(checkbox);
	}
	$('#_details').dialog({
		closeOnEscape: true,
		draggable: true,
		modal: true,
		resizable: false,
		position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
		title: 'Edit Details',
		width: '800',
		open: function(event,ui) {
			$("#wfd_action").trigger("change");
			$("#wfd_when").trigger("change");
			$("#_detail_form").find("input,select").each(function() {
                const defaultValue = $(this).data("default_value");
                if (!empty(defaultValue) && defaultValue.toString().length > 0) {
					$(this).val($(this).data("default_value"));
				}
			});
		},
		buttons:{
			Save: function (event) {
				if ($("#_detail_form").validationEngine("validate")) {
                    let detailList = JSON.parse($("#detail_list").val());
                    if (empty(detailList)) {
						detailList = {};
					}
                    let newIndex = 0;
                    for (const i in detailList) {
						if (i > newIndex) {
							newIndex = i;
						}
					}
					newIndex++;
					if (empty($("#wfd_work_flow_detail_code").val())) {
						$("#wfd_work_flow_detail_code").val(getCrcValue(newIndex + $("#wfd_description").val() + (new Date().getTime())).replace("#",""));
					}
					detailList[newIndex] = {};
					$("#_detail_form").find("input,select").each(function() {
						detailList[newIndex][$(this).attr("id")] = ($(this).is("input[type=checkbox]") ? ($(this).is(":checked") ? $(this).val() : "") : $(this).val());
					});
					$("#detail_list").val(JSON.stringify(detailList));
					$("#_details").dialog('close');
					displayDetails();
				}
				$("#_details").dialog('close');
			},
			Cancel: function (event) {
				$("#_details").dialog('close');
			}
		}
	});
	return false;
});
$("#wfd_action").change(function() {
	$(".action-row").hide();
	$(".wfd-" + $(this).val().toLowerCase() + "-row").show();
}).trigger("change");
$("#wfd_when").change(function() {
	if ($(this).val() === "completed") {
		$("#wfd_task_list_row").show();
	} else {
		$("#wfd_task_list_row").hide();
	}
}).trigger("change");
$("#detail_rows").sortable({
	update: function() {
		changeSortOrder();
	}
});
$(document).on("tap click",".detail-row",function() {
	$("#_detail_form").clearForm();
	$("#wfd_action").val("T");
    const thisIndex = $(this).data("row_index");
    $("#task_list_div").html("");
    let detailList = JSON.parse($("#detail_list").val());
    if (empty(detailList)) {
		detailList = {};
	}
	for (const i in detailList) {
		if (i === thisIndex) {
			continue;
		}
        const checkbox = "<p><input class='validate[minCheckbox[1]]' rel='selected-tasks' type='checkbox' value='1' id='wfd_selected_task_" + detailList[i]['wfd_work_flow_detail_code'] + "' name='wfd_selected_task_" +
            detailList[i]['wfd_work_flow_detail_code'] + "' /><label for='wfd_selected_task_" + detailList[i]['wfd_work_flow_detail_code'] +
            "' class='checkbox-label'>" + detailList[i]['wfd_description'] + "</label></p>";
        $("#task_list_div").append(checkbox);
	}
	for (const i in detailList[thisIndex]) {
		($("#" + i).is(":checkbox") ? $("#" + i).prop("checked",!empty(detailList[thisIndex][i])) : $("#" + i).val(detailList[thisIndex][i]));
	}
	$("#detail_list_index").val(thisIndex);
	$('#_details').dialog({
		closeOnEscape: true,
		draggable: true,
		modal: true,
		resizable: false,
		position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
		title: 'Edit Details',
		width: '600',
		open: function(event,ui) {
			$("#wfd_action").trigger("change");
			$("#wfd_when").trigger("change");
		},
		buttons:{
			Save: function (event) {
				if ($("#_detail_form").validationEngine("validate")) {
                    let detailList = JSON.parse($("#detail_list").val());
                    if (empty(detailList)) {
						detailList = {};
					}
                    const thisIndex = $("#detail_list_index").val();
                    if (empty($("#wfd_work_flow_detail_code").val())) {
						$("#wfd_work_flow_detail_code").val(getCrcValue(thisIndex + $("#wfd_description").val() + (new Date().getTime())).replace("#",""));
					}
					detailList[thisIndex] = {};
					$("#_detail_form").find("input,select").each(function() {
						detailList[thisIndex][$(this).attr("id")] = $(this).val();
					});
					$("#detail_list").val(JSON.stringify(detailList));
					$("#_details").dialog('close');
					displayDetails();
				}
			},
			Cancel: function (event) {
				$("#_details").dialog('close');
			}
		}
	});
	return false;
});
        </script>
<?php
	}

	function javascript() {
?>
        <script>
function beforeSaveChanges() {
	let foundDay = true;
	if ($("#repeating").is(":checked")) {
        const thisValue = $("#frequency").val();
        if (thisValue === "MONTHLY" || thisValue === "YEARLY") {
			foundDay = false;
			for (let x=1; x<=5; x++) {
				if (!empty($("#ordinal_day_" + x).val())) {
					foundDay = true;
					break;
				}
			}
		}
	}
	if (!foundDay) {
		$("#ordinal_day_1").validationEngine("showPrompt","At least one day is required.");
		return false;
	}
	return true;
}
function afterGetRecord() {
	displayDetails();
	$("#repeating").trigger("change");
	$("#frequency").trigger("change");
}
function changeSortOrder() {
    let detailList = JSON.parse($("#detail_list").val());
    if (empty(detailList)) {
		detailList = {};
	}
    const newList = {};
    let newIndex = 0;
    $("#detail_table tr").each(function() {
        const index = $(this).data("row_index");
        newIndex++;
		newList[newIndex] = detailList[index];
	});
	$("#detail_list").val(JSON.stringify(newList));
	displayDetails();
}
function displayDetails() {
    let detailList = JSON.parse($("#detail_list").val());
    if (empty(detailList)) {
		detailList = {};
	}
	$("#detail_table tr").remove();
    let index = 0;
    for (const i in detailList) {
		index++;
        let description = detailList[i]['wfd_description'] + ": ";
        if (detailList[i]['wfd_action'] === "T") {
			description += "Create task of type '" + $("#wfd_task_type_id option[value='" + detailList[i]['wfd_task_type_id'] + "']").text() + "'";
		} else {
			description += "Send an email" + (empty(detailList[i]['wfd_email_address']) ? "" : " to " + detailList[i]['wfd_email_address']);
		}
        let units = $("#wfd_units option[value='" + detailList[i]['wfd_units'] + "']").text();
        if (detailList[i]['wfd_unit_quantity'] === "1") {
			units = units.replace(/s$/,"");
		}
		description += ", starts " + (detailList[i]['wfd_unit_quantity'] === "0" ? "" : detailList[i]['wfd_unit_quantity'] + " " +
			units + " ") + $("#wfd_when option[value='" + detailList[i]['wfd_when'] + "']").text();
        const assignedUserId = detailList[i]['wfd_user_id'];
        if (!empty(assignedUserId)) {
			description += ", assigned to " + $("#wfd_user_id option[value='" + assignedUserId + "']").text();
		}
        const rowContent = "<tr class='detail-row' data-row_index='" + i + "'><td class='align-center'><img src='/images/drag_strip.png' alt='Drag Strip' /></td><td>Step " + index + "</td><td>" +
            description + "</td></tr>";
        $("#detail_rows").append(rowContent);
	}
}
        </script>
<?php
	}

	function hiddenElements() {
?>
<div id="_details" class="dialog-box">
<form id="_detail_form" name="_detail_form">
<input type="hidden" id="wfd_work_flow_detail_id" name="wfd_work_flow_detail_id" />
<input type="hidden" id="detail_list_index" name="detail_list_index" />
<p class="subheader">Work Flow Step Details</p>

<div class="form-line" id="_wfd_description_row">
	<label>Description</label>
	<input type="text" size="40" maxlength="255" class="validate[required]" id="wfd_description" name="wfd_description" value="" /><input type="hidden" id="wfd_work_flow_detail_code" name="wfd_work_flow_detail_code" value="" />
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_wfd_action_row">
	<label>Action</label>
	<select id="wfd_action" name="wfd_action">
		<option value="T">Create Task</option>
		<option value="E">Send Email</option>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line wfd-t-row action-row" id="_wfd_task_type_id_row">
	<label>Task Type</label>
	<select class="validate[required]" id="wfd_task_type_id" name="wfd_task_type_id">
		<option value="">[Select]</option>
<?php
		$resultSet = executeQuery("select * from task_types where client_id = ? and inactive = 0 and task_type_id not in (select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'REPEATABLE')) order by sort_order,description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
?>
		<option data-user_id="<?= $row['user_id'] ?>" data-user_group_id="<?= $row['user_group_id'] ?>" value="<?= $row['task_type_id'] ?>"><?= htmlText($row['description']) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line wfd-t-row action-row" id="_wfd_task_description_row">
	<label>Task Description</label>
	<input type="text" size="40" maxlength="255" id="wfd_task_description" name="wfd_task_description" value="" />
	<div class='clear-div'></div>
</div>

<div class="form-line wfd-t-row action-row" id="_wfd_user_id_row">
	<label>Assign To</label>
	<select id="wfd_user_id" name="wfd_user_id">
		<option value="">[Select]</option>
<?php
		$resultSet = executeQuery("select *,(select concat_ws(' ',first_name,last_name) from contacts " .
			"where contact_id = users.contact_id) full_name from users where administrator_flag = 1 and " .
			"inactive = 0 order by full_name");
		while ($row = getNextRow($resultSet)) {
?>
		<option value="<?= $row['user_id'] ?>"><?= htmlText($row['full_name']) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>

<?php
		$resultSet = executeQuery("select * from user_groups where client_id = ? and inactive = 0 order by sort_order,description",$GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
?>
<div class="form-line wfd-t-row action-row" id="_wfd_user_group_id_row">
	<label>Assign to Group</label>
	<select id="wfd_user_group_id" name="wfd_user_group_id">
		<option value="">[Select]</option>
<?php
			while ($row = getNextRow($resultSet)) {
?>
		<option value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
<?php
			}
?>
	</select>
	<div class='clear-div'></div>
</div>
<?php } ?>

<div class="form-line wfd-e-row action-row" id="_wfd_email_id_row">
	<label>Email</label>
	<select class="validate[required]" id="wfd_email_id" name="wfd_email_id">
		<option value="">[Select]</option>
<?php
		$resultSet = executeQuery("select * from emails where inactive = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
?>
		<option value="<?= $row['email_id'] ?>"><?= htmlText($row['description']) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line wfd-e-row action-row" id="_wfd_email_address_row">
	<label for="wfd_email_address">Email Address</label>
	<input type="text" size="40" maxlength="60" class="validate[custom[email]]" id="wfd_email_address" name="wfd_email_address" value="" /><span class="extra-info">Leave blank to use contact</span>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_wfd_email_id_row">
	<label>Set Work Flow Status</label>
	<select id="wfd_work_flow_status_id" name="wfd_work_flow_status_id">
		<option value="">[Select]</option>
<?php
		$resultSet = executeQuery("select * from work_flow_status where inactive = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
?>
		<option value="<?= $row['work_flow_status_id'] ?>"><?= htmlText($row['description']) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_wfd_unit_quantity_row">
	<label>Set Work Flow Status</label>
	<div>
	<input type="text" size="4" maxlength="4" class="float-left validate[required,custom[integer]]" id="wfd_unit_quantity" name="wfd_unit_quantity" value="" data-default_value="0" />
	<select id="wfd_units" name="wfd_units" class="float-left">
		<option value="days">Days</option>
	</select>
	<select id="wfd_when" name="wfd_when" class="float-left">
		<option value="after">After Work Flow is created</option>
		<option value="before">Before Work Flow due date</option>
		<option value="previous">After completion of previous task</option>
		<option value="completed">After completion of selected tasks</option>
	</select>
	<div class='clear-div'></div>
	</div>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_wfd_task_list_row">
	<label for="wfd_task_list">Selected Tasks</label>
	<div id="task_list_div">
	</div>
	<div class='clear-div'></div>
</div>

<div class="form-line wfd-t-row action-row" id="_wfd_days_required_row">
	<label for="wfd_days_required">Days until due</label>
	<input type="text" size="4" maxlength="4" class="validate[required,custom[integer]]" id="wfd_days_required" name="wfd_days_required" value="" />
	<div class='clear-div'></div>
</div>

</form>
</div>
<?php
	}

	function internalCSS() {
?>
        <style>
#task_list_div { height: 150px; width: 400px; overflow: auto; border: 1px solid rgb(180,180,180); padding: 10px; }
#detail_table { border: 1px solid rgb(180,180,180); }
#detail_table td { font-size: 12px; padding-left: 10px; padding-right: 10px; }
.detail-row:hover { background-color: rgb(250,250,200); cursor: pointer; }
#repeating_rules { display: none; }
#bymonth_table td { padding-right: 20px; }
#bymonth_row { display: none; }
#byday_row { display: none; }
#byday_weekly_table { display: none; }
#byday_weekly_table td { padding-right: 20px; }
#byday_monthly_table { display: none; }
#_units_row { display: none; }
        </style>
<?php
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		$detailList = json_decode($nameValues['detail_list'],true);
		$sequenceNumber = 0;
		$lastWorkFlowCode = "";
		foreach ($detailList as $details) {
			$workFlowDetailId = $details['wfd_work_flow_detail_id'];
			$workFlowDetailCode = $details['wfd_work_flow_detail_code'];
			$description = $details['wfd_description'];
			$taskTypeId = $details['wfd_task_type_id'];
			$taskDescription = $details['wfd_task_description'];
			$userId = $details['wfd_user_id'];
			$userGroupId = $details['wfd_user_group_id'];
			$emailId = $details['wfd_email_id'];
			$emailAddress = $details['wfd_email_address'];
			$workFlowStatusId = $details['wfd_work_flow_status_id'];
			$sequenceNumber++;
			$daysRequired = $details['wfd_days_required'];
			if (empty($daysRequired)) {
				$daysRequired = 0;
			}
			$startRules = "";
			switch ($details['wfd_when']) {
				case "after":
					$startRules = "AFTER=" . $details['wfd_unit_quantity'] . ";";
					break;
				case "before":
					$startRules = "BEFORE=" . $details['wfd_unit_quantity'] . ";";
					break;
				case "previous":
					if (empty($lastWorkFlowCode)) {
						$startRules = "AFTER=0;";
					} else {
						$startRules = "COMPLETED=" . $lastWorkFlowCode . ";AFTER=" . $details['wfd_unit_quantity'] . ";";
					}
					break;
				case "completed":
					$codeList = "";
					foreach ($details as $fieldName => $fieldValue) {
						if (substr($fieldName,0,strlen("wfd_selected_task_")) == "wfd_selected_task_") {
							if (!empty($fieldValue)) {
								if (!empty($codeList)) {
									$codeList .= ",";
								}
								$codeList .= str_replace("wfd_selected_task_","",$fieldName);
							}
						}
					}
					if (empty($codeList)) {
						$startRules = "AFTER=0;";
					} else {
						$startRules = "COMPLETED=" . $codeList . ";AFTER=" . $details['wfd_unit_quantity'] . ";";
					}
					break;
			}
			$startRules .= "UNITS=" . $details['wfd_units'] . ";";
			if (empty($workFlowDetailId)) {
				$resultSet = executeQuery("insert into work_flow_details (work_flow_definition_id,work_flow_detail_code,description," .
					"task_type_id,task_description,user_id,user_group_id,email_id,email_address,sequence_number,work_flow_status_id,start_rules,days_required) values " .
					"(?,?,?,?,?,?,?,?,?,?,?,?,?)",$nameValues['primary_id'],$workFlowDetailCode,$description,$taskTypeId,$taskDescription,
					$userId,$userGroupId,$emailId,$emailAddress,$sequenceNumber,$workFlowStatusId,$startRules,$daysRequired);
			} else {
				$resultSet = executeQuery("update work_flow_details set work_flow_detail_code = ?,description = ?,task_type_id = ?," .
					"task_description = ?,user_id = ?,user_group_id = ?,email_id = ?,email_address = ?,sequence_number = ?," .
					"work_flow_status_id = ?,start_rules = ?,days_required = ? where " .
					"work_flow_detail_id = ?",$workFlowDetailCode,$description,$taskTypeId,$taskDescription,$userId,$userGroupId,
					$emailId,$emailAddress,$sequenceNumber,$workFlowStatusId,$startRules,$daysRequired,$workFlowDetailId);
			}
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic",$resultSet['sql_error']);
			}
			$lastWorkFlowCode = $workFlowDetailCode;
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$detailList = array();
		$resultSet = executeQuery("select * from work_flow_details where work_flow_definition_id = ? order by sequence_number",$returnArray['work_flow_definition_id']['data_value']);
		$index = 0;
		$lastWorkFlowCode = "";
		while ($row = getNextRow($resultSet)) {
			foreach ($row as $fieldName => $fieldValue) {
				if (strlen($fieldValue) == 0) {
					$row[$fieldName] = "";
				}
			}
			$index++;
			$detailList[$index] = array();
			$detailList[$index]['wfd_work_flow_detail_id'] = $row['work_flow_detail_id'];
			$detailList[$index]['wfd_work_flow_detail_code'] = $row['work_flow_detail_code'];
			$detailList[$index]['wfd_description'] = $row['description'];
			$detailList[$index]['wfd_task_type_id'] = $row['task_type_id'];
			$detailList[$index]['wfd_task_description'] = $row['task_description'];
			$detailList[$index]['wfd_user_id'] = $row['user_id'];
			$detailList[$index]['wfd_user_group_id'] = $row['user_group_id'];
			$detailList[$index]['wfd_email_id'] = $row['email_id'];
			$detailList[$index]['wfd_email_address'] = $row['email_address'];
			$detailList[$index]['wfd_days_required'] = $row['days_required'];
			$detailList[$index]['wfd_work_flow_status_id'] = $row['work_flow_status_id'];
			$rules = parseNameValues($row['start_rules']);
			$detailList[$index]['wfd_unit_quantity'] = (array_key_exists("before",$rules) ? $rules['before'] : $rules['after']);
			if (empty($detailList[$index]['wfd_unit_quantity'])) {
				$detailList[$index]['wfd_unit_quantity'] = 0;
			}
			$detailList[$index]['wfd_units'] = $rules['units'];
			if (empty($detailList[$index]['wfd_units'])) {
				$detailList[$index]['wfd_units'] = "days";
			}
			$detailList[$index]['wfd_when'] = "";
			if (!empty($rules['completed'])) {
				if ($rules['completed'] == $lastWorkFlowCode) {
					$detailList[$index]['wfd_when'] = "previous";
				} else {
					$detailList[$index]['wfd_when'] = "completed";
				}
			} else if (!empty($rules['before'])) {
				$detailList[$index]['wfd_when'] = "before";
			} else {
				$detailList[$index]['wfd_when'] = "after";
			}
			if ($detailList[$index]['wfd_when'] == "completed") {
				foreach (explode(",",$rules['completed']) as $workFlowDetailCode) {
					$detailList[$index]['wfd_selected_task_' . $workFlowDetailCode] = "1";
				}
			}
			$detailList[$index]['wfd_action'] = (empty($row['task_type_id']) ? "E" : "T");
			$lastWorkFlowCode = $row['work_flow_detail_code'];
		}
		$returnArray['detail_list'] = array("data_value"=>jsonEncode($detailList),"crc_value"=>getCrcValue(jsonEncode($detailList)));

		$repeatRules = getFieldFromId("repeat_rules","work_flow_definitions","work_flow_definition_id",$returnArray['primary_id']['data_value']);
		if (!empty($repeatRules)) {
			$returnArray['repeating'] = array("data_value"=>1,"crc_value"=>getCrcValue("1"));
			$parsedRepeatRules = parseNameValues($repeatRules);
			$returnArray['start_date'] = array("data_value"=>(empty($parsedRepeatRules['start_date']) ? "" : date("m/d/Y",strtotime($parsedRepeatRules['start_date']))),"crc_value"=>getCrcValue((empty($parsedRepeatRules['start_date']) ? "" : date("m/d/Y",strtotime($parsedRepeatRules['start_date'])))));
			$returnArray['end_date'] = array("data_value"=>(empty($parsedRepeatRules['until']) ? "" : date("m/d/Y",strtotime($parsedRepeatRules['until']))),"crc_value"=>getCrcValue((empty($parsedRepeatRules['until']) ? "" : date("m/d/Y",strtotime($parsedRepeatRules['until'])))));
			$returnArray['frequency'] = array("data_value"=>$parsedRepeatRules['frequency'],"crc_value"=>getCrcValue($parsedRepeatRules['frequency']));
			$returnArray['interval'] = array("data_value"=>$parsedRepeatRules['interval'],"crc_value"=>getCrcValue($parsedRepeatRules['interval']));
			$returnArray['units'] = array("data_value"=>(empty($parsedRepeatRules['units']) ? "DAY" : $parsedRepeatRules['units']),"crc_value"=>getCrcValue((empty($parsedRepeatRules['units']) ? "DAY" : $parsedRepeatRules['units'])));
			if ($parsedRepeatRules['frequency'] == "WEEKLY") {
				$byDay = explode(",",$parsedRepeatRules['byday']);
			} else {
				$byDay = array();
			}
			foreach ($GLOBALS['gWeekdayCodes'] as $weekdayCode => $description) {
				$returnArray['byday_' . strtolower($weekdayCode)] = array("data_value"=>(in_array($weekdayCode,$byDay) ? 1 : 0),"crc_value"=>getCrcValue((in_array($weekdayCode,$byDay) ? 1 : 0)));
			}
			if ($parsedRepeatRules['frequency'] == "YEARLY") {
				$byMonth = explode(",",$parsedRepeatRules['bymonth']);
			} else {
				$byMonth = array();
			}
			for ($x=1;$x<=12;$x++) {
				$returnArray['bymonth_' . $x] = array("data_value"=>(in_array($x,$byMonth) ? 1 : 0),"crc_value"=>getCrcValue((in_array($x,$byMonth) ? 1 : 0)));
			}
			if ($parsedRepeatRules['frequency'] == "MONTHLY" || $parsedRepeatRules['frequency'] == "YEARLY") {
				$byDay = explode(",",$parsedRepeatRules['byday']);
			} else {
				$byDay = array();
			}
			for ($x=1;$x<=5;$x++) {
				$thisDay = $byDay[($x - 1)];
				$thisWeekDay = "";
				if (strlen($thisDay) < 3) {
					$thisOrdinalDay = $thisDay;
				} else {
					$thisOrdinalDay = substr($thisDay,0,-3);
					$thisWeekDay = substr($thisDay,-3);
				}
				$returnArray['ordinal_day_' . $x] = array("data_value"=>$thisOrdinalDay,"crc_value"=>getCrcValue($thisOrdinalDay));
				$returnArray['weekday_' . $x] = array("data_value"=>$thisWeekDay,"crc_value"=>getCrcValue($thisWeekDay));
			}
			$returnArray['count'] = array("data_value"=>$parsedRepeatRules['count'],"crc_value"=>getCrcValue($parsedRepeatRules['count']));
			$returnArray['due_after'] = array("data_value"=>$parsedRepeatRules['due_after'],"crc_value"=>getCrcValue($parsedRepeatRules['due_after']));
		} else {
			$returnArray['repeating'] = array("data_value"=>0,"crc_value"=>getCrcValue("0"));
		}
		return true;
	}

	function beforeSaveChanges(&$dataValues) {
		$dataValues['repeat_rules'] = "";
		if (!empty($dataValues['repeating'])) {
			$repeatRules = "FREQUENCY=" . $dataValues['frequency'] . ";";
			$repeatRules .= "START_DATE=" . (empty($dataValues['start_date']) ? date("m/d/Y") : date("m/d/Y",strtotime($dataValues['start_date']))) . ";";
			if (!empty($dataValues['end_date'])) {
				$repeatRules .= "UNTIL=" . date("m/d/Y",strtotime($dataValues['end_date'])) . ";";
			}
			if (!empty($dataValues['interval']) && $dataValues['interval'] != "1") {
				$repeatRules .= "INTERVAL=" . $dataValues['interval'] . ";";
			} else {
				$repeatRules .= "INTERVAL=1;";
			}
			if ($dataValues['frequency'] == "AFTER") {
				$repeatRules .= "UNITS=" . $dataValues['units'] . ";";
			} else if ($dataValues['frequency'] == "YEARLY") {
				$repeatRules .= "BYMONTH=";
				$parts = "";
				foreach ($dataValues as $fieldName => $fieldData) {
					if (substr($fieldName,0,strlen("bymonth_")) == "bymonth_" && !empty($fieldData)) {
						if (!empty($parts)) {
							$parts .= ",";
						}
						$parts .= $fieldData;
					}
				}
				$repeatRules .= $parts . ";";
				$repeatRules .= "BYDAY=";
				$parts = "";
				foreach ($dataValues as $fieldName => $fieldData) {
					if (substr($fieldName,0,strlen("ordinal_day_")) == "ordinal_day_" && !empty($fieldData)) {
						$fieldNumber = substr($fieldName,strlen("ordinal_day_"));
						if (!empty($fieldData)) {
							if (!empty($parts)) {
								$parts .= ",";
							}
							$parts .= $fieldData;
							$parts .= $dataValues['weekday_' . $fieldNumber];
						}
					}
				}
				$repeatRules .= $parts . ";";
			} else if ($dataValues['frequency'] == "WEEKLY") {
				$repeatRules .= "BYDAY=";
				$parts = "";
				foreach ($dataValues as $fieldName => $fieldData) {
					if (substr($fieldName,0,strlen("byday_")) == "byday_" && !empty($fieldData)) {
						if (!empty($parts)) {
							$parts .= ",";
						}
						$parts .= $fieldData;
					}
				}
				$repeatRules .= $parts . ";";
			} else if ($dataValues['frequency'] == "MONTHLY") {
				$repeatRules .= "BYDAY=";
				$parts = "";
				foreach ($dataValues as $fieldName => $fieldData) {
					if (substr($fieldName,0,strlen("ordinal_day_")) == "ordinal_day_" && !empty($fieldData)) {
						$fieldNumber = substr($fieldName,strlen("ordinal_day_"));
						if (!empty($fieldData)) {
							if (!empty($parts)) {
								$parts .= ",";
							}
							$parts .= $fieldData;
							$parts .= $dataValues['weekday_' . $fieldNumber];
						}
					}
				}
				$repeatRules .= $parts . ";";
			}
			if (!empty($dataValues['count'])) {
				$repeatRules .= "COUNT=" . $dataValues['count'] . ";";
			}
			if (!empty($dataValues['due_after'])) {
				$repeatRules .= "DUE_AFTER=" . $dataValues['due_after'] . ";";
			}
			if (!empty($dataValues['primary_id'])) {
				$oldRepeatRules = parseNameValues(getFieldFromId("repeat_rules","work_flow_definitions","work_flow_definition_id",$dataValues['primary_id']));
				if (!empty($oldRepeatRules['not'])) {
					$repeatRules .= "NOT=" . $oldRepeatRules['not'] . ";";
				}
			}
			$dataValues['repeat_rules'] = $repeatRules;
		}
		return true;
	}
}

$pageObject = new WorkFlowDefinitionMaintenancePage("work_flow_definitions");
$pageObject->displayPage();
