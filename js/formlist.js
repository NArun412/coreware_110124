$(function() {
	$(document).on("click",".form-list-item-header",function(event) {
		if ($(this).hasClass("always-open")) {
			return;
		}
		if ($(this).closest(".form-list-item").data('div_open') == "true") {
			if (event.altKey) {
				$(this).closest(".form-list").find(".form-list-item").data("div_open","").find(".form-list-item-caret").removeClass("fa-caret-down").addClass("fa-caret-right");
				$(this).closest(".form-list").find(".form-list-item").find(".form-list-item-form").addClass("hidden");
			} else {
				$(this).closest(".form-list-item").data("div_open","").find(".form-list-item-caret").removeClass("fa-caret-down").addClass("fa-caret-right");
				$(this).closest(".form-list-item").find(".form-list-item-form").addClass("hidden");
			}
		} else {
			if (event.altKey) {
				$(this).closest(".form-list").find(".form-list-item").data("div_open","true").find(".form-list-item-caret").removeClass("fa-caret-right").addClass("fa-caret-down");
				$(this).closest(".form-list").find(".form-list-item").find(".form-list-item-form").removeClass("hidden");
			} else {
				$(this).closest(".form-list-item").data("div_open","true").find(".form-list-item-caret").removeClass("fa-caret-right").addClass("fa-caret-down");
				$(this).closest(".form-list-item").find(".form-list-item-form").removeClass("hidden");
				$(this).closest(".form-list-item").find(".form-list-item-form").find(":input:visible:first").focus();
			}
		}
	});
	$(document).on("click",".form-list-add-button",function() {
		if ($("#_permission").val() == "1") {
			return false;
		}
		addFormListRow($(this).data("list_identifier"));
		return false;
	});
	$(document).on("click",".form-list-remove",function() {
		if ($("#_permission").val() == "1") {
			return false;
		}
		var listIdentifier = $(this).data("list_identifier");
		$(this).closest(".form-list-item").find(":input").each(function() {
			$(this).validationEngine("hide");
		});
		var thisPrimaryId = $(this).closest(".form-list-item").find(".form-list-primary-id").val();
		if (thisPrimaryId != "" && thisPrimaryId != undefined) {
			var deleteIds = $("#_" + listIdentifier + "_delete_ids").val();
			if (deleteIds != "") {
				deleteIds += ",";
			}
			deleteIds += thisPrimaryId;
			$("#_" + listIdentifier + "_delete_ids").val(deleteIds);
		}
		$(this).closest(".form-list-item").remove();
		return false;
	});
	$(document).on("change",".form-list input,.form-list select,.form-list textarea",function() {
		var controlName = $(this).closest(".form-list").data("control_name");
		var rowId = $(this).closest(".form-list-item").attr("id");
		var titleGenerator = $("#_" + controlName + "_form_list").data("title_generator");
		var titleText = "";
		if (titleGenerator != "" && (titleGenerator in window && typeof window[titleGenerator] === 'function')) {
			titleText = window[titleGenerator](rowId);
		} else {
			titleText = createFormListTitle(rowId);
		}
		$("#" + rowId).find(".form-list-item-title").html(titleText);
	});
});

function addFormListRow(listName,rowData) {
	var isNewRow = false;
	if (rowData == null) {
		rowData = new Array();
		isNewRow = true;
	}
	var rowNumber = $("#_" + listName + "_form_list").data("row_number");
	const maximumRows = $("#_" + listName + "_form_list").data("maximum_rows");
	if (!empty(maximumRows)) {
		if ($("#_" + listName + "_form_list").find(".form-list-item-form").length >= maximumRows) {
			return false;
		}
	}
	$("#_" + listName + "_form_list").data("row_number",rowNumber - 0 + 1);
	var newRow = $("#_" + listName + "_new_row").html().replace(/%rowId%/g,rowNumber);
	$("#_" + listName + "_form_list").find(".form-list-add-button").before(newRow);
	if (isNewRow) {
		$("#_" + listName + "_row-" + rowNumber).data("div_open","true").find(".form-list-item-caret").removeClass("fa-caret-right").addClass("fa-caret-down");
		$("#_" + listName + "_row-" + rowNumber).find(".form-list-item-form").removeClass("hidden").find(":input:visible:first").focus();
	}
	$("#_" + listName + "_row-" + rowNumber).find("input,select,textarea").not(".autocomplete-field").not("input[type=hidden]").data("crc_value",getCrcValue(""));
	if ("not_editable" in rowData && rowData['not_editable']['data_value']) {
		$("#_" + listName + "_row-" + rowNumber).find("input,textarea").prop("readonly",true);
		$("#_" + listName + "_row-" + rowNumber).find("select").addClass("disabled-select");
		$("#_" + listName + "_row-" + rowNumber).find(".editable-list-remove").remove();
	}
	var noDelete = ($("#_" + listName + "_delete_ids").length == 0);
	for (var i in rowData) {
		if (i == "select_values") {
			for (var j in rowData['select_values']) {
				var fieldName = listName + "_" + j.replace("%rowId%",rowNumber);
				if (!$("#" + fieldName).is("select")) {
					continue;
				}
				$("#" + fieldName + " option").each(function() {
					if ($(this).data("inactive") == "1") {
						$(this).remove();
					}
				});
				for (var k in rowData['select_values'][j]) {
					if ($("#" + fieldName + " option[value='" + rowData['select_values'][j][k]['key_value'] + "']").length == 0) {
						var inactive = ("inactive" in rowData['select_values'][j][k] ? rowData['select_values'][j][k]['inactive'] : "0");
						$("#" + fieldName).append("<option data-inactive='" + inactive + "' value='" + rowData['select_values'][j][k]['key_value'] + "'>" + rowData['select_values'][j][k]['description'] + "</option>");
					}
				}
			}
			continue;
		}
		if (i.indexOf("%rowId%") >= 0) {
			var fieldSubname = i.replace("%rowId%",rowNumber);
		} else {
			var fieldSubname = i + "-" + rowNumber;
		}
		if ($("#" + listName + "_" + fieldSubname).is(".form-list-primary-id") && rowData[i]['data_value'] != "" && noDelete) {
			$("#_" + listName + "_row-" + rowNumber).find(".form-list-remove").remove();
		}
		if ($("#" + listName + "_" + fieldSubname).is("input[type=checkbox]")) {
			$("#" + listName + "_" + fieldSubname).prop("checked",rowData[i]['data_value'] == 1);
		} else if ($("#_" + fieldSubname + "_table").is(".editable-list")) {
			$("#_" + fieldSubname + "_table tr").not(":first").not(":last").remove();
			for (var j in rowData[i]) {
				addEditableListRow(fieldSubname,rowData[i][j]);
			}
		} else if (!isNewRow) {
			$("#" + listName + "_" + fieldSubname).val(rowData[i]['data_value']);
		}
		if ("crc_value" in rowData[i]) {
			$("#" + listName + "_" + fieldSubname).data("crc_value",rowData[i]['crc_value']);
		} else {
			$("#" + listName + "_" + fieldSubname).removeData("crc_value");
		}
		if ("image_view" in rowData[i]) {
			$("#" + listName + "_" + fieldSubname + "_view").attr("href",rowData[i]['image_view']);
			if (rowData[i]['image_view'] == "") {
				$("#" + listName + "_" + fieldSubname + "_view").hide();
			} else {
				$("#" + listName + "_" + fieldSubname + "_view").show();
			}
		}
		if ("file_download" in rowData[i]) {
			if (rowData[i]['data_value'] == "") {
				$("#" + listName + "_" + fieldSubname + "_download").attr("href",rowData[i]['file_download']).hide();
			} else {
				$("#" + listName + "_" + fieldSubname + "_download").attr("href",rowData[i]['file_download']).show();
			}
		}
	}
	if (isNewRow) {
		$("#_" + listName + "_row-" + rowNumber).find(".contact-picker-value").trigger("change");
		$("#_" + listName + "_row-" + rowNumber).find(".user-picker-value").trigger("change");
	}
	var afterAddRow = $("#_" + listName + "_form_list").data("after_add_row");
	if (afterAddRow != "" && (afterAddRow in window && typeof window[afterAddRow] === 'function')) {
		window[afterAddRow](rowNumber);
	}
	var titleGenerator = $("#_" + listName + "_form_list").data("title_generator");
	var titleText = "";
	if (titleGenerator != "" && (titleGenerator in window && typeof window[titleGenerator] === 'function')) {
		titleText = window[titleGenerator]("_" + listName + "_row-" + rowNumber);
	} else {
		titleText = createFormListTitle("_" + listName + "_row-" + rowNumber);
	}
	$("#_" + listName + "_row-" + rowNumber).find(".form-list-item-title").html(titleText);
	$("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({social_tools:false,default_height: 480,default_width: 854,deeplinking: false});
	if ($().timepicker) {
		$(".timepicker").timepicker({
			showPeriod: true,
			showLeadingZero: false
		});
	}
	$("#_" + listName + "_row-" + rowNumber).find(".template-datepicker").removeClass("template-datepicker").addClass("datepicker");
	$(".datepicker").datepicker({
		beforeShow: function(input,inst) {
			$(input).trigger("keydown");
		},
		onClose: function(dateText,inst) {
			$(this).trigger("keydown");
		},
		showOn: "button",
		buttonText: "<span class='fad fa-calendar-alt'></span>",
		constrainInput: false,
		dateFormat: "mm/dd/yy",
		yearRange: "c-100:c+10"
	});
	$("#_" + listName + "_row-" + rowNumber).find(".template-monthpicker").removeClass("template-monthpicker").addClass("monthpicker");
	$("#_" + listName + "_row-" + rowNumber).find('.monthpicker').datepicker( {
		showOn: "button",
		buttonText: "<span class='fad fa-calendar-alt'></span>",
		constrainInput: true,
		changeMonth: true,
		changeYear: true,
		showButtonPanel: true,
		dateFormat: 'MM yy',
		onClose: function(dateText, inst) {
			var month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
			var year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
			$(this).datepicker('setDate', new Date(year, month, 1));
			$(".ui-datepicker-calendar").removeClass("monthpicker");
		},
		afterShow: function(input, inst) {
			$(".ui-datepicker-calendar").addClass("monthpicker");
		},
		yearRange: "c-100:c+10"
	}).prop("readonly",true);
	return rowNumber;
}

function createFormListTitle(listName) {
	var titleText = "";
	$("#" + listName).find("input[type=text],select,textarea").each(function() {
		if (titleText == "") {
			titleText = ($(this).is("select") ? ($(this).find("option:selected").val() == "" ? "" : $(this).find("option:selected").text()) : $(this).val());
		} else {
			var extraText = ($(this).is("select") ? ($(this).find("option:selected").val() == "" ? "" : $(this).find("option:selected").text()) : $(this).val());
			if (extraText != "") {
				titleText += (titleText == "" ? "" : ", ") + extraText;
			}
			return false;
		}
	});
	return titleText;
}

