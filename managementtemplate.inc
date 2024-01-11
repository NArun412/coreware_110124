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

function getManagementTemplate() {
	$templateContents = array();
	ob_start();
?>
var minSidebarWidth = 200;
var maxSidebarWidth = 400;
var maxSidebarPercent = 20;
var ulClicked = false;
var sidebarWidth = 300;
var shiftPressed = false;

function resizeSidebar() {
	sidebarWidth = readCookie("sidebar_width");
	var useMaximumWidth = readCookie("sidebar_maximum_width");
	if (empty(sidebarWidth)) {
	    useMaximumWidth = "1";
	}
	sidebarWidth = (sidebarWidth - 0);
	if (isNaN(sidebarWidth) || parseInt(Number(sidebarWidth)) != sidebarWidth || isNaN(parseInt(sidebarWidth, 10)) || sidebarWidth < minSidebarWidth || sidebarWidth > maxSidebarWidth) {
		sidebarWidth = 300;
	}
	if (useMaximumWidth == "1") {
		var pageWidth = $(window).width();
		sidebarWidth = Math.min(maxSidebarWidth,RoundFixed(pageWidth * (maxSidebarPercent / 100),0));
	}
    $('#_management_sidebar').css("flex-basis", sidebarWidth);
}

$(window).load(function() {
	resizeSidebar();
});
$(function() {
    setTimeout(function() {
        checkUserNotifications();
    },60000);
    $(document).on("click","#_wrapper",function() {
        $("#_list_actions").removeClass("dropdown");
    });
    $(document).on("click","#_list_actions",function(event) {
        $(this).toggleClass("dropdown");
        event.stopPropagation();
    });
    $(document).on("click","#_user_notifications_wrapper",function() {
        goToLink($(this),"/user-notifications",event.metaKey || event.ctrlKey);
    });

    $("#_page_chooser_filter").keyup(function(event) {
        var $pageChoices = $("#_page_choices").find("div.page-choice");
        if (event.which == 13 || event.which == 3) {
            var e = jQuery.Event("click");
            e.ctrlKey = event.ctrlKey;
            e.shiftKey = event.shiftKey;
            e.metaKey = event.metaKey;
            e.altKey = event.altKey;
            $("div.page-choice.selected").first().trigger(e);
            $("#_page_chooser_dialog").dialog('close');
            return;
        }
        if (event.which != 40 && event.which != 38) {
            var textValue = $(this).val().toLowerCase();
            if (textValue == "") {
                $pageChoices.removeClass("hidden");
            } else {
                $pageChoices.each(function() {
                    var description = $(this).html().toLowerCase();
                    if (description.indexOf(textValue) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            }
            $pageChoices.removeClass("selected");
            $pageChoices.not(".hidden").first().addClass("selected");
        } else {
            var $selected = $("#_page_choices").find("div.page-choice.selected");
            if (!$selected.length) {
                $pageChoices.not(".hidden").first().addClass("selected");
                return;
            }
            var $current = null;
            $pageChoices.removeClass("selected");
            if ($pageChoices.not(".hidden").length == 0) {
                return;
            }
            while (empty($current) || $current.hasClass("hidden")) {
                $pageChoices.removeClass("selected");
                if (event.which == 40) {
                    if ($selected.is(':last-child')) {
                        $current = $pageChoices.not(".hidden").first();
                    } else {
                        $current = (empty($current) ? $selected.next() : $current.next());
                    }
                } else if (event.which == 38) {
                    if ($selected.is(':first-child')) {
                        $current = $pageChoices.not(".hidden").last();
                    } else {
                        $current = (empty($current) ? $selected.prev() : $current.prev());
                    }
                }
                $selected = $current;
            }
            $current.addClass("selected");
            $("#_page_choices").scrollTop($("#_page_choices").scrollTop() + $current.position().top - $("#_page_choices").height()/2 + $current.height()/2);
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    })
    $(window).ready(function() {
        if ($("#text_filter").length > 0) {
            $("#text_filter").focus();
        }
    });

    $(document).keyup(function(event) {
        if (event.which == 16) {
            if (shiftPressed) {
                openPageChooser();
            } else {
                shiftPressed = true;
                setTimeout(function() {
                    shiftPressed = false;
                },300);
            }
        } else {
            shiftPressed = false;
        }
    });
	$(document).on("click","#_user_image_wrapper,#_user_name_link",function (event) {
		document.location = "/userselfmaintenance.php";
	});
	$(document).on("mousedown","#_sidebar_divider",function (event) {
		var pageWidth = $(window).width();
		event.preventDefault();
        $(document).on("mousemove.managementTemplate",function (event) {
            $("#_sidebar_divider").addClass("dragging");
            event.preventDefault();
            var x = event.pageX - $('#_management_sidebar').offset().left;
            var maximumWidth = Math.min(maxSidebarWidth,RoundFixed(pageWidth * (maxSidebarPercent / 100),0));
            if (x > minSidebarWidth && x < maximumWidth) {
                if (x > (maximumWidth - 30)) {
                    x = maximumWidth;
                }
                $('#_management_sidebar').css("flex-basis", x);
                sidebarWidth = x;
                if (x == maximumWidth) {
                    createCookie("sidebar_maximum_width","1",3650);
                } else {
                    createCookie("sidebar_maximum_width","0",3650);
                }
                createCookie("sidebar_width",sidebarWidth,3650);
            }
        });
	});
	$(window).resize(function() {
		resizeSidebar();
	});
	$(document).mouseup(function (event) {
		$(document).off("mousemove.managementTemplate");
	});

	$("#_logo").click(function() {
		if (!empty(logoLink)) {
			goToLink(logoLink);
		} else {
		    goToLink("/");
		}
	});
	$('html').flowtype({
		minimum: 400,
		maximum: 1600,
		minFont: 12,
		fontRatio: 90
	});

	$(document).on("click","#_mobile_menu_icon",function(event) {
		openPageChooser();
		event.stopPropagation();
	});
	$(window).click(function() {
		$("#_menu_contents ul ul ul").removeClass("visible");
	});
    $(document).on("click", "#_full_screen_icon", function() {
        $("#_wrapper").toggleClass("full-screen");
        createCookie("full_screen",$("#_wrapper").hasClass("full-screen"),365);
        return false;
    });
    if (readCookie("full_screen") == "true") {
        $("#_wrapper").addClass("full-screen");
    }
});

function checkUserNotifications() {
	if (!adminIsLoggedIn) {
		return;
	}
	return;
    if (!$("body").hasClass("waiting-for-ajax")) {
        $("body").addClass("no-waiting-for-ajax");
        loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_user_notification_count",function(returnArray){
            if ("user_notification_count" in returnArray) {
                $("#_user_notifications_count").attr("class",returnArray['user_notification_class']).html(returnArray['user_notification_count']);
            }
        });
    }
    setTimeout(function() {
        checkUserNotifications();
    },60000);
}

function loadPageChoices() {
    loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_page_choices",function(returnArray){
        if ("page_choices" in returnArray) {
            $("#_page_choices").html(returnArray['page_choices']);
            openPageChooser();
        }
    });
};

function openPageChooser() {
    const $pageChooserDialog = $("#_page_chooser_dialog");
    var $pageChoices = $("#_page_choices").find("div.page-choice");
    $pageChoices.removeClass("selected");

    if ($pageChooserDialog.is('dialog') && $pageChooserDialog.dialog('isOpen')) {
        return;
    }
    if (empty($("#_page_choices").html())) {
        loadPageChoices();
        return;
    }
    if ($pageChoices.find(".selected").length == 0) {
        $pageChoices.first().addClass("selected");
    }
    $pageChooserDialog.dialog({
        closeOnEscape: true,
        draggable: true,
        modal: true,
        resizable: true,
        position: {my: "center top", at: "center top+100px", of: window, collision: "none" },
        width: 800,
        title: 'Page Choices',
        buttons: {
            Close: function (event) {
                $pageChooserDialog.dialog('close');
            },
        }
    });
}

function afterLoadAdminMenu(menuCode,elementId) {
	if (elementId == "_menu_contents") {
		$("#_menu_contents > ul > li").each(function() {
			var shown = readCookie($(this).attr("id"));
			if (shown == "1" && $(this).find("ul").length > 0) {
				$(this).addClass("show-submenu");
				$(this).find("span.submenu-pointer").first().removeClass("fa-chevron-right").addClass("fa-chevron-down");
			}
		});
		$("#_menu_contents > ul > li > ul > li").click(function(event) {
			if (ulClicked) {
				ulClicked = false;
				return true;
			}
			if ($(this).find("ul").length > 0) {
				if ($(this).find("ul").is(".visible")) {
					$("#_menu_contents ul ul ul").removeClass("visible");
				} else {
					$("#_menu_contents ul ul ul").removeClass("visible");
					$(this).find("ul").addClass("visible");
				}
				event.stopPropagation();
			}
			return true;
		});
		$("#_menu_contents > ul > li > ul > li > a").click(function(event) {
			var scriptFilename = $(this).closest(".menu-item").data("script_filename");
			if (scriptFilename != "" && scriptFilename != undefined) {
				event.stopPropagation();
				event.preventDefault();
				if (scriptFilename.substring(0,1) == "#") {
					var dest = 0;
					if ($(this.hash).offset().top > $(document).height()-$(window).height()) {
						dest = $(document).height()-$(window).height();
					} else {
						dest = $(this.hash).offset().top;
					}
					$('html,body').animate({scrollTop:dest}, 1000,'swing');
					return false;
				} else {
				    event.stopPropagation();
					return goToLink($(this).closest(".menu-item"),scriptFilename,$(this).closest(".menu-item").data("separate_window") == "YES" || event.metaKey || event.ctrlKey);
				}
			}
		});
		$("#_menu_contents > ul > li > ul").click(function(event) {
			ulClicked = true;
		});
		$("#_menu_contents > ul > li").click(function(event) {
			if (ulClicked) {
				ulClicked = false;
				return true;
			}
			$("#_menu_contents ul ul ul").removeClass("visible");
			if ($(this).find("ul").length > 0) {
				$(this).toggleClass("show-submenu");
				if ($(this).is(".show-submenu")) {
					$(this).find("span.submenu-pointer").first().removeClass("fa-chevron-right").addClass("fa-chevron-down");
					createCookie($(this).attr("id"),"1",3650);
				} else {
					$(this).find("span.submenu-pointer").first().removeClass("fa-chevron-down").addClass("fa-chevron-right");
					createCookie($(this).attr("id"),"0",-1);
				}
			}
			if ($(this).find("ul").length > 0) {
				event.stopPropagation();
			}
			return true;
		});
		$("#_menu_contents > ul > li > a").click(function(event) {
			if ($(this).closest("li").find("ul").length > 0 && !empty($(this).attr("href"))) {
				event.stopPropagation();
			}
			return true;
		});
		$("#_menu_contents > ul > li > ul > li > ul").click(function(event) {
			ulClicked = true;
		});
		if (typeof adminMenusFullyLoaded === "function") {
			setTimeout(function() {
				adminMenusFullyLoaded();
			},100);
		}
	}
}

function processUserTimeoutLeft(secondsLeft) {
	if (secondsLeft < 300) {
		var minutes = Math.floor(secondsLeft / 60);
		var seconds = secondsLeft % 60;
		if (secondsLeft <= 0) {
			displayErrorMessage("Your login has timed out. If you have changes that need to be saved, open another window and log in");
			if (!empty(userTimeoutIntervalTimer)) {
				clearTimeout(userTimeoutIntervalTimer);
			}
		} else {
			displayErrorMessage("Logout because of inactivity will happen in " + (minutes > 0 ? minutes + " minute" + (minutes == 1 ? "" : "s") + " and " : "") + seconds + " second" + (seconds == 1 ? "" : "s"));
		}
	}
}
<?php
	$templateContents['javascript_code'] = ob_get_clean();
	ob_start();
?>
@import url('https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap');

html { font-size: 18px; }
body { font-family: 'Roboto', sans-serif; }

#_wrapper { display: flex; width: 100%; min-height: 100vh; table-layout: fixed; position: relative; }
#_system_version { display: block; position: absolute; right: 25px; top: 72px; font-size: .6rem; z-index: 5000; }
#_full_screen_icon { display: block; position: absolute; right: 5px; top: 72px; font-size: .8rem; z-index: 5000; }
#_full_screen_icon span { cursor: pointer; }
#_full_screen_icon .fa-expand { display: block; }
#_full_screen_icon .fa-compress { display: none; }
#_wrapper.full-screen {
    zoom: 1.25;
    #_full_screen_icon { top: 5px; }
    #_full_screen_icon .fa-expand { display: none; }
    #_full_screen_icon .fa-compress { display: block; }
    #_management_header { padding-left: 10px; }
    #_management_content { padding: 20px; }
    #_management_sidebar { display: none; }
    #_title_header { display: none; }
    #_system_version { display: none; }
}
button { font-size: .8rem; padding: 5px 15px; text-transform: uppercase; border-radius: 0; border: 1px solid rgb(0,124,124); background-color: rgb(255,255,255); cursor: pointer; color: rgb(0,124,124); font-weight: 700; margin: 0 5px; }
button.disabled-button { opacity: .3; }
button:hover { background-color: rgb(0,124,124); color: rgb(255,255,255); }
button .button-icon { margin-right: 10px; }
a.button { font-size: .8rem; padding: 5px 15px; text-transform: uppercase; border-radius: 0; border: 1px solid rgb(0,124,124); background-color: rgb(255,255,255); cursor: pointer; color: rgb(0,124,124); font-weight: 700; margin: 0 5px; }
a.button:hover { background-color: rgb(0,124,124); color: rgb(255,255,255); text-decoration: none; }
.selection-control td.selection-controls button { color: rgb(0,124,124); }
.selection-control td.selection-controls button:hover { color: rgb(255,255,255); }
input[type=text],input[type=password] { font-weight: 400; border: 1px solid rgb(220,220,220); border-radius: 0; padding: 5px 10px; font-size: 1rem; max-width: 100%; }
input[type=text]:focus { outline: none; }
.selection-choices-div li { border: 1px solid rgb(0,124,124); background-color: rgba(0,124,124,.1); }
.selection-chosen-div li { border: 1px solid rgb(0,124,124); background-color: rgba(0,124,124,.1); }
.editable-list {
    th { color: rgb(255,255,255); }
    th:hover { color: rgb(120,120,120); }
}
.editable-list-add span { color: rgb(50,150,50); font-size: 20px; }
.editable-list-add:hover span { color: rgb(120,120,120); }
.editable-list-remove span { color: rgb(150,50,50); font-size: 20px; }
.editable-list-remove:hover span { color: rgb(120,120,120); }
.ui-corner-all, .ui-corner-top, .ui-corner-right, .ui-corner-tr { border-top-right-radius: 0; }
.ui-corner-all, .ui-corner-top, .ui-corner-right, .ui-corner-tl { border-top-left-radius: 0; }
.ui-tabs .ui-tabs-nav li { top: 0; border: 1px solid #aaa; margin-bottom: 4px; }
.ui-tabs .ui-tabs-nav .ui-tabs-anchor { padding: .25em 1em; }
p { font-weight: 400; }
a,a:link,a:active,a:visited { font-weight: 900; color: rgb(0,124,124); text-align: left; text-decoration: none; }
a:hover { color: rgb(50,50,50); text-decoration: underline; }

a.page-logout,a.page-login { color: rgb(255,255,255); font-size: .9rem; z-index: 500; }
th:hover { color: rgb(0,124,124); }
#_main_content .ui-tabs .ui-tabs-nav li.ui-tabs-active { background: rgb(0,124,124); color: rgb(255,255,255); }
#_maintenance_list th:hover { color: rgb(0,124,124); }

#_management_sidebar { display: block; background-color: rgb(50,60,66); position: relative; flex: 0 0 20%; padding-bottom: 40px; max-width: 20%; }
#_logo { position: relative; display: none; height: 70px; cursor: pointer; }
#_logo img { display: block; max-width: 90%; max-height: 90%; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); }
#_alternate_header {
    height: 80px; display: none; position: relative; top: 10px; border-bottom: 2px solid rgb(180,180,180); cursor: pointer; overflow: visible;
    &.visible { display: block; }
    img { max-height: 80%; max-width: 60%; position: absolute; bottom: 10px; }
    .menu-item-link span.fad { display: none; }
    .menu-item-link span.fas { display: none; }
    .menu-item-link span.far { display: none; }
}

#_management_sidebar #_logo { background-color: rgb(240,240,240); display: block; }

#_sidebar_divider { background-color: rgb(200,200,200); cursor: col-resize; position: relative; flex: 0 0 4px; max-width: 4px; overflow: visible; }
#_management_content { position: relative; padding: 30px; flex: 1 1 auto; max-width: 100%; overflow: scroll; padding-top: 70px; }

#_title_header { position: absolute; top: 0; left: 0; width: 100%; background-color: rgb(0,124,124); height: 70px; }
#_title_header h1 { color: rgb(255,255,255); position: absolute; top: 50%; left: 40px; transform: translate(0,-50%); z-index: 1000; }
#_user_information {
    text-align: right; max-width: 60%; position: absolute; right: 40px; top: 0; height: 70px; display: flex; justify-content: flex-end; align-items: center;
    div { color: rgb(255,255,255); font-size: 1.2rem; white-space: nowrap; flex: 0 0 auto; position: relative; margin-right: 16px; }
    div#_user_image_wrapper { flex: 0 0 auto; height: 50px; width: 50px; border-radius: 50%; background-repeat: no-repeat; background-position: center center; background-size: cover; background-color: rgb(255,255,255); cursor: pointer; }
    div#_user_name_link { cursor: pointer; }
    div#_page_help_button { font-size: 2rem; cursor: pointer; padding-right: 0; }
    a { color: rgb(255,255,255); }
    a:hover { color: rgb(240,240,240); }
}
#_user_notifications_wrapper { cursor: pointer; padding-right: 10px; }
#_user_notifications_count { font-size: .6rem; background-color: rgb(180,180,180); min-width: 20px; height: 20px; border-radius: 10px; padding: 0 5px; display: block; text-align: center; position: absolute; top: 60%; left: 40%; line-height: 20px; }
#_user_notifications_count.notifications-exist { background-color: rgb(192,0,0); }
#_user_notifications { font-size: 1.8rem; }

#_management_header { position: relative; padding-bottom: 30px; max-width: 100%; }
#_whats_new_label { cursor: pointer; }

#_form_header_buttons {
    position: relative; white-space: nowrap; display: block; float: left; margin-right: 20px; margin-bottom: 10px; height: auto; max-width: 100%; min-width: 100%;
    button { border: 1px solid rgb(0,124,124); background-color: rgb(0,124,124); color: rgb(255,255,255); border-radius: 3px; height: auto; padding: 4px 15px; box-shadow: 3px 3px 10px 0px rgba(0,0,0,0.5); }
    button:hover { box-shadow: 5px 5px 5px 0px rgba(0,0,0,0.75); }
    button:active { position: relative; top: 2px; left: 2px; box-shadow: 3px 3px 5px 0px rgba(0,0,0,0.75); }
    button.disabled-button,button.disabled-button:hover,button.disabled-button:active { box-shadow: none; position: relative; top: 0px; left: 0px; }
    #_save_button { background-color: rgb(0,180,100); border: 1px solid rgb(0,180,100); }
    #_add_button { background-color: rgb(0,100,180); border: 1px solid rgb(0,100,180); }
    #_delete_button { background-color: rgb(180,20,0); border: 1px solid rgb(180,20,0); }
    #_list_button { background-color: rgb(80,0,120); border: 1px solid rgb(80,0,120); }
    #_duplicate_button { background-color: rgb(220,120,60); border: 1px solid rgb(220,120,60); }
    #_changes_button { background-color: rgb(125,100,25); border: 1px solid rgb(125,100,25); }
}

#_list_actions { overflow: visible; cursor: pointer; display: block; width: auto; float: right; height: 40px; position: relative; border: 1px solid rgb(0,0,0); background-color: rgb(10,20,40); color: rgb(255,255,255); padding: 0 15px; }
#_list_actions:hover { color: rgb(10,20,40); background-color: rgb(255,255,255); }
#_list_actions .fad { margin-right: 10px; font-size: 1.2rem; }
#_list_actions .fa-angle-down { margin-left: 20px; margin-right: 5px; }
#_list_actions #_actions { display: none; position: absolute; top: 100%; right: -1px; background-color: rgb(255,255,255); color: rgb(0,0,0); border: 1px solid rgb(0,124,124); }
#_list_actions.dropdown #_actions { display: block; width: auto; z-index: 10000; }
#_list_actions_text { line-height: 40px; }
#_actions .action-option { padding: 4px 10px; font-size: .8rem; white-space: nowrap; }
#_actions .action-option:hover { background-color: rgb(0,124,124); color: rgb(255,255,255); }

#_list_search_control { display: block; width: 400px; max-width: 100%; position: relative; margin-right: 0; margin-bottom: 20px; float: left; height: 40px; }
#_filter_text { width: 100%; position: relative; padding: 4px 20px 4px 10px; border: 1px solid rgb(0,124,124); border-radius: 0; height: 40px; font-size: 1.2rem; }
#_filter_text::-webkit-input-placeholder { color: rgb(180,180,180); text-align: left;  }
#_filter_text:-moz-placeholder { color: rgb(180,180,180); text-align: left; }
#_filter_text::-moz-placeholder { color: rgb(180,180,180); text-align: left; }
#_filter_text:-ms-input-placeholder { color: rgb(180,180,180); text-align: left;}
#_filters_on { display: none; }
#_filters_on.filters-on { display: inline; }
#_filter_button { height: 40px; padding: 0 20px; float: left; border: 1px solid rgb(0,124,124); cursor: pointer; border-left: none; }
#_filter_button div { line-height: 40px; }
#_filter_button:hover { background-color: rgb(0,124,124); color: rgb(255,255,255); }
#_filter_button .fad { margin-right: 10px; }
#_filters_on.filters-on { color: rgb(255,30,30); font-weight: 900; }
#_filter_button:hover #_filters_on.filters-on { color: rgb(255,255,255); }

#_search_button { position: absolute; right: 16px; top: 50%; transform: translate(0,-50%); }
#_search_button .fad { font-size: 1.1rem; }
#_search_button .fad:hover { color: rgb(0,124,124); }
#_list_search_control #_search_button .fa-eraser { display: none; }
#_list_search_control.unchanged #_search_button .fa-eraser { display: inline-block; }
#_list_search_control.unchanged #_search_button .fa-search { display: none; }
#_list_header_buttons { max-width: 100%; white-space: nowrap; display: block; float: right; margin-bottom: 20px; height: 40px; }
#_list_header_buttons button { border: 1px solid rgb(0,124,124); background-color: rgb(0,124,124); color: rgb(255,255,255); border-radius: 0; height: 40px; padding: 8px 20px; }
#_list_header_buttons button:hover { color: rgb(0,124,124); background-color: rgb(255,255,255); }
#_list_header_buttons #_add_button { background-color: rgb(0,100,180); border: 1px solid rgb(0,100,180); }
#_list_header_buttons #_add_button:hover { color: rgb(0,100,180); background-color: rgb(255,255,255); }

#_controls_wrapper { max-width: 100%; margin-top: 20px; }
#_page_number_controls { position: absolute; left: 0; bottom: 2px; }
#_page_number_controls button { width: 30px; height: 30px; padding: 0; text-align: center; border-radius: 3px; position: relative; }
#_page_number_controls button span { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); }
#_page_number_controls p { padding: 0; margin: 0; font-size: .6rem; }
.page-record-display { display: inline-block; text-align: center; margin: 0 10px; font-size: 1.0rem; }
.page-select-count-wrapper { font-weight: bold; color: rgb(200,0,0); }
#_list_page_number { position: relative; top: 10px; }

#_main_content { width: 100%; max-width: 100%; overflow-x: scroll; margin-top: 10px; padding-bottom: 200px; }
#_main_content p { font-size: 1rem; }
#_main_content li { font-size: .9rem; }
#_maintenance_list { max-width: 100%; }

#_mobile_menu_icon { display: none; position: absolute; bottom: 20px; right: 10px; height: 25px; width: 25px; font-size: 24px; color: rgb(180,180,180); cursor: pointer; z-index: 900; }

#_menu_wrapper { display: block; z-index: 500; }
#_menu_contents ul { padding: 0 0 0 0; list-style-type: none; list-style-position: inside; position: relative; z-index: 500; }
#_menu_contents ul li { padding: 8px 0 8px 0; position: relative; cursor: pointer; color: rgb(255,255,255); white-space: nowrap; }
#_menu_contents ul li.user-menu-item { background-color: rgb(0,124,124); }
#_menu_contents .menu-item-link span.fad { width: 30px; }
#_menu_contents .menu-item-link span.fas { width: 30px; }
#_menu_contents .menu-item-link span.far { width: 30px; }
#_menu_contents > ul > li >a.menu-item-link span.fad { width: 40px; }
#_menu_contents > ul > li >a.menu-item-link span.fas { width: 40px; }
#_menu_contents > ul > li >a.menu-item-link span.far { width: 40px; }
#_menu_contents ul li.user-menu-item:hover { filter: brightness(95%); }
#_menu_contents ul a { position: relative; left: 25px; text-decoration: none; color: rgb(255,255,255); font-weight: 500; font-size: .8rem; white-space: nowrap; }
#_menu_contents ul > li a { font-size: 1rem; }
#_menu_contents ul a span { color: inherit; }
#_menu_contents ul li:hover { background-color: rgb(0,0,0); }
#_menu_contents ul li.show-submenu { background-color: rgb(0,124,124); }
#_menu_contents ul li.show-submenu:hover { background-color: rgb(0,124,124); }
#_menu_contents ul li:hover > a { color: rgb(240,240,240); }
#_menu_contents .submenu-pointer { position: absolute; font-size: .7rem; top: 15px; right: 10px; }
#_menu_contents ul li ul a { left: 40px; color: rgb(255,255,255); }
#_menu_contents ul ul { display: none; background-color: rgb(30,33,39); position: relative; top: 8px; padding: 0; }
#_menu_contents ul ul li { padding: 6px 0 8px 0; }
#_menu_contents ul ul li a { font-size: .9rem; }
#_menu_contents ul li.show-submenu ul { display: block; }
#_menu_contents ul ul .submenu-pointer { right: 10px; color: rgb(255,255,255); top: 14px; }

#_menu_contents ul li.show-submenu ul ul { display: none; }
#_menu_contents ul ul ul { display: none; background-color: rgb(30,33,39); top: 10px; padding: 0; }
#_menu_contents ul ul ul li { padding: 6px 0 10px 20px; }
#_menu_contents ul ul ul li a { font-size: .8rem; }
#_menu_contents ul ul li ul a { color: rgb(220,220,220); }
#_menu_contents ul li.show-submenu ul ul.visible { display: block; }
#_menu_contents ul ul ul .submenu-pointer { right: 10px; color: rgb(0,0,0); }

#_log_in_out { position: absolute; top: 10px; right: 0; }
#_log_in_out a { font-size: .7rem; }

a#_user_name_link { text-decoration: none; color: rgb(255,255,255); font-weight: 500; }
a#_user_name_link:hover { color: rgb(200,200,200); }

p#_error_message { font-size: 1.2rem; margin-bottom: 20px; }
#_page_loading { display: block; position: absolute; top: 30%; left: calc(50% - 90px); font-size: 180px; color: rgb(200,200,200); z-index: 9999; }
#_page_chooser_filter { width: 100%; padding: 5px 20px; font-size: 1rem; }
#_page_choices { max-height: 400px; overflow: scroll; margin-top: 20px; border: 1px solid rgb(200,200,200); }
.page-choice { cursor: pointer; padding: 10px; font-size: 1.2rem; }
.page-choice.selected { background-color: rgb(180,240,240); }
.page-choice:hover { background-color: rgb(240,240,180); }

#_bookmark_page { position: absolute; right: 40px; color: rgb(200,0,200); top: 50%; transform: translate(0,-50%); cursor: pointer; font-size: 24px; }
#_show_last_error { position: absolute; right: 0; color: rgb(192,0,0); top: 50%; transform: translate(0,-50%); cursor: pointer; font-size: 24px; }
#_show_last_error.info-message { color: rgb(15,180,50); }
#_bookmark_page:hover { color: rgb(200,200,200); }
.remove-user-menu { color: rgba(200,200,200,.5); position: absolute; top: 50%; right: 10px; transform: translate(0,-50%); cursor: position; }

@media only screen and (max-width: 1500px) {
	#_list_search_control { width: 300px; }
}

@media only screen and (max-width: 1200px) {
	#_list_search_control { width: 200px; }
    #_search_button .fad { font-size: 1.6rem; }
    .ui-tabs-anchor { font-size: 1rem; }
}

@media only screen and (max-width: 1000px) {
	#_management_sidebar { display: none; }
	#_sidebar_divider { display: none; }
	#_mobile_menu_icon { display: block; }
    button .button-icon { font-size: 16px; }
	#_alternate_header { display: block; }
}

@media only screen and (max-width: 800px) {
    h1 { display: none; }
    #_management_content { padding: 70px 10px 30px 10px; }
    #_user_information { right: 0; }
    #_list_search_control, #_filter_button, #_list_actions, #_list_header_buttons, #_form_header_buttons { float: none; }
    #_filter_button { border-left: 1px solid rgb(0,124,124); width: 200px; margin-bottom: 20px; }
    #_list_actions { width: auto; margin-bottom: 20px; display: inline-block; float: none; }
    #_list_header_buttons button { margin: 0 10px 0 0; }
    #_form_header_buttons { white-space: normal; height: auto; margin-right: auto; margin-left: auto; text-align: center; }
    #_form_header_buttons button { margin: 0 5px 10px 5px; }
    #_list_actions #_actions { right: auto; left: -1px; }
    #_page_number_controls { width: 100%; text-align: center; }
    #_actions .action-option { font-size: 1.23rem; padding: 8px 10px; }
    button .button-icon-text { display: none; }
    button .button-icon { margin-right: 0; }
    .ui-tabs-anchor { font-size: 1.2rem; }
}
<?php
	$templateContents['css_content'] = ob_get_clean();
	ob_start();
?>
<html lang="en">

<!--suppress HtmlRequiredTitleElement -->
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- %metaKeywords% -->
<!-- %metaDescription% -->

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
%ifLoggedIn%
<meta name='robots' content='noindex,noarchive,nofollow' />
%endif%

<!-- %getPageTitle% -->

<!-- %favicon% -->

<!-- %crudIncludes% -->
<!-- %headerIncludes% -->

<!-- %internalCSS% -->

<!-- %crudJavascript% -->
<!-- %onLoadJavascript% -->
<!-- %javascript% -->

<!-- %getAnalyticsCode% -->

</head>

<body>

<span id="_page_loading" class="fad fa-spinner fa-spin"></span>

<div id="_wrapper" class="hidden">

<div id="_management_sidebar">
<div id="_logo">
<img src="/getimage.php?code=header_logo" alt="Header Logo">
</div> <!-- logo -->
<div id="_menu_wrapper">

<div id="_menu_contents">
</div>
</div> <!-- menu -->
</div> <!-- management_sidebar -->

<div id="_sidebar_divider">
</div>

<div id="_management_content">
<span id='_full_screen_icon'><span class='fad fa-expand'></span><span class='fad fa-compress'></span></span>
<span id="_system_version"></span>

<div id="_title_header">
    <h1 class="page-heading">Title</h1>
    <div id="_user_information">
        <div id='_whats_new_label' class='admin-logged-in inline-block'>What's New?</div><div class="admin-logged-in announcekit-widget"></div>
        <div id="_user_notifications_wrapper"><span class='fad fa-bell' id="_user_notifications"></span><span id="_user_notifications_count" class='%userNotificationClass%'>%userNotificationCount%</span></div>
        <div id="_user_name_link">%userDisplayName%</div>
        <div id="_user_image_wrapper" style="background-image: url('%userImageFilename%');"></div>
        <div id="_page_help_button" class="page-help-button fad fa-question-circle"></div>
        <div><a class="page-login user-not-logged-in" href='#'>Login</a><a class="page-logout user-logged-in" href='#'>Logout</a></div>
    </div> <!-- _user_information -->
</div>

<div id="_management_header">
<div id="_alternate_header">
<img src="/getimage.php?code=header_logo" alt="Header Logo">
<div id="_mobile_menu_icon"><span class="fa fa-bars"></span></div>
</div> <!-- _alternate_header -->

<!-- %pageData:pre_management_header% -->

<div id="_controls_wrapper" class="page-controls">
<div id="_form_header_buttons" class="page-form-control page-form-buttons">
<span id="_bookmark_page" class='fad fa-bookmark hidden' title='Bookmark this record'></span>
<span id="_show_last_error" class='fad fa-exclamation-triangle hidden' title='Last Error'></span>
</div> <!-- form_header_buttons -->

<div id="_list_search_control" class="page-list-control unchanged">
<input tabindex="10" type="text" id="_filter_text" name="_filter_text" placeholder="Search" />
<div id="_search_button" class="page-search-button"><span class="fad fa-search"></span><span class="fad fa-eraser"></span></div>
</div> <!-- list_search_control -->

<div id="_filter_button" class="hidden"><div><span class='fad fa-sliders-h'></span>Filters<span id='_filters_on'> SET</span></div></div>

<div id="_list_header_buttons" class="page-list-control page-list-buttons">
</div> <!-- list_header_buttons -->

<div id="_list_actions" class="page-list-control">
<div id="_list_actions_text"><span class='fad fa-cog'></span>Actions<span class='fas fa-angle-down'></span></div>
<div class='page-action-selector' id="_actions"></div>
</div> <!-- list_actions -->
<div class='clear-div'></div>
</div> <!-- controls_wrapper -->

<p class="error-message" id="_error_message"></p>

<div id="_page_number_controls" class="page-controls">

<button id="_page_previous_button" class="page-record-display page-previous-button"><span class="fa fa-chevron-left"></span></button>

<div id="_list_page_number" class="page-record-display page-list-control">
<span class="page-first-record-display"></span> - <span class="page-last-record-display"></span> of <span class="page-row-count"></span><span class='page-select-count-wrapper'>, <span class="page-select-count"></span> selected</span>
</div> <!-- list_page_number -->

<div id="_form_page_number" class="page-record-display page-form-control">
<span class="page-record-number"></span> of <span class="page-row-count"></span>
</div> <!-- form_page_number -->

<button id="_page_next_button" class="page-record-display page-next-button"><span class="fa fa-chevron-right"></span></button>

</div> <!-- page_number_controls -->

<!-- %pageData:post_management_header% -->
</div> <!-- management_header -->

<div id="_main_content">
<!-- %mainContent% -->
<div class='clear-div'></div>
</div> <!-- main_content -->
</div> <!-- management_content -->

</div> <!-- wrapper -->

<!-- %jqueryTemplates% -->
<!-- %hiddenElements% -->

<div id="_build_content_dialog" class="dialog-box">
</div>

<div id="_iframe_link_dialog" class="dialog-box">
	<iframe id="_iframe_link"></iframe>
</div>

<div id="_page_chooser_dialog" class="dialog-box">
    <input type="text" id="_page_chooser_filter" placeholder="Filter">
	<div id="_page_choices"></div>
</div>

<script>
$(function() {
	$("#_wrapper").removeClass("hidden");
	$("#_page_loading").remove();
	loadAdminMenu("admin_menu","_menu_contents",{ include_top_level_subpointer: true });
});
</script>
<script>
$(document).on("click","#_whats_new_label",function() {
    $("div.announcekit-widget").trigger("click");
});
</script>
</body>
</html>
<?php
	$templateContents['html_content'] = ob_get_clean();
	ob_start();
	?>
#_title_header { background-color: %management_color%; }
#_form_header_buttons button { border: 1px solid %management_color%; background-color: %management_color%; }
#_list_actions #_actions { border: 1px solid %management_color%; }
#_list_actions { background-color: %management_color%; }
#_list_actions:hover { color: %management_color%; }
#_actions .action-option:hover { background-color: %management_color%; }
#_filter_text { border: 1px solid %management_color%; }
#_filter_button { border: 1px solid %management_color%; border-left: none; }
#_filter_button:hover { background-color: %management_color%; }
#_list_header_buttons button { border: 1px solid %management_color%; background-color: %management_color%; }
#_menu_contents ul li.user-menu-item { background-color: %management_color%; }
#_menu_contents ul li.show-submenu { background-color: %management_color%; }
#_menu_contents ul li.show-submenu:hover { background-color: %management_color%; }
th:hover { color: %management_color%; }
#_main_content .ui-tabs .ui-tabs-nav li.ui-tabs-active { background: %management_color%; }
#_maintenance_list th:hover { color: %management_color%; }
button { border: 1px solid %management_color%; color: %management_color%; }
button:hover { background-color: %management_color%; }
.selection-control td.selection-controls button { color: %management_color%; }
.selection-choices-div li { border: 1px solid %management_color%; background-color: %management_color_light%; }
.selection-chosen-div li { border: 1px solid %management_color%; background-color: rgba(0,124,124,.1); }
a,a:link,a:active,a:visited { color: %management_color%; }
.basic-form-line input[type=text]:focus { border-color: %management_color%; }
.basic-form-line select:focus { border-color: %management_color%; }
.basic-form-line label { color: %management_color%; }
<?php
    $templateContents['color_override'] = ob_get_clean();
	return $templateContents;
}
