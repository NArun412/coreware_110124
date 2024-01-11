$(function() {
	$(document).on("keydown",".multiple-dropdown-filter",function(event) {
		if (event.which == 8) {
			if (empty($(this).val().toLowerCase())) {
				const $lastOption = $(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-selected-value").last();
				const dataValue = $lastOption.data("value_id");
				$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").find("li[data-value_id='" + dataValue + "']").removeClass("multiple-dropdown-disabled");
				var thisValue = $(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").val();
				var valuesArray = thisValue.split(",");
				var newValues = "";
				$.each(valuesArray, function (index, selectedValue) {
					if (selectedValue != dataValue) {
						newValues += (newValues == "" ? "" : ",") + selectedValue;
					}
				});
				$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-values").val(newValues);
				$lastOption.remove();
			}
		}
		if (event.which == 9) {
			$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").hide();
			$(this).closest(".multiple-dropdown-container").find(".focused").removeClass("focused");
			return true;
		}
		if (event.which == 40) {
			if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").length == 0) {
				$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option").not(".multiple-dropdown-disabled").not(".hidden").first().addClass("focused");
			} else {
				var nextOne = $(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").nextAll(".multiple-dropdown-options li.multiple-dropdown-option").not(".multiple-dropdown-disabled").not(".hidden").first();
				if (nextOne.length > 0) {
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").removeClass("focused");
					nextOne.addClass("focused");
				}
			}
			if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").length > 0) {
				if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").position().top > ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").outerHeight() - 30) ||
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").position().top < 0) {
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").scrollTop($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").scrollTop() + $(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").position().top);
				}
			}
			return false;
		} else if (event.which == 38) {
			if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").length > 0) {
				var nextOne = $(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").prevAll(".multiple-dropdown-options li.multiple-dropdown-option").not(".multiple-dropdown-disabled").not(".hidden").first();
				if (nextOne.length > 0) {
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").removeClass("focused");
					nextOne.addClass("focused");
				}
			}
			if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").length > 0) {
				if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").position().top > ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").outerHeight() - 30) ||
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").position().top < 0) {
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").scrollTop($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").scrollTop() + $(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").position().top);
				}
			}
			return false;
		} else if (event.which == 13 || event.which == 3) {
			if ($(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").not(".hidden").length > 0) {
				var addItem = $(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option.focused").first();
				if (addItem.length > 0) {
					addItem.addClass("multiple-dropdown-disabled");
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-option.focused").removeClass("focused");
					$(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").before("<div class='multiple-dropdown-selected-value' data-value_id='" + addItem.data("value_id") + "'>" + addItem.text() + "</div>");
					var dataValue = addItem.data("value_id");
					var valueIds = $(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").val();
					valueIds += (valueIds == "" ? "" : ",") + dataValue;
					$(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").val(valueIds);
					$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-filter").val("");
				}
			}
		}
		return true;
	});
	$(document).on("keyup",".multiple-dropdown-filter",function(event) {
		var filterValue = $(this).val().toLowerCase();
		$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option").each(function() {
			if ($(this).html().toLowerCase().indexOf(filterValue) >= 0) {
				$(this).removeClass("hidden");
			} else {
				$(this).addClass("hidden");
			}
		});
		if (event.which >= 65 || event.which == 32) {
			$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option").removeClass("focused");
			$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options li.multiple-dropdown-option").not(".multiple-dropdown-disabled").not(".hidden").first().addClass("focused");
		}
	});
	$(document).on("click",".multiple-dropdown-option",function() {
		if ($(this).is(".multiple-dropdown-disabled")) {
			return false;
		}
		console.log("clicked");
		$(this).addClass("multiple-dropdown-disabled");
		$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-option.focused").removeClass("focused");
		$(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").before("<div class='multiple-dropdown-selected-value' data-value_id='" + $(this).data("value_id") + "'>" + $(this).text() + "</div>");
		var dataValue = $(this).data("value_id");
		var valueIds = $(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").val();
		valueIds += (valueIds == "" ? "" : ",") + dataValue;
		$(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").val(valueIds);
		$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-filter").val("");
	});
	$(document).on("click",".multiple-dropdown-selected-value",function(event) {
		var posX = event.pageX - $(this).offset().left;
		var thisWidth = $(this).outerWidth();
		if (posX > (thisWidth - 25)) {
			var dataValue = $(this).data("value_id");
			$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").find("li[data-value_id='" + dataValue + "']").removeClass("multiple-dropdown-disabled");
			var thisValue = $(this).closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").val();
			var valuesArray = thisValue.split(",");
			var newValues = "";
			$.each(valuesArray, function(index, selectedValue) {
				if (selectedValue != dataValue) {
					newValues += (newValues == "" ? "" : ",") + selectedValue;
				}
			});
			$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-values").val(newValues);
			$(this).remove();
		}
	});
	$(document).on("focus",".multiple-dropdown-filter",function() {
		$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").show();
	});
	$(document).on("click",".multiple-dropdown-container",function(event) {
		$(".multiple-dropdown-container").find(".focused").removeClass("focused");
		$(".multiple-dropdown-options").hide();
		$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-options").show();
		$(this).closest(".multiple-dropdown-container").find(".multiple-dropdown-filter").focus();
		event.stopPropagation();
	});
	$(document).click(function() {
		$(".multiple-dropdown-container").find(".focused").removeClass("focused");
		$(".multiple-dropdown-options").hide();
	});
	$(document).on("change",".multiple-dropdown-values",function() {
		var thisId = $(this).attr("id");
		$("#_" + thisId + "_selector").find(".multiple-dropdown-selected-value").remove();
		$("#_" + thisId + "_selector").find(".multiple-dropdown-disabled").removeClass("multiple-dropdown-disabled");
		$("#_" + thisId + "_selector").find(".focused").removeClass("focused");
		var thisValue = $(this).val();
		var valuesArray = thisValue.split(",");
		$.each(valuesArray, function(index, selectedValue) {
			var addItem = $("#_" + thisId + "_selector").closest(".multiple-dropdown-container").find("li[data-value_id='" + selectedValue + "']");
			if (addItem.length > 0) {
				addItem.addClass("multiple-dropdown-disabled");
				$("#_" + thisId + "_selector").closest(".multiple-dropdown-container").find("input.multiple-dropdown-values").before("<div class='multiple-dropdown-selected-value' data-value_id='" + addItem.data("value_id") + "'>" + addItem.text() + "</div>");
			}
		});
	});
});
