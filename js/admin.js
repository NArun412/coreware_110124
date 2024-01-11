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

var manualChangesMade = false;

function unloadChanges(){
	if (changesMade()) {
		return "Changes have not been saved. Stay on page to save changes. If you leave the page, the changes will be lost.";
	}
}

$(function() {
	window.onbeforeunload = unloadChanges;
	$(document).on("tap click",".toggle-wysiwyg",function() {
		if ( typeof CKEDITOR !== "undefined" ) {
			var fieldId = $(this).data("id");
			if ($(this).data("checked") != "true") {
				$(this).data("checked","true");
				$("#" + fieldId).addClass("wysiwyg");
				var contentsCss = $("#" + fieldId).data("contents_css");
				var stylesSet = $("#" + fieldId).data("styles_set");
				if (stylesSet == "" || stylesSet == undefined) {
					stylesSet = new Array();
				}
				CKEDITOR.replace(fieldId, {
					resize_dir: 'both',
					contentsCss: contentsCss,
					width: 800,
					stylesSet: stylesSet,
					allowedContent: true,
					coreStyles_bold: { element: 'span', attributes: { 'class': 'highlighted-text' }, styles: { 'font-weight': 'bold' }},
					coreStyles_italic: { element: 'span', attributes: { 'class': 'italic' }, styles: { 'font-style': 'bold' }}
				});
			} else {
				$(this).data("checked","false");
				$("#" + fieldId).removeClass("wysiwyg");
				CKEDITOR.instances[fieldId].destroy();
			}
		}
		return false;
	});
	$(document).on("change",".image-picker-selector",function() {
		var elementId = $(this).attr("id");
		if (empty($(this).val())) {
			$("#" + elementId + "_view").attr("href","").hide();
		} else {
			$("#" + elementId + "_view").attr("href",$(this).find("option:selected").data("url")).show();
		}
	});
	$(document).on("tap click",".image-picker-item",function() {
		var imageId = $(this).data("image_id");
		var description = $(this).find("tr").find(".image-picker-description").html();
		var columnName = $("#_image_picker_column_name").val();
		var url = $(this).find("tr").find(".image-picker-thumbnail").attr("src");
		$("#" + columnName).append($("<option></option>").attr("value",imageId).text(description).data("url",url)).val(imageId);
		$("#" + columnName + "_view").attr("href",url).show();
		$("#_image_picker_dialog").dialog("close");
		$("#" + columnName).focus();
		return false;
	});
	$(document).on("tap click","#image_picker_new_image",function() {
		$("#image_picker_filter").val("");
		$("#image_picker_new_image_description").val("");
		$("#image_picker_file_content_file").val("");
		$('#_image_picker_new_image_dialog').dialog({
			closeOnEscape: true,
			draggable: false,
			modal: true,
			resizable: false,
			position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
			width: 600,
			title: 'Add new image',
			buttons:{
				Save: function (event) {
					if ($("#_new_image").validationEngine('validate')) {
						$("body").addClass("waiting-for-ajax");
						$("#_new_image").attr("action","/addimage.php?ajax=true").attr("method","POST").attr("target","post_iframe").submit();
						$("#_post_iframe").off("load");
						$("#_post_iframe").on("load",function() {
							$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
							var returnText = $(this).contents().find("body").html();
							const returnArray = processReturn(returnText);
							if (returnArray === false) {
								return;
							}
							if ("image" in returnArray) {
								$("#_image_picker_list").prepend("<div class='image-picker-item' data-image_id='" + returnArray['image']['image_id'] +
									"'><table cellspacing='0' cellpadding='0'><tr><td>" +
									"<a class='image-picker-description' href='" + returnArray['image']['url'] + "' rel='prettyPhoto'>" + returnArray['image']['description'] +
									"</a></td><td><img id='_image_picker_choice_" + returnArray['image']['image_id'] + "' src='" + returnArray['image']['url'].replace("-full-","-small-") + "' class='image-picker-thumbnail' /></td></tr></table></div>");
								$("#_image_picker_list a[rel^='prettyPhoto']").prettyPhoto({social_tools:false,default_height: 480,default_width: 854,deeplinking: false});
								$("#_image_picker_choice_" + returnArray['image']['image_id']).trigger("click");
							}
						});
					}
					$("#_image_picker_new_image_dialog").dialog('close');
				},
				Cancel: function (event) {
					$("#_image_picker_new_image_dialog").dialog('close');
				}
			}
		});
		return false;
	});
	$(document).on("tap click","#image_picker_no_image",function() {
		var columnName = $("#_image_picker_column_name").val();
		$("#" + columnName).val("");
		$("#" + columnName + "_view").attr("href","").hide();
		$("#_image_picker_dialog").dialog("close");
	});
	$("#image_picker_filter").keydown(function(event) {
		if (event.which == 13 || event.which == 3) {
			getImagePicker();
		}
	});
	$(document).on("tap click",".image-picker",function() {
		var columnName = $(this).data("column_name");
		$("#_image_picker_column_name").val(columnName);
		if ($("#_image_picker_list .image-picker-item").length == 0) {
			getImagePicker();
		}
		$('#_image_picker_dialog').dialog({
			autoOpen: true,
			closeOnEscape: true,
			draggable: true,
			resizable: false,
			modal: true,
			position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
			width: 800,
			height: 560,
			title: 'Image Picker',
			buttons:{
				Close: function (event) {
					$("#_image_picker_dialog").dialog("close");
				}
			}
		});
		return false;
	});

	$(document).on("change",".contact-picker-selector",function() {
		var columnName = $(this).data("column_name");
		$("#" + columnName).val($(this).val());
	});
	$(document).on("tap click",".contact-picker-choice",function() {
		var contactId = $(this).data("contact_id");
		var description = $(this).closest("tr").find(".contact-picker-description").text();
		var columnName = $("#_contact_picker_column_name").val();
		if ($("#" + columnName + "_selector").find("option[value=" + contactId + "]").length == 0) {
			$("#" + columnName + "_selector").append($("<option></option>").attr("value",contactId).text(description));
		}
		$("#" + columnName + "_selector").val(contactId);
		$("#" + columnName).val(contactId);
		$("#_contact_picker_dialog").dialog("close");
		$("#" + columnName).trigger("change")
		$("#" + columnName + "_selector").focus();
		return false;
	});
	$(document).on("tap click","#contact_picker_no_contact",function() {
		var columnName = $("#_contact_picker_column_name").val();
		$("#" + columnName).val("");
		$("#" + columnName + "_selector").val("");
		$("#" + columnName + "_view").attr("href","").hide();
		$("#" + columnName).trigger("change").focus();
		$("#_contact_picker_dialog").dialog("close");
		return false;
	});
	$('#contact_picker_filter_text').on('focus', function() {
		var $this = $(this).one('mouseup.mouseupSelect', function() {
			$this.select();
			return false;
		}).one('mousedown', function() {
			$this.off('mouseup.mouseupSelect');
		}).select();
	});
	$(document).on("tap click","#contact_picker_new_contact",function() {
		$("#contact_picker_filter_text").data("last_value","");
		window.open("/contactmaintenance.php?url_page=new");
		return false;
	});
	$("#contact_picker_contact_type_id").change(function() {
		getContactPickerList();
	});
	$("#contact_picker_filter_text").keydown(function(event) {
		if ($(this).data("last_value") == undefined) {
			$(this).data("last_value","");
		}
		if ($(this).data("last_value") == $(this).val()) {
			if (event.which == 13) {
				if ($("#_contact_picker_list").find(".contact-picker-item.selected").length > 0) {
					$("#_contact_picker_list").find(".contact-picker-item.selected").find(".contact-picker-choice").trigger("click");
				}
			} else if (event.which == 40) {
				var currentIndex = $("#_contact_picker_list .contact-picker-item.selected").index();
				currentIndex++;
				if (currentIndex >= $("#_contact_picker_list .contact-picker-item").length) {
					currentIndex = 0;
				}
				$("#_contact_picker_list .contact-picker-item.selected").removeClass("selected");
				$("#_contact_picker_list .contact-picker-item:eq(" + currentIndex + ")").addClass("selected");
				var maxScroll = ($("#_contact_picker_list .contact-picker-item").length * ($("#_contact_picker_list .contact-picker-item").height() + 1)) - $("#_contact_picker_list").height();
				if ($("#_contact_picker_list .contact-picker-item.selected").position().top > $("#_contact_picker_list").height() ||
					$("#_contact_picker_list .contact-picker-item.selected").position().top < 0 ||
					($("#_contact_picker_list .contact-picker-item.selected").position().top + $("#_contact_picker_list .contact-picker-item").height()) > $("#_contact_picker_list").height()) {
					var newScroll = $("#_contact_picker_list .contact-picker-item.selected").position().top;
					if (newScroll > maxScroll) {
						newScroll = maxScroll;
					}
					$("#_contact_picker_list").scrollTop(newScroll);
				}
			} else if (event.which == 38) {
				var currentIndex = $("#_contact_picker_list .contact-picker-item.selected").index();
				currentIndex--;
				if (currentIndex < 0) {
					currentIndex = $("#_contact_picker_list .contact-picker-item").length - 1;
				}
				$("#_contact_picker_list .contact-picker-item.selected").removeClass("selected");
				$("#_contact_picker_list .contact-picker-item:eq(" + currentIndex + ")").addClass("selected");
				var maxScroll = ($("#_contact_picker_list .contact-picker-item").length * ($("#_contact_picker_list .contact-picker-item").height() + 1)) - $("#_contact_picker_list").height();
				if ($("#_contact_picker_list .contact-picker-item.selected").position().top > $("#_contact_picker_list").height() ||
					$("#_contact_picker_list .contact-picker-item.selected").position().top < 0 ||
					($("#_contact_picker_list .contact-picker-item.selected").position().top + $("#_contact_picker_list .contact-picker-item").height()) > $("#_contact_picker_list").height()) {
					var newScroll = $("#_contact_picker_list .contact-picker-item.selected").position().top;
					if (newScroll > maxScroll) {
						newScroll = maxScroll;
					}
					$("#_contact_picker_list").scrollTop(newScroll);
				}
			}
		} else {
			$(this).data("last_value",$(this).val());
			if (event.which == 13) {
				getContactPickerList();
				return false;
			}
		}
	});
	$(document).on("change",".contact-picker-value",function(event) {
		if (empty($(this).val())) {
			return;
		}
		var contactField = $(this);
		$.ajax({
			url: "/getcontactpickerlist.php?ajax=true&contact_id=" + contactField.val(),
			type: "GET",
			timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
			success: function(returnText) {
				const returnArray = processReturn(returnText);
				if (returnArray === false) {
					return;
				}
				if ("contact_info" in returnArray) {
					if ($("#" + contactField.attr("id") + "_selector").find("option[value=" + returnArray['contact_info']['contact_id'] + "]").length == 0) {
						$("#" + contactField.attr("id") + "_selector").append($("<option></option>").attr("value",returnArray['contact_info']['contact_id']).text(returnArray['contact_info']['description']));
					}
					$("#" + contactField.attr("id") + "_selector").val(returnArray['contact_info']['contact_id']);
					var oldId = $("#" + contactField.attr("id")).val();
					$("#" + contactField.attr("id")).val(returnArray['contact_info']['contact_id']);
					if (oldId != returnArray['contact_info']['contact_id']) {
						$("#" + contactField.attr("id")).trigger("change");
					}
				} else {
					contactField.val("");
					$("#" + contactField.attr("id") + "_selector").val("");
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
				displayErrorMessage("The server is not responding. Try again in a few minutes. #797");
			},
			dataType: "text"}
		);
	});
	$(document).on("tap click","#contact_picker_filter",function() {
		getContactPickerList();
		return false;
	});
	$(document).on("tap click",".contact-picker",function() {
		var columnName = $(this).data("column_name");
		$("#" + columnName).trigger("keydown");
		$("#" + columnName + "_selector").trigger("keydown");
		var filterWhere = $(this).data("filter_where");
		if (filterWhere == undefined) {
			filterWhere = "";
		}
		if ($("#" + columnName + "_selector").val() != "") {
			var pieces = $("#" + columnName + "_selector option:selected").text().split("•");
			$("#contact_picker_filter_text").val(pieces[0].trim());
		}
		$("#_contact_picker_column_name").val(columnName);
		$("#_contact_picker_filter_where").val(filterWhere);
		getContactPickerList();
		return false;
	});
	$(document).on("change",".user-picker-selector",function() {
		var columnName = $(this).data("column_name");
		$("#" + columnName).val($(this).val());
	});
	$(document).on("tap click",".user-picker-choice",function() {
		var userId = $(this).data("user_id");
		var description = $(this).closest("tr").find(".user-picker-description").text();
		var columnName = $("#_user_picker_column_name").val();
		if ($("#" + columnName + "_selector").find("option[value=" + userId + "]").length == 0) {
			$("#" + columnName + "_selector").append($("<option></option>").attr("value",userId).text(description));
		}
		$("#" + columnName + "_selector").val(userId);
		$("#" + columnName).val(userId);
		$("#_user_picker_dialog").dialog("close");
		$("#" + columnName).trigger("change")
		$("#" + columnName + "_selector").focus();
		return false;
	});
	$(document).on("tap click","#user_picker_no_user",function() {
		var columnName = $("#_user_picker_column_name").val();
		$("#" + columnName).val("");
		$("#" + columnName + "_selector").val("");
		$("#" + columnName + "_view").attr("href","").hide();
		$("#" + columnName).trigger("change").focus();
		$("#_user_picker_dialog").dialog("close");
		return false;
	});
	$('#user_picker_filter_text').on('focus', function() {
		var $this = $(this).one('mouseup.mouseupSelect', function() {
			$this.select();
			return false;
		}).one('mousedown', function() {
			$this.off('mouseup.mouseupSelect');
		}).select();
	});
	$(document).on("tap click","#user_picker_new_user",function() {
		$("#user_picker_filter_text").data("last_value","");
		window.open("/usermaintenance.php?url_page=new");
		return false;
	});
	$("#user_picker_filter_text").keydown(function(event) {
		if ($(this).data("last_value") == undefined) {
			$(this).data("last_value","");
		}
		if ($(this).data("last_value") == $(this).val()) {
			if (event.which == 13) {
				if ($("#_user_picker_list").find(".user-picker-item.selected").length > 0) {
					$("#_user_picker_list").find(".user-picker-item.selected").find(".user-picker-choice").trigger("click");
				}
			} else if (event.which == 40) {
				var currentIndex = $("#_user_picker_list .user-picker-item.selected").index();
				currentIndex++;
				if (currentIndex >= $("#_user_picker_list .user-picker-item").length) {
					currentIndex = 0;
				}
				$("#_user_picker_list .user-picker-item.selected").removeClass("selected");
				$("#_user_picker_list .user-picker-item:eq(" + currentIndex + ")").addClass("selected");
				var maxScroll = ($("#_user_picker_list .user-picker-item").length * ($("#_user_picker_list .user-picker-item").height() + 1)) - $("#_user_picker_list").height();
				if ($("#_user_picker_list .user-picker-item.selected").position().top > $("#_user_picker_list").height() ||
					$("#_user_picker_list .user-picker-item.selected").position().top < 0 ||
					($("#_user_picker_list .user-picker-item.selected").position().top + $("#_user_picker_list .user-picker-item").height()) > $("#_user_picker_list").height()) {
					var newScroll = $("#_user_picker_list .user-picker-item.selected").position().top;
					if (newScroll > maxScroll) {
						newScroll = maxScroll;
					}
					$("#_user_picker_list").scrollTop(newScroll);
				}
			} else if (event.which == 38) {
				var currentIndex = $("#_user_picker_list .user-picker-item.selected").index();
				currentIndex--;
				if (currentIndex < 0) {
					currentIndex = $("#_user_picker_list .user-picker-item").length - 1;
				}
				$("#_user_picker_list .user-picker-item.selected").removeClass("selected");
				$("#_user_picker_list .user-picker-item:eq(" + currentIndex + ")").addClass("selected");
				var maxScroll = ($("#_user_picker_list .user-picker-item").length * ($("#_user_picker_list .user-picker-item").height() + 1)) - $("#_user_picker_list").height();
				if ($("#_user_picker_list .user-picker-item.selected").position().top > $("#_user_picker_list").height() ||
					$("#_user_picker_list .user-picker-item.selected").position().top < 0 ||
					($("#_user_picker_list .user-picker-item.selected").position().top + $("#_user_picker_list .user-picker-item").height()) > $("#_user_picker_list").height()) {
					var newScroll = $("#_user_picker_list .user-picker-item.selected").position().top;
					if (newScroll > maxScroll) {
						newScroll = maxScroll;
					}
					$("#_user_picker_list").scrollTop(newScroll);
				}
			}
		} else {
			$(this).data("last_value",$(this).val());
			if (event.which == 13) {
				getUserPickerList();
				return false;
			}
		}
	});
	$(document).on("change",".user-picker-value",function(event) {
		if (empty($(this).val())) {
			return;
		}
		var userField = $(this);
		$.ajax({
			url: "/getuserpickerlist.php?ajax=true&user_id=" + userField.val(),
			type: "GET",
			timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
			success: function(returnText) {
				const returnArray = processReturn(returnText);
				if (returnArray === false) {
					return;
				}
				if ("user_info" in returnArray) {
					if ($("#" + userField.attr("id") + "_selector").find("option[value=" + returnArray['user_info']['user_id'] + "]").length == 0) {
						$("#" + userField.attr("id") + "_selector").append($("<option></option>").attr("value",returnArray['user_info']['user_id']).text(returnArray['user_info']['description']));
					}
					$("#" + userField.attr("id") + "_selector").val(returnArray['user_info']['user_id']);
					var oldId = $("#" + userField.attr("id")).val();
					$("#" + userField.attr("id")).val(returnArray['user_info']['user_id']);
					if (oldId != returnArray['user_info']['user_id']) {
						$("#" + userField.attr("id")).trigger("change");
					}
				} else {
					userField.val("");
					$("#" + userField.attr("id") + "_selector").val("");
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
				displayErrorMessage("The server is not responding. Try again in a few minutes. #797");
			},
			dataType: "text"}
		);
	});
	$(document).on("tap click","#user_picker_filter",function() {
		getUserPickerList();
		return false;
	});
	$(document).on("tap click",".user-picker",function() {
		var columnName = $(this).data("column_name");
		$("#" + columnName).trigger("keydown");
		$("#" + columnName + "_selector").trigger("keydown");
		var filterWhere = $(this).data("filter_where");
		if (filterWhere == undefined) {
			filterWhere = "";
		}
		$("#_user_picker_column_name").val(columnName);
		$("#_user_picker_filter_where").val(filterWhere);
		getUserPickerList();
		return false;
	});

	$(document).on("tap click","#_page_help_link",function() {
		$('#_page_help').dialog({
			autoOpen: true,
			closeOnEscape: true,
			draggable: true,
			resizable: true,
			modal: true,
			position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
			minWidth: 400,
			width: 800,
			minHeight: 200,
			height: 500,
			title: 'Page Help',
			buttons:{
				Close: function (event) {
					$("#_page_help").dialog("close");
				}
			}
		});
		return false;
	});
	$(document).on("tap click","a#_my_home_link",function() {
		return goToLink(null,"/");
	});
	$("#_menu_bar ul").css({display: "none"});
	$("#_menu_bar li").hover(function() {
		$(this).find('ul:first').css({visibility: "visible",display: "none"}).show(200);
	},function() {
		$(this).find('ul:first').css({visibility: "hidden"});
	});
	if ($(".deep-link-tabbed-form").length > 0) {
		$(".deep-link-tabbed-form").data("tab_event",false);
		$(".deep-link-tabbed-form").find("div:first").each(function() {
			$(this).data("element_id",$(this).attr("id")).attr("id","");
		});

		$.address.strict(false).wrap(true);

		if ($.address.value() == '') {
			$.address.history(false).value($(".deep-link-tabbed-form").find("div:first").data("element_id")).history(true);
		}

		$.address.init(function(event) {
			$(".deep-link-tabbed-form").find("div:first").each(function() {
				$(this).attr("id",$(this).data("element_id"));
			});

			$(".deep-link-tabbed-form .ui-tabs-panel li a").address(function() {
				return getHash(this);
			});

			$(".deep-link-tabbed-form").tabs({
				beforeActivate: function(event, ui) {
					$("#_edit_form").validationEngine("hideAll");
				},
				activate: function(event, ui) {
					if ("scriptFilename" in window) {
						$("body").addClass("no-waiting-for-ajax");
						$.ajax({
							url: scriptFilename + "?ajax=true&url_action=select_tab&tab_index=" + $(this).tabs("option", "active"),
							type: "GET",
							timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
							success: function(returnText) {
								const returnArray = processReturn(returnText);
								if (returnArray === false) {
									return;
								}
							},
							error: function(XMLHttpRequest, textStatus, errorThrown) {
								$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
								displayErrorMessage("The server is not responding. Try again in a few minutes. #356");
							},
							dataType: "text"}
						);
					}
				},
				fx: {
					opacity: 'toggle',
					duration: 10
				}
			});

			$(document).on("tap click",".deep-link-tabbed-form .ui-tabs-anchor",function(event) {
				$(this).closest(".deep-link-tabbed-form").data("tab_event",true);
				$.address.value(getHash(event.target));
				$(this).closest(".deep-link-tabbed-form").data("tab_event",false);
				return false;
			});

		}).change(function(event) {

			var current = $('a[href=#' + event.value + ']:first');

			if (!current.closest(".deep-link-tabbed-form").data("tab_event")) {
				var index = $('.deep-link-tabbed-form a[href="' + current.attr("href") + '"]').parent().index();
				$(".deep-link-tabbed-form").tabs("option", "active", index);
			}

		});
	}
	$(document).on("keydown",".autocomplete-field",function(event) {
		if (event.which == 33 || event.which == 34) {
			return true;
		}
		if ($(this).prop("readonly") && event.which != 9 && (event.which < 16 || event.which > 18) && event.which != 91) {
			return false;
		}
		if (event.which != 9 && (event.which < 37 || event.which > 40) && (event.which < 16 || event.which > 18) && event.which != 91) {
			$("#" + $(this).attr("id").replace("_autocomplete_text","")).val("");
		}
		if (event.which == 40) {
			var currentIndex = $("#autocomplete_options ul li.selected").index();
			currentIndex++;
			if (currentIndex >= $("#autocomplete_options ul li").length) {
				currentIndex = 0;
			}
			$("#autocomplete_options ul li.selected").removeClass("selected");
			$("#autocomplete_options ul li:eq(" + currentIndex + ")").addClass("selected");
			showAutocompleteSelection();
			return false;
		} else if (event.which == 38) {
			var currentIndex = $("#autocomplete_options ul li.selected").index();
			currentIndex--;
			if (currentIndex < 0) {
				currentIndex = $("#autocomplete_options ul li").length - 1;
			}
			$("#autocomplete_options ul li.selected").removeClass("selected");
			$("#autocomplete_options ul li:eq(" + currentIndex + ")").addClass("selected");
			showAutocompleteSelection();
			return false;
		} else if (event.which == 13 || event.which == 3 || event.which == 9) {
			if ($("#autocomplete_options ul li.selected").length > 0) {
				var fieldName = $("#autocomplete_options").data("field_name");
				$("#" + fieldName).val($("#autocomplete_options ul li.selected").text());
				$("#" + fieldName.replace("_autocomplete_text","")).val($("#autocomplete_options ul li.selected").data("id_value"));
				$("#" + fieldName).data("last_autocomplete_data",$("#" + fieldName).val());
				$("#autocomplete_options").hide();
			}
			if (event.which == 9) {
				return true;
			} else {
				return false;
			}
		}
		return true;
	}).on("blur",".autocomplete-field",function() {
		if ($(this).prop("readonly")) {
			return false;
		}
		var fieldName = $(this).attr("id");
		setTimeout(function() {
			$("#autocomplete_options").hide();
			if ($("#" + fieldName.replace("_autocomplete_text","")).val() == "") {
				$("#" + fieldName.replace("_autocomplete_text","")).val($("#autocomplete_options ul li").filter(function() {
					if ($(this).text().toLowerCase() == $("#" + fieldName).val().toLowerCase()) {
						$("#" + fieldName).val($(this).text());
						return true;
					} else {
						return false;
					}
				}).data("id_value"));
			}
			if ($("#" + fieldName.replace("_autocomplete_text","")).val() == "") {
				$("#" + fieldName).data("last_autocomplete_data","");
				$("#" + fieldName).val("");
			}
		},200);
	}).on("focus",".autocomplete-field",function() {
		if ($(this).prop("readonly")) {
			return false;
		}
		if ($("#autocomplete_options").data("field_name") != $(this).attr("id")) {
			$(this).data("last_autocomplete_data","");
		}
		$("#autocomplete_options").data("field_name",$(this).attr("id"));
	}).on("keyup",".autocomplete-field",function(event) {
		if (event.which == 33 || event.which == 34) {
			return true;
		}
		if ($(this).prop("readonly") && event.which != 9 && (event.which < 16 || event.which > 18) && event.which != 91) {
			return false;
		}
		if (event.which != 9 && (event.which < 37 || event.which > 40) && (event.which < 16 || event.which > 18) && event.which != 91 && event.which != 13 && event.which != 3) {
			var thisValue = $(this).val();
			if (getAutocompleteTimer != null) {
				clearTimeout(getAutocompleteTimer);
			}
			var thisFieldName = $(this).attr("id");
			if (thisValue.length >= 2) {
				getAutocompleteTimer = setTimeout(function() {
					getAutocompleteData(thisFieldName);
				},400);
			} else {
				$("#autocomplete_options ul li.selected").removeClass("selected");
				$("#autocomplete_options").hide();
			}
		}
	}).on("get_autocomplete_text",".autocomplete-field",function() {
		var thisFieldName = $(this).attr("id");
		var thisValueId = $("#" + thisFieldName.replace("_autocomplete_text","")).val();
		var additionalFilter = $("#" + thisFieldName).data("additional_filter");
		var additionalFilterFunction = $("#" + thisFieldName).data("additional_filter_function");
		if (additionalFilterFunction != undefined && typeof window[additionalFilterFunction] == "function") {
			additionalFilter = window[additionalFilterFunction](thisFieldName.replace("_autocomplete_text",""));
		}
		if (additionalFilter == undefined) {
			additionalFilter = "";
		}
		if (thisValueId == "") {
			$("#" + thisFieldName).val("");
		} else {
			$("body").addClass("no-waiting-for-ajax");
			$.ajax({
				url: "getautocompletedata.php?ajax=true&tag=" + $("#" + thisFieldName).data("autocomplete_tag") + "&value_id=" + encodeURIComponent(thisValueId) + "&get_value=true" + "&additional_filter=" + encodeURIComponent(additionalFilter),
				type: "GET",
				timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
				success: function(returnText) {
					const returnArray = processReturn(returnText);
					if (returnArray === false) {
						return;
					}
					if ("autocomplete_text" in returnArray) {
						$("#" + thisFieldName).val(returnArray['autocomplete_text']);
						$("#" + thisFieldName).data("last_autocomplete_data",$("#" + thisFieldName).val());
					}
				},
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
				},
				dataType: "text"}
			);
		}
	});
	$(document).on("click","#autocomplete_options ul li",function() {
		var fieldName = $("#autocomplete_options").data("field_name");
		$("#" + fieldName).val($(this).text());
		$("#" + fieldName).data("last_autocomplete_data",$("#" + fieldName).val());
		$("#" + fieldName.replace("_autocomplete_text","")).val($(this).data("id_value"));
		$("#" + fieldName).focus();
	});
	window.onerror=function(message, url, lineNo) {
		if (lineNo > 0) {
			$.ajax({
				url: "logjavascripterror.php?ajax=true&message=" + escape(message) + "&url=" + escape(url) + "&line_no=" + escape(lineNo),
				type: "GET",
				timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
				dataType: "text"}
			);
		}
	}
	if (!$("#_header_wrapper").is(".no-at-top")) {
		$(window).scroll(function() {
			if ($("#_menu").length > 0 && !$('#_menu').is(':hover')) {
				if ($(document).scrollTop() == 0) {
					if (!$("#_header_wrapper").is(".at-top")) {
						$("#_header_wrapper").addClass("at-top");
						$("#_inner").addClass("at-top");
					}
				} else {
					if ($("#_header_wrapper").is(".at-top")) {
						$("#_header_wrapper").removeClass("at-top");
						$("#_inner").removeClass("at-top");
					}
				}
			}
		}).trigger("scroll");
		$('#_header_wrapper').mouseleave(function() {
			if ($("#header_wrapper").is(".at-top")) {
				$("#_header_wrapper").removeClass("at-top");
				$("#_inner").removeClass("at-top");
			}
		}).mouseenter(function() {
			$(window).trigger("scroll");
		});
	}
	$(document).on("tap click","div#_banner_left img,div#_banner_left h1",function() {
		if (logoLink != "" && logoLink != undefined) {
			goToLink(null,logoLink);
		}
		return false;
	});
	$(document).on("tap click","a#_logout",function() {
		return goToLink(null,"logout.php");
	});
	$(document).on("tap click","a#_login",function() {
		return goToLink(null,"loginform.php");
	});
	if (jQuery.ui) {
		if ($("button:not(.no-ui)").length > 0) {
			$("button:not(.no-ui)").button();
		}
	}
	if (jQuery.ui && $(".disabled-button").length > 0) {
		$(".disabled-button").button("disable");
	}

// For a tabbed form, save the last tab used

	if ($(".tabbed-form").length > 0 && $().tabs) {
		$(".tabbed-form").tabs({
			beforeActivate: function(event, ui) {
				$("#_edit_form").validationEngine("hideAll");
			},
			activate: function(event, ui) {
				if ("scriptFilename" in window) {
					$("body").addClass("no-waiting-for-ajax");
					$.ajax({
						url: scriptFilename + "?ajax=true&url_action=select_tab&tab_index=" + $(this).tabs("option", "active"),
						type: "GET",
						timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
						success: function(returnText) {
							const returnArray = processReturn(returnText);
							if (returnArray === false) {
								return;
							}
						},
						error: function(XMLHttpRequest, textStatus, errorThrown) {
							$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
							displayErrorMessage("The server is not responding. Try again in a few minutes. #9874");
						},
						dataType: "text"}
					);
				}
			}
		});
	}
	$("#_wrapper").show();
});

var getAutocompleteTimer = null;

function getAutocompleteData(fieldName) {
	if ($("#" + fieldName).val() == $("#" . fieldName).data("last_autocomplete_data")) {
		$("#autocomplete_options").width($("#" + fieldName).outerWidth() - 2).css("left",$("#" + fieldName).offset().left).css("top",$("#" + fieldName).offset().top + $("#" + fieldName).outerHeight()).show();
		return;
	}
	$("#autocomplete_options ul li").remove();
	$("body").addClass("no-waiting-for-ajax");
	var searchValue = $("#" + fieldName).val();
	var additionalFilter = $("#" + fieldName).data("additional_filter");
	var additionalFilterFunction = $("#" + fieldName).data("additional_filter_function");
	if (additionalFilterFunction != undefined && typeof window[additionalFilterFunction] == "function") {
		additionalFilter = window[additionalFilterFunction](fieldName.replace("_autocomplete_text",""));
	}
	if (additionalFilter == undefined) {
		additionalFilter = "";
	}
	$.ajax({
		url: "getautocompletedata.php?ajax=true&tag=" + $("#" + fieldName).data("autocomplete_tag") + "&search_text=" + encodeURIComponent(searchValue) + "&additional_filter=" + encodeURIComponent(additionalFilter),
		type: "GET",
		timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
		success: function(returnText) {
			const returnArray = processReturn(returnText);
			if (returnArray === false) {
				return;
			}
			if ("results" in returnArray) {
				var added = false;
				for (var i in returnArray['results']) {
					if (returnArray['results'][i] instanceof Object) {
						var keyValue = returnArray['results'][i]['key_value'];
						var description = returnArray['results'][i]['description'];
					} else {
						var keyValue = i;
						var description = returnArray['results'][i];
					}
					added = true;
					$("#autocomplete_options ul").append($("<li></li>").data("id_value",keyValue).html(description.replace(new RegExp(searchValue, 'ig'), "<b>$&</b>")));
				}
				$("#autocomplete_options p").remove();
				if (!added) {
					$("#autocomplete_options").prepend("<p>No matching results</p>");
				}
				$("#autocomplete_options").width($("#" + fieldName).outerWidth() - 2).css("left",$("#" + fieldName).offset().left).css("top",$("#" + fieldName).offset().top + $("#" + fieldName).outerHeight()).show();
				$("#" + fieldName).data("last_autocomplete_data",$("#" + fieldName).val());
				$("#autocomplete_options ul li:eq(0)").addClass("selected");
				showAutocompleteSelection();
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
		},
		dataType: "text"}
	);
}

function showAutocompleteSelection() {
	if ($("#autocomplete_options ul li.selected").length == 0) {
		return;
	}
	var topPosition = $("#autocomplete_options ul").position().top;
	var height = $("#autocomplete_options").height();
	var selectedTop = $("#autocomplete_options ul li.selected").position().top;
	var lineHeight = $("#autocomplete_options ul li").outerHeight();
	if ((selectedTop + topPosition) < 0 || selectedTop > (height - lineHeight - topPosition)) {
		var scrollTop = (selectedTop - (height / 2) + (lineHeight / 2));
		$("#autocomplete_options").animate({scrollTop: scrollTop},100);
	}
}

function getImagePicker() {
	$.ajax({
		url: "/getimagepickerlist.php?ajax=true&search_text=" + encodeURIComponent($("#image_picker_filter").val()),
		type: "GET",
		timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
		success: function(returnText) {
			const returnArray = processReturn(returnText);
			if (returnArray === false) {
				return;
			}
			$("#_image_picker_list").html("");
			if ("images" in returnArray) {
				$.each(returnArray['images'], function(index, imageArray) {
					$("#_image_picker_list").append("<div class='image-picker-item' data-image_id='" + imageArray['image_id'] +
								"'><table cellspacing='0' cellpadding='0'><tr><td>" +
						"<a class='image-picker-description' href='" + imageArray['url'] + "' rel='prettyPhoto'>" + imageArray['description'] +
						"</a></td><td><img src='" + imageArray['url'].replace("-full-","-small-") +
						"' class='image-picker-thumbnail' /></td></tr></table></div>");
				});
				$("#_image_picker_list a[rel^='prettyPhoto']").prettyPhoto({social_tools:false,default_height: 480,default_width: 854,deeplinking: false});
				$("#_image_picker_list button").button();
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
			displayErrorMessage("The server is not responding. Try again in a few minutes. #489");
		},
		dataType: "text"}
	);
}

function getHash(element) {
	return $(element).attr('href').replace(/^#/, '')
}

function getContactPickerList() {
	$("#_contact_picker_list").find("tr.selected").removeClass("selected");
	$.ajax({
		url: "/getcontactpickerlist.php?ajax=true",
		type: "POST",
		data: $("#_contact_picker_filter_form").serialize(),
		timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
		success: function(returnText) {
			const returnArray = processReturn(returnText);
			if (returnArray === false) {
				return;
			}
			$("#_contact_picker_list").html("");
			if ("contacts" in returnArray) {
				$.each(returnArray['contacts'], function(index, contactArray) {
					$("#_contact_picker_list").append("<div class='contact-picker-item'><table><tr><td class='contact-picker-description'>" +
						"<a target='_blank' href='/contactmaintenance.php?url_page=show&clear_filter=true&primary_id=" + contactArray['contact_id'] + "'>" +
						contactArray['description'] + "</a></td><td>" +
						"<button accesskey='c' class='contact-picker-choice' data-contact_id='" + contactArray['contact_id'] +
						"'>Choose</button></td></tr></table></div>");
				});
				$("#_contact_picker_list table").attr("cellspacing","0px").attr("cellpadding","0px");
				$("#_contact_picker_list button").button();
				$('#_contact_picker_dialog').dialog({
					autoOpen: true,
					closeOnEscape: true,
					draggable: true,
					resizable: false,
					modal: true,
					position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
					width: 850,
					height: 550,
					title: 'Choose a Contact',
					buttons:{
						Close: function (event) {
							$("#_contact_picker_dialog").dialog("close");
						}
					}
				});
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
			displayErrorMessage("The server is not responding. Try again in a few minutes. #365");
		},
		dataType: "text"}
	);
}

function getUserPickerList() {
	$("#_user_picker_list").find("tr.selected").removeClass("selected");
	$.ajax({
		url: "/getuserpickerlist.php?ajax=true",
		type: "POST",
		data: $("#_user_picker_filter_form").serialize(),
		timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
		success: function(returnText) {
			const returnArray = processReturn(returnText);
			if (returnArray === false) {
				return;
			}
			$("#_user_picker_list").html("");
			if ("users" in returnArray) {
				$.each(returnArray['users'], function(index, userArray) {
					$("#_user_picker_list").append("<div class='user-picker-item'><table><tr><td class='user-picker-description'>" +
						"<a target='_blank' href='/usermaintenance.php?url_page=show&clear_filter=true&primary_id=" + userArray['user_id'] + "'>" +
						userArray['description'] + "</a></td><td>" +
						"<button accesskey='c' class='user-picker-choice' data-user_id='" + userArray['user_id'] +
						"'>Choose</button></td></tr></table></div>");
				});
				$("#_user_picker_list table").attr("cellspacing","0px").attr("cellpadding","0px");
				$("#_user_picker_list button").button();
				$('#_user_picker_dialog').dialog({
					autoOpen: true,
					closeOnEscape: true,
					draggable: true,
					resizable: false,
					modal: true,
					position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
					width: 850,
					height: 550,
					title: 'Choose a user',
					buttons:{
						Close: function (event) {
							$("#_user_picker_dialog").dialog("close");
						}
					}
				});
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
			displayErrorMessage("The server is not responding. Try again in a few minutes. #683");
		},
		dataType: "text"}
	);
}

function disableButtons(buttonElement) {
	if (buttonElement == undefined) {
		$("#_page_header button").button("disable");
	} else {
		$(buttonElement).button("disable");
	}
}

function enableButtons(buttonElement) {
	if (buttonElement == undefined) {
		$("#_page_header").find(".enabled-button").button("enable");
	} else {
		$(buttonElement).button("enable");
	}
}

function changesMade() {
	if ($("body").data("just_saved") == "true") {
		return false;
	}
	var returnValue = false;
	if ( typeof CKEDITOR !== "undefined" ) {
		for (instance in CKEDITOR.instances) {
			CKEDITOR.instances[instance].updateElement();
		}
	}
	$(".editable-list").each(function() {
		if ($(this).hasClass("no-add")) {
			return true;
		}
		$(this).find(".editable-list-data-row").each(function() {
			var allEmpty = true;
			$(this).find("input,select,textarea").each(function() {
				if (!empty($(this).val())) {
					allEmpty = false;
					return true;
				}
			});
			if (allEmpty) {
				$(this).remove();
			}
		});
	});
	if ($("#_permission").val() == "1") {
		return false;
	}
	if ("manualChangesMade" in window && manualChangesMade) {
		return true;
	}
	$("input").add("textarea").add("select").each(function() {
		if ($(this).hasClass("ignore-changes")) {
			return true;
		}
		if (($(this).data("check_crc_anyway") == undefined || $(this).data("check_crc_anyway") == "") && ($(this).prop("readonly") || $(this).prop("disabled"))) {
			return true;
		} else {
			if ($(this).data("field-changed")) {
				returnValue = true;
				return false;
			}
			var crcValue = $(this).data("crc_value");
			if ((typeof crcValue != 'undefined') && !(crcValue === null) && crcValue.length > 0) {
				if ($(this).is("input[type=radio]")) {
					var fieldName = $(this).attr("name");
					var currentValue = $("input[type=radio][name='" + fieldName + "']:checked").val();
				} else {
					var currentValue = ($(this).attr("type") == "checkbox" ? ($(this).prop("checked") ? "1" : "0") : $(this).val());
				}
				var currentCrcValue = getCrcValue(currentValue);
				if (currentCrcValue != crcValue) {
					if ($("#superuser_logged_in").length > 0) {
						console.log($(this).attr("id") + ":" + crcValue + ":" + currentCrcValue + ":" + currentValue);
					}
					return false;
				}
			}
		}
	});
	return returnValue;
}

function askAboutChanges(afterFunction) {
	if (typeof dontAskAboutChanges == "function") {
		if (dontAskAboutChanges()) {
			afterFunction();
			return;
		}
	}
	$('#_save_changes_dialog').dialog({
		autoOpen: false,
		closeOnEscape: true,
		draggable: false,
		modal: true,
		resizable: false,
		position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
		title: 'Save Changes?',
		buttons:{
			Yes: function (event) {
				if (!("saveChanges" in window)) {
					displayErrorMessage("Save function does not exist");
					$("#_save_changes_dialog").dialog('close');
					return;
				}
				$("#_save_changes_dialog").dialog('close');
				saveChanges(function() {
					afterFunction();
				},function() {
				});
			},
			No: function (event) {
				if (!("ignoreChanges" in window)) {
					displayErrorMessage("Ignore function does not exist");
					$("#_save_changes_dialog").dialog('close');
					return;
				}
				ignoreChanges(function() {
					$("#_save_changes_dialog").dialog('close');
					afterFunction();
				});
			},
			Cancel: function (event) {
				$("#_save_changes_dialog").dialog('close');
			}
		}
	});
	if ($("#_save_changes_dialog").data("keypress_added") != "true") {
		$('#_save_changes_dialog').closest(".ui-dialog").keypress(function (event) {
			if ($('#_save_changes_dialog').dialog('isOpen')) {
				switch (event.which) {
			// "Y" for yes
					case 121:
					case 89:
						if (!("saveChanges" in window)) {
							displayErrorMessage("Save function does not exist");
							$("#_save_changes_dialog").dialog('close');
							break;
						}
						$('#_save_changes_dialog').dialog('close');
						saveChanges(function() {
							afterFunction();
						});
						break;
			// "N" for no
					case 110:
					case 78:
						if (!("ignoreChanges" in window)) {
							displayErrorMessage("Ignore function does not exist");
							$("#_save_changes_dialog").dialog('close');
							break;
						}
						ignoreChanges(function() {
							$('#_save_changes_dialog').dialog('close');
							afterFunction();
						});
						break;
				}
			}
		})
		$("#_save_changes_dialog").data("keypress_added","true");
	}
	$("#_save_changes_dialog").dialog("open");
}

function ignoreChanges(afterFunction) {
	afterFunction();
}
