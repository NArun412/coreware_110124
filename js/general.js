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

var lastFocusFieldId = "";
var minimumPasswordStrength = 3;
var userTimeoutIntervalTimer = null;
var gDefaultAjaxTimeout = 30000;
var logJavascriptErrors = false;

function goToTabbedContentPage($listItem) {
    const sectionId = $listItem.data("section_id");
    if (empty(sectionId)) {
        return;
    }
    $listItem.closest(".tabbed-content-nav").find(".tabbed-content-tab").removeClass("active").removeClass("initial-active");
    $listItem.addClass("active").addClass("visited");
    $listItem.closest(".tabbed-content").find("> .tabbed-content-body > .tabbed-content-page").addClass("hidden").removeClass("active");
    $("#" + sectionId).removeClass("hidden").addClass("active");
    if ($listItem.is(":first-child")) {
        $listItem.closest(".tabbed-content").find("> .tabbed-content-buttons").find(".tabbed-content-previous-page").addClass("disabled-button");
    } else {
        $listItem.closest(".tabbed-content").find("> .tabbed-content-buttons").find(".tabbed-content-previous-page").removeClass("disabled-button");
    }
    if ($listItem.is(":last-child")) {
        $listItem.closest(".tabbed-content").find("> .tabbed-content-buttons").find(".tabbed-content-next-page").addClass("disabled-button");
    } else {
        $listItem.closest(".tabbed-content").find("> .tabbed-content-buttons").find(".tabbed-content-next-page").removeClass("disabled-button");
    }
    if (empty($listItem.closest(".tabbed-content").data("no_scroll"))) {
        $("html,body").animate({ scrollTop: $listItem.closest(".tabbed-content").offset().top }, 400, function () {
            if ("afterGoToTabbedContentPage" in window) {
                afterGoToTabbedContentPage($listItem);
            }
        });
    } else if ($listItem.closest(".tabbed-content").data("no_scroll") === "top") {
        $("html,body").animate({ scrollTop: 0 }, 400, function () {
            if ("afterGoToTabbedContentPage" in window) {
                afterGoToTabbedContentPage($listItem);
            }
        });
    } else {
        if ("afterGoToTabbedContentPage" in window) {
            afterGoToTabbedContentPage($listItem);
        }
    }
}

$.fn.reverse = [].reverse;

$.fn.bindFirst = function (name, fn) {
    this.on(name, fn);
    this.each(function () {
        let handlers = $._data(this, 'events')[name.split('.')[0]];
        let handler = handlers.pop();
        handlers.splice(0, 0, handler);
    });
};

$(function () {
    $(".tooltip-element").tooltip();
    $(document).on("click", "#_show_last_error", function () {
        if (clearMessageTimer != null) {
            clearTimeout(clearMessageTimer);
            clearMessageTimer = null;
        }
        $("#_error_message").add(".error-message").html("");
        if ($(this).hasClass("info-message")) {
            if (!empty(lastInfoMessageText)) {
                displayInfoMessage(lastInfoMessageText);
            }
        } else {
            if (!empty(lastErrorMessageText)) {
                displayErrorMessage(lastErrorMessageText);
            }
        }
        if (!empty(lastErrorMessageText) && !empty(lastInfoMessageText)) {
            $("#_show_last_error").toggleClass("info-message");
        }
    });
    $(".tabbed-content").each(function () {
        $(this).find(".tabbed-content-page").addClass("hidden");
        if ($(this).hasClass("ignore-tab-clicks")) {
            $(this).find('> ul.tabbed-content-nav li.tabbed-content-tab').first().on('click', function () {
                goToTabbedContentPage($(this));
            });
        } else {
            $(this).find('> ul.tabbed-content-nav li.tabbed-content-tab').on('click', function () {
                goToTabbedContentPage($(this));
            });
        }
        let foundInitial = false;
        $(this).find("> ul.tabbed-content-nav li.tabbed-content-tab").each(function () {
            if ($(this).hasClass("initial-active")) {
                $(this).trigger("click");
                foundInitial = true;
                return false;
            }
        });
        if (!foundInitial) {
            $(this).find("> ul.tabbed-content-nav li.tabbed-content-tab").first().trigger("click");
        }
        $(this).find(".tabbed-content-previous-page").on("click", function () {
            if ($(this).hasClass("disabled-button")) {
                return false;
            }
            $previousTab = $(this).closest(".tabbed-content").find('> ul.tabbed-content-nav li.tabbed-content-tab.active').first().prev();
            if ($.isEmptyObject($previousTab)) {
                $(this).addClass("hidden");
                return false;
            }
            goToTabbedContentPage($previousTab);
            return false;
        });
        $(this).find(".tabbed-content-next-page").on("click", function () {
            if ($(this).hasClass("disabled-button")) {
                return false;
            }
            let functionName = "";
            if ($(this).closest(".tabbed-content").find(".tabbed-content-page.active").length > 0 && !$(this).closest(".tabbed-content").find(".tabbed-content-page.active").validationEngine("validate")) {
                if (!empty($(this).closest(".tabbed-content").find(".tabbed-content-page.active").data("validation_error"))) {
                    displayErrorMessage($(this).closest(".tabbed-content").find(".tabbed-content-page.active").data("validation_error"));
                }
                functionName = $(this).closest(".tabbed-content").find(".tabbed-content-page.active").data("validation_error_function");
                if (typeof window[functionName] === "function") {
                    window[functionName]();
                }
                return false;
            }
            if (!empty($(this).closest(".tabbed-content").find(".tabbed-content-page.active").data("validation_error_function"))) {
                functionName = $(this).closest(".tabbed-content").find(".tabbed-content-page.active").data("validation_error_function");
                if (typeof window[functionName] === "function") {
                    if (!window[functionName]()) {
                        return false;
                    }
                }
            }
            $nextTab = $(this).closest(".tabbed-content").find('> ul.tabbed-content-nav li.tabbed-content-tab.active').first().next();
            if ($.isEmptyObject($nextTab)) {
                $(this).addClass("hidden");
                return false;
            }
            goToTabbedContentPage($nextTab);
            return false;
        });
    });

    $(document).on("click", ".page-module-system-notice", function () {
        const systemNoticeId = $(this).data("system_notice_id");
        const requireAcceptance = $(this).data("require_acceptance");
        const $originalElement = $(this);
        if ($("#_page_module_system_notice_dialog_box").length === 0) {
            $("body").append("<div class='dialog-box' id='_page_module_system_notice_dialog_box'><div id='_system_notice_content'></div></div>");
        }
        loadAjaxRequest("/index.php?ajax=true&url_action=get_system_notice_content&system_notice_id=" + systemNoticeId, function (returnArray) {
            if ("system_notice_content" in returnArray) {
                $("#_system_notice_content").html(returnArray['system_notice_content']);
                $('#_page_module_system_notice_dialog_box').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Preferences',
                    buttons: [
                        {
                            text: (empty(requireAcceptance) ? "Dismiss" : "Acknowledge & Accept"),
                            click: function () {
                                loadAjaxRequest("/index.php?ajax=true&url_action=mark_system_notice_read&system_notice_id=" + systemNoticeId, function (returnArray) {
                                    $originalElement.remove();
                                });
                                $("#_page_module_system_notice_dialog_box").dialog('close');
                            }
                        },
                        {
                            text: "Cancel",
                            click: function () {
                                $("#_page_module_system_notice_dialog_box").dialog('close');
                            }
                        }
                    ]
                });
            }
        });
    });

    $(document).on("click", "#_login_popup_dialog a#access_link", function () {
        if ($().validationEngine) {
            $("#_login_edit_form").validationEngine("hideAll");
        }
        $("#_login_popup_dialog").data("forgot_form", true);
        $("#_login_popup_dialog #login_form").slideUp();
        $("#_login_popup_dialog #access_link_div").slideUp();
        $("#_login_popup_dialog #forgot_form").slideDown();
        $("#_login_popup_dialog #forgot_user_name").focus();
        $("#_login_popup_dialog_button").html("Submit");
        return false;
    });
    $(document).on("click", ".login-popup-link", function () {
        showLoginPopup();
        return false;
    });
});

$(function () {
    $(document).on("click", ".copy-to-clipboard", function (event) {
        event.stopPropagation();
        event.preventDefault();
        let $tempElement = $("<input>");
        $("body").append($tempElement);
        if ($(this).find("span.copy-text").length > 0) {
            $tempElement.val($(this).find("span.copy-text").text()).select();
        } else {
            $tempElement.val($(this).text()).select();
        }
        document.execCommand("Copy");
        $tempElement.remove();
    });
    $(document).on("click", ".show-password", function () {
        const fieldName = $(this).data("field_name");
        let fieldType = false;
        if (empty(fieldName)) {
            fieldType = $(this).closest("div.form-line").find("input[type=password]").attr("type");
            $(this).closest("div.form-line").find("input[type=password]").attr("type", (fieldType === "password" ? "text" : "password"));
        } else {
            fieldType = $("#" + fieldName).attr("type");
            $("#" + fieldName).attr("type", (fieldType === "password" ? "text" : "password"));
        }
    })
    $(document).on("tap click", ".track-click", function () {
        let clickDescription = $(this).data("click_name");
        if (empty(clickDescription)) {
            clickDescription = $(this).attr("id");
        }
        if (empty(clickDescription)) {
            return;
        }
        $("body").addClass("no-waiting-for-ajax");
        loadAjaxRequest("/index.php?ajax=true&url_action=log_click", { description: clickDescription });
    });
    if ($("#onload_website_popup").length > 0) {
        if (empty($.cookie("onload_website_popup"))) {
            setTimeout(function () {
                showOnloadWebsitePopup();
            }, 500);
        }
    }

    $.ajaxSetup({
        cache: false
    });

    if ($(".modal").length === 0) {
        $("body").append('<div class="modal"><span class="fad fa-spinner fa-spin"></span></div>');
    }
    window.onerror = function (message, url, lineNo, colno, error) {
        if (logJavascriptErrors) {
            let thisError = error.stack;
            if (lineNo > 0) {
                loadAjaxRequest("logjavascripterror.php?ajax=true", { message: message, url: url, line_no: lineNo, script_filename: scriptFilename, error_source: thisError });
            }
        }
    }

    $(document).on("click", ".jump-link", function () {
        const elementId = $(this).data("element_id");
        if (!empty(elementId) && $("#" + elementId).length > 0) {
            $("html,body").animate({ scrollTop: $("#" + elementId).offset().top });
        }
        return false;
    });

    $(document).on("mousedown", "select.disabled-select", function (event) {
        event.preventDefault();
        this.blur();
        window.focus();
        return false;
    });
    $(document).on("focus", "select.disabled-select", function (event) {
        event.preventDefault();
        this.blur();
        window.focus();
        return false;
    });

// Show page help

    $(document).on("keydown", ".monthpicker", function (event) {
        if (event.which === 8 || event.which === 46) {
            $(this).val("");
        }
    });

    $(document).on("tap click", ".clickable", function () {
        const scriptFilename = $(this).data("script_filename");
        if (empty(scriptFilename)) {
            return true;
        }
        if (scriptFilename.substring(0, 4) === "http") {
            window.open(scriptFilename);
        } else {
            document.location = scriptFilename;
        }
    });

    $(document).on("tap click", ".page-help-button", function () {
        loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_help_file", function (returnArray) {
            if ("page_help" in returnArray) {
                $("#_page_help").html(returnArray['page_help']);
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
                    buttons: {
                        Close: function (event) {
                            $("#_page_help").dialog("close");
                        }
                    }
                });
            } else if ("help_url" in returnArray) {
                window.open(returnArray['help_url']);
            } else {
                $("#_page_help").html("No help available");
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
                    buttons: {
                        Close: function (event) {
                            $("#_page_help").dialog("close");
                        }
                    }
                });
                setTimeout(function() {
                    $("#_page_help").dialog("close");
                    $("#_page_help_button").remove();
                },3000)
            }
        });
        return false;
    });

// Sort table where the table is set to be sortable by the header

    $(document).on("click", "table.header-sortable th", function () {
        let i;
        if ($(this).hasClass("not-sortable")) {
            return;
        }
        let beforeSortFunction = $(this).closest("table.header-sortable").data("before_sort");
        if (typeof window[beforeSortFunction] === "function") {
            window[beforeSortFunction]();
        }
        let columnNumber = $(this).parent().children().index($(this));
        let sortDirection = 1;
        if ($(this).hasClass("header-sorted-up")) {
            sortDirection = -1;
        }
        let sortObjects = [];
        let rowNumber = 0;
        $(this).closest("table").find("tr").each(function () {
            if ($(this).is(".header-row") || $(this).is(".footer-row")) {
                return true;
            }
            rowNumber++;
            let thisId = $(this).attr("id");
            if (empty(thisId)) {
                thisId = makeId() + "_" + rowNumber;
                $(this).attr("id", thisId);
            }
            let thisColumn = $(this).find("td:eq(" + columnNumber + ")");
            let dataValue = "";
            if (thisColumn.attr("data-sort_value")) {
                dataValue = thisColumn.data("sort_value");
            } else {
                dataValue = thisColumn.text().replace(/,/g, "").toLowerCase();
            }
            sortObjects.push({ "data_value": dataValue, "row_id": thisId })
        });
        let objectCount = 0;
        for (let i in sortObjects) {
            objectCount++;
        }
        let emptyLast = $(this).closest("table.header-sortable").hasClass("empty-last");
        if (!emptyLast) {
            emptyLast = $(this).hasClass("empty-last");
        }
        if (objectCount > 0) {
            sortObjects.sort(function (a, b) {
                if (a.data_value === b.data_value || (a.data_value.length === 0 && b.data_value.length === 0)) {
                    return 0;
                } else if (b.data_value.length === 0) {
                    return (emptyLast ? -1 : 1);
                } else if (a.data_value.length === 0) {
                    return (emptyLast ? 1 : -1);
                } else {
                    if ($.isNumeric(a.data_value.replace("$", "")) && $.isNumeric(b.data_value.replace("$", ""))) {
                        return (parseFloat(a.data_value.replace("$", "")) < parseFloat(b.data_value.replace("$", "")) ? -1 : 1) * sortDirection;
                    } else if (isDate(a.data_value) && isDate(b.data_value)) {
                        let aDate = $.formatDate(new Date(Date.parse(a.data_value)), (a.data_value.length > 10 ? "yyyy/MM/dd HH:mm:ss" : "yyyy/MM/dd"));
                        let bDate = $.formatDate(new Date(Date.parse(b.data_value)), (b.data_value.length > 10 ? "yyyy/MM/dd HH:mm:ss" : "yyyy/MM/dd"));
                        return (aDate < bDate ? -1 : 1) * sortDirection;
                    } else {
                        return (a.data_value < b.data_value ? -1 : 1) * sortDirection;
                    }
                }
            });
            for (i in sortObjects) {
                if ($(this).closest("table.header-sortable").find("tr.footer-row").length > 0) {
                    $(this).closest("table").find("#" + sortObjects[i].row_id).insertBefore($(this).closest("table.header-sortable").find(".footer-row").eq(0));
                } else {
                    $(this).closest("table").find("#" + sortObjects[i].row_id).appendTo($(this).closest("table.header-sortable"));
                }
            }
        }
        $(this).closest("table.header-sortable").find("tr.header-row").find("span.fa-sort-alpha-down-alt,span.fa-sort-alpha-down").remove();
        $(this).closest("table.header-sortable").find("th.header-sorted-column").removeClass("header-sorted-column");
        $(this).closest("table.header-sortable").find("th.header-sorted-up").removeClass("header-sorted-up");
        $(this).closest("table.header-sortable").find("th.header-sorted-down").removeClass("header-sorted-down");
        $(this).append("<span class='fad fa-sort-alpha-down" + (sortDirection === 1 ? "" : "-alt") + "'></span>").addClass("header-sorted-column").addClass((sortDirection === 1 ? "header-sorted-up" : "header-sorted-down"));
        let afterSortFunction = $(this).closest("table.header-sortable").data("after_sort");
        if (typeof window[afterSortFunction] === "function") {
            window[afterSortFunction]();
        }
    });

// Check password string of field set to do so

    $(".password-strength").keyup(function () {
        if (empty($(this).val())) {
            $("#" + $(this).attr("id") + "_strength_bar_div").addClass("hidden");
        } else {
            checkPasswordStrength($(this).attr("id"));
        }
    });
    $(".password-strength").change(function () {
        if (empty($(this).val())) {
            $("#" + $(this).attr("id") + "_strength_bar_div").addClass("hidden");
        } else if (checkPasswordStrength($(this).attr("id")) < minimumPasswordStrength) {
            $("#" + $(this).attr("id") + "_strength_bar_div").removeClass("hidden");
            $("#" + $(this).attr("id") + "_strength_bar").css("background-color", "FF0000");
            $("#" + $(this).attr("id") + "_strength_bar_label").html("Password is too weak");
            $(this).val("").focus();
        }
    });

// If image slider is installed, activate it on divs with the image-slider class

    if ($().imageSlider) {
        $(".image-slider").imageSlider();
    }

// If there is a good analytics installed, register an event for a downloaded file

    $('a').on('click tap', function (event) {
        if (typeof ga !== 'function') {
            return;
        }
        let thisElement = $(this);
        let track = true;
        let href = (typeof (thisElement.attr('href')) != 'undefined') ? thisElement.attr('href') : "";
        if (empty(href)) {
            return;
        }
        let isThisDomain = href.match(document.domain.split('.').reverse()[1] + '.' + document.domain.split('.').reverse()[0]);
        let elementParts = {};
        elementParts.value = 0;
        elementParts.non_i = false;
        if (href.indexOf("download.php") > -1) {
            elementParts.category = "download";
            elementParts.action = "download-file";
            let labelDescription = thisElement.data("description");
            if (labelDescription === "" || labelDescription === undefined) {
                elementParts.label = href.replace(/ /g, "-");
            } else {
                elementParts.label = labelDescription;
            }
            elementParts.loc = href;
        } else {
            track = false;
        }

        if (track) {
            ga('send', 'event', elementParts.category, elementParts.action, elementParts.label, { 'page': elementParts.loc });
            if (thisElement.attr('target') === undefined || thisElement.attr('target').toLowerCase() !== '_blank') {
                setTimeout(function () {
                    location.href = elementParts.loc;
                }, 400);
                return false;
            }
        }
    });

// reset all cell spacing and padding on tables

    $("table").attr("cellspacing", "0px").attr("cellpadding", "0px");

// Ignore return on input and select so that it doesn't exit or jump to another field

    $(document).on("keypress", "input,select", function (event) {
        return event.keyCode !== 13;
    });
    let hostName = location.host;
    let parts = location.host.split(".");
    if (parts.length > 1) {
        hostName = parts[parts.length - 2] + "." + parts[parts.length - 1];
    }

// Any links that go off site, make target another window

    $("a[href^='http']").add("area[href^='http']").add("a[href^='//']").add("area[href^='http']").add("a[href*='download.php']").add("a.download-file-link").not("a[rel^='prettyPhoto']").not("a[href*='" + hostName + "']").not(".same-page").attr("target", "_blank");

// If prettyPhoto is installed, use it

    if ($().prettyPhoto) {
        $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
            social_tools: false,
            default_height: 480,
            default_width: 854,
            deeplinking: false
        });
    }

// Set the ID of the last field gone into

    $(document).on("focus", "input,select,textarea", function () {
        lastFocusFieldId = $(this).attr("id");
    });

// If minicolors is installed, use it

    if ($().minicolors) {
        $(".minicolors").minicolors({ letterCase: 'uppercase', control: 'wheel' });
    }

// allow quick keys for a date field: .=current date, +=tomorrow, -=yesterday

    $(document).on("keypress", "input[type=text][class*='custom[date]']:not(readonly):not(.monthpicker)", function (event) {
        switch (event.which) {
            case 46:
                $(this).val($.formatDate(new Date(), "MM/dd/yyyy"));
                $(this).trigger("change");
                return false;
            case 45:
                $(this).val(addDays($(this).val(), -1));
                $(this).trigger("change");
                return false;
            case 61:
            case 43:
                $(this).val(addDays($(this).val(), 1));
                $(this).trigger("change");
                return false;
            default:
                return true;
        }
    });

// allow quick keys for a time field: .=current time

    $(document).on("keypress", "input[type=text][class*='custom[time]']:not(readonly)", function (event) {
        switch (event.which) {
            case 46:
                $(this).val($.formatDate(new Date(), "h:mm a"));
                return false;
            default:
                return true;
        }
    });

// Add and remove waiting for ajax classes

    $(document).ajaxStart(function () {
        if (!$("body").hasClass("no-waiting-for-ajax")) {
            $("body").addClass("waiting-for-ajax");
        }
    }).ajaxStop(function () {
        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
    });

// If timepicker is installed, use it

    if ($().timepicker) {
        $(".timepicker").timepicker({
            showPeriod: true,
            showLeadingZero: false
        });
    }

// If date picker is installed, use it

    installDatePicker();

// Whenever there is a class required-label, append the required tag

    $(".required-label").append("<span class='required-tag fa fa-asterisk'></span>");

// When a menu item is clicked, go to the link designed for it

    $(document).on("tap click", ".menu-item", function (event) {
        if ($(this).hasClass("not-clickable")) {
            return false;
        }
        let menuScriptFilename = $(this).data("script_filename");
        if (empty(menuScriptFilename)) {
            menuScriptFilename = $(this).find("> a").attr("href");
            if ($(this).find("> a").hasClass("not-clickable")) {
                return false;
            }
        }
        if (empty(menuScriptFilename)) {
            return false;
        }
        if ("gUserUid" in window && "gUserKey" in window) {
            menuScriptFilename = menuScriptFilename.replace("%userUid%", gUserUid).replace("%userKey%", gUserKey);
        } else {
            menuScriptFilename = menuScriptFilename.replace("%userUid%", "").replace("%userKey%", "");
        }
        if (!empty(menuScriptFilename)) {
            event.stopPropagation();
            if (menuScriptFilename.substring(0, 1) === "#") {
                let dest;
                if ($(this.hash).offset().top > $(document).height() - $(window).height()) {
                    dest = $(document).height() - $(window).height();
                } else {
                    dest = $(this.hash).offset().top;
                }
                $('html,body').animate({ scrollTop: dest }, 1000, 'swing');
                return false;
            } else {
                $element = $(this);
                setTimeout(function () {
                    return goToLink($element, menuScriptFilename, $element.data("separate_window") === "YES" || event.altKey || event.metaKey || event.ctrlKey);
                }, 100);
            }
        }
        return false;
    });

    $(document).on("tap click", ".menu-item a", function (event) {
        if ($(this).hasClass("not-clickable")) {
            return false;
        }
        let menuItem = $(this).closest(".menu-item");
        let menuScriptFilename = $(this).closest(".menu-item").data("script_filename");
        let separateWindow = $(this).closest(".menu-item").data("separate_window");
        if (empty(menuScriptFilename)) {
            menuScriptFilename = $(this).attr("href");
        }
        if ("gUserUid" in window && "gUserKey" in window) {
            menuScriptFilename = menuScriptFilename.replace("%userUid%", gUserUid).replace("%userKey%", gUserKey);
        } else {
            menuScriptFilename = menuScriptFilename.replace("%userUid%", "").replace("%userKey%", "");
        }
        if (!empty(menuScriptFilename)) {
            event.stopPropagation();
            if (menuScriptFilename.substring(0, 1) === "#") {
                let dest;
                if ($(this.hash).offset().top > $(document).height() - $(window).height()) {
                    dest = $(document).height() - $(window).height();
                } else {
                    dest = $(this.hash).offset().top;
                }
                $('html,body').animate({ scrollTop: dest }, 1000, 'swing');
                return false;
            } else {
                setTimeout(function () {
                    return goToLink(menuItem, menuScriptFilename, separateWindow === "YES" || event.metaKey || event.ctrlKey);
                }, 100);
            }
        }
        return false;
    });

// If the div as a source, get the content of the source and put into the div

    $("div[src]").each(function () {
        let thisDiv = $(this);
        $.ajax({
                url: thisDiv.attr("src"),
                type: "GET",
                timeout: (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout),
                success: function (returnText) {
                    thisDiv.html(returnText);
                    $("table").attr("cellspacing", "0px").attr("cellpadding", "0px");
                    $("a[href^='http']").add("area[href^='http']").add("a[href*='download.php']").add("a.download-file-link").not("a[rel^='prettyPhoto']").attr("target", "_blank");
                    $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
                        social_tools: false,
                        default_height: 480,
                        default_width: 854,
                        deeplinking: false
                    });
                },
                dataType: "text"
            }
        );
    });


    $(document).on("tap click", ".photo-gallery-album", function () {
        let albumId = $(this).data("album_id");
        let topAlbumId = $(this).closest(".photo-gallery").data("top_album_id");
        let downloadLink = $(this).closest(".photo-gallery").data("download_link");
        loadAjaxRequest("photoalbum.php?ajax=true&url_action=get_gallery&album_id=" + albumId + "&download_link=" + downloadLink + (topAlbumId !== albumId ? "&parent=true" : ""), function (returnArray) {
            if ("gallery_content" in returnArray) {
                $("#_gallery_" + topAlbumId).data("album_id", albumId).html(returnArray['gallery_content']);
            }
            if ($().prettyPhoto) {
                $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
                    social_tools: false,
                    default_height: 480,
                    default_width: 854,
                    deeplinking: false
                });
            }
        });
        return false;
    });

// If validationEngine is installed, use it on all forms

    if ($("form").length > 0 && $().validationEngine) {
        $("form").validationEngine();
    }

    if ($(".tabbed-form").length > 0 && $().tabs) {
        let activeTab = ($("#_active_tab").length > 0 ? $("#_active_tab").val() - 0 : 0);
        $(".tabbed-form").tabs({
            active: activeTab,
            beforeActivate: function (event, ui) {
                $("#_edit_form").validationEngine("hideAll");
            },
            activate: function (event, ui) {
                if ("scriptFilename" in window) {
                    $("body").addClass("no-waiting-for-ajax");
                    loadAjaxRequest(scriptFilename + "?ajax=true&url_action=select_tab&tab_index=" + $(this).tabs("option", "active"));
                }
            },
            cookie: { expires: 120 }
        });
    }

    $(document).on("click", ".autocomplete-option", function () {
        $("#autocomplete_options ul li.selected").removeClass("selected");
        $(this).addClass("selected");
        let fieldName = $("#autocomplete_options").data("field_name");
        $("#" + fieldName).val($(this).text());
        $("#" + fieldName).data("last_autocomplete_data", $("#" + fieldName).val());
        $("#" + fieldName.replace("_autocomplete_text", "")).val($(this).data("id_value"));
        setTimeout(function () {
            $("#" + fieldName.replace("_autocomplete_text", "")).trigger("change");
        }, 200);
        $("#" + fieldName).focus();
    });
    $(document).on("keydown", ".autocomplete-field", function (event) {
        if (event.which === 33 || event.which === 34) {
            return true;
        }
        if ($(this).prop("readonly") && event.which !== 9 && (event.which < 16 || event.which > 18) && event.which !== 91) {
            return false;
        }
        if ($("#autocomplete_options").length === 0) {
            $("body").append("<div id='autocomplete_options'><ul></ul></div>");
        }
        if ($("#autocomplete_options").data("field_name") !== $(this).attr("id")) {
            $(this).data("last_autocomplete_data", "");
        }
        $("#autocomplete_options").data("field_name", $(this).attr("id"));

        if (event.which !== 9 && (event.which < 37 || event.which > 40) && (event.which < 16 || event.which > 18)) {
            $("#" + $(this).attr("id").replace("_autocomplete_text", "")).val("");
            const elementId = $(this).attr("id").replace("_autocomplete_text", "");
            setTimeout(function () {
                $("#" + elementId).trigger("change");
            }, 200);
        }
        if (event.which === 40) {
            let currentIndex = $("#autocomplete_options ul li.selected").index();
            currentIndex++;
            if (currentIndex >= $("#autocomplete_options ul li").length) {
                currentIndex = 0;
            }
            $("#autocomplete_options ul li.selected").removeClass("selected");
            $("#autocomplete_options ul li:eq(" + currentIndex + ")").addClass("selected");
            showAutocompleteSelection();
            return false;
        } else if (event.which === 38) {
            let currentIndex = $("#autocomplete_options ul li.selected").index();
            currentIndex--;
            if (currentIndex < 0) {
                currentIndex = $("#autocomplete_options ul li").length - 1;
            }
            $("#autocomplete_options ul li.selected").removeClass("selected");
            $("#autocomplete_options ul li:eq(" + currentIndex + ")").addClass("selected");
            showAutocompleteSelection();
            return false;
        } else if (event.which === 13 || event.which === 3 || event.which === 9) {
            if ($("#autocomplete_options ul li.selected").length > 0) {
                let fieldName = $("#autocomplete_options").data("field_name");
                $("#" + fieldName).val($("#autocomplete_options ul li.selected").text());
                $("#" + fieldName.replace("_autocomplete_text", "")).val($("#autocomplete_options ul li.selected").data("id_value"));
                $("#autocomplete_options ul li.selected").removeClass("selected");
                setTimeout(function () {
                    $("#" + fieldName.replace("_autocomplete_text", "")).trigger("change");
                }, 200);
                $("#" + fieldName).data("last_autocomplete_data", $("#" + fieldName).val());
                setTimeout(function () {
                    $("#autocomplete_options").hide();
                }, 200);
            }
            return event.which === 9;
        }
        return true;
    }).on("blur", ".autocomplete-field", function () {
        if ($(this).prop("readonly")) {
            return false;
        }
        let fieldName = $(this).attr("id");
        setTimeout(function () {
            $("#autocomplete_options").hide();
        }, 200);
        if (empty($("#" + fieldName.replace("_autocomplete_text", "")).val())) {
            $("#" + fieldName.replace("_autocomplete_text", "")).val($("#autocomplete_options ul li").filter(function () {
                if ($(this).text().toLowerCase() === $("#" + fieldName).val().toLowerCase()) {
                    $("#" + fieldName).val($(this).text());
                    return true;
                } else {
                    return false;
                }
            }).data("id_value"));
            setTimeout(function () {
                $("#" + fieldName.replace("_autocomplete_text", "")).trigger("change");
            }, 200);
        }
        if (empty($("#" + fieldName.replace("_autocomplete_text", "")).val())) {
            $("#" + fieldName).data("last_autocomplete_data", "");
            $("#" + fieldName).val("");
        }
    }).on("focus", ".autocomplete-field", function () {
        if ($("#autocomplete_options").length === 0) {
            $("body").append("<div id='autocomplete_options'><ul></ul></div>");
        }
        if ($(this).prop("readonly")) {
            return false;
        }
        if ($("#autocomplete_options").data("field_name") !== $(this).attr("id")) {
            $(this).data("last_autocomplete_data", "");
        }
        $("#autocomplete_options").data("field_name", $(this).attr("id"));
    }).on("keyup", ".autocomplete-field", function (event) {
        if (event.which === 33 || event.which === 34) {
            return true;
        }
        if ($(this).prop("readonly") && event.which !== 9 && (event.which < 16 || event.which > 18) && event.which !== 91) {
            return false;
        }
        if (event.which !== 9 && (event.which < 37 || event.which > 40) && (event.which < 16 || event.which > 18) && event.which !== 13 && event.which !== 3) {
            let thisValue = $(this).val();
            if (getAutocompleteTimer != null) {
                clearTimeout(getAutocompleteTimer);
            }
            let thisFieldName = $(this).attr("id");
            if (thisValue.length >= 2) {
                getAutocompleteTimer = setTimeout(function () {
                    getAutocompleteData(thisFieldName);
                }, 400);
            } else {
                $("#autocomplete_options ul li.selected").removeClass("selected");
                setTimeout(function () {
                    $("#autocomplete_options").hide();
                }, 200);
            }
        }
    }).on("get_autocomplete_text", ".autocomplete-field", function () {
        let thisFieldName = $(this).attr("id");
        let thisValueId = $("#" + thisFieldName.replace("_autocomplete_text", "")).val();
        let additionalFilter = $("#" + thisFieldName).data("additional_filter");
        let additionalFilterFunction = $("#" + thisFieldName).data("additional_filter_function");
        if (additionalFilterFunction !== undefined && typeof window[additionalFilterFunction] == "function") {
            additionalFilter = window[additionalFilterFunction](thisFieldName.replace("_autocomplete_text", ""));
        }
        if (additionalFilter === undefined) {
            additionalFilter = "";
        }
        if ($("#autocomplete_options").length === 0) {
            $("body").append("<div id='autocomplete_options'><ul></ul></div>");
        }
        if (empty(thisValueId)) {
            $("#" + thisFieldName).val("");
        } else {
            $("body").addClass("no-waiting-for-ajax");
            loadAjaxRequest("getautocompletedata.php?ajax=true&tag=" + $("#" + thisFieldName).data("autocomplete_tag") + "&value_id=" + encodeURIComponent(thisValueId) + "&get_value=true" + "&additional_filter=" + encodeURIComponent(additionalFilter), function (returnArray) {
                if ("autocomplete_text" in returnArray) {
                    $("#" + thisFieldName).val(returnArray['autocomplete_text']);
                    $("#" + thisFieldName).data("last_autocomplete_data", $("#" + thisFieldName).val());
                }
            });
        }
    });

    $("#_subscribe_form_button").click(function (e) {
        e.preventDefault();
        if ($("#_subscribe_form").length === 0 || $("#signup_email_address").length === 0) {
            return;
        }
        const url = scriptFilename + "?ajax=true&url_action=newsletter_signup";
        if ($("#_subscribe_form").validationEngine("validate")) {
            loadAjaxRequest(url, $("#_subscribe_form").serialize(), function (returnArray) {
                if (!("error_message" in returnArray)) {
                    let subscribeResponse = ("subscribe_response" in returnArray && !empty(returnArray['subscribe_response']) ? returnArray['subscribe_response'] : "<p id='_subscribe_response'>You're signed up</p>");
                    $("#_subscribe_form_wrapper").html(subscribeResponse);
                }
                if (typeof zaius !== 'undefined' && !empty(zaius)) {
                    let emailAddress = $("#signup_email_address").val();
                    let phoneNumber = $("#signup_phone_number").val();
                    if (typeof phoneNumber !== 'undefined' && !empty(phoneNumber)) {
                        zaius.subscribe({ list_id: 'newsletter', email: emailAddress, phone: phoneNumber });
                    } else {
                        zaius.subscribe({ list_id: 'newsletter', email: emailAddress });
                    }
                    // Zaius requires an explicit consent event in the case that a customer had previously unsubscribed and now wants to resubscribe.
                    zaius.event('consent', { action: 'opt-in', email: emailAddress });
                }
            });
        }
    });
});

$(window).on("load", function () {
    if ($(".equal-height-blocks").length > 0) {
        setTimeout(function () {
            equalizeBlockHeights();
        }, 1000);
        $(window).on("resize", function () {
            clearTimeout(equalizerTimer);
            equalizerTimer = setTimeout(function () {
                equalizeBlockHeights();
            }, 500);
        });
    }
})

$(window).on("load", function () {
    if ($(".equal-width-blocks").length > 0) {
        setTimeout(function () {
            equalizeBlockWidths();
        }, 1000);
        $(window).on("resize", function () {
            clearTimeout(equalizerTimer);
            equalizerTimer = setTimeout(function () {
                equalizeBlockWidths();
            }, 500);
        });
    }
})

$(window).on("load", function () {
    setTimeout(function () {
        $(".defer-load").each(function () {
            const imageSource = $(this).data("image_source");
            if (!empty(imageSource)) {
                $(this).attr("src", imageSource);
            }
            const backgroundImage = $(this).data("background_image");
            if (!empty(backgroundImage)) {
                $(this).css("background-image", "url('" + backgroundImage + "')");
            }
        });
    }, 1000);
})

$.fn.redraw = function () {
    $(this).each(function () {
        let redraw = this.offsetHeight;
    });
};

$.fn.clearForm = function () {
    return this.each(function () {
        if ($(this).hasClass("no-clear")) {
            return true;
        }
        const type = this.type, tag = this.tagName.toLowerCase();
        if (tag === 'form')
            return $(':input', this).clearForm();
        if (type === 'file' || type === 'text' || type === 'password' || tag === 'textarea' || type === "hidden")
            $(this).val("");
        else if (type === 'checkbox' || type === 'radio')
            this.checked = false;
        else if (tag === 'select') {
            if ($(this).find("option[value='']").length > 0) {
                this.value = "";
            } else {
                this.selectedIndex = -1;
            }
        }
    });
};

// Swap two elements

$.fn.swapWith = function (to) {
    return this.each(function () {
        let copy_to = $(to).clone(true);
        $(to).replaceWith(this);
        $(this).replaceWith(copy_to);
    });
};

// Create the format Date function

(function ($) {
    $.formatDate = function (date, pattern) {
        let year = null, month = null, day = null;
        if (typeof (date) == 'string') {
            let dateParts = date.split("/");
            if (dateParts.length === 3) {
                month = dateParts[0];
                day = dateParts[1];
                year = dateParts[2];
            } else {
                let dateParts = date.split("-");
                if (dateParts.length === 3) {
                    year = dateParts[0];
                    month = dateParts[1];
                    day = dateParts[2];
                }
            }
            if (year < 100) {
                year += 2000;
            }
            date = new Date(year, month - 1, day);
        }
        let result = [];
        while (pattern.length > 0) {
            $.formatDate.patternParts.lastIndex = 0;
            let matched = $.formatDate.patternParts.exec(pattern);
            if (matched) {
                result.push($.formatDate.patternValue[matched[0]].call(this, date));
                pattern = pattern.slice(matched[0].length);
            } else {
                result.push(pattern.charAt(0));
                pattern = pattern.slice(1);
            }
        }
        return result.join('');
    };
    $.formatDate.patternParts =
        /^(yy(yy)?|M(M(M(M)?)?)?|d(d)?|EEE(E)?|a|H(H)?|h(h)?|m(m)?|s(s)?|S)/;
    $.formatDate.monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June', 'July',
        'August', 'September', 'October', 'November', 'December'
    ];
    $.formatDate.dayNames = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
    ];
    $.formatDate.patternValue = {
        yy: function (date) {
            return $.toFixedWidth(date.getFullYear(), 2);
        },
        yyyy: function (date) {
            return date.getFullYear().toString();
        },
        MMMM: function (date) {
            return $.formatDate.monthNames[date.getMonth()];
        },
        MMM: function (date) {
            return $.formatDate.monthNames[date.getMonth()].substr(0, 3);
        },
        MM: function (date) {
            return $.toFixedWidth(date.getMonth() + 1, 2);
        },
        M: function (date) {
            return date.getMonth() + 1;
        },
        dd: function (date) {
            return $.toFixedWidth(date.getDate(), 2);
        },
        d: function (date) {
            return date.getDate();
        },
        EEEE: function (date) {
            return $.formatDate.dayNames[date.getDay()];
        },
        EEE: function (date) {
            return $.formatDate.dayNames[date.getDay()].substr(0, 3);
        },
        HH: function (date) {
            return $.toFixedWidth(date.getHours(), 2);
        },
        H: function (date) {
            return date.getHours();
        },
        hh: function (date) {
            let hours = date.getHours();
            return $.toFixedWidth(hours > 12 ? hours - 12 : hours, 2);
        },
        h: function (date) {
            let hours = date.getHours();
            return (hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours));
        },
        mm: function (date) {
            return $.toFixedWidth(date.getMinutes(), 2);
        },
        m: function (date) {
            return date.getMinutes();
        },
        ss: function (date) {
            return $.toFixedWidth(date.getSeconds(), 2);
        },
        s: function (date) {
            return date.getSeconds();
        },
        S: function (date) {
            return $.toFixedWidth(date.getMilliseconds(), 3);
        },
        a: function (date) {
            let hours = date.getHours();
            let minutes = date.getMinutes();
            return (hours === 0 && minutes === 0 ? "midnight" : (hours === 12 && minutes === 0 ? "noon" : hours < 12 ? 'am' : 'pm'));
        }
    };
})(jQuery);

// Function to go to a link. This is typically used when there is a possibility that changes have been made on the current page.

function goToLink(callingElement, linkUrl, separateWindow) {
    if (linkUrl === undefined && separateWindow === undefined) {
        linkUrl = callingElement;
        callingElement = null;
    }
    if (empty(separateWindow)) {
        separateWindow = false;
    }
    if (empty(linkUrl)) {
        return false;
    }
    try {
        if (separateWindow) {
            window.open(linkUrl);
        } else {
            if ("changesMade" in window && changesMade()) {
                askAboutChanges(function () {
                    $("#_edit_form").clearForm();
                    $('body').data('just_saved', 'true');
                    document.location = linkUrl;
                });
            } else {
                document.location = linkUrl;
            }
        }
    } catch (e) {
        document.location = linkUrl;
    }
    return false;
}

// Dynamically add a CSS rule to the loaded style sheets

function addCSSRule(sel, prop, val) {
    for (let i = 0; i < document.styleSheets.length; i++) {
        let ss = document.styleSheets[i];
        let rules = (ss.cssRules || ss.rules);
        let lsel = sel.toLowerCase();

        for (let i2 = 0, len = rules.length; i2 < len; i2++) {
            if (rules[i2].selectorText && (rules[i2].selectorText.toLowerCase() === lsel)) {
                if (val != null) {
                    let useProp = prop;
                    if (useProp in rules[i2].style) {
                        rules[i2].style[useProp] = val;
                    } else {
                        useProp = prop.replace(/(-.)/g, function (x) {
                            return x[1].toUpperCase()
                        });
                        if (useProp in rules[i2].style) {
                            rules[i2].style[useProp] = val;
                        }
                    }
                    return;
                } else {
                    if (ss.deleteRule) {
                        ss.deleteRule(i2);
                    } else if (ss.removeRule) {
                        ss.removeRule(i2);
                    } else {
                        rules[i2].style.cssText = '';
                    }
                }
            }
        }
    }

    const firstSS = document.styleSheets[0] || {};
    if (firstSS.insertRule) {
        let rules = (firstSS.cssRules || firstSS.rules);
        firstSS.insertRule(sel + '{ ' + prop + ':' + val + '; }', rules.length);
    } else if (firstSS.addRule) {
        firstSS.addRule(sel, prop + ':' + val + ';', 0);
    }
}

// Display Error message to an element with ID _error_message. Clear in 5 seconds.

var clearMessageTimer = null;
var errorMessageQueue = [];
var lastErrorMessageText = "";
var lastInfoMessageText = "";

function displayErrorMessage(messageText, clearMessageAfter) {
    console.log(messageText);
    if ("thisIsAPublicWebsite" in window && thisIsAPublicWebsite) {
        if (messageText.toLowerCase().indexOf("not respond") > 0) {
            return;
        }
    }
    if (clearMessageAfter == null) {
        clearMessageAfter = true;
    }
    if (empty(messageText) || typeof messageText != 'string') {
        clearMessage();
        return;
    }
    lastErrorMessageText = messageText;
    $("#_show_last_error").removeClass("hidden");
    if (!empty($("#_error_message").add(".error-message").not(".persistent").val()) || clearMessageTimer != null) {
        errorMessageQueue.push(messageText);
        return;
    }
    if ($("#_error_message").add(".error-message").length > 0) {
        $("#_error_message").add(".error-message").removeClass("info-message").html(messageText).addClass('error-visible').show();
    }
    if (clearMessageTimer != null) {
        clearTimeout(clearMessageTimer);
    }
    clearMessageTimer = null;
    if (clearMessageAfter) {
        let seconds = 5;
        if (typeof secondsToClearMessage !== 'undefined') {
            seconds = secondsToClearMessage;
        }
        if (isNaN(seconds) || seconds <= 0) {
            seconds = 5;
        }
        clearMessageTimer = setTimeout("clearMessage()", seconds * 1000);
    }
}

// Display info message to an element with ID _error_message. Clear in 10 seconds.

function displayInfoMessage(messageText, clearMessageAfter) {
    if (clearMessageAfter == null) {
        clearMessageAfter = true;
    }
    if (empty(messageText) || typeof messageText != 'string') {
        clearMessage();
        return;
    }
    lastInfoMessageText = messageText;
    $("#_show_last_error").removeClass("hidden").addClass("info-message");
    $("#_error_message").add(".error-message").addClass("info-message").html(messageText).addClass('error-visible').show();
    if (clearMessageAfter) {
        let seconds = 5;
        if (typeof secondsToClearMessage !== 'undefined') {
            seconds = secondsToClearMessage;
        }
        if (isNaN(seconds) || seconds <= 0) {
            seconds = 5;
        }
        clearMessageTimer = setTimeout("clearMessage()", seconds * 1000);
    } else if (clearMessageTimer != null) {
        clearTimeout(clearMessageTimer);
        clearMessageTimer = null;
    }
}

// Clear message

function clearMessage() {
    if (clearMessageTimer != null) {
        clearTimeout(clearMessageTimer);
    }
    clearMessageTimer = null;
    if (errorMessageQueue.length > 0) {
        displayErrorMessage(errorMessageQueue.shift());
    } else {
        $("#_error_message").add(".error-message").not(".persistent").removeClass('error-visible').html("");
    }
}

// Code to create CRC value

var Crc32Tab = [ 0x00000000, 0x77073096, 0xEE0E612C, 0x990951BA, 0x076DC419, 0x706AF48F, 0xE963A535, 0x9E6495A3,
    0x0EDB8832, 0x79DCB8A4, 0xE0D5E91E, 0x97D2D988, 0x09B64C2B, 0x7EB17CBD, 0xE7B82D07, 0x90BF1D91,
    0x1DB71064, 0x6AB020F2, 0xF3B97148, 0x84BE41DE, 0x1ADAD47D, 0x6DDDE4EB, 0xF4D4B551, 0x83D385C7,
    0x136C9856, 0x646BA8C0, 0xFD62F97A, 0x8A65C9EC, 0x14015C4F, 0x63066CD9, 0xFA0F3D63, 0x8D080DF5,
    0x3B6E20C8, 0x4C69105E, 0xD56041E4, 0xA2677172, 0x3C03E4D1, 0x4B04D447, 0xD20D85FD, 0xA50AB56B,
    0x35B5A8FA, 0x42B2986C, 0xDBBBC9D6, 0xACBCF940, 0x32D86CE3, 0x45DF5C75, 0xDCD60DCF, 0xABD13D59,
    0x26D930AC, 0x51DE003A, 0xC8D75180, 0xBFD06116, 0x21B4F4B5, 0x56B3C423, 0xCFBA9599, 0xB8BDA50F,
    0x2802B89E, 0x5F058808, 0xC60CD9B2, 0xB10BE924, 0x2F6F7C87, 0x58684C11, 0xC1611DAB, 0xB6662D3D,
    0x76DC4190, 0x01DB7106, 0x98D220BC, 0xEFD5102A, 0x71B18589, 0x06B6B51F, 0x9FBFE4A5, 0xE8B8D433,
    0x7807C9A2, 0x0F00F934, 0x9609A88E, 0xE10E9818, 0x7F6A0DBB, 0x086D3D2D, 0x91646C97, 0xE6635C01,
    0x6B6B51F4, 0x1C6C6162, 0x856530D8, 0xF262004E, 0x6C0695ED, 0x1B01A57B, 0x8208F4C1, 0xF50FC457,
    0x65B0D9C6, 0x12B7E950, 0x8BBEB8EA, 0xFCB9887C, 0x62DD1DDF, 0x15DA2D49, 0x8CD37CF3, 0xFBD44C65,
    0x4DB26158, 0x3AB551CE, 0xA3BC0074, 0xD4BB30E2, 0x4ADFA541, 0x3DD895D7, 0xA4D1C46D, 0xD3D6F4FB,
    0x4369E96A, 0x346ED9FC, 0xAD678846, 0xDA60B8D0, 0x44042D73, 0x33031DE5, 0xAA0A4C5F, 0xDD0D7CC9,
    0x5005713C, 0x270241AA, 0xBE0B1010, 0xC90C2086, 0x5768B525, 0x206F85B3, 0xB966D409, 0xCE61E49F,
    0x5EDEF90E, 0x29D9C998, 0xB0D09822, 0xC7D7A8B4, 0x59B33D17, 0x2EB40D81, 0xB7BD5C3B, 0xC0BA6CAD,
    0xEDB88320, 0x9ABFB3B6, 0x03B6E20C, 0x74B1D29A, 0xEAD54739, 0x9DD277AF, 0x04DB2615, 0x73DC1683,
    0xE3630B12, 0x94643B84, 0x0D6D6A3E, 0x7A6A5AA8, 0xE40ECF0B, 0x9309FF9D, 0x0A00AE27, 0x7D079EB1,
    0xF00F9344, 0x8708A3D2, 0x1E01F268, 0x6906C2FE, 0xF762575D, 0x806567CB, 0x196C3671, 0x6E6B06E7,
    0xFED41B76, 0x89D32BE0, 0x10DA7A5A, 0x67DD4ACC, 0xF9B9DF6F, 0x8EBEEFF9, 0x17B7BE43, 0x60B08ED5,
    0xD6D6A3E8, 0xA1D1937E, 0x38D8C2C4, 0x4FDFF252, 0xD1BB67F1, 0xA6BC5767, 0x3FB506DD, 0x48B2364B,
    0xD80D2BDA, 0xAF0A1B4C, 0x36034AF6, 0x41047A60, 0xDF60EFC3, 0xA867DF55, 0x316E8EEF, 0x4669BE79,
    0xCB61B38C, 0xBC66831A, 0x256FD2A0, 0x5268E236, 0xCC0C7795, 0xBB0B4703, 0x220216B9, 0x5505262F,
    0xC5BA3BBE, 0xB2BD0B28, 0x2BB45A92, 0x5CB36A04, 0xC2D7FFA7, 0xB5D0CF31, 0x2CD99E8B, 0x5BDEAE1D,
    0x9B64C2B0, 0xEC63F226, 0x756AA39C, 0x026D930A, 0x9C0906A9, 0xEB0E363F, 0x72076785, 0x05005713,
    0x95BF4A82, 0xE2B87A14, 0x7BB12BAE, 0x0CB61B38, 0x92D28E9B, 0xE5D5BE0D, 0x7CDCEFB7, 0x0BDBDF21,
    0x86D3D2D4, 0xF1D4E242, 0x68DDB3F8, 0x1FDA836E, 0x81BE16CD, 0xF6B9265B, 0x6FB077E1, 0x18B74777,
    0x88085AE6, 0xFF0F6A70, 0x66063BCA, 0x11010B5C, 0x8F659EFF, 0xF862AE69, 0x616BFFD3, 0x166CCF45,
    0xA00AE278, 0xD70DD2EE, 0x4E048354, 0x3903B3C2, 0xA7672661, 0xD06016F7, 0x4969474D, 0x3E6E77DB,
    0xAED16A4A, 0xD9D65ADC, 0x40DF0B66, 0x37D83BF0, 0xA9BCAE53, 0xDEBB9EC5, 0x47B2CF7F, 0x30B5FFE9,
    0xBDBDF21C, 0xCABAC28A, 0x53B39330, 0x24B4A3A6, 0xBAD03605, 0xCDD70693, 0x54DE5729, 0x23D967BF,
    0xB3667A2E, 0xC4614AB8, 0x5D681B02, 0x2A6F2B94, 0xB40BBE37, 0xC30C8EA1, 0x5A05DF1B, 0x2D02EF8D ];

function Crc32Add(crc, c) {
    return Crc32Tab[(crc ^ c) & 0xFF] ^ ((crc >> 8) & 0xFFFFFF);
}

function Crc32Str(str) {
    if (str === undefined) {
        str = "";
    }
    if (typeof (str) == "object") {
        str = str.join(",");
    }
    let n;
    let len = str.length;
    let crc;

    crc = 0xFFFFFFFF;
    for (n = 0; n < len; n++) {
        let code = str.charCodeAt(n);
        if (code === 13 && n < (len - 1) && str.charCodeAt(n + 1) === 10) {
            continue;
        }
        if (code > 122) {
            continue;
        }
        if (code < 32) {
            code = 32;
        }
        crc = Crc32Add(crc, code);
    }
    return crc ^ 0xFFFFFFFF;
}

function Hex32(val) {
    let n;
    let str1;
    let str2;

    n = val & 0xFFFF;
    str1 = n.toString(16).toUpperCase();
    while (str1.length < 4) {
        str1 = "0" + str1;
    }
    n = (val >>> 16) & 0xFFFF;
    str2 = n.toString(16).toUpperCase();
    while (str2.length < 4) {
        str2 = "0" + str2;
    }
    return str2 + str1;
}

function getCrcValue(text, noHash) {
    if (empty(noHash)) {
        noHash = false;
    }
    return (noHash ? "" : "#") + Hex32(Crc32Str(text + ""));
}

// turn a value to fixed width

$.toFixedWidth = function (value, length, fill) {
    let result = (value || '').toString();
    fill = fill || '0';
    let padding = length - result.length;
    if (padding < 0) {
        result = result.substr(-padding);
    } else {
        for (let n = 0; n < padding; n++)
            result = fill + result;
    }
    return result;
};

// Add a number of days to a date value

function addDays(dateValue, numberDays) {
    let oneDay = 3600 * 1000 * 24
    let thisDate = false;
    if (dateValue.length === 0) {
        return $.formatDate(new Date(), "MM/dd/yyyy");
    }
    try {
        thisDate = Date.parse(dateValue);
    } catch (err) {
        return "";
    }
    let msTime = thisDate + (oneDay * numberDays) + 3600001;
    if (isNaN(msTime)) {
        return "";
    }
    return $.formatDate(new Date(msTime), "MM/dd/yyyy");
}

// Round a number

function Round(Number, DecimalPlaces) {
    return Math.round(parseFloat((Number + "").replace(/,/g, '')) * Math.pow(10, DecimalPlaces)) / Math.pow(10, DecimalPlaces);
}

// Round a number and give it a fixed number of decimal places and add commas

function RoundFixed(Number, DecimalPlaces, noCommas) {
    if (empty(noCommas)) {
        noCommas = false;
    }
    if (noCommas) {
        return Round(Number, DecimalPlaces).toFixed(DecimalPlaces);
    } else {
        return addCommas(Round(Number, DecimalPlaces).toFixed(DecimalPlaces));
    }
}

// Add commas to a number

function addCommas(val) {
    let parts = val.toString().split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join(".");
}

// Process a value returned from an ajax call

function processReturn(returnText) {
    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
    let returnArray = {};
    try {
        returnArray = JSON.parse(returnText);
    } catch (e) {
        if ("displayErrors" in window && "showError" in window && displayErrors) {
            showError(returnText);
            return false;
        }
    }
    if (!(returnArray instanceof Array) && !(returnArray instanceof Object)) {
        if ("displayErrors" in window && "showError" in window && displayErrors) {
            showError(returnText);
            return false;
        }
        returnArray = {};
    }
    if ("debug" in returnArray) {
        console.log(returnText);
    }
    if ("console" in returnArray) {
        console.log(returnArray['console']);
    }
    if ("error_message" in returnArray) {
        displayErrorMessage(returnArray['error_message']);
    }
    if ("info_message" in returnArray) {
        displayInfoMessage(returnArray['info_message']);
    }
    return returnArray;
}

// Extract a URL parameter from the URL and return the value

function getURLParameter(name) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search) || [ , "" ])[1].replace(/\+/g, '%20')) || null;
}

function getURLParameters(url) {
    let request = {};
    let pairs = url.substring(url.indexOf('?') + 1).split('&');
    for (let i = 0; i < pairs.length; i++) {
        if (!pairs[i]) {
            continue;
        }
        let pair = pairs[i].split('=');
        request[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
    }
    return request;
}

// Validate a postal code and set the city and state

function validatePostalCode(postalCodeField) {
    if (empty(postalCodeField)) {
        postalCodeField = "postal_code";
    }
    let stateField = $("#" + postalCodeField).data("state_field");
    if (empty(stateField)) {
        stateField = "state";
    }
    let cityField = $("#" + postalCodeField).data("city_field");
    if (empty(cityField)) {
        cityField = "city";
    }
    let citySelectField = $("#" + postalCodeField).data("city_select_field");
    if (empty(citySelectField)) {
        citySelectField = cityField + "_select";
    }
    let cityHide = $("#" + postalCodeField).data("city_hide");
    if (empty(cityHide)) {
        cityHide = "_" + cityField + "_row";
    }
    let citySelectHide = $("#" + postalCodeField).data("city_select_hide");
    if (empty(citySelectHide)) {
        citySelectHide = "_" + cityField + "_select_row";
    }
    if (empty($("#" + postalCodeField).val())) {
        return;
    }
    loadAjaxRequest("validatepostalcode.php?ajax=true&postal_code=" + $("#" + postalCodeField).val(), function (returnArray) {
        let cityCount = 0;
        let currentCity = $("#" + cityField).val();
        $("#" + citySelectField + " option").remove();
        for (let x in returnArray['cities']) {
            cityCount++;
            $("#" + citySelectField).append($("<option></option>", {
                value: returnArray['cities'][x]['city'],
                text: returnArray['cities'][x]['city']
            }).data("state", returnArray['cities'][x]['state']));
        }
        $("#" + citySelectHide).hide();
        $("#" + cityHide).show();
        if (cityCount === 0) {
            $("#" + cityField).val("");
            $("#" + stateField).val("");
            $("#" + postalCodeField).val("").focus();
        } else if (cityCount === 1) {
            $("#" + cityField).val($("#" + citySelectField).val());
            $("#" + stateField).val($("#" + citySelectField + " option:selected").data("state"));
        } else {
            $("#" + citySelectHide).show();
            $("#" + cityHide).hide();
            $("#" + citySelectField).val(currentCity);
            if ($("#" + citySelectField).val() !== currentCity || $("#" + citySelectField).val() == null) {
                $("#" + citySelectField).val($("#" + citySelectField + " option:first").val());
                $("#" + cityField).val($("#" + citySelectField).val());
                $("#" + citySelectField).focus();
            }
            $("#" + stateField).val($("#" + citySelectField + " option:selected").data("state"));
        }
    });
}

// Check password strength

var commonPasswords = [ 'password', 'pass', '1234', '1246', 'asdf', 'qwerty', '123456', '12345678', '12345', 'dragon', 'pussy', 'baseball', 'football', 'letmein', 'monkey', '696969', 'abc123', 'mustang', 'michael', 'shadow', 'master', 'jennifer', '111111' ];

var minimumLength = 6;
var recommendedLength = 8;
var numbers = "0123456789";
var lowercase = "abcdefghijklmnopqrstuvwxyz";
var uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
var punctuation = "!@#$%^&*()_+=-{}[]|\:';,./?><`~";
var strength_label = Array('Too common or repetitive', 'Weak', 'Fair', 'Medium', 'Strong', 'Very Strong');
var strength_color = Array('FF0000', 'FF9900', 'FFCC33', '99CC99', 'ffd801', '3bce08');

function checkPasswordStrength(passwordField) {
    let password = $("#" + passwordField).val();
    let strengthPoints = 0;
    let strengthIndex = 0;
    if (password.length === 0) {
        return strengthIndex;
    }

    let username = "";
    if ($("#user_name").length > 0) {
        username = $("#user_name").val();
    }
    if (password.length >= username.length && password.indexOf(username) === 0) {
        password = password.substring(username.length);
    }
    for (let i = 0; i < commonPasswords.length; i++) {
        if (password.length >= commonPasswords[i].length && password.indexOf(commonPasswords[i]) === 0) {
            password = password.substring(commonPasswords[i].length);
        }
    }
    strengthPoints += password.length;
    strengthPoints += (contains(password, numbers) > 0 ? 30 : 0);
    strengthPoints += (contains(password, lowercase) > 0 ? 10 : 0);
    strengthPoints += (contains(password, uppercase) > 0 ? 20 : 0);
    strengthPoints += (contains(password, punctuation) > 0 ? 40 : 0);
    if (password.length > 2) {
        strengthPoints += (contains(password.substring(1, password.length - 1), punctuation) > 0 ? 20 : 0);
    }
    if (password.length > 2) {
        strengthPoints += (contains(password.substring(1, password.length - 1), numbers) > 0 ? 20 : 0);
    }
    if (password.length >= recommendedLength) {
        strengthPoints += 10;
    } else if (password.length >= minimumLength) {
        strengthPoints += 5;
    } else {
        strengthPoints = 0;
    }
    if (isCommonPassword(password)) {
        strengthPoints = 0;
    }
    if (contains(password, numbers + lowercase + uppercase + punctuation) < 3) {
        strengthPoints = 0;
    }

    let percentage = strengthPoints / 100;
    let friendlyPercentage = Math.max(1, Math.min(Math.round(percentage * 100), 100));

    $("#" + passwordField + "_strength_bar_div").removeClass("hidden");
    $("#" + passwordField + "_strength_bar").css("width", friendlyPercentage + "%");

    if (percentage > .85) {
        strengthIndex = 5;
    } else if (percentage > .7) {
        strengthIndex = 4;
    } else if (percentage > .55) {
        strengthIndex = 3;
    } else if (percentage > .4) {
        strengthIndex = 2;
    } else if (percentage > .25) {
        strengthIndex = 1;
    } else {
        strengthIndex = 0;
    }
    $("#" + passwordField + "_strength_bar").css("background-color", "#" + strength_color[strengthIndex]);
    $("#" + passwordField + "_strength_bar_label").html(strength_label[strengthIndex]);
    return strengthIndex;
}

function isCommonPassword(password) {
    for (i = 0; i < commonPasswords.length; i++) {
        let commonPassword = commonPasswords[i];
        if (password === commonPassword) {
            return true;
        }
    }
    return false;
}

function contains(password, validChars) {
    count = 0;
    let usedChars = "";
    for (i = 0; i < password.length; i++) {
        let thisChar = password.charAt(i);
        if (validChars.indexOf(thisChar) > -1 && usedChars.indexOf(thisChar) === -1) {
            count++;
            usedChars += thisChar;
        }
    }
    return count;
}

// Scroll so that an element comes into view

function scrollInView(element, fromTop) {
    if (empty(fromTop)) {
        fromTop = 0;
    }
    $('html,body').animate({ scrollTop: (element.offset().top + fromTop) }, 750);
    return false;
}

// Check to see if any part of an element is inside viewport

function isScrolledIntoView(element) {
    let elementTop = element.offset().top;
    let elementBottom = elementTop + element.outerHeight();

    let viewportTop = $(window).scrollTop();
    let viewportBottom = viewportTop + $(window).height();

    return elementBottom > viewportTop && elementTop < viewportBottom;
}

// Check to see if an element is completely inside viewport

function isInsideViewPort(element) {
    let elementTop = element.offset().top;
    let elementBottom = elementTop + element.outerHeight();

    let viewportTop = $(window).scrollTop();
    let viewportBottom = viewportTop + $(window).height();

    return elementBottom < viewportBottom && elementTop > viewportTop;
}

// Check to see if an element is completely outside viewport

function isOutsideViewPort(element) {
    let elementTop = element.offset().top;
    let elementBottom = elementTop + element.outerHeight();

    let viewportTop = $(window).scrollTop();
    let viewportBottom = viewportTop + $(window).height();

    return elementBottom < viewportTop || elementTop > viewportBottom;
}

// check if an email address is valid

function isValidEmailAddress(emailAddress) {
    let pattern = new RegExp(/^(("[\w-+\s]+")|([\w-+]+(?:\.[\w-+]+)*)|("[\w-+\s]+")([\w-+]+(?:\.[\w-+]+)*))(@((?:[\w-+]+\.)*\w[\w-+]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);
    return pattern.test(emailAddress);
}

function makeId(stringLength) {
    if (empty(stringLength) || isNaN(stringLength)) {
        stringLength = 5;
    }
    let text = "";
    let possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    for (let i = 0; i <= stringLength; i++) {
        text += possible.charAt(Math.floor(Math.random() * possible.length));
    }
    return text;
}

function makeCode(string, parameters) {
    if (empty(parameters)) {
        parameters = {};
    }
    if (!string) {
        return "";
    }
    string = string.toLowerCase();
    do {
        string = string.replaceAll("  ", " ");
    } while (string.indexOf("  ") >= 0);

    if (parameters.useDash) {
        string = string.replaceAll(" ", "-");
        string = string.replaceAll("_", "-");
        string = string.replaceAll("/", "-");
    } else {
        string = string.replaceAll(" ", "_");
        if (!parameters.allowDash) {
            string = string.replaceAll("-", "_");
        }
    }
    do {
        string = string.replaceAll("--", "-");
        string = string.replaceAll("__", "_");
    } while (string.indexOf("--") >= 0 || string.indexOf("__") >= 0);

    let codeValue = "";
    for (let charIndex = 0; charIndex < string.length; charIndex++) {
        let thisChar = string.charAt(charIndex);
        if ("abcdefghijklmnopqrstuvwxyz01234567890@_.-".indexOf(thisChar) >= 0) {
            codeValue += thisChar;
        }
    }
    if (!parameters.lowercase) {
        codeValue = codeValue.toUpperCase();
    }
    return codeValue;
}

function isInArray(value, thisArray, caseSensitive) {
    if (empty(value)) {
        return false;
    }
    if (typeof thisArray === "string") {
        thisArray = thisArray.split(",");
    }
    if (empty(caseSensitive)) {
        caseSensitive = false;
    }
    for (let i = 0; i < thisArray.length; i++) {
        if (caseSensitive) {
            if (thisArray[i] === value) {
                return true;
            }
        } else {
            if (String(thisArray[i]).toLowerCase() === String(value).toLowerCase()) {
                return true;
            }
        }
    }
    return false;
}

function createCookie(name, value, days) {
    let expires = "";
    if (days) {
        let date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}

function readCookie(name) {
    let nameEQ = name + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function eraseCookie(name) {
    createCookie(name, "", -1);
}

// Enable and disable buttons

function disableButtons(buttonElement) {
    if (empty(buttonElement)) {
        $(".page-button").addClass("disabled-button").prop("disabled", true);
    } else {
        $(buttonElement).addClass("disabled-button").prop("disabled", true);
    }
}

function enableButtons(buttonElement) {
    if (empty(buttonElement)) {
        $(".page-button.enabled-button").removeClass("disabled-button").prop("disabled", false);
    } else {
        $(buttonElement).removeClass("disabled-button").prop("disabled", false);
    }
}

function isDate(val) {
    if (val.charAt(2) !== "/" || val.charAt(5) !== "/") {
        return false;
    }
    let d = new Date(val);
    return !isNaN(d.valueOf());
}

function isTouchDevice() {
    return 'ontouchstart' in window || navigator.maxTouchPoints;
}

var lastMilliseconds = 0;

function getElapsedTime(description) {
    let now = new Date();
    let nowMilliseconds = now.getTime();
    if (lastMilliseconds > 0) {
        let elapsedTime = RoundFixed(((nowMilliseconds - lastMilliseconds) / 1000), 2);
        console.log(description + ": " + elapsedTime);
    }
    lastMilliseconds = nowMilliseconds;
}

function empty(mixedVariable) {
    if (typeof mixedVariable === undefined || mixedVariable === null || mixedVariable === undefined) {
        return true;
    }
    let key, i, len;
    let emptyValues = [ false, 0, '', '0', '0.0', '0.00', '0.000', '0.0000' ];
    if (typeof mixedVariable === "string") {
        mixedVariable = mixedVariable.trim();
    }
    for (i = 0, len = emptyValues.length; i < len; i++) {
        if (mixedVariable === emptyValues[i]) {
            return true;
        }
    }
    if (typeof mixedVariable === 'object') {
        for (key in mixedVariable) {
            if (mixedVariable.hasOwnProperty(key)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

function loadAjaxRequest(ajaxUrl, data, successCode, errorCode, hideWaitingModal) {
    if (empty(hideWaitingModal)) {
        hideWaitingModal = false;
    } else {
        hideWaitingModal = true;
    }
    let ajaxType = "POST";
    if (arguments.length == 1) {
        ajaxType = "GET";
    }
    if (typeof data === "function") {
        errorCode = successCode;
        successCode = data;
        data = [];
        ajaxType = "GET";
    }
    if (!(typeof successCode === "function")) {
        successCode = function (returnArray) {
        }
    }
    if (!(typeof errorCode === "function")) {
        errorCode = function (returnArray) {
        }
    }
    ajaxTimeout = (empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout);

    if (hideWaitingModal) {
        $("body").addClass("no-waiting-for-ajax");
    }
    $.ajax({
        url: ajaxUrl,
        type: ajaxType,
        data: data,
        timeout: ajaxTimeout,
        cache: false,
        headers: { "cache-control": "no-cache" },
        success: function (returnText) {
            let returnArray = processReturn(returnText);
            if (returnArray === false) {
                returnArray = { error_message: "Invalid response" };
            }
            if("analytics_event" in returnArray && window.sendAnalyticsEvent) {
                sendAnalyticsEvent(returnArray['analytics_event']);
            }
            successCode(returnArray);
            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
            if (("adminLoggedIn" in window) && adminLoggedIn) {
                displayErrorMessage("Server not responding. Please try again or contact support.");
            }
            errorCode({ error_message: "Invalid response" });
            console.log("ajax call timed out: " + ajaxUrl + ":" + ajaxTimeout);
        },
        dataType: "text",
    });
}

function loadAdminMenu(menuCode, elementId, options) {
    const cachedMenu = window.sessionStorage.getItem(loggedInUserId + ":" + menuCode + ":" + elementId + ":" + JSON.stringify(options));
    if (empty(cachedMenu)) {
        if ("scriptFilename" in window) {
            loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_admin_menu&menu_code=" + menuCode, options, function (returnArray) {
                window.sessionStorage.setItem(loggedInUserId + ":" + menuCode + ":" + elementId + ":" + JSON.stringify(options), JSON.stringify(returnArray));
                // console.log("userid - "+loggedInUserId+", menucode - "+menuCode+", element_id - "+elementId+", options - "+JSON.stringify(options)+", array - "+JSON.stringify(returnArray));
                // return;
                if ("menu" in returnArray) {
                    $("#" + elementId).html(returnArray['menu']);
                }
                if (typeof afterLoadAdminMenu === "function") {
                    afterLoadAdminMenu(menuCode, elementId);
                }
            }, function (returnArray) {
                setTimeout(function () {
                    loadAdminMenu(menuCode, elementId, options);
                }, 200);
            });
        }
    } else {
        const returnArray = JSON.parse(cachedMenu);
        if (returnArray === false) {
            return;
        }
        if ("menu" in returnArray) {
            $("#" + elementId).html(returnArray['menu']);
        }
        if (typeof afterLoadAdminMenu === "function") {
            afterLoadAdminMenu(menuCode, elementId);
        }
    }
}

var equalizerTimer;

function equalizeElementHeights($elementsToEqualize) {
    let blockHeight = 0;
    $elementsToEqualize.each(function () {
        if ($(this).outerHeight() > blockHeight) {
            blockHeight = $(this).outerHeight();
        }
    });
    $elementsToEqualize.css("height", blockHeight + "px");
}

function equalizeBlockHeights() {
    $(".blocks-being-equalized").removeClass("blocks-being-equalized");
    $(".equal-height-blocks").parent().closest("div").addClass("blocks-being-equalized");
    $(".blocks-being-equalized").each(function () {
        let blockHeight = 0;
        $(this).find(".equal-height-blocks").css("height", "auto");
        let minWidth = $(this).find(".equal-height-blocks").data("minimum_width");
        if (empty(minWidth)) {
            minWidth = 550;
        }
        if ($(window).width() > minWidth) {
            $(this).find(".equal-height-blocks").each(function () {
                if ($(this).outerHeight() > blockHeight) {
                    blockHeight = $(this).outerHeight();
                }
            });
            $(this).find(".equal-height-blocks").css("height", blockHeight + "px");
        }
        $(this).removeClass("blocks-being-equalized");
    });
}

function equalizeBlockWidths() {
    $(".blocks-being-equalized").removeClass("blocks-being-equalized");
    $(".equal-width-blocks").parent().closest("div").addClass("blocks-being-equalized");
    $(".blocks-being-equalized").each(function () {
        let blockWidth = 0;
        $(this).find(".equal-width-blocks").css("width", "auto");
        let minWidth = $(this).find(".equal-width-blocks").data("minimum_width");
        if (empty(minWidth)) {
            minWidth = 550;
        }
        if ($(window).width() > minWidth) {
            $(this).find(".equal-width-blocks").each(function () {
                if ($(this).outerWidth() > blockWidth) {
                    blockWidth = $(this).outerWidth();
                }
            });
            $(this).find(".equal-width-blocks").css("width", blockWidth + "px");
        }
        $(this).removeClass("blocks-being-equalized");
    });
}

var moveableBackgroundFollowX = 0, moveableBackgroundFollowY = 0, moveableBackgroundX = 0, moveableBackgroundY = 0,
    moveableBackgroundFriction = 1 / 30;

function moveBackground() {
    moveableBackgroundX += (moveableBackgroundFollowX - moveableBackgroundX) * moveableBackgroundFriction;
    moveableBackgroundY += (moveableBackgroundFollowY - moveableBackgroundY) * moveableBackgroundFriction;

    translate = 'translate(' + moveableBackgroundX + 'px, ' + moveableBackgroundY + 'px) scale(1.1)';

    $('.moveable-background').css({
        '-webit-transform': translate,
        '-moz-transform': translate,
        'transform': translate
    });

    window.requestAnimationFrame(moveBackground);
}

$(function () {
    if ($(".moveable-background").length > 0) {
        $(window).on('mousemove click', function (event) {
            let lMouseX = Math.max(-100, Math.min(100, $(window).width() / 2 - event.clientX));
            let lMouseY = Math.max(-100, Math.min(100, $(window).height() / 2 - event.clientY));
            moveableBackgroundFollowX = (20 * lMouseX) / 100;
            moveableBackgroundFollowY = (10 * lMouseY) / 100;
        });

        moveBackground();
    }
});

function logBannerImpression(bannerElement) {
    let bannerId = $(bannerElement).data('banner_id');
    let bannerImpressionLogId = $(bannerElement).data('banner_impression_log_id');
    if (!empty(bannerImpressionLogId)) {
        return;
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/index.php?ajax=true&url_action=log_banner_impression&banner_id=" + bannerId, function (returnArray) {
        if (!empty(bannerElement)) {
            $(bannerElement).data("banner_impression_log_id", returnArray['banner_impression_log_id']);
        }
    });
}

function logBannerClick(bannerElement, afterFunction) {
    let bannerId = $(bannerElement).data('banner_id');
    let bannerImpressionLogId = $(bannerElement).data('banner_impression_log_id');
    if (empty(bannerId)) {
        if (typeof afterFunction == "function") {
            afterFunction();
        }
        return false;
    }
    if (empty(bannerImpressionLogId)) {
        bannerImpressionLogId = "";
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/index.php?ajax=true&url_action=log_banner_click&banner_id=" + bannerId + "&banner_impression_log_id=" + bannerImpressionLogId, function (returnArray) {
        if (typeof afterFunction == "function") {
            afterFunction();
        }
    });
    return false;
}

function processMagneticData(magneticData, setName) {
    if (empty(setName)) {
        setName = false;
    }
    $("#payment_method_id").data("swipe_string", "");
    $("#account_id").data("swipe_string", "");
    if (magneticData.substring(0, 2) !== "%B") {
        return;
    }
    magneticData = magneticData.substring(2);
    stringParts = magneticData.split("^");
    if (stringParts.length < 3) {
        return;
    }
    let creditCardNumber = $.trim(stringParts[0].replace(/\s/g, ''));
    let creditCardNameParts = $.trim(stringParts[1]).split("/");
    let creditCardName = "";
    let firstName = "";
    let lastName = "";
    let businessName = "";
    if (creditCardNameParts.length !== 2) {
        businessName = creditCardNameParts.join(" ");
    } else {
        firstName = creditCardNameParts[1];
        if (firstName.substring(firstName.length - 3) === " MR") {
            firstName = firstName.substring(0, firstName.length - 3);
        }
        if (firstName.substring(firstName.length - 4) === " MRS") {
            firstName = firstName.substring(0, firstName.length - 4);
        }
        lastName = $.trim(creditCardNameParts[0]);
    }
    let expireMonth = parseInt(stringParts[2].substring(2, 4));
    let expireYear = parseInt(stringParts[2].substring(0, 2)) + 2000;
    if (isNaN(expireYear) || isNaN(expireMonth) || expireMonth < 1 || expireMonth > 12) {
        return;
    }
    creditCardName = creditCardName.toLowerCase().replace(/(^|\s)([a-z])/g, function (m, p1, p2) {
        return p1 + p2.toUpperCase();
    });
    let creditCardType = "";
    if (creditCardNumber.substring(0, 1) === "4") {
        creditCardType = "Visa";
    } else if (creditCardNumber.substring(0, 1) === "5") {
        creditCardType = "MasterCard";
    } else if (creditCardNumber.substring(0, 4) === "6011") {
        creditCardType = "Discover";
    } else if ((creditCardNumber.substring(0, 2) === "34" || creditCardNumber.substring(0, 2) === "37") && creditCardNumber.length === 15) {
        creditCardType = "American Express";
    } else {
        return;
    }
    $("#account_id").val("");
    $("#payment_method_id").find("option:contains('" + creditCardType + "')").prop("selected", true).trigger("change");
    $("#account_number").val(creditCardNumber);
    $("#expiration_month").val(expireMonth);
    $("#expiration_year").val(expireYear);
    $("#billing_business_name").val(businessName);
    $("#billing_first_name").val(firstName);
    $("#billing_last_name").val(lastName);
    if (setName) {
        $("#first_name").val(firstName);
        $("#last_name").val(lastName);
        if ($("#credit_card_name").length > 0) {
            $("#credit_card_name").val(creditCardName);
        }
    }
}

function createGoogleEvent(eventCategory, eventAction, eventLabel, eventValue) {
    ga(
        'send',
        'event',
        eventCategory,
        eventAction,
        eventLabel,
        eventValue
    );
}

function calculateDistance(point1, point2) {
    const radius = 3958;                    // Earth's radius (miles)
    const degreesPerRadian = 57.29578;	    // Number of degrees/radian (for conversion)

    return (radius * Math.PI * Math.sqrt((point1['latitude'] - point2['latitude'])
        * (point1['latitude'] - point2['latitude'])
        + Math.cos(point1['latitude'] / degreesPerRadian)  // Convert these to
        * Math.cos(point2['latitude'] / degreesPerRadian)  // radians for cos()
        * (point1['longitude'] - point2['longitude'])
        * (point1['longitude'] - point2['longitude'])
    ) / 180);
}

var getAutocompleteTimer = null;

function getAutocompleteTextValues() {
    let autocompleteFields = [];
    if ($("#autocomplete_options").length === 0) {
        $("body").append("<div id='autocomplete_options'><ul></ul></div>");
    }

    $("#_edit_form").find(".autocomplete-field").each(function () {
        const thisFieldName = $(this).attr("id");
        const thisValueId = $("#" + thisFieldName.replace("_autocomplete_text", "")).val();
        let additionalFilter = $("#" + thisFieldName).data("additional_filter");
        const additionalFilterFunction = $("#" + thisFieldName).data("additional_filter_function");
        if (additionalFilterFunction !== undefined && typeof window[additionalFilterFunction] == "function") {
            additionalFilter = window[additionalFilterFunction](thisFieldName.replace("_autocomplete_text", ""));
        }
        if (empty(additionalFilter)) {
            additionalFilter = "";
        }
        const tag = $("#" + thisFieldName).data("autocomplete_tag");
        autocompleteFields.push({
            "field_name": $(this).attr("id"),
            "value_id": $("#" + thisFieldName.replace("_autocomplete_text", "")).val(),
            "additional_filter": additionalFilter,
            "tag": tag
        });
    });
    loadAjaxRequest("getautocompletedata.php?ajax=true&get_values=true", { "autocomplete_fields": autocompleteFields }, function (returnArray) {
        if ("autocomplete_text_values" in returnArray) {
            for (let i in returnArray['autocomplete_text_values']) {
                const thisFieldName = returnArray['autocomplete_text_values'][i]['field_name'];
                $("#" + thisFieldName).val(returnArray['autocomplete_text_values'][i]['autocomplete_text']);
                $("#" + thisFieldName).data("last_autocomplete_data", returnArray['autocomplete_text_values'][i]['autocomplete_text']);
            }
        }
    });
}

function getAutocompleteData(fieldName) {
    if ($("#" + fieldName).val() === $("#".fieldName).data("last_autocomplete_data")) {
        $("#autocomplete_options").width($("#" + fieldName).outerWidth() - 2).css("left", $("#" + fieldName).offset().left).css("top", $("#" + fieldName).offset().top + $("#" + fieldName).outerHeight()).show();
        return;
    }
    $("#autocomplete_options ul li").remove();
    $("body").addClass("no-waiting-for-ajax");
    let searchValue = $("#" + fieldName).val();
    let additionalFilter = $("#" + fieldName).data("additional_filter");
    let additionalFilterFunction = $("#" + fieldName).data("additional_filter_function");
    if (additionalFilterFunction !== undefined && typeof window[additionalFilterFunction] == "function") {
        additionalFilter = window[additionalFilterFunction](fieldName.replace("_autocomplete_text", ""));
    }
    if (empty(additionalFilter)) {
        additionalFilter = "";
    }
    if ($("#autocomplete_options").length === 0) {
        $("body").append("<div id='autocomplete_options'><ul></ul></div>");
    }
    loadAjaxRequest("getautocompletedata.php?ajax=true&tag=" + $("#" + fieldName).data("autocomplete_tag") + "&search_text=" + encodeURIComponent(searchValue.trim()) + "&additional_filter=" + encodeURIComponent(additionalFilter), function (returnArray) {
        if ("results" in returnArray) {
            let added = false;
            let keyValue = false;
            let description = false;
            for (let i in returnArray['results']) {
                if (returnArray['results'][i] instanceof Object) {
                    keyValue = returnArray['results'][i]['key_value'];
                    description = returnArray['results'][i]['description'];
                } else {
                    keyValue = i;
                    description = returnArray['results'][i];
                }
                added = true;
                $("#autocomplete_options ul").append($("<li class='autocomplete-option'></li>").data("id_value", keyValue).html(description.replace(new RegExp(searchValue, 'ig'), "<b>$&</b>")));
            }
            $("#autocomplete_options p").remove();
            if (!added) {
                $("#autocomplete_options").prepend("<p>No matching results</p>");
            }
            $("#autocomplete_options").width($("#" + fieldName).outerWidth() - 2).css("left", $("#" + fieldName).offset().left).css("top", $("#" + fieldName).offset().top + $("#" + fieldName).outerHeight()).show();
            $("#" + fieldName).data("last_autocomplete_data", $("#" + fieldName).val());
            $("#autocomplete_options ul li:eq(0)").addClass("selected");
            showAutocompleteSelection();
        }
    });
}

function showAutocompleteSelection() {
    if ($("#autocomplete_options ul li.selected").length === 0) {
        return;
    }
    let topPosition = $("#autocomplete_options ul").position().top;
    let height = $("#autocomplete_options").height();
    let selectedTop = $("#autocomplete_options ul li.selected").position().top;
    let lineHeight = $("#autocomplete_options ul li").outerHeight();
    if ((selectedTop + topPosition) < 0 || selectedTop > (height - lineHeight - topPosition)) {
        let scrollTop = (selectedTop - (height / 2) + (lineHeight / 2));
        $("#autocomplete_options").animate({ scrollTop: scrollTop }, 100);
    }
}

function installDatePicker() {
    if ($().datepicker) {
        if (empty($.datepicker._updateDatepicker_original)) {
            $.datepicker._updateDatepicker_original = $.datepicker._updateDatepicker;
            $.datepicker._updateDatepicker = function (inst) {
                $.datepicker._updateDatepicker_original(inst);
                let afterShow = this._get(inst, 'afterShow');
                if (afterShow)
                    afterShow.apply((inst.input ? inst.input[0] : null));  // trigger custom callback
            }
        }
        $(".datepicker").not(".no-datepicker").datepicker({
            beforeShow: function (input, inst) {
                $(input).trigger("keydown");
            },
            onClose: function (dateText, inst) {
                $(this).trigger("keydown");
            },
            showOn: "button",
            buttonText: "<span class='fad fa-calendar-alt'></span>",
            constrainInput: false,
            dateFormat: "mm/dd/yy",
            yearRange: "c-100:c+10"
        });
        $('.monthpicker').datepicker({
            showOn: "button",
            buttonText: "<span class='fad fa-calendar-alt'></span>",
            constrainInput: true,
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true,
            dateFormat: 'MM yy',
            onClose: function (dateText, inst) {
                let month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
                let year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
                $(this).datepicker('setDate', new Date(year, month, 1));
                $(".ui-datepicker-calendar").removeClass("monthpicker");
            },
            afterShow: function (input, inst) {
                $(".ui-datepicker-calendar").addClass("monthpicker");
            },
            yearRange: "c-100:c+10"
        }).prop("readonly", true);
    }
    $("#_templates").find(".hasDatepicker").removeClass("hasDatepicker");
    $("#_templates").find(".ui-datepicker-trigger").remove();
}

function addCKEditor() {
    width = "95%";
    $(".ck-editor").each(function () {
        if (typeof CKEDITOR !== "undefined") {
            let fieldId = $(this).data("id");
            if (empty(fieldId)) {
                fieldId = $(this).attr("id");
                $(this).data("id", fieldId);
            }

            if ($(this).data("checked") === "true") {
                $(this).data("checked", "false");
                $("#" + fieldId).removeClass("wysiwyg");
                CKEDITOR.instances[fieldId].destroy();
            }

            if ($(this).data("checked") !== "true") {
                $(this).data("checked", "true");
                $("#" + fieldId).addClass("wysiwyg");
                let contentsCss = $("#" + fieldId).data("contents_css");
                if (empty(contentsCss)) {
                    contentsCss = "";
                }
                let stylesSet = $("#" + fieldId).data("styles_set");
                let includeMentions = $("#" + fieldId).data("include_mentions");
                if (empty(stylesSet)) {
                    stylesSet = [];
                }
                if (typeof beforeCKEditorLoad === "function") {
                    beforeCKEditorLoad();
                }
                const editorConfig = {
                    resize_dir: 'both',
                    disableNativeSpellChecker: false,
                    scayt_autoStartup: true,
                    contentsCss: contentsCss,
                    width: width,
                    height: 300,
                    stylesSet: stylesSet,
                    allowedContent: true,
                    removePlugins: 'flash,image,maximize',
                    extraPlugins: 'uploadimage,fontawesome5',
                    fontawesome: {
                        'path': '/fontawesome-core/css/all.min.css',
                        'version': '5.15.1',
                        'edition': 'pro',
                        'element': 'i'
                    },
                    uploadUrl: '/ckeditorimageupload.php',
                    removeButtons: 'Select,HiddenField,ImageButton,Button,Textarea,TextField,Form,Radio,Checkbox,Iframe,PageBreak,InsertPre,Anchor,Language,BidiRtl,BidiLtr,ShowBlocks,Save,Print,Preview,NewPage,Templates',
                    smiley_path: '/ckeditor/plugins/smiley/images/',
                    coreStyles_bold: {
                        element: 'span',
                        attributes: { 'class': 'highlighted-text' },
                        styles: { 'font-weight': 'bold' }
                    },
                    coreStyles_italic: {
                        element: 'span',
                        attributes: { 'class': 'italic' },
                        styles: { 'font-style': 'bold' }
                    }
                };
                CKEDITOR.dtd.$removeEmpty['span'] = false;
                CKEDITOR.dtd.$removeEmpty['i'] = false;

                if (includeMentions) {
                    editorConfig.extraPlugins = "uploadimage,fontawesome5,mentions";
                    editorConfig.mentions = [
                        {
                            feed: function (options, feedCallback) {
                                loadAjaxRequest("/getuserpickerlist.php?ajax=true&admin=true", { "user_picker_filter_text": options.query }, function (returnArray) {
                                    if ("error_message" in returnArray) {
                                        feedCallback([]);
                                    }
                                    if (returnArray.users) {
                                        feedCallback(returnArray.users.map(function (user) {
                                            return {
                                                id: user.user_id,
                                                name: user.display_name,
                                                userName: user.user_name
                                            };
                                        }));
                                    } else {
                                        feedCallback([]);
                                    }
                                });
                            },
                            minChars: 0,
                            itemTemplate: '<li data-id="{id}">{name} ({userName})</li>',
                            outputTemplate: '<a data-user-id="{id}" class="user-mention" href="#">{name}</a>&nbsp;'
                        }
                    ];
                }

                CKEDITOR.replace(fieldId, editorConfig);

                CKEDITOR.dtd.$removeEmpty['span'] = false;
                CKEDITOR.dtd.$removeEmpty['i'] = false;
                if ($("#" + fieldId + "-ace_editor").length > 0) {
                    $("#" + fieldId + "-ace_editor").addClass("hidden");
                }
            }
        }
    });
}

function showOnloadWebsitePopup() {
    if (typeof beforeShowOnloadWebsitePopup == "function") {
        beforeShowOnloadWebsitePopup()
    }
    $('#onload_website_popup').dialog({
        closeOnEscape: true,
        draggable: false,
        modal: true,
        resizable: false,
        classes: {
            "ui-dialog": "no-titlebar"
        },
        position: { my: "center center", at: "center center", of: window, collision: "none" },
        width: 600,
        title: ""
    });
    $.cookie("onload_website_popup", "true", { expires: 60, path: '/' });
}

function showLoginPopup() {
    if ($("#_login_popup_dialog").length === 0) {
        $("body").append('<div class="dialog-box" id="_login_popup_dialog"></div>');
        loadAjaxRequest("/loginform.php?ajax=true&url_action=get_login_form", function (returnArray) {
            $("#_login_popup_dialog").html(returnArray['login_form']);
            showLoginPopup();
        });
        return;
    } else {
        $("#_login_edit_form").append("<input type='hidden' name='from_form' value='853923'>");
    }
    $("#_login_popup_dialog #forgot_form").slideUp();
    $("#_login_popup_dialog #login_form").slideDown();
    $("#_login_popup_dialog #access_link_div").slideDown();
    $("#_login_popup_dialog #user_name").focus();
    $('#_login_popup_dialog').dialog({
        autoOpen: true,
        closeOnEscape: true,
        draggable: true,
        resizable: true,
        modal: true,
        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
        width: 600,
        title: 'Login',
        buttons: [ {
            id: "_login_popup_dialog_button",
            text: "Login",
            click: function () {
                const forgotForm = $("#_login_popup_dialog").data("forgot_form");
                if (!empty(forgotForm)) {
                    if ($("#_forgot_form").validationEngine('validate')) {
                        $("#_forgot_button").prop("disabled", true);
                        loadAjaxRequest("loginform.php?ajax=true&url_action=forgot", $("#_forgot_form").serialize(), function (returnArray) {
                            if ("error_message" in returnArray) {
                                $("#_error_message").removeClass("info-message").html(returnArray['error_message']);
                                $("#_forgot_button").prop("disabled", false);
                            } else {
                                $("#forgot_form").addClass("info-message");
                                if ("info_message" in returnArray) {
                                    $("#forgot_form").html(returnArray['info_message']);
                                } else {
                                    $("#forgot_form").html("An email has been sent to the email address on file. Follow the instructions in the email.");
                                }
                                $("#_forgot_button").prop("disabled", false);
                                $("#_login_popup_dialog").data("forgot_form", "");
                                setTimeout(function () {
                                    $("#_login_popup_dialog").dialog("close");
                                }, 2000);
                            }
                        });
                    }
                } else {
                    if ($("#_login_edit_form").validationEngine("validate")) {
                        loadAjaxRequest("/loginform.php?ajax=true&url_action=login", $("#_login_edit_form").serialize(), function (returnArray) {
                            if ("error_message" in returnArray) {
                                $("#login_now_button").removeClass("hidden");
                                $("#logging_in_message").html("");
                            } else {
                                if (typeof afterSuccessfulLogin == "function") {
                                    afterSuccessfulLogin(returnArray['email_address']);
                                }
                                location.reload();
                            }
                        });
                    }
                }
            }
        },
            {
                text: "Cancel",
                click: function () {
                    const forgotForm = $("#_login_popup_dialog").data("forgot_form");
                    if (!empty(forgotForm)) {
                        $("#_login_popup_dialog").data("forgot_form", "");
                        if ($().validationEngine) {
                            $("#_login_edit_form").validationEngine("hideAll");
                        }
                        $("#_login_popup_dialog #forgot_form").slideUp();
                        $("#_login_popup_dialog #login_form").slideDown();
                        $("#_login_popup_dialog #access_link_div").slideDown();
                        $("#_login_popup_dialog #user_name").focus();
                        $("#_login_popup_dialog_button").html("Login");
                        return;
                    }
                    $("#_login_popup_dialog").dialog("close");
                }
            } ]
    });
}

function logJavascriptError(message, isError) {
    if (empty(isError) && isError != 0) {
        isError = 1;
    }
    loadAjaxRequest("/logjavascripterror.php?ajax=true", { message: message, is_error: isError, url: window.location.protocol + "//" + window.location.host + "/" + window.location.pathname + window.location.search });
}

var autocompleteAddressesTimer = null;
var lastAutocompleteValue = null;
var autocompletePrefix = "";
var $currentAutocompleteAddressField = null;

$(function () {
    $(document).on("focus", ".autocomplete-address", function () {
        const prefix = $(this).data("prefix");
        autocompletePrefix = (empty(prefix) ? "" : prefix);
        $currentAutocompleteAddressField = $(this);
        lastAutocompleteValue = $(this).val();
    });
    $(document).on("keyup", ".autocomplete-address", function (event) {
        if (!empty(lastAutocompleteValue) && lastAutocompleteValue === $(this).val()) {
            return;
        }

        lastAutocompleteValue = $(this).val();
        if (!empty(autocompleteAddressesTimer)) {
            clearTimeout(autocompleteAddressesTimer);
            autocompleteAddressesTimer = null;
        }
        if ($(this).val().length > 3) {
            autocompleteAddressesTimer = setTimeout(function () {
                getAutocompleteAddresses();
            }, 300);
        }
    });
    $(document).on("click", ".autocomplete-address-choice", function () {
        $("#autocomplete_address_choices_wrapper ul li.selected").removeClass("selected");
        $(this).addClass("selected");
        chooseAutocompleteAddress();
    });
    $(document).on("blur", ".autocomplete-address", function () {
        const $thisElement = $(this);
        setTimeout(function () {
            $("#autocomplete_address_choices_wrapper").addClass("hidden");
            lastAutocompleteValue = $thisElement.val();
        }, 500)
    });
    $(document).on("keydown", ".autocomplete-address", function (event) {
        if (event.which === 33 || event.which === 34) {
            return true;
        }
        if ($(this).prop("readonly") && event.which !== 9 && (event.which < 16 || event.which > 18) && event.which !== 91) {
            return false;
        }
        if (event.which === 40) {
            let currentIndex = $("#autocomplete_address_choices_wrapper ul li.selected").index();
            currentIndex++;
            if (currentIndex >= $("#autocomplete_address_choices_wrapper ul li").length) {
                currentIndex = 0;
            }
            $("#autocomplete_address_choices_wrapper ul li.selected").removeClass("selected");
            $("#autocomplete_address_choices_wrapper ul li:eq(" + currentIndex + ")").addClass("selected");
            showAutocompleteAddress();
            return false;
        } else if (event.which === 38) {
            let currentIndex = $("#autocomplete_address_choices_wrapper ul li.selected").index();
            currentIndex--;
            if (currentIndex < 0) {
                currentIndex = $("#autocomplete_address_choices_wrapper ul li").length - 1;
            }
            $("#autocomplete_address_choices_wrapper ul li.selected").removeClass("selected");
            $("#autocomplete_address_choices_wrapper ul li:eq(" + currentIndex + ")").addClass("selected");
            showAutocompleteAddress();
            return false;
        } else if (event.which === 13 || event.which === 3) {
            if ($("#autocomplete_address_choices_wrapper ul li.selected").length > 0) {
                chooseAutocompleteAddress();
            }
        }
        return true;
    });
});

function chooseAutocompleteAddress() {
    if ($("#autocomplete_address_choices_wrapper ul li.selected").length === 0) {
        return;
    }
    const $choice = $("#autocomplete_address_choices_wrapper ul li.selected");
    $("#" + autocompletePrefix + "address_1").val($choice.data("address_1"));
    $("#" + autocompletePrefix + "address_2").val($choice.data("address_2"));
    $("#" + autocompletePrefix + "city").val($choice.data("city"));
    $("#" + autocompletePrefix + "state").val($choice.data("state"));
    if ($("#" + autocompletePrefix + "state_select").length > 0) {
        $("#" + autocompletePrefix + "state_select").val($choice.data("state"));
    }
    $("#" + autocompletePrefix + "postal_code").val($choice.data("postal_code"));
    $("#" + autocompletePrefix + "postal_code").trigger("change");
    $("#autocomplete_address_choices_wrapper").addClass("hidden");
    lastAutocompleteValue = $("#" + autocompletePrefix + "address_1").val();
    $("#" + autocompletePrefix + "address_1").focus();
}

function getAutocompleteAddresses() {
    if ($("#" + autocompletePrefix + "address_1").length == 0) {
        return;
    }
    if ($("#" + autocompletePrefix + "address_1").val().length > 3 && parseInt($("#" + autocompletePrefix + "country_id").val()) === 1000) {
        $("body").addClass("no-waiting-for-ajax");
        loadAjaxRequest("/get-possible-addresses?ajax=true&search=" + encodeURIComponent($("#" + autocompletePrefix + "address_1").val()) + "&country_id=1000", function (returnArray) {
            if ($("#autocomplete_address_choices_wrapper").length === 0) {
                $("body").append("<div id='autocomplete_address_choices_wrapper' class='hidden'><ul id='autocomplete_address_choices'></ul></div>");
            }
            $("#autocomplete_address_choices").html("");
            if (returnArray.length > 0) {
                for (let i in returnArray) {
                    let $thisChoice = $("<li class='autocomplete-address-choice'></li>");
                    let description = "";
                    for (let j in returnArray[i]) {
                        if (empty(returnArray[i][j])) {
                            continue;
                        }
                        description += (empty(description) ? "" : ", ") + returnArray[i][j];
                        $thisChoice.data(j, returnArray[i][j]);
                    }
                    $thisChoice.text(description);
                    $("#autocomplete_address_choices").append($thisChoice);
                }
                $("#autocomplete_address_choices_wrapper").removeClass("hidden").width($("#" + autocompletePrefix + "address_1").outerWidth() + 80).css("left", $("#" + autocompletePrefix + "address_1").offset().left).css("top", $("#" + autocompletePrefix + "address_1").offset().top + $("#" + autocompletePrefix + "address_1").outerHeight());
            }
        });
    }
}

function showAutocompleteAddress() {
    if ($("#autocomplete_address_choices_wrapper ul li.selected").length === 0) {
        return;
    }
    let topPosition = $("#autocomplete_address_choices_wrapper ul").position().top;
    let height = $("#autocomplete_address_choices_wrapper").height();
    let selectedTop = $("#autocomplete_address_choices_wrapper ul li.selected").position().top;
    let lineHeight = $("#autocomplete_address_choices_wrapper ul li").outerHeight();
    if ((selectedTop + topPosition) < 0 || selectedTop > (height - lineHeight - topPosition)) {
        let scrollTop = (selectedTop - (height / 2) + (lineHeight / 2));
        $("#autocomplete_address_choices_wrapper").animate({ scrollTop: scrollTop }, 100);
    }
}
