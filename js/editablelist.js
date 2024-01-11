$(function() {
	$(document).on("click",".editable-list-header",function() {
		var columnNumber = $(this).parent().children().index($(this));
		var sortDirection = 1;
		if ($(this).find(".fa-sort-up").length > 0) {
			sortDirection = -1;
		}
		var sortObjects = new Array();
		$(this).closest("table").find("tr.editable-list-data-row").each(function() {
			var thisId = $(this).attr("id");
			var thisColumn = $(this).find("td:eq(" + columnNumber + ")");
			var dataValue = "";
			if (thisColumn.find("input[type=text]").length > 0) {
				dataValue = thisColumn.find("input[type=text]").val();
				var classes = thisColumn.find("input[type=text]").attr("class");
				if (classes.indexOf("custom[date]") >= 0 && dataValue != "") {
					dataValue = dataValue.substr(6,4) + "-" + dataValue.substr(0,2) + "-" + dataValue.substr(3, 2);
				} else if (classes.indexOf("custom[integer]") >= 0 && dataValue != "") {
					dataValue = parseInt(dataValue);
				} else if (classes.indexOf("custom[number]") >= 0 && dataValue != "") {
					dataValue = parseFloat(dataValue);
				}
			} else if (thisColumn.find("textarea").length > 0) {
				dataValue = thisColumn.find("textarea").val();
			} else if (thisColumn.find("select").length > 0) {
				dataValue = thisColumn.find("select").find("option:selected").text();
			} else if (thisColumn.find("input[type=checkbox]")) {
				return false;
			} else {
				return false;
			}
			sortObjects.push({"data_value":dataValue,"row_id":thisId})
		});
		var objectCount = 0;
		for (var i in sortObjects) {
			objectCount++;
		}
		if (objectCount > 0) {
			sortObjects.sort(function(a, b) {
				if (a.data_value == b.data_value) {
					return 0;
				} else {
					return (a.data_value < b.data_value ? -1 : 1) * sortDirection;
				}
			});
			for (var i in sortObjects) {
				$(this).closest("table").find("#" + sortObjects[i].row_id).insertBefore($(this).closest("table").find(".add-row"));
			}
		}
		$(this).closest("table").find(".editable-list-sort-image").remove();
		$(this).append("<span class='editable-list-sort-image fa fa-sort-" + (sortDirection == 1 ? "up" : "down") + "'></span>")
	});
	$(document).on("click",".editable-list-add",function() {
		if ($("#_permission").val() == "1") {
			return false;
		}
		addEditableListRow($(this).data("list_identifier"));
		return false;
	});
	$(document).on("click",".export-editable-list",function() {
		var exportContents = "";
		var rowCount = $(this).closest("table").find("tr").length - 1;
		$(this).closest("table").find("tr").each(function(index, element) {
			if (index >= rowCount) {
				return false;
			}
			var thisExport = "";
			var columnCount = $(this).find("th,td").length - 1;
			$(this).find("th,td").each(function(index, element) {
				if (index >= columnCount) {
					return false;
				}
				if ($(this).is("th")) {
					thisExport += (thisExport == "" ? "" : ",") + '"' + $(this).html().replace(/\"/g,"'") + '"';
				} else if ($(this).find("input[type=text]").length > 0) {
					thisExport += (thisExport == "" ? "" : ",") + '"' + $(this).find("input[type=text]").val().replace(/\"/g,"'") + '"';
				} else if ($(this).find("input[type=checkbox]").length > 0) {
					thisExport += (thisExport == "" ? "" : ",") + '"' + ($(this).find("input[type=checkbox]").prop("checked") ? "YES" : "no") + '"';
				} else if ($(this).find("select").length > 0) {
					thisExport += (thisExport == "" ? "" : ",") + '"' + $(this).find("select").find("option:selected").text().replace(/\"/g,"'") + '"';
				} else if ($(this).find("textarea").length > 0) {
					thisExport += (thisExport == "" ? "" : ",") + '"' + $(this).find("textarea").val().replace(/\"/g,"'") + '"';
				} else {
					thisExport += (thisExport == "" ? "" : ",") + '""';
				}
			});
			exportContents += thisExport + "<br>";
		});
		var exportWindow = window.open();
		$(exportWindow.document.body).html(exportContents);
		return false;
	});
	$(document).on("click",".editable-list-remove",function() {
		if ($("#_permission").val() == "1") {
			return false;
		}
		var listIdentifier = $(this).data("list_identifier");
		$(this).closest("tr").find(":input").each(function() {
			$(this).validationEngine("hide");
		});
		var thisPrimaryId = $(this).closest("tr").find(".editable-list-primary-id").val();
		if (thisPrimaryId != "" && thisPrimaryId != undefined) {
			var deleteIds = $("#_" + listIdentifier + "_delete_ids").val();
			if (deleteIds != "") {
				deleteIds += ",";
			}
			deleteIds += thisPrimaryId;
			$("#_" + listIdentifier + "_delete_ids").val(deleteIds);
		}
		var thisId = $(this).closest("tr").attr("id");
		setTimeout(function() {
			$("#" + thisId).remove();
			if (typeof afterEditableListRemove == "function") {
				afterEditableListRemove(listIdentifier);
			}
		},50);
		return false;
	});
});

function addEditableListRow(listName,rowData,deferAutocompleteText) {
	var isNewRow = false;
	if (rowData == null) {
		rowData = new Array();
		isNewRow = true;
	}
	if (empty(deferAutocompleteText)) {
		deferAutocompleteText = false;
	}
	var sectionId = "";
	if (listName.indexOf("-") > 0) {
		var templateName = listName.substring(0,listName.indexOf("-"));
		sectionId = listName.substring(listName.indexOf("-") + 1);
	} else {
		var templateName = listName;
	}
	var rowNumber = $("#_" + listName + "_table").data("row_number");
	const maximumRows = $("#_" + listName + "_table").data("maximum_rows");
	if (!empty(maximumRows)) {
		if ($("#_" + listName + "_table").find(".editable-list-data-row").length >= maximumRows) {
			return false;
		}
	}
	$("#_" + listName + "_table").data("row_number",rowNumber - 0 + 1);
	if ($("#_" + templateName + (sectionId == "" ? "" : "-sectionId") + "_new_row").length == 0) {
		return false;
	}
	$("#_" + templateName + (sectionId == "" ? "" : "-sectionId") + "_new_row").find(".timepicker").removeClass("timepicker").addClass("template-timepicker");
	var newRow = $("#_" + templateName + (sectionId == "" ? "" : "-sectionId") + "_new_row").html().replace(/%rowId%/g,rowNumber).replace(/-sectionId/g,"-" + sectionId);
	$("#_" + listName + "_table").find(".add-row").before(newRow);
	if ("beforeAddingDataEditableRow" in window && typeof window['beforeAddingDataEditableRow'] === 'function') {
		window['beforeAddingDataEditableRow'](listName,rowNumber,rowData);
	}
	if (isNewRow) {
		$("#_" + listName + "_row-" + rowNumber).find(":input:visible:first").focus();
	}
	$("#_" + listName + "_row-" + rowNumber).find("input,select,textarea").not(".autocomplete-field").not("input[type=hidden]").data("crc_value",getCrcValue(""));
	if ("not_editable" in rowData && rowData['not_editable']['data_value']) {
		$("#_" + listName + "_row-" + rowNumber).find("input,textarea").prop("readonly",true);
		$("#_" + listName + "_row-" + rowNumber).find("select").addClass("disabled-select");
		$("#_" + listName + "_row-" + rowNumber).find(".editable-list-remove").remove();
	}
	var noDelete = ($("#_" + listName + "_delete_ids").length == 0);
	for (var i in rowData) {
		if (typeof rowData[i] != "object" && typeof rowData[i] != "array") {
			rowData[i] = { data_value: rowData[i] };
		}
		var fieldName = listName + "_" + i + "-" + rowNumber;
		if ($("#" + fieldName).is(".editable-list-primary-id") && rowData[i]['data_value'] != "" && noDelete) {
			$("#_" + listName + "_row-" + rowNumber).find(".editable-list-remove").remove();
		}
		if ("description" in rowData[i] && $("#" + fieldName).is("select")) {
			$("#" + fieldName).append($("<option></option>").attr("value",rowData[i]['data_value']).text(rowData[i]['description']));
		}
		if ("description" in rowData[i] && $("#" + fieldName).is(".contact-picker-value")) {
			$("#" + fieldName + "_selector").append($("<option></option>").attr("value",rowData[i]['data_value']).html(rowData[i]['description'])).val(rowData[i]['data_value']).data("crc_value",rowData[i]['crc_value']);
		}
		if ("description" in rowData[i] && $("#" + fieldName).is(".user-picker-value")) {
			$("#" + fieldName + "_selector").append($("<option></option>").attr("value",rowData[i]['data_value']).html(rowData[i]['description'])).val(rowData[i]['data_value']).data("crc_value",rowData[i]['crc_value']);
		}
		if ("description" in rowData[i] && $("#" + fieldName + "_autocomplete_text").is(".autocomplete-field")) {
			$("#" + fieldName + "_autocomplete_text").val(rowData[i]['description']);
		}
		if ($("#" + fieldName).is("input[type=checkbox]")) {
			$("#" + fieldName).prop("checked",rowData[i]['data_value'] == 1);
		} else if ($("#" + fieldName).is("div") || $("#" + fieldName).is("span") || $("#" + fieldName).is("td") || $("#" + fieldName).is("tr") || $("#" + fieldName).is("p") || $("#" + fieldName).is("h2")) {
			$("#" + fieldName).html(rowData[i]['data_value']);
		} else {
			$("#" + fieldName).val(rowData[i]['data_value']);
		}
		if ("crc_value" in rowData[i]) {
			$("#" + fieldName).data("crc_value",rowData[i]['crc_value']);
		} else {
			$("#" + fieldName).removeData("crc_value");
		}
		if (!deferAutocompleteText && !("description" in rowData[i]) && $("#" + fieldName + "_autocomplete_text").is(".autocomplete-field")) {
			$("#" + fieldName + "_autocomplete_text").trigger("get_autocomplete_text");
		}
		if ("image_view" in rowData[i]) {
			$("#" + fieldName + "_view").attr("href",rowData[i]['image_view']).show();
		}
		if ("file_download" in rowData[i]) {
			if (rowData[i]['data_value'] == "") {
				$("#" + fieldName + "_download").attr("href",rowData[i]['file_download']).hide();
			} else {
				$("#" + fieldName + "_download").attr("href",rowData[i]['file_download']).show();
			}
		}
		if ($("#remove_" + fieldName).length > 0 && $("#" + fieldName + "_file").is("input[type=file]")) {
			$("#remove_" + fieldName).data("crc_value",getCrcValue("0"));
		}
		if ("filename" in rowData[i]) {
			if (rowData[i]['data_value'] == "") {
				$("#" + fieldName + "_filename").html();
			} else {
				$("#" + fieldName + "_filename").html(rowData[i]['filename']);
			}
		}
		if ($("#" + fieldName + "_file").length > 0) {
			if (("not_editable" in rowData[i] || $("#" + fieldName + "_file").hasClass("not-editable")) && rowData[i]['data_value'] != "") {
				$("#" + fieldName + "_file").hide();
			}
		}
	}
	if ("afterAddEditableRow" in window && typeof window['afterAddEditableRow'] === 'function') {
		window['afterAddEditableRow'](listName,rowNumber,rowData);
	}
	if (isNewRow) {
		$("#_" + listName + "_row-" + rowNumber).find(".contact-picker-value").trigger("change");
		$("#_" + listName + "_row-" + rowNumber).find(".user-picker-value").trigger("change");
	}
	$("#_" + listName + "_row-" + rowNumber).find(".template-datepicker").removeClass("template-datepicker").addClass("datepicker");
	$("#_" + listName + "_row-" + rowNumber).find(".datepicker").datepicker({
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
	if ($().timepicker) {
		$("#_" + listName + "_row-" + rowNumber).find(".template-timepicker").removeClass("hasTimepicker").removeClass("template-timepicker").addClass("timepicker");
		$("#_" + listName + "_row-" + rowNumber).find(".timepicker").timepicker({
			showPeriod: true,
			showLeadingZero: false
		});
	}
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
	$("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({social_tools:false,default_height: 480,default_width: 854,deeplinking: false});
	installDatePicker();
	return rowNumber;
}
