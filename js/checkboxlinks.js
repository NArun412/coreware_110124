$(function() {
	$(document).on("click",".checkbox-link-choice",function(event) {
		var controlId = $(this).closest(".checkbox-links-wrapper").attr("id");
		var newList = "";
		$("#" + controlId).find(".checkbox-link-choice").each(function() {
			if ($(this).prop("checked")) {
				if (newList != "") {
					newList += ",";
				}
				newList += $(this).val();
			}
		});
		$("#" + controlId + " .checkbox-link-choice-list").val(newList);
		$("#" + controlId + " .checkbox-link-choice-list").trigger("change");
	});
	$(document).on("change",".checkbox-link-choice-list",function() {
		var thisId = $(this).attr("id");
		$("#_" + thisId + "_checkbox_links_wrapper").find(".checkbox-link-choice").prop("checked",false);
		var thisValue = $(this).val();
		var valuesArray = thisValue.split(",");
		$("#_delete_" + thisId).val("");
		for (var i in valuesArray) {
			$("#_" + thisId + "_choice_" + valuesArray[i]).prop("checked",true);
		}
		var unusedIds = "";
		$("#_" + thisId + "_checkbox_links_wrapper").find(".checkbox-link-choice").each(function() {
			if (!$(this).prop("checked")) {
				unusedIds += (empty(unusedIds) ? "" : ",") + $(this).val();
			}
		});
		$("#_delete_" + thisId).val(unusedIds);
	});
});
